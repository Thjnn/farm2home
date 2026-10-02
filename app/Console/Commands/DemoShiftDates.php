<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('demo:shift-dates {--force : Cho phép chạy ở môi trường production}')]
#[Description('Dịch ngày của dữ liệu demo về gần hôm nay (theo tuần nguyên)')]
class DemoShiftDates extends Command
{
    /**
     * Cột ngày nghiệp vụ của từng bảng (created_at/updated_at được dịch kèm).
     * Hai cột đầu dùng để sắp thứ tự cập nhật, tránh đụng khóa unique giữa chừng.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'customers' => [],
        'subscriptions' => ['start_date', 'end_date'],
        'orders' => ['delivery_date', 'confirmed_at', 'delivered_at'],
        'order_items' => [],
        'supply_reports' => ['available_date'],
        'forecasts' => ['target_date', 'generated_on'],
        'harvest_plans' => ['delivery_date', 'harvest_date', 'confirmed_at'],
        'harvest_plan_items' => ['farmer_confirmed_at'],
        'batches' => ['harvested_at', 'received_at', 'processed_at', 'packed_at', 'best_before'],
        'feedbacks' => ['resolved_at'],
        'waste_logs' => ['log_date'],
        'packaging_returns' => ['return_date'],
        'point_transactions' => [],
    ];

    /**
     * Dịch theo tuần nguyên để giữ đúng thứ trong tuần (giỏ định kỳ và nhu cầu cuối tuần phụ thuộc vào thứ).
     */
    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Không chạy ở production nếu thiếu --force.');

            return self::FAILURE;
        }

        $latest = DB::table('orders')->max('delivery_date');

        if ($latest === null) {
            $this->info('Không có dữ liệu để dịch.');

            return self::SUCCESS;
        }

        $gap = (int) CarbonImmutable::parse($latest)->startOfDay()->diffInDays(CarbonImmutable::tomorrow(), false);
        $days = intdiv(max(0, $gap), 7) * 7;

        if ($days === 0) {
            $this->info('Không cần dịch: dữ liệu demo cách hôm nay chưa tới một tuần.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($days) {
            foreach (self::COLUMNS as $table => $columns) {
                $sets = array_map(
                    fn (string $column) => "`{$column}` = DATE_ADD(`{$column}`, INTERVAL {$days} DAY)",
                    [...$columns, 'created_at', 'updated_at'],
                );

                // MySQL gán SET từ trái sang phải, nên biểu thức mã dùng giá trị ngày đã dịch.
                if ($table === 'orders') {
                    $sets[] = "`code` = CONCAT('DH', DATE_FORMAT(`delivery_date`, '%y%m%d'), RIGHT(`code`, 4))";
                }

                if ($table === 'batches') {
                    $sets[] = "`code` = CONCAT('F2H-', DATE_FORMAT(`harvested_at`, '%y%m%d'), SUBSTRING(`code`, 11))";
                }

                // Dịch tiến: cập nhật dòng muộn nhất trước để không trùng khóa unique.
                $order = implode(', ', array_map(
                    fn (string $column) => "`{$column}` DESC",
                    $columns === [] ? ['id'] : array_slice($columns, 0, 2),
                ));

                DB::statement("UPDATE `{$table}` SET ".implode(', ', $sets)." ORDER BY {$order}");
            }
        });

        $this->info("Đã dịch dữ liệu demo {$days} ngày.");

        return self::SUCCESS;
    }
}

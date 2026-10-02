<?php

namespace App\Services;

use App\Enums\FarmerStatus;
use App\Enums\ForecastMethod;
use App\Enums\HarvestPlanStatus;
use App\Models\Farmer;
use App\Models\HarvestPlan;
use App\Models\HarvestPlanItem;
use App\Models\Product;
use App\Models\SupplyReport;
use App\Models\User;
use App\Support\Format;
use App\Support\HarvestMath;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class HarvestPlanService
{
    public function __construct(
        private readonly ForecastService $forecasts,
        private readonly BatchService $batches,
    ) {}

    /**
     * Sinh (hoặc sinh lại) gợi ý thu hoạch cho một ngày giao. AI chỉ gợi ý: final_kg để trống cho operator chốt.
     *
     * @throws DomainException khi kế hoạch của ngày đó đã được chốt
     */
    public function generateDraft(CarbonInterface $deliveryDate, ?User $by = null): HarvestPlan
    {
        $date = CarbonImmutable::parse($deliveryDate)->startOfDay();

        return DB::transaction(function () use ($date, $by) {
            $plan = HarvestPlan::whereDate('delivery_date', $date->toDateString())->lockForUpdate()->first();

            if ($plan !== null && $plan->status !== HarvestPlanStatus::Draft) {
                throw new DomainException('Kế hoạch ngày này đã chốt, không thể tạo lại gợi ý.');
            }

            $plan ??= HarvestPlan::create([
                'harvest_date' => $date->toDateString(),
                'delivery_date' => $date->toDateString(),
                'safety_margin_pct' => config('farm2home.safety_margin_pct'),
                'status' => HarvestPlanStatus::Draft,
                'created_by' => $by?->id,
            ]);

            $plan->items()->delete();

            $method = ForecastMethod::from(config('farm2home.forecast.default_method'));
            $margin = (float) $plan->safety_margin_pct;

            foreach (Product::where('is_active', true)->orderBy('id')->get() as $product) {
                $confirmed = $this->forecasts->confirmedKg($product, $date);
                $forecast = $this->forecasts->latestFor($product, $date, $method);
                $extra = $forecast === null ? 0.0 : max(0.0, round((float) $forecast->predicted_total_kg - $confirmed, 2));
                $stock = $this->batches->availableStockKg($product, $date);
                $suggested = HarvestMath::suggestedKg($confirmed, $extra, $stock, $margin);

                $supply = SupplyReport::where('product_id', $product->id)
                    ->whereDate('available_date', $date->toDateString())
                    ->whereHas('farmer', fn ($query) => $query->where('status', FarmerStatus::Active))
                    ->pluck('quantity_kg', 'farmer_id')
                    ->map(fn ($kg) => (float) $kg)
                    ->all();

                foreach (HarvestMath::allocate($suggested, $supply) as $index => $row) {
                    // Quyết định D5: số nhu cầu chỉ ghi ở dòng đầu để tổng theo sản phẩm không bị nhân đôi.
                    $plan->items()->create([
                        'product_id' => $product->id,
                        'farmer_id' => $row['farmer_id'],
                        'confirmed_order_kg' => $index === 0 ? $confirmed : 0,
                        'forecast_extra_kg' => $index === 0 ? $extra : 0,
                        'available_stock_kg' => $index === 0 ? $stock : 0,
                        'suggested_kg' => $row['kg'],
                    ]);
                }
            }

            return $plan->load('items.product', 'items.farmer');
        });
    }

    /**
     * @throws DomainException khi kế hoạch không còn là nháp, số âm, hoặc số chốt khác gợi ý mà thiếu lý do
     */
    public function updateItem(HarvestPlanItem $item, float $finalKg, ?string $reason = null, ?int $farmerId = null): HarvestPlanItem
    {
        if ($item->plan->status !== HarvestPlanStatus::Draft) {
            throw new DomainException('Chỉ sửa được khi kế hoạch còn ở trạng thái nháp.');
        }

        if ($finalKg < 0) {
            throw new DomainException('Số kg chốt không được âm.');
        }

        $differs = abs($finalKg - (float) $item->suggested_kg) > 0.001;

        if ($differs && blank($reason)) {
            throw new DomainException('Cần ghi lý do khi số chốt khác số gợi ý.');
        }

        $item->update([
            'final_kg' => $finalKg,
            'adjust_reason' => $differs ? $reason : null,
            'farmer_id' => $farmerId ?? $item->farmer_id,
        ]);

        return $item;
    }

    /**
     * Operator chốt kế hoạch: dòng chưa chỉnh lấy đúng số gợi ý. Lỗi thì rollback, kế hoạch vẫn là nháp.
     *
     * @throws DomainException khi đã chốt trước đó hoặc còn dòng chưa có nông hộ
     */
    public function confirm(HarvestPlan $plan, User $by): HarvestPlan
    {
        if ($plan->status !== HarvestPlanStatus::Draft) {
            throw new DomainException('Kế hoạch này đã được chốt trước đó.');
        }

        return DB::transaction(function () use ($plan, $by) {
            $plan->items()->whereNull('final_kg')->update(['final_kg' => DB::raw('suggested_kg')]);

            if ($plan->items()->whereNull('farmer_id')->where('final_kg', '>', 0)->exists()) {
                throw new DomainException('Còn dòng chưa có nông hộ. Hãy chọn nông hộ hoặc đặt số chốt về 0.');
            }

            $plan->update([
                'status' => HarvestPlanStatus::Confirmed,
                'confirmed_by' => $by->id,
                'confirmed_at' => now(),
            ]);

            return $plan->load('items.product', 'items.farmer');
        });
    }

    /**
     * @throws DomainException khi kế hoạch chưa được chốt
     */
    public function markSent(HarvestPlan $plan): HarvestPlan
    {
        if ($plan->status !== HarvestPlanStatus::Confirmed) {
            throw new DomainException('Chỉ đánh dấu đã gửi sau khi kế hoạch được chốt.');
        }

        $plan->update(['status' => HarvestPlanStatus::Sent]);

        return $plan;
    }

    /**
     * @return int số dòng nông hộ vừa xác nhận
     *
     * @throws DomainException khi kế hoạch chưa chốt hoặc đã hoàn tất
     */
    public function farmerConfirm(HarvestPlan $plan, Farmer $farmer): int
    {
        if (! in_array($plan->status, [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent], true)) {
            throw new DomainException('Kế hoạch chưa được chốt hoặc đã hoàn tất.');
        }

        return $plan->items()
            ->where('farmer_id', $farmer->id)
            ->whereNull('farmer_confirmed_at')
            ->update(['farmer_confirmed_at' => now()]);
    }

    /**
     * Tin nhắn để operator sao chép gửi Zalo (MVP không dùng Zalo OA API).
     */
    public function zaloMessage(HarvestPlan $plan, Farmer $farmer): string
    {
        $lines = $plan->items()
            ->with('product')
            ->where('farmer_id', $farmer->id)
            ->where('final_kg', '>', 0)
            ->orderBy('product_id')
            ->get()
            ->map(fn (HarvestPlanItem $item) => '- '.$item->product->name.': '.Format::kg($item->final_kg));

        return implode("\n", [
            "FARM2HOME gửi {$farmer->name} danh sách thu hoạch ngày {$plan->harvest_date->format('d/m/Y')}:",
            ...$lines,
            'Xác nhận giúp tại: '.route('farmer.show', $farmer->access_token),
        ]);
    }

    /**
     * @throws DomainException khi kế hoạch chưa chốt hoặc còn dòng chưa nhập số thực nhận
     */
    public function complete(HarvestPlan $plan): HarvestPlan
    {
        if (! in_array($plan->status, [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent], true)) {
            throw new DomainException('Chỉ hoàn tất được kế hoạch đã chốt.');
        }

        if ($plan->items()->where('final_kg', '>', 0)->whereNull('received_kg')->exists()) {
            throw new DomainException('Còn dòng chưa nhập số kg thực nhận.');
        }

        $plan->update(['status' => HarvestPlanStatus::Completed]);

        return $plan;
    }
}

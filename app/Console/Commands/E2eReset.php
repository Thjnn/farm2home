<?php

namespace App\Console\Commands;

use App\Models\Basket;
use App\Models\Product;
use Database\Seeders\CatalogVolumeSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('e2e:reset
    {--products=30 : Số món đang bán sau khi dựng lại}
    {--baskets=10 : Số giỏ đang bán sau khi dựng lại}')]
#[Description('Dựng lại DB cho test Playwright (chỉ chạy trên DB tên kết thúc bằng _e2e)')]
class E2eReset extends Command
{
    public function handle(): int
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_e2e')) {
            $this->error("Từ chối: DB đang dùng là \"{$database}\", lệnh này chỉ chạy trên DB *_e2e.");

            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => CatalogVolumeSeeder::class, '--force' => true]);

        // Giữ đúng số món/giỏ đang bán theo kịch bản test (vd 5 món để kiểm tra không có phân trang).
        // MySQL bỏ qua OFFSET trong UPDATE nên lấy id trước.
        $extraProducts = Product::where('is_active', true)->orderBy('id')->pluck('id')->slice((int) $this->option('products'));
        Product::whereIn('id', $extraProducts)->update(['is_active' => false]);
        $extraBaskets = Basket::where('is_active', true)->orderBy('price')->orderBy('id')->pluck('id')->slice((int) $this->option('baskets'));
        Basket::whereIn('id', $extraBaskets)->update(['is_active' => false]);

        $this->info(sprintf('%s: %d món, %d giỏ đang bán.', $database, Product::where('is_active', true)->count(), Basket::where('is_active', true)->count()));

        return self::SUCCESS;
    }
}

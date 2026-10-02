<?php

namespace Tests\Feature\Loyalty;

use App\Enums\PointReason;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Services\LoyaltyService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->seed(DemoSeeder::class);
    }

    public function test_backfill_awards_the_sample_data_and_is_safe_to_rerun(): void
    {
        $this->artisan('loyalty:backfill')->expectsOutputToContain('Đã cộng 445 điểm trong 89 giao dịch.')->assertSuccessful();

        $this->assertSame(78, PointTransaction::where('reason', PointReason::OrderDelivered)->count());
        $this->assertSame(11, PointTransaction::where('reason', PointReason::PackagingReturned)->count());
        $this->assertSame(445, (int) PointTransaction::sum('points'));
        $this->assertSame(20, app(LoyaltyService::class)->balance(Customer::where('phone', '0988000001')->firstOrFail()));

        // Dòng điểm mang thời điểm của sự kiện gốc, không phải lúc chạy lệnh.
        $this->assertSame('2026-09-19 18:00:00', PointTransaction::where('packaging_return_id', 1)->sole()->created_at->toDateTimeString());
        $this->assertSame(0, PointTransaction::where('created_at', '>=', '2026-10-02 10:00:00')->count());

        $this->artisan('loyalty:backfill')->expectsOutputToContain('Đã cộng 0 điểm trong 0 giao dịch.')->assertSuccessful();
        $this->assertSame(89, PointTransaction::count());
    }
}

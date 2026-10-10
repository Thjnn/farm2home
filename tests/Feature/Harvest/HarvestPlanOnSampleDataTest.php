<?php

namespace Tests\Feature\Harvest;

use App\Models\Batch;
use App\Models\Product;
use App\Services\BatchService;
use App\Services\HarvestPlanService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HarvestPlanOnSampleDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->seed(DemoSeeder::class);
    }

    public function test_remaining_and_stock_on_sample_batches(): void
    {
        $batches = app(BatchService::class);

        // 2,90 kg − 4 gói × 0,5 kg − 0,9 kg rau dư = 0
        $this->assertSame(0.0, $batches->remainingKg(Batch::where('code', 'F2H-260917-RM-NH01')->firstOrFail()));
        // Tồn còn hạn cho ngày 03/10: hành lá 0,2 kg, xà lách 0,1 kg, rau muống 0.
        $date = CarbonImmutable::parse('2026-10-03');
        $this->assertSame(0.2, $batches->availableStockKg(Product::where('code', 'HL')->firstOrFail(), $date));
        $this->assertSame(0.1, $batches->availableStockKg(Product::where('code', 'XL')->firstOrFail(), $date));
        $this->assertSame(0.0, $batches->availableStockKg(Product::where('code', 'RM')->firstOrFail(), $date));
    }

    public function test_regenerating_the_draft_for_3_october(): void
    {
        $plan = app(HarvestPlanService::class)->generateDraft(CarbonImmutable::parse('2026-10-03'));

        $byCode = $plan->items->groupBy(fn ($item) => $item->product->code)
            ->map(fn ($rows) => (float) $rows->sum('suggested_kg'))->all();

        // Khớp kế hoạch mẫu, trừ hành lá: mẫu ghi 2,5 kg vì không trừ 0,2 kg tồn.
        $this->assertSame(['RM' => 8.5, 'CN' => 7.0, 'CX' => 3.0, 'MT' => 5.5, 'RD' => 3.5, 'XL' => 1.0, 'HL' => 2.0], $byCode);

        // Rau muống 8,5 kg chia theo tỉ lệ sản lượng đã báo của NH01 và NH03.
        $rauMuong = $plan->items->filter(fn ($item) => $item->product->code === 'RM')->values();
        $this->assertSame([['NH01', 4.5], ['NH03', 4.0]], $rauMuong->map(fn ($item) => [$item->farmer->code, (float) $item->suggested_kg])->all());
        $this->assertSame(17, $plan->id);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ForecastMethod;
use App\Models\Forecast;
use App\Services\ForecastService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoOrdersTest extends TestCase
{
    use RefreshDatabase;

    private const OPTIONS = ['--count' => 300, '--days' => 28, '--customers' => 20, '--seed' => 7];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->travelTo('2026-10-06 20:00:00');
    }

    private function simulatedOrders(): Builder
    {
        return DB::table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('customers.phone', 'like', '0999%')
            ->select('orders.*');
    }

    public function test_generates_the_requested_number_of_consistent_orders(): void
    {
        $sampleOrders = DB::table('orders')->count();

        $this->artisan('demo:orders', self::OPTIONS)->expectsOutputToContain('300 đơn')->assertSuccessful();

        $this->assertSame($sampleOrders + 300, DB::table('orders')->count());
        $this->assertSame(20, DB::table('customers')->where('phone', 'like', '0999%')->count());

        $orders = $this->simulatedOrders()->get();
        $this->assertGreaterThanOrEqual('2026-09-09', $orders->min('delivery_date'));
        $this->assertLessThanOrEqual('2026-10-12', $orders->max('delivery_date'));
        $this->assertSame($orders->count(), $orders->unique('code')->count());

        $packWeight = DB::table('products')->pluck('pack_weight_kg', 'id');
        $basketPrice = DB::table('baskets')->pluck('price', 'id');
        $items = DB::table('order_items')->whereIn('order_id', $orders->pluck('id'))->get()->groupBy('order_id');

        foreach ($orders as $order) {
            $date = CarbonImmutable::parse($order->delivery_date);
            $this->assertSame('DH'.$date->format('ymd'), substr($order->code, 0, 8));
            $this->assertMatchesRegularExpression('/^DH\d{6}-\d{3}$/', $order->code);
            // Đặt trước giờ chốt 18:00 hôm trước.
            $this->assertTrue(CarbonImmutable::parse($order->created_at)->lt($date->subDay()->setTime(18, 0)), $order->code);
            $this->assertSame($order->subtotal + $order->shipping_fee - $order->discount, $order->total);

            $lines = $items[$order->id];
            foreach ($lines as $line) {
                $this->assertEqualsWithDelta($line->quantity * $packWeight[$line->product_id], (float) $line->weight_kg, 0.0001);
            }

            if ($order->order_type === 'basket') {
                $this->assertSame($basketPrice[$lines->first()->basket_id], $order->subtotal);
                $this->assertSame(0, $lines->sum('unit_price'));
            } else {
                $this->assertSame($lines->sum('line_total'), $order->subtotal);
            }

            $expectedStatuses = match (true) {
                $date->isBefore(today()) => ['delivered', 'cancelled'],
                $date->isToday() => ['delivering'],
                default => ['pending', 'confirmed'],
            };
            $this->assertContains($order->status, $expectedStatuses, $order->code);
        }
    }

    public function test_running_again_replaces_the_simulated_data(): void
    {
        $this->artisan('demo:orders', self::OPTIONS)->assertSuccessful();
        $codes = $this->simulatedOrders()->orderBy('code')->pluck('code');
        $total = DB::table('orders')->count();

        $this->artisan('demo:orders', self::OPTIONS)->assertSuccessful();

        $this->assertSame($total, DB::table('orders')->count());
        $this->assertEquals($codes, $this->simulatedOrders()->orderBy('code')->pluck('code'));
    }

    public function test_replays_past_forecasts_with_actuals(): void
    {
        $this->artisan('demo:orders', self::OPTIONS)->assertSuccessful();

        $replayed = Forecast::where('method', ForecastMethod::WeekdayAverage)->whereDate('generated_on', '2026-10-01')->count();
        $this->assertSame(5 * 7, $replayed);
        $this->assertSame('2026-10-06', Forecast::max('generated_on'));

        // Thực tế đã được điền tới hôm qua, và chỉ tới hôm qua.
        $this->assertSame(0, Forecast::whereDate('target_date', '<=', '2026-10-05')->whereNull('actual_kg')->count());
        $this->assertSame(0, Forecast::whereDate('target_date', '>=', '2026-10-06')->whereNotNull('actual_kg')->count());

        // Lần chạy 05/10 cho ngày 06/10 chỉ thấy đơn đặt tới 05/10 18:15.
        $rm = DB::table('products')->where('code', 'RM')->value('id');
        $knownThen = (float) DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $rm)->where('orders.delivery_date', '2026-10-06')
            ->where('orders.status', '!=', 'cancelled')->where('orders.created_at', '<=', '2026-10-05 18:15:00')
            ->sum('order_items.weight_kg');
        $forecast = Forecast::where('product_id', $rm)->whereDate('target_date', '2026-10-06')
            ->whereDate('generated_on', '2026-10-05')->where('method', ForecastMethod::MovingAverage)->firstOrFail();
        $this->assertEqualsWithDelta($knownThen, (float) $forecast->confirmed_kg, 0.01);

        $this->assertNotEmpty(app(ForecastService::class)->accuracy(today()->subDays(14), today()));
    }

    public function test_refuses_to_run_in_production_without_force(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('demo:orders', self::OPTIONS)->assertFailed();

        $this->assertSame(0, $this->simulatedOrders()->count());
    }
}

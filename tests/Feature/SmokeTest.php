<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Farmer;
use App\Models\Feedback;
use App\Models\HarvestPlan;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page must render with the full sample data set, not only with the small test catalog.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->seed(DemoSeeder::class);
    }

    public function test_public_and_farmer_pages_render_with_sample_data(): void
    {
        $farmer = Farmer::where('code', 'NH01')->firstOrFail();

        foreach (['/', '/dat-hang', '/tra-cuu', '/login', '/lo/F2H-260917-RM-NH01', '/nong-ho/'.$farmer->access_token] as $url) {
            $this->get($url)->assertOk();
        }

        $this->post('/tra-cuu', ['phone' => '0988000001'])->assertOk()->assertSee('DH26');
    }

    public function test_every_admin_page_renders_with_sample_data(): void
    {
        $this->actingAs(User::where('email', 'admin@farm2home.vn')->firstOrFail());

        $order = Order::where('code', 'DH260917-001')->firstOrFail();
        $batch = Batch::where('code', 'F2H-260917-RM-NH01')->firstOrFail();
        $completedPlan = HarvestPlan::whereDate('delivery_date', '2026-09-17')->firstOrFail();
        $confirmedPlan = HarvestPlan::whereDate('delivery_date', '2026-10-02')->firstOrFail();
        $draftPlan = HarvestPlan::whereDate('delivery_date', '2026-10-03')->firstOrFail();

        $urls = [
            '/admin',
            '/admin?from=2026-09-10&to=2026-10-03',
            '/admin/orders?delivery_date=2026-10-03',
            '/admin/orders/create',
            '/admin/orders/'.$order->id,
            '/admin/subscriptions',
            '/admin/subscriptions/create',
            '/admin/subscriptions/'.Subscription::firstOrFail()->id.'/edit',
            '/admin/supply?date=2026-10-03',
            '/admin/supply?date=2026-10-02',
            '/admin/forecasts',
            '/admin/harvest-plans',
            '/admin/harvest-plans/'.$completedPlan->id,
            '/admin/harvest-plans/'.$confirmedPlan->id,
            '/admin/harvest-plans/'.$draftPlan->id,
            '/admin/batches?date=2026-10-01',
            '/admin/batches/'.$batch->id,
            '/admin/batches/'.$batch->id.'/labels?count=3',
            '/admin/feedbacks',
            '/admin/feedbacks?status=new',
            '/admin/waste?date=2026-10-01',
            '/admin/packaging-returns',
            '/admin/products',
            '/admin/products/create',
            '/admin/baskets',
            '/admin/baskets/create',
            '/admin/farmers',
            '/admin/farmers/create',
            '/admin/farmers/'.Farmer::firstOrFail()->id.'/edit',
            '/admin/zones',
            '/admin/zones/create',
            '/admin/users',
            '/admin/users/create',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->status(), "GET {$url} returned {$response->status()}");
        }

        $this->assertGreaterThan(0, Feedback::count());
    }
}

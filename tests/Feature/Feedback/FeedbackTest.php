<?php

namespace Tests\Feature\Feedback;

use App\Enums\FeedbackStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Batch;
use App\Models\Feedback;
use App\Models\Order;
use App\Services\BatchService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Batch $batch;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 12:00:00');
        $this->buildCatalog();

        $orders = app(OrderService::class);
        $this->order = $orders->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
        $orders->transition($this->order, OrderStatus::Confirmed);

        $this->batch = Batch::factory()->create([
            'code' => 'F2H-261003-RM-NH01', 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => 5,
            'harvested_at' => '2026-10-03 05:30:00', 'best_before' => '2026-10-05', 'quality_status' => 'passed',
        ]);
        app(BatchService::class)->allocate(CarbonImmutable::parse('2026-10-03'));
    }

    public function test_anonymous_feedback_is_stored_as_new(): void
    {
        $this->post('/lo/F2H-261003-RM-NH01/phan-hoi', ['rating' => 4, 'freshness_rating' => 5, 'comment' => 'Rau tươi'])
            ->assertRedirect('/lo/F2H-261003-RM-NH01')
            ->assertSessionHas('status');

        $feedback = Feedback::firstOrFail();
        $this->assertSame([$this->batch->id, 4, 5, 'Rau tươi', FeedbackStatus::New], [$feedback->batch_id, $feedback->rating, $feedback->freshness_rating, $feedback->comment, $feedback->status]);
        $this->assertNull($feedback->customer_id);
        $this->assertNull($feedback->order_id);
    }

    public function test_known_phone_links_the_customer_and_the_order_that_used_this_batch(): void
    {
        $this->post('/lo/F2H-261003-RM-NH01/phan-hoi', ['rating' => 2, 'comment' => 'Rau hơi héo', 'phone' => '0988000001']);

        $feedback = Feedback::firstOrFail();
        $this->assertSame([$this->order->customer_id, $this->order->id], [$feedback->customer_id, $feedback->order_id]);
    }

    public function test_unknown_phone_is_not_stored_anywhere(): void
    {
        $this->post('/lo/F2H-261003-RM-NH01/phan-hoi', ['rating' => 5, 'phone' => '0977000000'])->assertRedirect();

        $this->assertNull(Feedback::firstOrFail()->customer_id);
        $this->assertDatabaseMissing('customers', ['phone' => '0977000000']);
    }

    public function test_rating_and_phone_are_validated(): void
    {
        $this->post('/lo/F2H-261003-RM-NH01/phan-hoi', ['rating' => 6])->assertSessionHasErrors('rating');
        $this->post('/lo/F2H-261003-RM-NH01/phan-hoi', [])->assertSessionHasErrors('rating');
        $this->post('/lo/F2H-261003-RM-NH01/phan-hoi', ['rating' => 3, 'freshness_rating' => 0])->assertSessionHasErrors('freshness_rating');
        $this->post('/lo/F2H-261003-RM-NH01/phan-hoi', ['rating' => 3, 'phone' => '123'])->assertSessionHasErrors('phone');

        $this->assertSame(0, Feedback::count());
    }

    public function test_feedback_is_refused_for_a_batch_that_is_not_public(): void
    {
        $this->batch->update(['quality_status' => 'rejected']);

        $this->post('/lo/F2H-261003-RM-NH01/phan-hoi', ['rating' => 4])->assertNotFound();
    }

    public function test_public_page_has_the_form_fields(): void
    {
        $this->get('/lo/F2H-261003-RM-NH01')
            ->assertSee('name="rating"', false)
            ->assertSee('name="freshness_rating"', false)
            ->assertSee('name="comment"', false)
            ->assertSee('name="phone"', false);
    }

    public function test_operator_lists_and_resolves_feedback(): void
    {
        $this->post('/lo/F2H-261003-RM-NH01/phan-hoi', ['rating' => 2, 'comment' => 'Rau hơi héo', 'phone' => '0988000001']);
        $feedback = Feedback::firstOrFail();
        $this->actingAs($this->operator);

        $this->get('/admin/feedbacks?status=new')
            ->assertOk()
            ->assertSee('Rau hơi héo')
            ->assertSee('F2H-261003-RM-NH01')
            ->assertSee('Nguyễn Thị Lan');

        $this->from('/admin/feedbacks')
            ->patch('/admin/feedbacks/'.$feedback->id, ['status' => 'resolved', 'response' => ''])
            ->assertSessionHasErrors('action');

        $this->patch('/admin/feedbacks/'.$feedback->id, ['status' => 'resolved', 'response' => 'Đã gọi xin lỗi và bù 1 gói ở đơn sau.'])
            ->assertRedirect();

        $feedback->refresh();
        $this->assertSame([FeedbackStatus::Resolved, $this->operator->id], [$feedback->status, $feedback->resolved_by]);
        $this->assertNotNull($feedback->resolved_at);
        $this->get('/admin/feedbacks?status=new')->assertDontSee('Rau hơi héo');
    }

    public function test_guest_cannot_open_the_feedback_inbox(): void
    {
        $this->get('/admin/feedbacks')->assertRedirect('/login');
    }
}

# Phase 5 — Lô hàng, QR truy xuất, phản hồi: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Khi nhận rau từ nông hộ, operator tạo lô, kiểm chất lượng, gán lô cho từng dòng đơn, in tem QR. Khách quét QR thấy dữ kiện của lô và gửi phản hồi; operator xử lý phản hồi.

**Architecture:** `BatchService` (đã có phần tồn kho từ Phase 4) nhận thêm `receive`, `setQuality`, `allocate`, `logWaste`. Trang `/lo/{code}` công khai, chỉ hiển thị lô đã đạt kiểm tra. QR chứa URL đầy đủ của trang lô, sinh bằng `endroid/qr-code` dạng SVG (không cần GD).

**Tech Stack:** Laravel 13 · endroid/qr-code 6 · Blade · PHPUnit 12.

**Spec:** [`docs/CLAUDE.md`](../CLAUDE.md) mục 1 (QR ≠ certification), 4 (Traceability, Feedback), 5 · [`docs/PLAN.md`](../PLAN.md) quyết định D8, D12, D13.

## Global Constraints

Xem `docs/PLAN.md`. Riêng phase này:

- Mã lô `F2H-{yymmdd}-{product.code}-{farmer.code}`, `yymmdd` là ngày thu hoạch.
- Trang QR **không** có câu quảng cáo ("100% sạch", "an toàn tuyệt đối"…), không lời khuyên sức khỏe.
- Chứng nhận chỉ hiện khi `Farmer::hasValidCertificate()` trả `true`.
- Phản hồi không lưu số điện thoại; chỉ dùng số điện thoại để nối với khách và đơn đã có.

---

### Task 5.1: Nhận hàng tạo lô, kiểm chất lượng

**Files:**
- Modify: `app/Services/BatchService.php`
- Test: `tests/Feature/Batches/BatchReceiveTest.php`

**Interfaces:**
- Consumes: `HarvestPlanService` (dựng dữ liệu test), model `Batch`, `HarvestPlanItem`.
- Produces:
  - `BatchService::receive(HarvestPlanItem $item, float $receivedKg, ?CarbonInterface $harvestedAt = null): Batch`
  - `BatchService::setQuality(Batch $batch, QualityStatus $status, ?string $note = null): Batch`

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Batches/BatchReceiveTest.php`:

```php
<?php

namespace Tests\Feature\Batches;

use App\Enums\OrderChannel;
use App\Enums\QualityStatus;
use App\Models\Batch;
use App\Models\HarvestPlanItem;
use App\Models\SupplyReport;
use App\Services\BatchService;
use App\Services\HarvestPlanService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class BatchReceiveTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private BatchService $batches;

    private HarvestPlanItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->buildCatalog();
        $this->batches = app(BatchService::class);

        app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 14]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
        SupplyReport::create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 9, 'source' => 'form']);

        $plans = app(HarvestPlanService::class);
        $plan = $plans->generateDraft(CarbonImmutable::parse('2026-10-03'), $this->operator);
        $this->item = $plan->items->firstOrFail();
    }

    private function confirmPlan(): void
    {
        app(HarvestPlanService::class)->confirm($this->item->plan, $this->operator);
        $this->item->refresh();
        $this->travelTo('2026-10-03 07:00:00');
    }

    public function test_receiving_creates_a_batch_with_code_and_shelf_life(): void
    {
        $this->confirmPlan();

        $batch = $this->batches->receive($this->item, 7.8)->refresh();

        $this->assertSame('F2H-261003-RM-NH01', $batch->code);
        $this->assertSame('7.80', $batch->quantity_kg);
        $this->assertSame('2026-10-03 05:30', $batch->harvested_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-03 07:00', $batch->received_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-05', $batch->best_before->toDateString());
        $this->assertSame('Bảo quản ngăn mát 4–8°C.', $batch->storage_guide);
        $this->assertSame(QualityStatus::Pending, $batch->quality_status);
        $this->assertSame($this->item->id, $batch->harvest_plan_item_id);
        $this->assertSame('7.80', $this->item->refresh()->received_kg);
    }

    public function test_receiving_again_corrects_the_quantity_instead_of_duplicating(): void
    {
        $this->confirmPlan();

        $this->batches->receive($this->item, 7.8);
        $this->batches->receive($this->item, 8.1);

        $this->assertSame(1, Batch::count());
        $this->assertSame('8.10', Batch::firstOrFail()->quantity_kg);
        $this->assertSame('8.10', $this->item->refresh()->received_kg);
    }

    public function test_cannot_receive_for_a_draft_plan(): void
    {
        try {
            $this->batches->receive($this->item, 7.8);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Chỉ nhận hàng cho kế hoạch đã chốt.', $e->getMessage());
        }

        $this->assertSame(0, Batch::count());
    }

    public function test_quantity_must_be_positive_and_row_must_have_a_farmer(): void
    {
        $this->confirmPlan();

        try {
            $this->batches->receive($this->item, 0);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Khối lượng nhận phải lớn hơn 0.', $e->getMessage());
        }

        $this->item->update(['farmer_id' => null]);

        $this->expectException(DomainException::class);
        $this->batches->receive($this->item->refresh(), 5);
    }

    public function test_quality_check_passes_or_rejects_a_batch(): void
    {
        $this->confirmPlan();
        $batch = $this->batches->receive($this->item, 7.8);

        $this->batches->setQuality($batch, QualityStatus::Rejected, 'Lá úa nhiều');

        $this->assertSame([QualityStatus::Rejected, 'Lá úa nhiều'], [$batch->refresh()->quality_status, $batch->qc_note]);
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=BatchReceiveTest`
Expected: FAIL (`Call to undefined method App\Services\BatchService::receive()`).

- [ ] **Step 3: Thêm vào `app/Services/BatchService.php`**

```php
    public function receive(HarvestPlanItem $item, float $receivedKg, ?CarbonInterface $harvestedAt = null): Batch
    {
        $item->loadMissing('plan', 'product', 'farmer');

        if (! in_array($item->plan->status, [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent], true)) {
            throw new DomainException('Chỉ nhận hàng cho kế hoạch đã chốt.');
        }

        if ($item->farmer === null) {
            throw new DomainException('Dòng kế hoạch chưa có nông hộ.');
        }

        if ($receivedKg <= 0) {
            throw new DomainException('Khối lượng nhận phải lớn hơn 0.');
        }

        return DB::transaction(function () use ($item, $receivedKg, $harvestedAt) {
            $harvestedAt = CarbonImmutable::parse(
                $harvestedAt ?? $item->plan->harvest_date->toDateString().' '.config('farm2home.batch.harvest_time')
            );

            $item->update(['received_kg' => $receivedKg]);

            // Mỗi nông hộ + loại rau + ngày thu hoạch chỉ có một lô: nhận lại là sửa số.
            return Batch::updateOrCreate(
                ['code' => sprintf('F2H-%s-%s-%s', $harvestedAt->format('ymd'), $item->product->code, $item->farmer->code)],
                [
                    'harvest_plan_item_id' => $item->id,
                    'farmer_id' => $item->farmer_id,
                    'product_id' => $item->product_id,
                    'quantity_kg' => $receivedKg,
                    'harvested_at' => $harvestedAt,
                    'received_at' => now(),
                    'storage_guide' => $item->product->storage_guide,
                    'best_before' => $harvestedAt->addDays((int) $item->product->shelf_life_days)->toDateString(),
                ],
            );
        });
    }

    public function setQuality(Batch $batch, QualityStatus $status, ?string $note = null): Batch
    {
        $batch->update(['quality_status' => $status, 'qc_note' => $note]);

        return $batch;
    }
```

Thêm import: `App\Enums\HarvestPlanStatus`, `App\Models\HarvestPlanItem`, `DomainException`.

- [ ] **Step 4: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter=BatchReceiveTest`
Expected: PASS (5 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: receive harvested produce into traceable batches with quality status"
```

---

### Task 5.2: Gán lô cho đơn và ghi rau dư

**Files:**
- Modify: `app/Services/BatchService.php`
- Test: `tests/Feature/Batches/BatchAllocationTest.php`

**Interfaces:**
- Consumes: `BatchService::remainingKg`, `OrderService::transition`.
- Produces:
  - `BatchService::allocate(CarbonInterface $deliveryDate): array{allocated_packs: int, short_packs: int}` — gán cho đơn `confirmed`/`packing` của ngày giao; lô hết hạn sớm nhất dùng trước; đơn giỏ định kỳ trước, rồi theo thời điểm đặt (quyết định D12); chạy lại không gán trùng.
  - `BatchService::logWaste(Batch $batch, float $kg, WasteReason $reason, User $by, ?string $note = null): WasteLog`

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Batches/BatchAllocationTest.php`:

```php
<?php

namespace Tests\Feature\Batches;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\WasteReason;
use App\Models\Batch;
use App\Models\Order;
use App\Models\Subscription;
use App\Services\BatchService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class BatchAllocationTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private BatchService $batches;

    private OrderService $orders;

    private CarbonImmutable $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 07:00:00');
        $this->buildCatalog();
        $this->batches = app(BatchService::class);
        $this->orders = app(OrderService::class);
        $this->date = CarbonImmutable::parse('2026-10-03');
    }

    private function order(string $phone, int $rmPacks, bool $confirm = true): Order
    {
        $order = $this->orders->place([
            'name' => 'Khách '.$phone, 'phone' => $phone, 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => $rmPacks]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);

        return $confirm ? $this->orders->transition($order, OrderStatus::Confirmed) : $order;
    }

    private function batch(string $code, float $kg, string $bestBefore = '2026-10-05', string $quality = 'passed', string $harvestedAt = '2026-10-03 05:30:00'): Batch
    {
        return Batch::create([
            'code' => $code, 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => $kg,
            'harvested_at' => $harvestedAt, 'best_before' => $bestBefore, 'quality_status' => $quality,
        ]);
    }

    public function test_packs_are_allocated_and_shortage_is_reported(): void
    {
        $order = $this->order('0988000001', 14);
        $batch = $this->batch('F2H-261003-RM-NH01', 5.0);

        $result = $this->batches->allocate($this->date);

        // 5,0 kg = 10 gói 0,5 kg; đơn cần 14 gói.
        $this->assertSame(['allocated_packs' => 10, 'short_packs' => 4], $result);
        $this->assertSame(10, (int) $order->items[0]->batches()->sum('order_item_batches.quantity'));
        $this->assertSame(0.0, $this->batches->remainingKg($batch));
    }

    public function test_running_twice_does_not_allocate_twice(): void
    {
        $this->order('0988000001', 4);
        $batch = $this->batch('F2H-261003-RM-NH01', 5.0);

        $this->batches->allocate($this->date);
        $second = $this->batches->allocate($this->date);

        $this->assertSame(['allocated_packs' => 0, 'short_packs' => 0], $second);
        $this->assertSame(3.0, $this->batches->remainingKg($batch));
    }

    public function test_batch_expiring_first_is_used_first_and_an_item_can_span_batches(): void
    {
        $order = $this->order('0988000001', 6);
        $fresh = $this->batch('F2H-261003-RM-NH01', 5.0, '2026-10-05');
        $older = $this->batch('F2H-261002-RM-NH01', 1.0, '2026-10-04', 'passed', '2026-10-02 05:30:00');

        $this->batches->allocate($this->date);

        $pivot = $order->items[0]->batches()->pluck('order_item_batches.quantity', 'batches.code')->all();
        // assertEquals: không phụ thuộc thứ tự dòng trả về.
        $this->assertEquals(['F2H-261002-RM-NH01' => 2, 'F2H-261003-RM-NH01' => 4], $pivot);
        $this->assertSame([0.0, 3.0], [$this->batches->remainingKg($older), $this->batches->remainingKg($fresh)]);
    }

    public function test_subscription_orders_are_served_first(): void
    {
        $walkIn = $this->order('0988000001', 6);
        $subscriber = $this->order('0988000002', 6);
        $subscription = Subscription::create([
            'customer_id' => $subscriber->customer_id, 'basket_id' => $this->basket->id, 'frequency' => 'weekly',
            'delivery_weekday' => 6, 'time_slot' => '17:00-19:00', 'start_date' => '2026-09-01', 'status' => 'active',
        ]);
        $subscriber->update(['subscription_id' => $subscription->id]);
        $this->batch('F2H-261003-RM-NH01', 4.0);

        $result = $this->batches->allocate($this->date);

        $this->assertSame(['allocated_packs' => 8, 'short_packs' => 4], $result);
        $this->assertSame(6, (int) $subscriber->items[0]->batches()->sum('order_item_batches.quantity'));
        $this->assertSame(2, (int) $walkIn->items[0]->batches()->sum('order_item_batches.quantity'));
    }

    public function test_pending_orders_rejected_or_expired_batches_are_skipped(): void
    {
        $this->order('0988000001', 4, confirm: false);
        $confirmed = $this->order('0988000002', 4);
        $this->batch('F2H-261003-RM-NH01', 5.0, '2026-10-05', 'rejected');
        $this->batch('F2H-261001-RM-NH01', 5.0, '2026-10-02', 'passed', '2026-10-01 05:30:00');
        $this->batch('F2H-261003-RM-NH02', 5.0, '2026-10-05', 'pending');

        $result = $this->batches->allocate($this->date);

        $this->assertSame(['allocated_packs' => 0, 'short_packs' => 4], $result);
        $this->assertSame(0, $confirmed->items[0]->batches()->count());
    }

    public function test_cancelling_an_order_returns_its_packs_to_the_batch(): void
    {
        $order = $this->order('0988000001', 4);
        $batch = $this->batch('F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);
        $this->assertSame(3.0, $this->batches->remainingKg($batch));

        $this->orders->transition($order, OrderStatus::Cancelled, 'Khách đổi lịch');

        $this->assertSame(5.0, $this->batches->remainingKg($batch));
    }

    public function test_delivery_records_what_was_actually_allocated(): void
    {
        $order = $this->order('0988000001', 14);
        $this->batch('F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);

        foreach ([OrderStatus::Packing, OrderStatus::Delivering, OrderStatus::Delivered] as $status) {
            $this->orders->transition($order, $status);
        }

        $this->assertSame([14, 10], [$order->items[0]->refresh()->quantity, $order->items[0]->fulfilled_quantity]);
    }

    public function test_waste_cannot_exceed_what_is_left_in_the_batch(): void
    {
        $this->order('0988000001', 4);
        $batch = $this->batch('F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);

        $log = $this->batches->logWaste($batch, 1.0, WasteReason::Discounted, $this->operator, 'Bán giảm giá cuối ngày');

        $this->assertSame(['1.00', WasteReason::Discounted, '2026-10-03'], [$log->quantity_kg, $log->reason, $log->log_date->toDateString()]);
        $this->assertSame(2.0, $this->batches->remainingKg($batch));

        try {
            $this->batches->logWaste($batch, 2.5, WasteReason::Surplus, $this->operator);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Lô này chỉ còn 2 kg.', $e->getMessage());
        }
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=BatchAllocationTest`
Expected: FAIL (`Call to undefined method App\Services\BatchService::allocate()`).

- [ ] **Step 3: Thêm vào `app/Services/BatchService.php`**

```php
    /** @return array{allocated_packs: int, short_packs: int} */
    public function allocate(CarbonInterface $deliveryDate): array
    {
        $date = CarbonImmutable::parse($deliveryDate)->startOfDay();

        return DB::transaction(function () use ($date) {
            $items = OrderItem::with('order', 'product', 'batches')
                ->whereHas('order', fn ($query) => $query
                    ->whereDate('delivery_date', $date->toDateString())
                    ->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Packing]))
                ->get()
                // Quyết định D12: giỏ định kỳ trước, rồi theo thời điểm đặt.
                ->sortBy(fn (OrderItem $item) => [
                    $item->order->subscription_id === null ? 1 : 0,
                    $item->order->created_at->getTimestamp(),
                    $item->id,
                ]);

            $batchesByProduct = [];
            $packsLeft = [];
            $allocated = 0;
            $short = 0;

            foreach ($items as $item) {
                $need = $item->quantity - (int) $item->batches->sum('pivot.quantity');

                if ($need <= 0) {
                    continue;
                }

                $batchesByProduct[$item->product_id] ??= Batch::where('product_id', $item->product_id)
                    ->where('quality_status', QualityStatus::Passed)
                    ->whereDate('best_before', '>=', $date->toDateString())
                    ->where('harvested_at', '<', $date->addDay())
                    ->orderBy('best_before')
                    ->orderBy('harvested_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($batchesByProduct[$item->product_id] as $batch) {
                    if ($need <= 0) {
                        break;
                    }

                    $packsLeft[$batch->id] ??= (int) floor(round($this->remainingKg($batch) / (float) $item->product->pack_weight_kg, 6));
                    $take = min($need, $packsLeft[$batch->id]);

                    if ($take <= 0) {
                        continue;
                    }

                    $already = (int) ($item->batches->firstWhere('id', $batch->id)?->pivot->quantity ?? 0);
                    $item->batches()->syncWithoutDetaching([$batch->id => ['quantity' => $already + $take]]);

                    $packsLeft[$batch->id] -= $take;
                    $need -= $take;
                    $allocated += $take;
                }

                $short += $need;
            }

            return ['allocated_packs' => $allocated, 'short_packs' => $short];
        });
    }

    public function logWaste(Batch $batch, float $kg, WasteReason $reason, User $by, ?string $note = null): WasteLog
    {
        $remaining = $this->remainingKg($batch);

        if ($kg <= 0) {
            throw new DomainException('Khối lượng phải lớn hơn 0.');
        }

        if ($kg > $remaining + 0.001) {
            throw new DomainException('Lô này chỉ còn '.Format::kg($remaining).'.');
        }

        return WasteLog::create([
            'log_date' => today()->toDateString(),
            'product_id' => $batch->product_id,
            'batch_id' => $batch->id,
            'quantity_kg' => $kg,
            'reason' => $reason,
            'note' => $note,
            'created_by' => $by->id,
        ]);
    }
```

Thêm import: `App\Enums\OrderStatus`, `App\Enums\WasteReason`, `App\Models\OrderItem`, `App\Models\User`, `App\Models\WasteLog`, `App\Support\Format`.

- [ ] **Step 4: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter=BatchAllocationTest`
Expected: PASS (8 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: allocate batches to order items (FEFO, subscriptions first) and log waste"
```

---

### Task 5.3: Màn hình lô hàng, tem QR, trang QR công khai

**Files:**
- Create: `app/Services/QrCodeService.php`, `app/Http/Controllers/Admin/BatchController.php`, `app/Http/Controllers/Public/BatchPageController.php`, `resources/views/admin/batches/{index,show,labels}.blade.php`, `resources/views/public/batches/show.blade.php`
- Modify: `app/Http/Controllers/Admin/HarvestPlanController.php` (thêm `receive`), `resources/views/admin/harvest-plans/show.blade.php` (ô nhập thực nhận), `routes/web.php`, `composer.json` (qua `composer require`)
- Test: `tests/Feature/Batches/BatchScreensTest.php`, `tests/Feature/Batches/PublicBatchPageTest.php`

**Interfaces:**
- Consumes: `BatchService::receive`, `setQuality`, `allocate`, `remainingKg`; `Farmer::hasValidCertificate()`.
- Produces:
  - `QrCodeService::svg(string $data, int $size = 220): string` — chuỗi `<svg …>` nhúng thẳng vào HTML.
  - Route `admin.harvest-plan-items.receive`, `admin.batches.index`, `admin.batches.show`, `admin.batches.update`, `admin.batches.labels`, `admin.batches.allocate`, `batches.public`.

- [ ] **Step 1: Cài thư viện QR**

```bash
composer require endroid/qr-code:^6.0
```

- [ ] **Step 2: Viết test (sẽ fail)**

`tests/Feature/Batches/PublicBatchPageTest.php`:

```php
<?php

namespace Tests\Feature\Batches;

use App\Models\Batch;
use App\Models\Farmer;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class PublicBatchPageTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 12:00:00');
        $this->buildCatalog();
    }

    private function batch(Farmer $farmer, string $quality = 'passed'): Batch
    {
        return Batch::create([
            'code' => 'F2H-261003-RM-'.$farmer->code, 'farmer_id' => $farmer->id, 'product_id' => $this->rm->id, 'quantity_kg' => 5,
            'harvested_at' => '2026-10-03 05:30:00', 'received_at' => '2026-10-03 07:00:00',
            'processed_at' => '2026-10-03 08:30:00', 'packed_at' => '2026-10-03 10:00:00',
            'processing_note' => 'Nhặt lá úa, rửa sơ, để ráo', 'storage_guide' => 'Bảo quản ngăn mát 4–8°C.',
            'best_before' => '2026-10-05', 'quality_status' => $quality,
        ]);
    }

    public function test_page_shows_batch_facts_and_a_feedback_form(): void
    {
        $this->batch($this->nh01);

        $this->get('/lo/F2H-261003-RM-NH01')
            ->assertOk()
            ->assertSee('F2H-261003-RM-NH01')
            ->assertSee('Rau muống')
            ->assertSee('Nguyễn Văn Tâm')
            ->assertSee('Xã Thân Cửu Nghĩa, Đồng Tháp')
            ->assertSee('03/10/2026 05:30')
            ->assertSee('03/10/2026 10:00')
            ->assertSee('Nhặt lá úa, rửa sơ, để ráo')
            ->assertSee('Bảo quản ngăn mát 4–8°C.')
            ->assertSee('05/10/2026')
            ->assertSee('Gửi phản hồi');
    }

    public function test_page_makes_no_marketing_or_health_claims(): void
    {
        $this->batch($this->nh01);

        $this->get('/lo/F2H-261003-RM-NH01')
            ->assertDontSee('100%')
            ->assertDontSee('tuyệt đối')
            ->assertDontSee('sức khỏe')
            ->assertDontSee('0911000001');
    }

    public function test_certificate_is_shown_only_for_a_farmer_who_has_one(): void
    {
        $this->batch($this->nh01);
        $this->batch($this->nh02);

        $this->get('/lo/F2H-261003-RM-NH01')->assertDontSee('VietGAP')->assertSee('Chưa có chứng nhận được xác thực');
        $this->get('/lo/F2H-261003-RM-NH02')->assertSee('VietGAP (mẫu)')->assertSee('30/06/2027');
    }

    public function test_expired_certificate_is_not_shown(): void
    {
        $this->batch($this->nh02);
        $this->nh02->update(['certificate_expires_at' => '2026-10-02']);

        $this->get('/lo/F2H-261003-RM-NH02')->assertOk()->assertDontSee('VietGAP');
    }

    public function test_only_batches_that_passed_quality_check_are_public(): void
    {
        $this->batch($this->nh01, 'pending');
        $this->batch($this->nh02, 'rejected');

        $this->get('/lo/F2H-261003-RM-NH01')->assertNotFound();
        $this->get('/lo/F2H-261003-RM-NH02')->assertNotFound();
        $this->get('/lo/KHONG-TON-TAI')->assertNotFound();
    }

    public function test_qr_service_returns_inline_svg(): void
    {
        $svg = app(QrCodeService::class)->svg('http://farm2home.test/lo/F2H-261003-RM-NH01');

        $this->assertStringStartsWith('<svg', ltrim($svg));
        $this->assertStringNotContainsString('<?xml', $svg);
    }
}
```

`tests/Feature/Batches/BatchScreensTest.php`:

```php
<?php

namespace Tests\Feature\Batches;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\QualityStatus;
use App\Models\Batch;
use App\Models\HarvestPlanItem;
use App\Models\SupplyReport;
use App\Services\HarvestPlanService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class BatchScreensTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private HarvestPlanItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->buildCatalog();

        $order = app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 14]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
        app(OrderService::class)->transition($order, OrderStatus::Confirmed);
        SupplyReport::create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 9, 'source' => 'form']);

        $plans = app(HarvestPlanService::class);
        $plan = $plans->confirm($plans->generateDraft(CarbonImmutable::parse('2026-10-03'), $this->operator), $this->operator);
        $this->item = $plan->items->firstOrFail();

        $this->travelTo('2026-10-03 07:00:00');
        $this->actingAs($this->operator);
    }

    private function receive(): Batch
    {
        $this->post('/admin/harvest-plan-items/'.$this->item->id.'/receive', ['received_kg' => '7.8']);

        return Batch::firstOrFail();
    }

    public function test_operator_receives_produce_from_the_plan_screen(): void
    {
        $this->post('/admin/harvest-plan-items/'.$this->item->id.'/receive', ['received_kg' => '7.8'])
            ->assertRedirect('/admin/harvest-plans/'.$this->item->harvest_plan_id);

        $this->assertSame('F2H-261003-RM-NH01', Batch::firstOrFail()->code);
        $this->get('/admin/harvest-plans/'.$this->item->harvest_plan_id)->assertSee('F2H-261003-RM-NH01')->assertSee('7,8 kg');
    }

    public function test_received_kg_is_validated(): void
    {
        $this->post('/admin/harvest-plan-items/'.$this->item->id.'/receive', ['received_kg' => 'abc'])->assertSessionHasErrors('received_kg');
        $this->from('/admin/harvest-plans/'.$this->item->harvest_plan_id)
            ->post('/admin/harvest-plan-items/'.$this->item->id.'/receive', ['received_kg' => '0'])
            ->assertSessionHasErrors('action');
    }

    public function test_batch_list_and_detail(): void
    {
        $batch = $this->receive();

        $this->get('/admin/batches?date=2026-10-03')->assertOk()->assertSee('F2H-261003-RM-NH01')->assertSee('Chờ kiểm');
        $this->get('/admin/batches/'.$batch->id)
            ->assertOk()
            ->assertSee('Nguyễn Văn Tâm')
            ->assertSee('7,8 kg')
            ->assertSee('In tem QR');
    }

    public function test_operator_records_processing_times_and_passes_the_batch(): void
    {
        $batch = $this->receive();

        $this->patch('/admin/batches/'.$batch->id, [
            'quality_status' => 'passed', 'qc_note' => '',
            'processed_at' => '2026-10-03T08:30', 'packed_at' => '2026-10-03T10:00',
            'processing_note' => 'Nhặt lá úa, rửa sơ, để ráo',
        ])->assertRedirect('/admin/batches/'.$batch->id);

        $batch->refresh();
        $this->assertSame(QualityStatus::Passed, $batch->quality_status);
        $this->assertSame('2026-10-03 10:00', $batch->packed_at->format('Y-m-d H:i'));
        $this->assertSame('Nhặt lá úa, rửa sơ, để ráo', $batch->processing_note);
    }

    public function test_allocate_button_assigns_batches_and_reports_shortage(): void
    {
        $batch = $this->receive();
        $batch->update(['quality_status' => 'passed', 'quantity_kg' => 5]);

        $this->post('/admin/batches/allocate', ['date' => '2026-10-03'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Đã gán 10 gói. Còn thiếu 4 gói.');
    }

    public function test_label_sheet_prints_one_qr_per_label_with_the_public_url(): void
    {
        $batch = $this->receive();
        $batch->update(['quality_status' => 'passed', 'packed_at' => '2026-10-03 10:00:00']);

        $response = $this->get('/admin/batches/'.$batch->id.'/labels?count=6')->assertOk();

        $this->assertSame(6, substr_count($response->getContent(), '<svg'));
        $response->assertSee('F2H-261003-RM-NH01')->assertSee('Rau muống')->assertSee('05/10/2026');
    }

    public function test_labels_are_refused_for_a_batch_that_did_not_pass(): void
    {
        $batch = $this->receive();

        $this->get('/admin/batches/'.$batch->id.'/labels')->assertRedirect()->assertSessionHasErrors('action');
    }
}
```

- [ ] **Step 3: Chạy test, xác nhận fail**

Run: `php artisan test --filter="PublicBatchPageTest|BatchScreensTest"`
Expected: FAIL (404, class not found).

- [ ] **Step 4: Viết `app/Services/QrCodeService.php`**

```php
<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    public function svg(string $data, int $size = 220): string
    {
        $builder = new Builder(
            writer: new SvgWriter,
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            data: $data,
            size: $size,
            margin: 8,
        );

        return $builder->build()->getString();
    }
}
```

Nếu `composer show endroid/qr-code` báo bản 5.x (API cũ), dùng `Builder::create()->writer(new SvgWriter)->writerOptions([...])->data($data)->size($size)->margin(8)->build()->getString()`. Test `test_qr_service_returns_inline_svg` phải xanh với cách nào cũng vậy.

- [ ] **Step 5: Viết `app/Http/Controllers/Public/BatchPageController.php`**

```php
<?php

namespace App\Http\Controllers\Public;

use App\Enums\QualityStatus;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\View\View;

class BatchPageController extends Controller
{
    public function __invoke(Batch $batch): View
    {
        // Chỉ lô đã đạt kiểm tra mới được công khai.
        abort_unless($batch->quality_status === QualityStatus::Passed, 404);

        return view('public.batches.show', ['batch' => $batch->load('product', 'farmer')]);
    }
}
```

- [ ] **Step 6: Viết `app/Http/Controllers/Admin/BatchController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QualityStatus;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Services\BatchService;
use App\Services\QrCodeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(Request $request, BatchService $batches): View
    {
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? today()->toDateString();

        $rows = Batch::with('product', 'farmer')->whereDate('harvested_at', $date)->orderBy('product_id')->get();

        return view('admin.batches.index', [
            'date' => $date,
            'batches' => $rows,
            'remaining' => $rows->mapWithKeys(fn (Batch $batch) => [$batch->id => $batches->remainingKg($batch)]),
        ]);
    }

    public function show(Batch $batch, BatchService $batches): View
    {
        return view('admin.batches.show', [
            'batch' => $batch->load('product', 'farmer', 'orderItems.order', 'wasteLogs', 'feedbacks'),
            'remainingKg' => $batches->remainingKg($batch),
            'qualityStatuses' => QualityStatus::cases(),
        ]);
    }

    public function update(Request $request, Batch $batch, BatchService $batches): RedirectResponse
    {
        $data = $request->validate([
            'quality_status' => ['required', Rule::enum(QualityStatus::class)],
            'qc_note' => ['nullable', 'string', 'max:255'],
            'processed_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'packed_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'processing_note' => ['nullable', 'string', 'max:255'],
        ]);

        $batch->update([
            'processed_at' => $data['processed_at'] ?? null,
            'packed_at' => $data['packed_at'] ?? null,
            'processing_note' => $data['processing_note'] ?? null,
        ]);
        $batches->setQuality($batch, QualityStatus::from($data['quality_status']), $data['qc_note'] ?? null);

        return redirect()->route('admin.batches.show', $batch)->with('status', 'Đã cập nhật lô hàng.');
    }

    public function allocate(Request $request, BatchService $batches): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $result = $batches->allocate(CarbonImmutable::parse($data['date']));

        $message = "Đã gán {$result['allocated_packs']} gói.";
        if ($result['short_packs'] > 0) {
            $message .= " Còn thiếu {$result['short_packs']} gói.";
        }

        return back()->with('status', $message);
    }

    public function labels(Request $request, Batch $batch, QrCodeService $qr): View|RedirectResponse
    {
        if ($batch->quality_status !== QualityStatus::Passed) {
            return back()->withErrors(['action' => 'Chỉ in tem cho lô đã đạt kiểm tra chất lượng.']);
        }

        $count = (int) ($request->validate(['count' => ['nullable', 'integer', 'min:1', 'max:200']])['count'] ?? 12);

        // Quyết định D8: QR chứa URL đầy đủ của trang lô.
        return view('admin.batches.labels', [
            'batch' => $batch->load('product', 'farmer'),
            'count' => $count,
            'qrSvg' => $qr->svg(route('batches.public', $batch->code), 160),
        ]);
    }
}
```

- [ ] **Step 7: Thêm `receive` vào `HarvestPlanController`**

```php
    public function receive(Request $request, HarvestPlanItem $item, BatchService $batches): RedirectResponse
    {
        $data = $request->validate(['received_kg' => ['required', 'numeric', 'min:0', 'max:1000']]);

        return $this->attempt(function () use ($data, $item, $batches) {
            $batch = $batches->receive($item, (float) $data['received_kg']);

            return redirect()->route('admin.harvest-plans.show', $item->harvest_plan_id)->with('status', "Đã nhận hàng, tạo lô {$batch->code}.");
        });
    }
```

Thêm `use App\Services\BatchService;`.

- [ ] **Step 8: Thêm route**

Ngoài nhóm admin:

```php
Route::get('/lo/{batch:code}', BatchPageController::class)->name('batches.public');
```

Trong nhóm admin (đặt `batches/allocate` **trước** `batches/{batch}`):

```php
    Route::post('harvest-plan-items/{item}/receive', [HarvestPlanController::class, 'receive'])->name('harvest-plan-items.receive');

    Route::get('batches', [BatchController::class, 'index'])->name('batches.index');
    Route::post('batches/allocate', [BatchController::class, 'allocate'])->name('batches.allocate');
    Route::get('batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
    Route::patch('batches/{batch}', [BatchController::class, 'update'])->name('batches.update');
    Route::get('batches/{batch}/labels', [BatchController::class, 'labels'])->name('batches.labels');
```

- [ ] **Step 9: Viết view**

`public/batches/show.blade.php` (layout `x-layouts.public`, title "Lô {{ $batch->code }}"):
- Tiêu đề: tên rau; dòng phụ: "Mã lô {{ $batch->code }}".
- Danh sách dữ kiện (nhãn : giá trị), mục nào `null` thì bỏ qua:
  - Nông hộ: `{{ $batch->farmer->name }}`
  - Khu vực trồng: `{{ $batch->farmer->growing_area }}`
  - Cách canh tác (theo nông hộ khai): `{{ $batch->farmer->farming_method }}`
  - Thu hoạch: `{{ $batch->harvested_at->format('d/m/Y H:i') }}`
  - Sơ chế: `{{ $batch->processed_at?->format('d/m/Y H:i') }}` — `{{ $batch->processing_note }}`
  - Đóng gói: `{{ $batch->packed_at?->format('d/m/Y H:i') }}`
  - Nên dùng trước: `{{ $batch->best_before?->format('d/m/Y') }}`
  - Bảo quản: `{{ $batch->storage_guide }}`
- Khối chứng nhận:
  - `@if ($batch->farmer->hasValidCertificate())` → "Chứng nhận: {{ $batch->farmer->certificate_name }}" và "Hiệu lực đến {{ $batch->farmer->certificate_expires_at?->format('d/m/Y') }}".
  - `@else` → "Chưa có chứng nhận được xác thực. Thông tin trên là dữ kiện do FARM2HOME ghi nhận khi nhận hàng."
- Không in số điện thoại, địa chỉ nhà của nông hộ.
- Khối "Gửi phản hồi": form sẽ được nối ở Task 5.4; ở task này chỉ cần tiêu đề "Gửi phản hồi".

`admin/harvest-plans/show.blade.php` — bổ sung cột "Thực nhận": khi kế hoạch `confirmed` hoặc `sent` và dòng có nông hộ, hiện form `POST route('admin.harvest-plan-items.receive', $item)` với input số `received_kg` (step 0.1, giá trị `$item->received_kg ?? $item->final_kg`) và nút "Nhận". Nếu đã có lô: in `@kg($item->received_kg)` và link mã lô `{{ $item->batch->code }}` → `admin.batches.show`.

`admin/batches/index.blade.php` (title "Lô hàng"):
- Form GET chọn ngày thu hoạch (`date`).
- Form `POST admin.batches.allocate` (input ẩn `date`) nút "Gán lô cho đơn ngày này".
- Bảng: mã lô (link), rau, nông hộ, `@kg($batch->quantity_kg)`, còn lại `@kg($remaining[$batch->id])`, `<x-status-badge :status="$batch->quality_status" />`, hạn dùng.

`admin/batches/show.blade.php` (title = mã lô):
- Thông tin: rau, nông hộ, khối lượng nhận `@kg`, còn lại `@kg($remainingKg)`, thu hoạch, nhận, hạn dùng.
- Form `PATCH admin.batches.update`: select `quality_status`, input `qc_note`, `processed_at` và `packed_at` kiểu `datetime-local` (giá trị `$batch->processed_at?->format('Y-m-d\TH:i')`), input `processing_note`, nút "Lưu".
- Form GET `admin.batches.labels`: input số `count` (mặc định 12), nút "In tem QR".
- Link "Xem trang QR công khai" → `route('batches.public', $batch->code)` (chỉ hiện khi đã đạt).
- Bảng đơn đã gán: mã đơn, số gói (`pivot->quantity`). Bảng rau dư của lô. Số phản hồi.

`admin/batches/labels.blade.php` — trang in độc lập (không dùng layout admin; tự có `<html>`, `@vite`):
- Lưới tem (`grid grid-cols-3 gap-2`, mỗi tem `break-inside-avoid border p-2 text-xs`), lặp `$count` lần.
- Mỗi tem: `{!! $qrSvg !!}`, tên rau, mã lô, "Nông hộ: {{ $batch->farmer->name }}", "Đóng gói: {{ $batch->packed_at?->format('d/m/Y') }}", "Dùng trước: {{ $batch->best_before->format('d/m/Y') }}", dòng nhỏ "Quét mã để xem thông tin lô".
- Nút "In" (`onclick="window.print()"`, lớp `print:hidden`).

- [ ] **Step 10: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter="PublicBatchPageTest|BatchScreensTest"`
Expected: PASS (13 tests).

Kiểm tra tay: in thử một tờ tem ra PDF, quét QR bằng camera điện thoại (điện thoại phải truy cập được domain trong `APP_URL`).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: batch screens, printable QR labels and public traceability page"
```

---

### Task 5.4: Phản hồi của khách

**Files:**
- Create: `app/Services/FeedbackService.php`, `app/Http/Requests/StoreFeedbackRequest.php`, `app/Http/Controllers/Public/FeedbackController.php`, `app/Http/Controllers/Admin/FeedbackController.php`, `resources/views/admin/feedbacks/index.blade.php`
- Modify: `resources/views/public/batches/show.blade.php`, `routes/web.php`
- Test: `tests/Feature/Feedback/FeedbackTest.php`

**Interfaces:**
- Produces:
  - `FeedbackService::submit(Batch $batch, array $data): Feedback` — `$data`: `rating` (1–5), `freshness_rating` (nullable 1–5), `comment` (nullable), `phone` (nullable).
  - `FeedbackService::respond(Feedback $feedback, FeedbackStatus $status, ?string $response, User $by): Feedback`
  - Route `feedbacks.store`, `admin.feedbacks.index`, `admin.feedbacks.update`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Feedback/FeedbackTest.php`:

```php
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

        $this->batch = Batch::create([
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
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=FeedbackTest`
Expected: FAIL (404 / 405).

- [ ] **Step 3: Viết `app/Services/FeedbackService.php`**

```php
<?php

namespace App\Services;

use App\Enums\FeedbackStatus;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\User;
use DomainException;

class FeedbackService
{
    public function submit(Batch $batch, array $data): Feedback
    {
        // Số điện thoại chỉ dùng để nối với khách đã có; không lưu lại.
        $customer = filled($data['phone'] ?? null) ? Customer::where('phone', $data['phone'])->first() : null;

        $order = $customer === null ? null : Order::where('customer_id', $customer->id)
            ->whereHas('items.batches', fn ($query) => $query->where('batches.id', $batch->id))
            ->orderByDesc('delivery_date')
            ->first();

        return Feedback::create([
            'batch_id' => $batch->id,
            'order_id' => $order?->id,
            'customer_id' => $customer?->id,
            'rating' => (int) $data['rating'],
            'freshness_rating' => isset($data['freshness_rating']) ? (int) $data['freshness_rating'] : null,
            'comment' => $data['comment'] ?? null,
            'status' => FeedbackStatus::New,
        ]);
    }

    public function respond(Feedback $feedback, FeedbackStatus $status, ?string $response, User $by): Feedback
    {
        if ($status === FeedbackStatus::Resolved && blank($response)) {
            throw new DomainException('Cần ghi cách xử lý trước khi đóng phản hồi.');
        }

        $feedback->update([
            'status' => $status,
            'response' => $response,
            'resolved_by' => $status === FeedbackStatus::Resolved ? $by->id : null,
            'resolved_at' => $status === FeedbackStatus::Resolved ? now() : null,
        ]);

        return $feedback;
    }
}
```

- [ ] **Step 4: Viết request và controller**

`app/Http/Requests/StoreFeedbackRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'freshness_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'regex:/^0\d{9}$/'],
        ];
    }
}
```

`app/Http/Controllers/Public/FeedbackController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Enums\QualityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Batch;
use App\Services\FeedbackService;
use Illuminate\Http\RedirectResponse;

class FeedbackController extends Controller
{
    public function store(StoreFeedbackRequest $request, Batch $batch, FeedbackService $feedbacks): RedirectResponse
    {
        abort_unless($batch->quality_status === QualityStatus::Passed, 404);

        $feedbacks->submit($batch, $request->validated());

        return redirect()->route('batches.public', $batch->code)->with('status', 'Cảm ơn bạn đã phản hồi. Chúng tôi sẽ xem và xử lý.');
    }
}
```

`app/Http/Controllers/Admin/FeedbackController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedbackStatus;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\FeedbackService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(FeedbackStatus::class)]])['status'] ?? null;

        return view('admin.feedbacks.index', [
            'status' => $status,
            'statuses' => FeedbackStatus::cases(),
            'feedbacks' => Feedback::with('batch.product', 'batch.farmer', 'customer', 'order')
                ->when($status, fn ($query, $value) => $query->where('status', $value))
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function update(Request $request, Feedback $feedback, FeedbackService $feedbacks): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(FeedbackStatus::class)],
            'response' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $feedbacks->respond($feedback, FeedbackStatus::from($data['status']), $data['response'] ?? null, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('status', 'Đã cập nhật phản hồi.');
    }
}
```

Route, ngoài nhóm admin:

```php
Route::post('/lo/{batch:code}/phan-hoi', [PublicFeedbackController::class, 'store'])->middleware('throttle:5,1')->name('feedbacks.store');
```

Trong nhóm admin:

```php
    Route::get('feedbacks', [AdminFeedbackController::class, 'index'])->name('feedbacks.index');
    Route::patch('feedbacks/{feedback}', [AdminFeedbackController::class, 'update'])->name('feedbacks.update');
```

với `use App\Http\Controllers\Public\FeedbackController as PublicFeedbackController;` và `use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;`.

- [ ] **Step 5: Viết view**

`public/batches/show.blade.php` — thay tiêu đề "Gửi phản hồi" bằng khối đầy đủ:
- `<x-flash />`.
- Form `POST route('feedbacks.store', $batch->code)`, `@csrf`:
  - "Bạn đánh giá lô rau này thế nào?" — 5 radio `rating` (1–5) dạng nút lớn.
  - "Độ tươi" — 5 radio `freshness_rating` (không bắt buộc).
  - `<textarea name="comment">` nhãn "Nhận xét (không bắt buộc)".
  - `<x-field name="phone" label="Số điện thoại đặt hàng (không bắt buộc, để chúng tôi liên hệ xử lý)" type="tel" />`.
  - Nút "Gửi phản hồi".
- Hiện lỗi từng field bằng `@error`.

`admin/feedbacks/index.blade.php` (title "Phản hồi của khách"):
- Lọc theo `status` (link cho từng trạng thái + "Tất cả").
- Mỗi phản hồi một thẻ: ngày giờ, `{{ $feedback->rating }}/5` (và độ tươi nếu có), nhận xét, mã lô (link `admin.batches.show`) + tên rau + tên nông hộ, tên khách và mã đơn nếu có, `<x-status-badge :status="$feedback->status" />`.
- Form `PATCH route('admin.feedbacks.update', $feedback)`: select `status`, textarea `response` (giá trị hiện tại), nút "Lưu".
- `{{ $feedbacks->links() }}`.

- [ ] **Step 6: Chạy toàn bộ test, commit**

Run: `php artisan test`
Expected: PASS toàn bộ.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: customer feedback from the QR page and operator feedback inbox"
```

---

## Kết quả Phase 5

- [ ] Nhận hàng từ kế hoạch tạo lô đúng mã, đúng hạn dùng.
- [ ] Gán lô cho đơn theo hạn dùng và ưu tiên giỏ định kỳ; hủy đơn trả tồn.
- [ ] In được tem QR; quét ra trang lô chỉ có dữ kiện, chứng nhận chỉ hiện khi hợp lệ.
- [ ] Khách gửi phản hồi không cần tài khoản; operator xử lý và đóng phản hồi.
- [ ] **Toàn bộ luồng P0 chạy được từ đầu đến cuối.**

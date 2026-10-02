# Phase 2 — Đơn hàng: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Khách đặt giỏ rau hoặc chọn lẻ trên điện thoại không cần tài khoản, tra cứu đơn bằng số điện thoại; operator xem, nhập hộ và chuyển trạng thái đơn.

**Architecture:** `OrderService` là nơi duy nhất tạo đơn và đổi trạng thái (trong transaction). Phí ship và cửa sổ ngày giao là hàm thuần trong `app/Support`. Form công khai và form admin dùng chung `OrderService::place()`, khác nhau ở Form Request.

**Tech Stack:** Laravel 13 · Blade · Alpine.js · PHPUnit 12.

**Spec:** [`docs/CLAUDE.md`](../CLAUDE.md) mục 4 (Data conventions), 6 · [`docs/PLAN.md`](../PLAN.md) quyết định D9, D10, D11.

## Global Constraints

Xem `docs/PLAN.md`. Riêng phase này: tiền là số nguyên VND; mã đơn `DH{yymmdd}-{seq:03d}` theo **ngày giao**; SĐT `^0\d{9}$`; dòng thuộc giỏ có `unit_price = 0`, giá giỏ nằm ở `orders.subtotal`.

---

### Task 2.1: Phí ship và cửa sổ ngày giao (hàm thuần)

**Files:**
- Create: `app/Support/ShippingCalculator.php`, `app/Support/OrderWindow.php`
- Test: `tests/Unit/Support/ShippingCalculatorTest.php`, `tests/Unit/Support/OrderWindowTest.php`

**Interfaces:**
- Produces:
  - `ShippingCalculator::fee(int $shippingFee, ?int $freeShipFrom, bool $isPickup, int $subtotal): int`
  - `ShippingCalculator::forZone(DeliveryZone $zone, int $subtotal): int`
  - `OrderWindow::earliest(CarbonImmutable $now, int $cutoffHour): CarbonImmutable`
  - `OrderWindow::latest(CarbonImmutable $now, int $maxDaysAhead): CarbonImmutable`

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Unit/Support/ShippingCalculatorTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\ShippingCalculator;
use PHPUnit\Framework\TestCase;

class ShippingCalculatorTest extends TestCase
{
    public function test_zone_fee_applies_below_the_free_shipping_threshold(): void
    {
        $this->assertSame(10000, ShippingCalculator::fee(10000, 150000, false, 149999));
    }

    public function test_shipping_is_free_from_the_threshold(): void
    {
        $this->assertSame(0, ShippingCalculator::fee(10000, 150000, false, 150000));
    }

    public function test_zone_without_threshold_always_charges(): void
    {
        $this->assertSame(15000, ShippingCalculator::fee(15000, null, false, 999999));
    }

    public function test_pickup_is_always_free(): void
    {
        $this->assertSame(0, ShippingCalculator::fee(10000, null, true, 1000));
    }
}
```

`tests/Unit/Support/OrderWindowTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\OrderWindow;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class OrderWindowTest extends TestCase
{
    public function test_before_cutoff_tomorrow_is_still_open(): void
    {
        $now = CarbonImmutable::parse('2026-10-02 17:59:00', 'Asia/Ho_Chi_Minh');

        $this->assertSame('2026-10-03', OrderWindow::earliest($now, 18)->toDateString());
    }

    public function test_from_cutoff_the_earliest_day_moves_to_the_day_after_tomorrow(): void
    {
        $now = CarbonImmutable::parse('2026-10-02 18:00:00', 'Asia/Ho_Chi_Minh');

        $this->assertSame('2026-10-04', OrderWindow::earliest($now, 18)->toDateString());
    }

    public function test_latest_day_is_max_days_ahead(): void
    {
        $now = CarbonImmutable::parse('2026-10-02 10:00:00', 'Asia/Ho_Chi_Minh');

        $this->assertSame('2026-10-09', OrderWindow::latest($now, 7)->toDateString());
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter="ShippingCalculatorTest|OrderWindowTest"`
Expected: FAIL (class not found).

- [ ] **Step 3: Viết hai class**

`app/Support/ShippingCalculator.php`:

```php
<?php

namespace App\Support;

use App\Models\DeliveryZone;

final class ShippingCalculator
{
    public static function fee(int $shippingFee, ?int $freeShipFrom, bool $isPickup, int $subtotal): int
    {
        if ($isPickup) {
            return 0;
        }

        if ($freeShipFrom !== null && $subtotal >= $freeShipFrom) {
            return 0;
        }

        return $shippingFee;
    }

    public static function forZone(DeliveryZone $zone, int $subtotal): int
    {
        return self::fee((int) $zone->shipping_fee, $zone->free_ship_from, $zone->isPickup(), $subtotal);
    }
}
```

`app/Support/OrderWindow.php`:

```php
<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class OrderWindow
{
    // Trước giờ chốt: còn nhận đơn cho ngày mai. Từ giờ chốt: sớm nhất là ngày kia.
    public static function earliest(CarbonImmutable $now, int $cutoffHour): CarbonImmutable
    {
        return $now->startOfDay()->addDays($now->hour < $cutoffHour ? 1 : 2);
    }

    public static function latest(CarbonImmutable $now, int $maxDaysAhead): CarbonImmutable
    {
        return $now->startOfDay()->addDays($maxDaysAhead);
    }
}
```

Trong `app/Models/DeliveryZone.php`, đảm bảo `casts()` có `'shipping_fee' => 'integer'`, `'free_ship_from' => 'integer'`, `'is_active' => 'boolean'`.

- [ ] **Step 4: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter="ShippingCalculatorTest|OrderWindowTest"`
Expected: PASS (7 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: shipping fee and order window calculators"
```

---

### Task 2.2: `OrderService` — tạo đơn và đổi trạng thái

**Files:**
- Create: `app/Services/OrderService.php`
- Test: `tests/Feature/Orders/OrderServiceTest.php`

**Interfaces:**
- Consumes: `ShippingCalculator::forZone`, model và enum của Phase 1, `Tests\Support\BuildsCatalog`.
- Produces:
  - `OrderService::place(array $data, OrderChannel $channel): Order` — `$data` có các key: `name`, `phone`, `address` (nullable), `delivery_zone_id`, `delivery_date` (`Y-m-d`), `time_slot`, `order_type` (`basket`|`single`), `basket_id` (khi basket), `items` (khi single: list `['product_id' => int, 'quantity' => int]`), `payment_method`, `data_consent` (bool), `note` (nullable), `subscription_id` (nullable, tùy chọn).
  - `OrderService::transition(Order $order, OrderStatus $to, ?string $cancelReason = null): Order`
  - Ném `Illuminate\Validation\ValidationException` (key `items`) khi không có dòng hàng hợp lệ; ném `DomainException` khi chuyển trạng thái sai.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Orders/OrderServiceTest.php`:

```php
<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryMethod;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->orders = app(OrderService::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nguyễn Thị Lan',
            'phone' => '0988000001',
            'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id,
            'delivery_date' => '2026-10-03',
            'time_slot' => '17:00-19:00',
            'order_type' => 'single',
            'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod',
            'data_consent' => true,
            'note' => null,
        ], $overrides);
    }

    public function test_single_order_totals_weight_and_code(): void
    {
        $order = $this->orders->place($this->payload(), OrderChannel::Website);

        $this->assertSame('DH261003-001', $order->code);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(OrderChannel::Website, $order->channel);
        $this->assertSame(DeliveryMethod::Home, $order->delivery_method);
        $this->assertSame('129 Rạch Gầm', $order->delivery_address);
        $this->assertSame([24000, 10000, 0, 34000], [$order->subtotal, $order->shipping_fee, $order->discount, $order->total]);
        $this->assertCount(1, $order->items);
        $this->assertSame('1.000', $order->items[0]->weight_kg);
        $this->assertSame([12000, 24000], [$order->items[0]->unit_price, $order->items[0]->line_total]);
        $this->assertNull($order->items[0]->basket_id);
    }

    public function test_basket_order_puts_the_basket_price_in_subtotal_and_zero_on_lines(): void
    {
        $order = $this->orders->place($this->payload(['order_type' => 'basket', 'basket_id' => $this->basket->id, 'items' => null]), OrderChannel::Website);

        $this->assertSame([45000, 10000, 55000], [$order->subtotal, $order->shipping_fee, $order->total]);
        $this->assertCount(3, $order->items);
        $this->assertSame([0], $order->items->pluck('unit_price')->unique()->values()->all());
        $this->assertSame([$this->basket->id], $order->items->pluck('basket_id')->unique()->values()->all());
        $this->assertEqualsWithDelta(1.2, $order->items->sum(fn ($i) => (float) $i->weight_kg), 0.0001);
    }

    public function test_pickup_zone_has_no_shipping_fee_and_no_address(): void
    {
        $order = $this->orders->place($this->payload(['delivery_zone_id' => $this->pickupZone->id, 'address' => null]), OrderChannel::Website);

        $this->assertSame(DeliveryMethod::Pickup, $order->delivery_method);
        $this->assertSame(0, $order->shipping_fee);
        $this->assertNull($order->delivery_address);
    }

    public function test_shipping_is_free_from_the_zone_threshold(): void
    {
        $order = $this->orders->place($this->payload(['items' => [['product_id' => $this->rm->id, 'quantity' => 13]]]), OrderChannel::Website);

        $this->assertSame([156000, 0, 156000], [$order->subtotal, $order->shipping_fee, $order->total]);
    }

    public function test_codes_are_sequential_per_delivery_date(): void
    {
        $a = $this->orders->place($this->payload(), OrderChannel::Website);
        $b = $this->orders->place($this->payload(['phone' => '0988000002']), OrderChannel::Zalo);
        $c = $this->orders->place($this->payload(['phone' => '0988000003', 'delivery_date' => '2026-10-04']), OrderChannel::Website);

        $this->assertSame(['DH261003-001', 'DH261003-002', 'DH261004-001'], [$a->code, $b->code, $c->code]);
    }

    public function test_code_continues_after_a_cancelled_order(): void
    {
        $a = $this->orders->place($this->payload(), OrderChannel::Website);
        $this->orders->transition($a, OrderStatus::Cancelled, 'Khách đổi lịch');
        $b = $this->orders->place($this->payload(), OrderChannel::Website);

        $this->assertSame('DH261003-002', $b->code);
    }

    public function test_returning_phone_reuses_customer(): void
    {
        $this->orders->place($this->payload(), OrderChannel::Zalo);
        $this->orders->place($this->payload(['name' => 'Chị Lan', 'address' => '5 Lê Lợi']), OrderChannel::Website);

        $this->assertSame(1, Customer::count());
        $customer = Customer::firstOrFail();
        $this->assertSame('Chị Lan', $customer->name);
        $this->assertSame('5 Lê Lợi', $customer->address);
        $this->assertSame('zalo', $customer->source->value);
        $this->assertTrue($customer->data_consent);
        $this->assertSame(2, $customer->orders()->count());
    }

    public function test_zero_quantity_lines_are_ignored_and_duplicates_merged(): void
    {
        $order = $this->orders->place($this->payload(['items' => [
            ['product_id' => $this->rm->id, 'quantity' => 1],
            ['product_id' => $this->cn->id, 'quantity' => 0],
            ['product_id' => $this->rm->id, 'quantity' => 2],
        ]]), OrderChannel::Website);

        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items[0]->quantity);
        $this->assertSame(36000, $order->subtotal);
    }

    public function test_order_without_any_quantity_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->orders->place($this->payload(['items' => [['product_id' => $this->rm->id, 'quantity' => 0]]]), OrderChannel::Website);
    }

    public function test_inactive_product_is_rejected(): void
    {
        $this->cn->update(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->orders->place($this->payload(['items' => [['product_id' => $this->cn->id, 'quantity' => 1]]]), OrderChannel::Website);
    }

    public function test_nothing_is_saved_when_placing_fails(): void
    {
        try {
            $this->orders->place($this->payload(['items' => []]), OrderChannel::Website);
        } catch (ValidationException) {
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Customer::count());
    }

    public function test_confirming_and_delivering_a_cod_order(): void
    {
        $order = $this->orders->place($this->payload(), OrderChannel::Website);

        $this->orders->transition($order, OrderStatus::Confirmed);
        $this->assertNotNull($order->confirmed_at);

        $this->orders->transition($order, OrderStatus::Packing);
        $this->orders->transition($order, OrderStatus::Delivering);
        $this->orders->transition($order, OrderStatus::Delivered);

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(2, $order->items[0]->fulfilled_quantity);
    }

    public function test_bank_transfer_order_is_not_marked_paid_on_delivery(): void
    {
        $order = $this->orders->place($this->payload(['payment_method' => 'bank_transfer']), OrderChannel::Website);
        foreach ([OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Delivering, OrderStatus::Delivered] as $status) {
            $this->orders->transition($order, $status);
        }

        $this->assertSame(PaymentStatus::Unpaid, $order->refresh()->payment_status);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $order = $this->orders->place($this->payload(), OrderChannel::Website);

        $this->expectException(DomainException::class);

        $this->orders->transition($order, OrderStatus::Delivered);
    }

    public function test_cancelling_requires_a_reason(): void
    {
        $order = $this->orders->place($this->payload(), OrderChannel::Website);

        try {
            $this->orders->transition($order, OrderStatus::Cancelled);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Cần nhập lý do hủy đơn.', $e->getMessage());
        }

        $this->orders->transition($order, OrderStatus::Cancelled, 'Khách đổi lịch');
        $this->assertSame('Khách đổi lịch', $order->refresh()->cancel_reason);
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=OrderServiceTest`
Expected: FAIL (`Class "App\Services\OrderService" not found`).

- [ ] **Step 3: Viết `app/Services/OrderService.php`**

```php
<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Basket;
use App\Models\BasketItem;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Support\ShippingCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderService
{
    public function place(array $data, OrderChannel $channel): Order
    {
        return DB::transaction(function () use ($data, $channel) {
            $zone = DeliveryZone::findOrFail($data['delivery_zone_id']);
            $type = OrderType::from($data['order_type']);

            [$lines, $subtotal] = $type === OrderType::Basket
                ? $this->basketLines(Basket::with('items.product')->where('is_active', true)->findOrFail($data['basket_id']))
                : $this->singleLines($data['items'] ?? []);

            $customer = $this->upsertCustomer($data, $zone, $channel);
            $shipping = ShippingCalculator::forZone($zone, $subtotal);
            $date = CarbonImmutable::parse($data['delivery_date'])->startOfDay();

            $order = $this->createWithCode($date, [
                'customer_id' => $customer->id,
                'subscription_id' => $data['subscription_id'] ?? null,
                'delivery_zone_id' => $zone->id,
                'order_type' => $type,
                'channel' => $channel,
                'delivery_date' => $date->toDateString(),
                'time_slot' => $data['time_slot'],
                'delivery_method' => $zone->isPickup() ? DeliveryMethod::Pickup : DeliveryMethod::Home,
                'delivery_address' => $zone->isPickup() ? null : ($data['address'] ?? null),
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping,
                'discount' => 0,
                'total' => $subtotal + $shipping,
                'payment_method' => PaymentMethod::from($data['payment_method']),
                'payment_status' => PaymentStatus::Unpaid,
                'status' => OrderStatus::Pending,
                'note' => $data['note'] ?? null,
            ]);

            $order->items()->createMany($lines);

            return $order->load('items.product', 'customer', 'zone');
        });
    }

    public function transition(Order $order, OrderStatus $to, ?string $cancelReason = null): Order
    {
        if (! $order->status->canTransitionTo($to)) {
            throw new DomainException("Không thể chuyển đơn từ \"{$order->status->label()}\" sang \"{$to->label()}\".");
        }

        if ($to === OrderStatus::Cancelled && blank($cancelReason)) {
            throw new DomainException('Cần nhập lý do hủy đơn.');
        }

        return DB::transaction(function () use ($order, $to, $cancelReason) {
            $order->status = $to;

            if ($to === OrderStatus::Confirmed) {
                $order->confirmed_at = now();
            }

            if ($to === OrderStatus::Delivered) {
                $this->markDelivered($order);
            }

            if ($to === OrderStatus::Cancelled) {
                $order->cancel_reason = $cancelReason;
                // Trả tồn về lô (quyết định D13).
                $order->items->each(fn ($item) => $item->batches()->detach());
            }

            $order->save();

            return $order;
        });
    }

    private function markDelivered(Order $order): void
    {
        $order->delivered_at = now();

        if ($order->payment_method === PaymentMethod::Cod) {
            $order->payment_status = PaymentStatus::Paid;
        }

        foreach ($order->items()->with('batches')->get() as $item) {
            if ($item->fulfilled_quantity !== null) {
                continue;
            }
            $allocated = (int) $item->batches->sum('pivot.quantity');
            $item->update(['fulfilled_quantity' => $allocated > 0 ? $allocated : $item->quantity]);
        }
    }

    private function upsertCustomer(array $data, DeliveryZone $zone, OrderChannel $channel): Customer
    {
        $customer = Customer::firstOrNew(['phone' => $data['phone']]);

        if (! $customer->exists) {
            $customer->source = $channel->value;
        }

        $customer->name = $data['name'];
        $customer->delivery_zone_id = $zone->id;

        if (filled($data['address'] ?? null)) {
            $customer->address = $data['address'];
        }

        if (! empty($data['data_consent'])) {
            $customer->data_consent = true;
        }

        $customer->save();

        return $customer;
    }

    /** @return array{0: list<array<string, mixed>>, 1: int} */
    private function basketLines(Basket $basket): array
    {
        $lines = $basket->items->map(fn (BasketItem $item) => [
            'product_id' => $item->product_id,
            'basket_id' => $basket->id,
            'quantity' => $item->quantity,
            'weight_kg' => round($item->quantity * (float) $item->product->pack_weight_kg, 3),
            'unit_price' => 0,
            'line_total' => 0,
        ])->all();

        return [$lines, (int) $basket->price];
    }

    /** @return array{0: list<array<string, mixed>>, 1: int} */
    private function singleLines(array $items): array
    {
        $quantities = collect($items)
            ->groupBy('product_id')
            ->map(fn ($group) => (int) $group->sum('quantity'))
            ->filter(fn (int $quantity) => $quantity > 0);

        $products = Product::where('is_active', true)->findMany($quantities->keys());

        if ($quantities->isEmpty() || $products->count() !== $quantities->count()) {
            throw ValidationException::withMessages(['items' => 'Vui lòng chọn ít nhất một loại rau còn bán.']);
        }

        $lines = [];
        $subtotal = 0;

        foreach ($products as $product) {
            $quantity = $quantities[$product->id];
            $lineTotal = $quantity * (int) $product->price;
            $lines[] = [
                'product_id' => $product->id,
                'basket_id' => null,
                'quantity' => $quantity,
                'weight_kg' => round($quantity * (float) $product->pack_weight_kg, 3),
                'unit_price' => (int) $product->price,
                'line_total' => $lineTotal,
            ];
            $subtotal += $lineTotal;
        }

        return [$lines, $subtotal];
    }

    private function createWithCode(CarbonInterface $date, array $attributes): Order
    {
        $prefix = 'DH'.$date->format('ymd').'-';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $last = Order::where('code', 'like', $prefix.'%')->lockForUpdate()->max('code');
            $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

            try {
                return Order::create($attributes + ['code' => $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT)]);
            } catch (UniqueConstraintViolationException) {
                // Một đơn khác vừa lấy số này: thử số kế tiếp.
                continue;
            }
        }

        throw new RuntimeException('Không tạo được mã đơn, vui lòng thử lại.');
    }
}
```

- [ ] **Step 4: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter=OrderServiceTest`
Expected: PASS (15 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: order service with pricing, shipping, order codes and status transitions"
```

---

### Task 2.3: Trang chủ và form đặt hàng công khai

**Files:**
- Create: `app/Http/Requests/StoreOrderRequest.php`, `app/Http/Controllers/Public/HomeController.php`, `app/Http/Controllers/Public/OrderController.php`, `resources/views/public/home.blade.php`, `resources/views/public/orders/create.blade.php`, `resources/views/public/orders/thanks.blade.php`
- Modify: `routes/web.php`
- Delete: `resources/views/welcome.blade.php`, `tests/Feature/ExampleTest.php`
- Test: `tests/Feature/Orders/PublicOrderFormTest.php`

**Interfaces:**
- Consumes: `OrderService::place`, `OrderWindow`, layout `x-layouts.public`.
- Produces: route `home`, `orders.create`, `orders.store`, `orders.thanks` (GET `/dat-hang/cam-on`, đọc `session('last_order_code')`).

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Orders/PublicOrderFormTest.php`:

```php
<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class PublicOrderFormTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nguyễn Thị Lan',
            'phone' => '0988000001',
            'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id,
            'delivery_date' => '2026-10-03',
            'time_slot' => '17:00-19:00',
            'order_type' => 'single',
            'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod',
            'data_consent' => '1',
        ], $overrides);
    }

    public function test_home_page_shows_baskets_and_makes_no_marketing_claims(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Điều phối nông sản theo nhu cầu')
            ->assertSee('Giỏ Mini')
            ->assertSee('45.000đ')
            ->assertSee('Đặt hàng')
            ->assertDontSee('100%');
    }

    public function test_order_form_lists_catalog_and_allowed_dates(): void
    {
        $this->get('/dat-hang')
            ->assertOk()
            ->assertSee('Giỏ Mini')
            ->assertSee('Rau muống')
            ->assertSee('12.000đ')
            ->assertSee('Cụm 1 – Trung tâm')
            ->assertSee('17:00-19:00')
            ->assertSee('value="2026-10-03"', false)
            ->assertSee('value="2026-10-09"', false)
            ->assertDontSee('value="2026-10-02"', false)
            ->assertDontSee('value="2026-10-10"', false);
    }

    public function test_valid_order_is_stored_and_customer_sees_the_code(): void
    {
        $this->post('/dat-hang', $this->form())->assertRedirect('/dat-hang/cam-on');

        $order = Order::firstOrFail();
        $this->assertSame('DH261003-001', $order->code);
        $this->assertSame('website', $order->channel->value);

        $this->get('/dat-hang/cam-on')
            ->assertOk()
            ->assertSee('DH261003-001')
            ->assertSee('34.000đ')
            ->assertSee('03/10/2026')
            ->assertDontSee('129 Rạch Gầm')
            ->assertDontSee('0988000001');
    }

    public function test_order_after_cutoff_for_tomorrow_is_rejected(): void
    {
        $this->travelTo('2026-10-02 18:30:00');

        $this->post('/dat-hang', $this->form())->assertSessionHasErrors('delivery_date');
        $this->assertSame(0, Order::count());

        $this->post('/dat-hang', $this->form(['delivery_date' => '2026-10-04']))->assertRedirect('/dat-hang/cam-on');
        $this->assertSame(1, Order::count());
    }

    public function test_date_beyond_the_window_is_rejected(): void
    {
        $this->post('/dat-hang', $this->form(['delivery_date' => '2026-10-10']))->assertSessionHasErrors('delivery_date');
    }

    public function test_phone_must_be_ten_digits_starting_with_zero(): void
    {
        foreach (['12345', '988000001', '09880000011', '0988 000 001'] as $phone) {
            $this->post('/dat-hang', $this->form(['phone' => $phone]))->assertSessionHasErrors('phone');
        }
    }

    public function test_consent_is_required(): void
    {
        $this->post('/dat-hang', $this->form(['data_consent' => null]))->assertSessionHasErrors('data_consent');
    }

    public function test_address_is_required_for_home_delivery_but_not_for_pickup(): void
    {
        $this->post('/dat-hang', $this->form(['address' => '']))->assertSessionHasErrors('address');

        $this->post('/dat-hang', $this->form(['address' => '', 'delivery_zone_id' => $this->pickupZone->id]))
            ->assertRedirect('/dat-hang/cam-on');
    }

    public function test_unknown_time_slot_and_missing_basket_are_rejected(): void
    {
        $this->post('/dat-hang', $this->form(['time_slot' => '02:00-03:00']))->assertSessionHasErrors('time_slot');
        $this->post('/dat-hang', $this->form(['order_type' => 'basket', 'items' => null]))->assertSessionHasErrors('basket_id');
    }

    public function test_basket_order_through_the_form(): void
    {
        $this->post('/dat-hang', $this->form(['order_type' => 'basket', 'basket_id' => $this->basket->id, 'items' => null]))
            ->assertRedirect('/dat-hang/cam-on');

        $this->assertSame(55000, Order::firstOrFail()->total);
    }

    public function test_thanks_page_without_an_order_goes_home(): void
    {
        $this->get('/dat-hang/cam-on')->assertRedirect('/');
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=PublicOrderFormTest`
Expected: FAIL (404 cho `/dat-hang`).

- [ ] **Step 3: Viết `app/Http/Requests/StoreOrderRequest.php`**

```php
<?php

namespace App\Http\Requests;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\DeliveryZone;
use App\Support\OrderWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $now = CarbonImmutable::now();
        $earliest = OrderWindow::earliest($now, config('farm2home.order.cutoff_hour'))->toDateString();
        $latest = OrderWindow::latest($now, config('farm2home.order.max_days_ahead'))->toDateString();

        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^0\d{9}$/'],
            'delivery_zone_id' => ['required', Rule::exists('delivery_zones', 'id')->where('is_active', true)],
            'address' => [Rule::requiredIf(fn () => ! $this->zoneIsPickup()), 'nullable', 'string', 'max:255'],
            'delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$earliest, 'before_or_equal:'.$latest],
            'time_slot' => ['required', Rule::in(config('farm2home.order.time_slots'))],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'basket_id' => ['required_if:order_type,basket', 'nullable', Rule::exists('baskets', 'id')->where('is_active', true)],
            'items' => ['required_if:order_type,single', 'nullable', 'array', 'max:20'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:20'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'data_consent' => ['accepted'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function zoneIsPickup(): bool
    {
        return (bool) DeliveryZone::find($this->input('delivery_zone_id'))?->isPickup();
    }
}
```

- [ ] **Step 4: Viết controller**

`app/Http/Controllers/Public/HomeController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Basket;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'baskets' => Basket::with('items.product')->where('is_active', true)->orderBy('price')->get(),
        ]);
    }
}
```

`app/Http/Controllers/Public/OrderController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Enums\OrderChannel;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Basket;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Support\OrderWindow;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function create(): View
    {
        $now = CarbonImmutable::now();

        return view('public.orders.create', [
            'baskets' => Basket::with('items.product')->where('is_active', true)->orderBy('price')->get(),
            'products' => Product::where('is_active', true)->orderBy('id')->get(),
            'zones' => DeliveryZone::where('is_active', true)->orderBy('id')->get(),
            'dates' => collect(CarbonPeriod::create(
                OrderWindow::earliest($now, config('farm2home.order.cutoff_hour')),
                OrderWindow::latest($now, config('farm2home.order.max_days_ahead')),
            )),
            'timeSlots' => config('farm2home.order.time_slots'),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function store(StoreOrderRequest $request, OrderService $orders): RedirectResponse
    {
        $order = $orders->place($request->validated(), OrderChannel::Website);

        $request->session()->put('last_order_code', $order->code);

        return redirect()->route('orders.thanks');
    }

    public function thanks(Request $request): View|RedirectResponse
    {
        $order = Order::where('code', $request->session()->get('last_order_code'))->first();

        if ($order === null) {
            return redirect()->route('home');
        }

        return view('public.orders.thanks', ['order' => $order]);
    }
}
```

- [ ] **Step 5: Cập nhật `routes/web.php`**

Thay dòng `Route::view('/', 'welcome')->name('home');` bằng:

```php
Route::get('/', HomeController::class)->name('home');

Route::get('/dat-hang', [PublicOrderController::class, 'create'])->name('orders.create');
Route::post('/dat-hang', [PublicOrderController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');
Route::get('/dat-hang/cam-on', [PublicOrderController::class, 'thanks'])->name('orders.thanks');
```

và thêm import:

```php
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\OrderController as PublicOrderController;
```

Xóa `resources/views/welcome.blade.php` và `tests/Feature/ExampleTest.php`.

- [ ] **Step 6: Viết 3 view theo hợp đồng sau**

`public/home.blade.php` (layout `x-layouts.public`, title "FARM2HOME"):
- Tiêu đề: "Điều phối nông sản theo nhu cầu, giảm dư thừa."
- Một đoạn mô tả 2 câu: đặt trước → nông hộ thu hoạch vừa đủ → giao trong ngày.
- Thẻ cho từng giỏ: tên, `@vnd($basket->price)`, mô tả, danh sách rau (`{{ $item->quantity }} × {{ $item->product->name }}`).
- Nút "Đặt hàng" → `route('orders.create')`; link "Tra cứu đơn" (chỉ hiện khi `Route::has('lookup.show')`).
- Không dùng từ "sạch 100%", "an toàn tuyệt đối", không nói về sức khỏe.

`public/orders/create.blade.php` — một form `POST route('orders.store')`, `@csrf`, bọc trong `x-data` của Alpine:
- **Loại đơn:** 2 radio `order_type` = `basket` | `single` (mặc định `basket`), nhãn lấy từ `OrderType::label()`.
- **Khi basket:** radio `basket_id` cho từng giỏ (tên + `@vnd` giá + danh sách rau).
- **Khi single:** với từng sản phẩm (chỉ số `$i`): input ẩn `items[{{ $i }}][product_id]`, input số `items[{{ $i }}][quantity]` (min 0, max 20, mặc định 0) kèm nút `−` / `+` cao 44px; hiện tên, `@kg($product->pack_weight_kg)`/gói, `@vnd($product->price)`.
- **Ngày giao:** `<select name="delivery_date">`, mỗi `<option value="{{ $date->format('Y-m-d') }}">` hiển thị `{{ $date->isoFormat('dddd, DD/MM') }}`.
- **Khung giờ:** radio `time_slot` cho từng phần tử `$timeSlots`.
- **Khu vực:** `<select name="delivery_zone_id">`; option hiển thị tên, và "Phí giao `@vnd`" hoặc "Nhận tại: {pickup_point}".
- **Thông tin:** `<x-field name="name" label="Họ tên" />`, `<x-field name="phone" label="Số điện thoại" type="tel" inputmode="numeric" />`, `<x-field name="address" label="Địa chỉ giao" />` (ẩn bằng `x-show` khi khu vực là điểm nhận).
- **Thanh toán:** radio `payment_method` theo `$paymentMethods`.
- **Ghi chú:** `<textarea name="note">`.
- **Đồng ý:** checkbox `data_consent` value `1`, nhãn "Tôi đồng ý để FARM2HOME dùng thông tin này để xử lý và giao đơn."
- **Tạm tính:** khối hiển thị tiền hàng, phí giao, tổng, tính bằng Alpine từ dữ liệu `@js(...)` (giá giỏ, giá sản phẩm, `shipping_fee`, `free_ship_from`, `pickup_point` của từng khu vực). Ghi chú nhỏ: "Tổng tiền chính xác được xác nhận sau khi gửi đơn."
- Lỗi: mỗi field hiện `@error`; lỗi `items` và `basket_id` hiện ở đầu khối chọn hàng.
- Giữ giá trị cũ bằng `old()` cho mọi field.
- Nút gửi "Gửi đơn", rộng toàn chiều ngang, cao tối thiểu 48px.

`public/orders/thanks.blade.php`:
- "Đã nhận đơn của bạn", mã đơn `{{ $order->code }}`, ngày giao `{{ $order->delivery_date->format('d/m/Y') }}`, khung giờ, tổng tiền `@vnd($order->total)`, hình thức thanh toán (label).
- Nếu chuyển khoản: "Chúng tôi sẽ gửi thông tin chuyển khoản qua Zalo."
- Không hiển thị tên, số điện thoại, địa chỉ.
- Link "Tra cứu đơn" (khi route tồn tại) và "Đặt thêm".

- [ ] **Step 7: Chạy test, xác nhận pass**

Run: `php artisan test --filter=PublicOrderFormTest`
Expected: PASS (11 tests).

- [ ] **Step 8: Kiểm tra tay trên khổ điện thoại, commit**

Run: `npm run dev`, mở `http://farm2home.test/dat-hang` ở chế độ thiết bị 360px. Kiểm tra: đổi loại đơn ẩn/hiện đúng khối; chọn "Điểm nhận tập trung" thì ô địa chỉ ẩn và phí giao về 0; tạm tính đổi theo số lượng; gửi form lỗi thì giá trị cũ còn nguyên.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: public landing page and mobile order form"
```

---

### Task 2.4: Tra cứu đơn bằng số điện thoại

**Files:**
- Create: `app/Http/Controllers/Public/OrderLookupController.php`, `resources/views/public/orders/lookup.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Orders/OrderLookupTest.php`

**Interfaces:**
- Produces: route `lookup.show` (GET `/tra-cuu`), `lookup.search` (POST `/tra-cuu`, `throttle:10,1`).

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Orders/OrderLookupTest.php`:

```php
<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderChannel;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class OrderLookupTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();

        foreach (['2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08'] as $date) {
            app(OrderService::class)->place([
                'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '129 Rạch Gầm',
                'delivery_zone_id' => $this->zone->id, 'delivery_date' => $date, 'time_slot' => '17:00-19:00',
                'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
                'payment_method' => 'cod', 'data_consent' => true,
            ], OrderChannel::Website);
        }
    }

    public function test_lookup_shows_the_five_latest_orders_without_personal_data(): void
    {
        $this->post('/tra-cuu', ['phone' => '0988000001'])
            ->assertOk()
            ->assertSee('DH261008-001')
            ->assertSee('DH261004-001')
            ->assertDontSee('DH261003-001')
            ->assertSee('Chờ xác nhận')
            ->assertSee('34.000đ')
            ->assertDontSee('129 Rạch Gầm')
            ->assertDontSee('Nguyễn Thị Lan');
    }

    public function test_unknown_phone_gets_a_neutral_message(): void
    {
        $this->post('/tra-cuu', ['phone' => '0977000000'])
            ->assertOk()
            ->assertSee('Không tìm thấy đơn nào với số điện thoại này.');
    }

    public function test_phone_format_is_validated(): void
    {
        $this->post('/tra-cuu', ['phone' => 'abc'])->assertSessionHasErrors('phone');
    }

    public function test_lookup_page_renders(): void
    {
        $this->get('/tra-cuu')->assertOk()->assertSee('Tra cứu đơn');
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=OrderLookupTest`
Expected: FAIL (404).

- [ ] **Step 3: Viết controller**

`app/Http/Controllers/Public/OrderLookupController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderLookupController extends Controller
{
    public function show(): View
    {
        return view('public.orders.lookup', ['orders' => null]);
    }

    public function search(Request $request): View
    {
        $data = $request->validate(['phone' => ['required', 'regex:/^0\d{9}$/']]);

        // Quyết định D11: chỉ trả mã, ngày, trạng thái, tổng tiền của 5 đơn gần nhất.
        $orders = Order::query()
            ->whereHas('customer', fn ($query) => $query->where('phone', $data['phone']))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'code', 'delivery_date', 'time_slot', 'status', 'total']);

        return view('public.orders.lookup', ['orders' => $orders, 'phone' => $data['phone']]);
    }
}
```

Thêm vào `routes/web.php`:

```php
Route::get('/tra-cuu', [OrderLookupController::class, 'show'])->name('lookup.show');
Route::post('/tra-cuu', [OrderLookupController::class, 'search'])->middleware('throttle:10,1')->name('lookup.search');
```

- [ ] **Step 4: Viết view `public/orders/lookup.blade.php`**

- Tiêu đề "Tra cứu đơn"; form `POST route('lookup.search')` với `<x-field name="phone" label="Số điện thoại đã đặt hàng" type="tel" :value="$phone ?? null" />` và nút "Tra cứu".
- Khi `$orders !== null && $orders->isEmpty()`: hiện "Không tìm thấy đơn nào với số điện thoại này."
- Khi có đơn: danh sách thẻ, mỗi thẻ gồm mã đơn, `{{ $order->delivery_date->format('d/m/Y') }} · {{ $order->time_slot }}`, `<x-status-badge :status="$order->status" />`, `@vnd($order->total)`.
- Không in bất kỳ trường nào của `customer`.

- [ ] **Step 5: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter=OrderLookupTest`
Expected: PASS (4 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: order lookup by phone with minimal data exposure"
```

---

### Task 2.5: Quản lý đơn trong admin

**Files:**
- Create: `app/Http/Controllers/Admin/OrderController.php`, `app/Http/Requests/StoreAdminOrderRequest.php`, `resources/views/admin/orders/{index,show,create}.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Orders/AdminOrderTest.php`

**Interfaces:**
- Consumes: `OrderService::place`, `OrderService::transition`.
- Produces: route `admin.orders.index`, `admin.orders.create`, `admin.orders.store`, `admin.orders.show`, `admin.orders.transition` (PATCH `/admin/orders/{order}/status`, body `status`, `cancel_reason`).

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Orders/AdminOrderTest.php`:

```php
<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->order = app(OrderService::class)->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Website);
    }

    public function test_guests_cannot_see_orders(): void
    {
        $this->get('/admin/orders')->assertRedirect('/login');
    }

    public function test_index_filters_by_delivery_date_and_status(): void
    {
        $this->actingAs($this->operator);

        $this->get('/admin/orders?delivery_date=2026-10-03')
            ->assertOk()
            ->assertSee('DH261003-001')
            ->assertSee('Nguyễn Thị Lan')
            ->assertSee('1 kg');

        $this->get('/admin/orders?delivery_date=2026-10-04')->assertOk()->assertDontSee('DH261003-001');
        $this->get('/admin/orders?delivery_date=2026-10-03&status=delivered')->assertOk()->assertDontSee('DH261003-001');
    }

    public function test_show_lists_items_and_allowed_next_steps(): void
    {
        $this->actingAs($this->operator)->get('/admin/orders/'.$this->order->id)
            ->assertOk()
            ->assertSee('Rau muống')
            ->assertSee('0988000001')
            ->assertSee('129 Rạch Gầm')
            ->assertSee('34.000đ')
            ->assertSee('Đã xác nhận')
            ->assertSee('Đã hủy');
    }

    public function test_operator_confirms_an_order(): void
    {
        $this->actingAs($this->operator)
            ->patch('/admin/orders/'.$this->order->id.'/status', ['status' => 'confirmed'])
            ->assertRedirect('/admin/orders/'.$this->order->id)
            ->assertSessionHas('status');

        $this->assertSame(OrderStatus::Confirmed, $this->order->refresh()->status);
    }

    public function test_invalid_transition_shows_the_domain_error(): void
    {
        $this->actingAs($this->operator)
            ->from('/admin/orders/'.$this->order->id)
            ->patch('/admin/orders/'.$this->order->id.'/status', ['status' => 'delivered'])
            ->assertRedirect('/admin/orders/'.$this->order->id)
            ->assertSessionHasErrors('action');

        $this->assertSame(OrderStatus::Pending, $this->order->refresh()->status);
    }

    public function test_cancel_needs_a_reason(): void
    {
        $this->actingAs($this->operator)
            ->from('/admin/orders/'.$this->order->id)
            ->patch('/admin/orders/'.$this->order->id.'/status', ['status' => 'cancelled'])
            ->assertSessionHasErrors('action');

        $this->actingAs($this->operator)
            ->patch('/admin/orders/'.$this->order->id.'/status', ['status' => 'cancelled', 'cancel_reason' => 'Khách đổi lịch']);

        $this->assertSame(OrderStatus::Cancelled, $this->order->refresh()->status);
    }

    public function test_operator_can_enter_a_zalo_order_after_cutoff_without_consent_box(): void
    {
        $this->travelTo('2026-10-02 20:00:00');

        $this->actingAs($this->operator)->post('/admin/orders', [
            'name' => 'Trần Văn Bình', 'phone' => '0988000009', 'address' => '5 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '06:00-08:00',
            'order_type' => 'basket', 'basket_id' => $this->basket->id,
            'payment_method' => 'bank_transfer', 'channel' => 'zalo',
        ])->assertRedirect();

        $order = Order::where('code', 'DH261003-002')->firstOrFail();
        $this->assertSame(OrderChannel::Zalo, $order->channel);
        $this->assertFalse($order->customer->data_consent);
    }

    public function test_admin_order_cannot_be_dated_in_the_past(): void
    {
        $this->actingAs($this->operator)->post('/admin/orders', [
            'name' => 'Trần Văn Bình', 'phone' => '0988000009', 'address' => '5 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-01', 'time_slot' => '06:00-08:00',
            'order_type' => 'basket', 'basket_id' => $this->basket->id,
            'payment_method' => 'cod', 'channel' => 'phone',
        ])->assertSessionHasErrors('delivery_date');
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=AdminOrderTest`
Expected: FAIL (404).

- [ ] **Step 3: Viết `app/Http/Requests/StoreAdminOrderRequest.php`**

```php
<?php

namespace App\Http\Requests;

use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\DeliveryZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^0\d{9}$/'],
            'delivery_zone_id' => ['required', Rule::exists('delivery_zones', 'id')->where('is_active', true)],
            'address' => [Rule::requiredIf(fn () => ! DeliveryZone::find($this->input('delivery_zone_id'))?->isPickup()), 'nullable', 'string', 'max:255'],
            // Operator nhập hộ: không áp giờ chốt, chỉ chặn ngày đã qua.
            'delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today()->toDateString()],
            'time_slot' => ['required', Rule::in(config('farm2home.order.time_slots'))],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'basket_id' => ['required_if:order_type,basket', 'nullable', Rule::exists('baskets', 'id')->where('is_active', true)],
            'items' => ['required_if:order_type,single', 'nullable', 'array', 'max:20'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:50'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'channel' => ['required', Rule::enum(OrderChannel::class)],
            'data_consent' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

- [ ] **Step 4: Viết `app/Http/Controllers/Admin/OrderController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminOrderRequest;
use App\Models\Basket;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ]);
        $date = $filters['delivery_date'] ?? today()->toDateString();

        $orders = Order::with('customer', 'zone')
            ->whereDate('delivery_date', $date)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('time_slot')
            ->orderBy('id')
            ->get();

        // Tổng kg theo loại rau của các đơn chưa hủy trong ngày.
        $demand = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereDate('orders.delivery_date', $date)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('products.id', 'products.name')
            ->orderBy('products.id')
            ->selectRaw('products.name as name, SUM(order_items.weight_kg) as kg')
            ->get();

        return view('admin.orders.index', [
            'orders' => $orders,
            'demand' => $demand,
            'date' => $date,
            'status' => $filters['status'] ?? null,
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.orders.create', [
            'baskets' => Basket::with('items.product')->where('is_active', true)->get(),
            'products' => Product::where('is_active', true)->orderBy('id')->get(),
            'zones' => DeliveryZone::where('is_active', true)->get(),
            'timeSlots' => config('farm2home.order.time_slots'),
            'paymentMethods' => PaymentMethod::cases(),
            'channels' => OrderChannel::cases(),
        ]);
    }

    public function store(StoreAdminOrderRequest $request, OrderService $orders): RedirectResponse
    {
        $data = $request->validated();
        $order = $orders->place($data, OrderChannel::from($data['channel']));

        return redirect()->route('admin.orders.show', $order)->with('status', "Đã tạo đơn {$order->code}.");
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load('customer', 'zone', 'items.product', 'items.batches'),
        ]);
    }

    public function transition(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $orders->transition($order, OrderStatus::from($data['status']), $data['cancel_reason'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('status', 'Đã cập nhật trạng thái đơn.');
    }
}
```

Thêm vào nhóm `admin` trong `routes/web.php`:

```php
    Route::resource('orders', AdminOrderController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('orders/{order}/status', [AdminOrderController::class, 'transition'])->name('orders.transition');
```

với `use App\Http\Controllers\Admin\OrderController as AdminOrderController;`.

- [ ] **Step 5: Viết 3 view**

`admin/orders/index.blade.php` (layout admin, title "Đơn hàng"):
- Form GET lọc: input `date` tên `delivery_date` (giá trị `$date`), select `status` (thêm option trống "Tất cả"), nút "Lọc"; link "Tạo đơn" → `admin.orders.create`.
- Khối "Nhu cầu trong ngày": mỗi dòng `{{ $row->name }}: @kg($row->kg)`.
- Bảng: mã đơn (link `admin.orders.show`), khung giờ, tên khách, khu vực, loại đơn (label), `@vnd($order->total)`, `<x-status-badge>`.
- Khi rỗng: "Chưa có đơn nào cho ngày này."

`admin/orders/show.blade.php` (title = mã đơn):
- Thông tin: khách (tên, SĐT, địa chỉ giao hoặc "Nhận tại điểm"), kênh, ngày + khung giờ, thanh toán (hình thức + trạng thái), ghi chú, lý do hủy nếu có.
- Bảng dòng hàng: tên rau, số gói, `@kg($item->weight_kg)`, `@vnd($item->line_total)`, số gói thực giao, mã lô đã gán (`$item->batches->pluck('code')->join(', ')`).
- Tổng: tiền hàng, phí giao, tổng.
- Với mỗi `$next` trong `$order->status->next()`: một form `PATCH route('admin.orders.transition', $order)` có input ẩn `status = $next->value` và nút mang `$next->label()`. Form hủy có thêm input `cancel_reason` nhãn "Lý do hủy".

`admin/orders/create.blade.php` (title "Tạo đơn hộ khách"): cùng các field như form công khai, thêm select `channel` (mặc định `zalo`), input `delivery_date` kiểu `date` (min = hôm nay), checkbox `data_consent` không bắt buộc với nhãn "Khách đã đồng ý cho dùng thông tin".

- [ ] **Step 6: Chạy toàn bộ test, commit**

Run: `php artisan test`
Expected: PASS toàn bộ.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: admin order list, detail, manual entry and status transitions"
```

---

## Kết quả Phase 2

- [ ] Đặt được đơn giỏ và đơn lẻ từ điện thoại; mã đơn, phí giao, tổng tiền đúng.
- [ ] Giờ chốt 18:00 được áp cho khách, không áp cho operator.
- [ ] Tra cứu đơn không lộ tên và địa chỉ.
- [ ] Operator lọc đơn theo ngày, xem nhu cầu kg theo loại rau, chuyển trạng thái đúng thứ tự.

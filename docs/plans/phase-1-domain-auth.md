# Phase 1 — Domain, đăng nhập, khung giao diện: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Có đủ enum, model với quan hệ đúng theo schema, đăng nhập cho admin/operator, middleware phân quyền và ba layout Blade dùng chung.

**Architecture:** Enum backed-string khớp từng giá trị ENUM trong MySQL. Model dùng `#[Fillable]` + `casts()` như `User.php`. Hai bảng pivot (`farmer_products`, `order_item_batches`) không có model riêng.

**Tech Stack:** Laravel 13 · Blade · Tailwind 4 · Alpine.js · PHPUnit 12.

**Spec:** [`docs/CLAUDE.md`](../CLAUDE.md) mục 4, 6, 8 · `database/farm2home.sql` dòng 17–449 · [`docs/PLAN.md`](../PLAN.md).

## Global Constraints

Xem `docs/PLAN.md`. Phase 0 phải xong trước (DB test, `DemoSeeder`).

---

### Task 1.1: Enum

**Files:**
- Create: 17 file trong `app/Enums/`
- Test: `tests/Unit/Enums/EnumLabelsTest.php`, `tests/Unit/Enums/OrderStatusTest.php`

**Interfaces:**
- Produces: mọi enum có `label(): string` (tiếng Việt). Riêng `OrderStatus` có thêm `canTransitionTo(self $to): bool`, `next(): array`, `countsAsDemand(): bool`.

Danh sách enum, giá trị phải khớp chính xác ENUM trong SQL:

| Enum | Case => value | `label()` |
|---|---|---|
| `UserRole` | `Admin`=>`admin`, `Operator`=>`operator` | Quản trị, Vận hành |
| `FarmerStatus` | `Active`=>`active`, `Paused`=>`paused`, `Inactive`=>`inactive` | Đang hợp tác, Tạm nghỉ, Ngừng |
| `CustomerSource` | `Zalo`, `Form`, `Website`, `Phone`, `Referral` (value viết thường) | Zalo, Form, Website, Điện thoại, Giới thiệu |
| `OrderType` | `Basket`=>`basket`, `Single`=>`single` | Giỏ rau, Chọn lẻ |
| `OrderChannel` | `Zalo`, `Form`, `Website`, `Phone` (value viết thường) | Zalo, Form, Website, Điện thoại |
| `DeliveryMethod` | `Home`=>`home`, `Pickup`=>`pickup` | Giao tận nhà, Nhận tại điểm |
| `PaymentMethod` | `Cod`=>`cod`, `BankTransfer`=>`bank_transfer` | Tiền mặt khi nhận, Chuyển khoản |
| `PaymentStatus` | `Unpaid`=>`unpaid`, `Paid`=>`paid`, `Refunded`=>`refunded` | Chưa thanh toán, Đã thanh toán, Đã hoàn tiền |
| `OrderStatus` | `Pending`, `Confirmed`, `Packing`, `Delivering`, `Delivered`, `Cancelled` (value viết thường) | Chờ xác nhận, Đã xác nhận, Đang đóng gói, Đang giao, Đã giao, Đã hủy |
| `SubscriptionFrequency` | `Weekly`=>`weekly`, `Biweekly`=>`biweekly`, `Monthly`=>`monthly` | Hằng tuần, 2 tuần/lần, 4 tuần/lần |
| `SubscriptionStatus` | `Active`, `Paused`, `Cancelled` (value viết thường) | Đang chạy, Tạm dừng, Đã hủy |
| `SupplySource` | `Zalo`, `Form`, `Operator` (value viết thường) | Zalo, Link nông hộ, Operator nhập |
| `ForecastMethod` | `MovingAverage`=>`moving_average`, `ExpSmoothing`=>`exp_smoothing`, `TreeRegression`=>`tree_regression`, `Manual`=>`manual` | Trung bình trượt 7 ngày, Làm mượt hàm mũ, Cây hồi quy, Nhập tay |
| `HarvestPlanStatus` | `Draft`, `Confirmed`, `Sent`, `Completed` (value viết thường) | Nháp, Đã chốt, Đã gửi nông hộ, Hoàn tất |
| `QualityStatus` | `Pending`, `Passed`, `Rejected` (value viết thường) | Chờ kiểm, Đạt, Không đạt |
| `FeedbackStatus` | `New`=>`new`, `Processing`=>`processing`, `Resolved`=>`resolved` | Mới, Đang xử lý, Đã xử lý |
| `WasteReason` | `Surplus`, `Damaged`, `Expired`, `Discounted` (value viết thường) | Dư thừa, Hư hỏng, Hết hạn, Bán giảm giá |

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Unit/Enums/EnumLabelsTest.php`:

```php
<?php

namespace Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;

class EnumLabelsTest extends TestCase
{
    public function test_there_are_17_enums_and_every_case_has_a_label(): void
    {
        $files = glob(dirname(__DIR__, 3).'/app/Enums/*.php');
        $this->assertCount(17, $files);

        foreach ($files as $file) {
            $enum = 'App\\Enums\\'.basename($file, '.php');
            foreach ($enum::cases() as $case) {
                $this->assertNotSame('', trim($case->label()), "{$enum}::{$case->name} has no label");
            }
        }
    }

    public function test_values_match_the_mysql_enums(): void
    {
        $this->assertSame(['pending', 'confirmed', 'packing', 'delivering', 'delivered', 'cancelled'], array_column(\App\Enums\OrderStatus::cases(), 'value'));
        $this->assertSame(['moving_average', 'exp_smoothing', 'tree_regression', 'manual'], array_column(\App\Enums\ForecastMethod::cases(), 'value'));
        $this->assertSame(['cod', 'bank_transfer'], array_column(\App\Enums\PaymentMethod::cases(), 'value'));
        $this->assertSame(['surplus', 'damaged', 'expired', 'discounted'], array_column(\App\Enums\WasteReason::cases(), 'value'));
        $this->assertSame(['draft', 'confirmed', 'sent', 'completed'], array_column(\App\Enums\HarvestPlanStatus::cases(), 'value'));
        $this->assertSame(['zalo', 'form', 'website', 'phone', 'referral'], array_column(\App\Enums\CustomerSource::cases(), 'value'));
    }
}
```

`tests/Unit/Enums/OrderStatusTest.php`:

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_happy_path_transitions(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Confirmed));
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Packing));
        $this->assertTrue(OrderStatus::Packing->canTransitionTo(OrderStatus::Delivering));
        $this->assertTrue(OrderStatus::Delivering->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_every_open_status_can_be_cancelled(): void
    {
        foreach ([OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Delivering] as $status) {
            $this->assertTrue($status->canTransitionTo(OrderStatus::Cancelled));
        }
    }

    public function test_closed_statuses_are_terminal_and_steps_cannot_be_skipped(): void
    {
        $this->assertSame([], OrderStatus::Delivered->next());
        $this->assertSame([], OrderStatus::Cancelled->next());
        $this->assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Delivered));
        $this->assertFalse(OrderStatus::Delivered->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_only_cancelled_orders_are_excluded_from_demand(): void
    {
        $this->assertTrue(OrderStatus::Pending->countsAsDemand());
        $this->assertTrue(OrderStatus::Delivered->countsAsDemand());
        $this->assertFalse(OrderStatus::Cancelled->countsAsDemand());
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter="EnumLabelsTest|OrderStatusTest"`
Expected: FAIL (`assertCount(17, [])`, class not found).

- [ ] **Step 3: Viết `app/Enums/OrderStatus.php`**

```php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Packing = 'packing';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Packing => 'Đang đóng gói',
            self::Delivering => 'Đang giao',
            self::Delivered => 'Đã giao',
            self::Cancelled => 'Đã hủy',
        };
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Packing, self::Cancelled],
            self::Packing => [self::Delivering, self::Cancelled],
            self::Delivering => [self::Delivered, self::Cancelled],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    // Đơn chưa hủy đều được tính vào nhu cầu (quyết định D3).
    public function countsAsDemand(): bool
    {
        return $this !== self::Cancelled;
    }
}
```

- [ ] **Step 4: Viết 16 enum còn lại theo cùng mẫu**

Mỗi file có dạng sau, với case, value và label lấy đúng từ bảng ở đầu task. Ví dụ đầy đủ `app/Enums/PaymentMethod.php`:

```php
<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Tiền mặt khi nhận',
            self::BankTransfer => 'Chuyển khoản',
        };
    }
}
```

Tên file = tên enum: `UserRole`, `FarmerStatus`, `CustomerSource`, `OrderType`, `OrderChannel`, `DeliveryMethod`, `PaymentStatus`, `SubscriptionFrequency`, `SubscriptionStatus`, `SupplySource`, `ForecastMethod`, `HarvestPlanStatus`, `QualityStatus`, `FeedbackStatus`, `WasteReason`.

- [ ] **Step 5: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter="EnumLabelsTest|OrderStatusTest"`
Expected: PASS (6 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: backed enums for every database enum with vietnamese labels"
```

---

### Task 1.2: Model và quan hệ

**Files:**
- Modify: `app/Models/User.php`
- Create: 17 model trong `app/Models/`, `tests/Support/BuildsCatalog.php`
- Test: `tests/Feature/Models/SampleDataRelationsTest.php`, `tests/Feature/Models/BuildsCatalogTest.php`

**Interfaces:**
- Consumes: enum của Task 1.1, `DemoSeeder` của Task 0.2.
- Produces: các model và tên quan hệ trong bảng dưới (các phase sau gọi đúng tên này); trait `Tests\Support\BuildsCatalog` với các thuộc tính `$zone`, `$pickupZone`, `$rm`, `$cn`, `$hl`, `$nh01`, `$nh02`, `$basket`, `$admin`, `$operator` và hàm `buildCatalog(): void`.

Quy tắc chung cho mọi model:
- `#[Fillable([...])]` = mọi cột trừ `id`, `created_at`, `updated_at`.
- Cột tiền (`INT`) cast `'integer'`; cột `DECIMAL(…,2)` cast `'decimal:2'`; `pack_weight_kg` và `weight_kg` cast `'decimal:3'`; cột `DATE` cast `'date'`; `DATETIME`/`TIMESTAMP` cast `'datetime'`; `TINYINT(1)` cast `'boolean'`; cột ENUM cast sang enum tương ứng.
- Cast `decimal` trả về **chuỗi**: mọi phép tính trong service phải ép `(float)`.

| Model | Bảng / đặc biệt | Quan hệ (tên hàm → kiểu) |
|---|---|---|
| `User` | thêm `phone`, `role` vào Fillable; cast `role` => `UserRole` | — |
| `DeliveryZone` | — | `customers` hasMany, `orders` hasMany · `isPickup(): bool` = `pickup_point !== null` |
| `Farmer` | cast `status`, `certificate_expires_at` | `products` belongsToMany(Product, `farmer_products`)->withPivot(`typical_weekly_kg`), `supplyReports` hasMany, `harvestPlanItems` hasMany, `batches` hasMany · `hasValidCertificate(): bool`, `zaloLink(): string` |
| `Product` | — | `farmers` belongsToMany(Farmer, `farmer_products`)->withPivot(`typical_weekly_kg`), `basketItems`, `orderItems`, `supplyReports`, `forecasts`, `batches` hasMany |
| `Basket` | — | `items` hasMany(BasketItem) |
| `BasketItem` | `public $timestamps = false` | `basket` belongsTo, `product` belongsTo |
| `Customer` | cast `preferences` => `'array'`, `source` => `CustomerSource` | `zone` belongsTo(DeliveryZone, `delivery_zone_id`), `orders`, `subscriptions`, `feedbacks` hasMany |
| `Subscription` | cast `frequency`, `status` | `customer`, `basket` belongsTo, `orders` hasMany |
| `Order` | cast 6 enum | `customer`, `subscription` belongsTo, `zone` belongsTo(DeliveryZone, `delivery_zone_id`), `items` hasMany(OrderItem), `feedbacks`, `packagingReturns` hasMany |
| `OrderItem` | — | `order`, `product`, `basket` belongsTo, `batches` belongsToMany(Batch, `order_item_batches`)->withPivot(`quantity`) |
| `SupplyReport` | cast `source` | `farmer`, `product` belongsTo |
| `Forecast` | cast `method` | `product` belongsTo |
| `HarvestPlan` | cast `status` | `items` hasMany(HarvestPlanItem), `creator` belongsTo(User, `created_by`), `confirmer` belongsTo(User, `confirmed_by`) |
| `HarvestPlanItem` | — | `plan` belongsTo(HarvestPlan, `harvest_plan_id`), `product`, `farmer` belongsTo, `batch` hasOne |
| `Batch` | cast `quality_status` | `planItem` belongsTo(HarvestPlanItem, `harvest_plan_item_id`), `farmer`, `product` belongsTo, `orderItems` belongsToMany(OrderItem, `order_item_batches`)->withPivot(`quantity`), `feedbacks`, `wasteLogs` hasMany |
| `Feedback` | **`protected $table = 'feedbacks';`** (Laravel tự suy ra `feedback`) | `batch`, `order`, `customer` belongsTo, `resolver` belongsTo(User, `resolved_by`) |
| `WasteLog` | cast `reason` | `product`, `batch` belongsTo, `creator` belongsTo(User, `created_by`) |
| `PackagingReturn` | — | `customer`, `order` belongsTo |

- [ ] **Step 1: Viết test quan hệ trên dữ liệu mẫu (sẽ fail)**

`tests/Feature/Models/SampleDataRelationsTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\SubscriptionFrequency;
use App\Enums\UserRole;
use App\Models\Basket;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\Farmer;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleDataRelationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->seed(DemoSeeder::class);
    }

    public function test_order_casts_and_relations(): void
    {
        $order = Order::where('code', 'DH260911-003')->firstOrFail();

        $this->assertSame(OrderType::Basket, $order->order_type);
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(125000, $order->total);
        $this->assertSame('2026-09-11', $order->delivery_date->toDateString());
        $this->assertSame('Nguyễn Thị Lan', $order->customer->name);
        $this->assertSame('Cụm 1 – Trung tâm Mỹ Tho', $order->zone->name);
        $this->assertGreaterThan(0, $order->items->count());
        $this->assertSame(3, $order->items->first()->basket_id);
    }

    public function test_farmer_products_and_certificate(): void
    {
        $nh02 = Farmer::where('code', 'NH02')->firstOrFail();
        $nh01 = Farmer::where('code', 'NH01')->firstOrFail();

        $this->assertCount(4, $nh02->products);
        $this->assertSame('35.00', $nh02->products->firstWhere('code', 'CN')->pivot->typical_weekly_kg);
        $this->assertTrue($nh02->hasValidCertificate());
        $this->assertFalse($nh01->hasValidCertificate());
        $this->assertSame('https://zalo.me/0911000001', $nh01->zaloLink());
    }

    public function test_certificate_is_invalid_after_expiry(): void
    {
        $nh02 = Farmer::where('code', 'NH02')->firstOrFail();

        $this->travelTo('2027-07-01 08:00:00');

        $this->assertFalse($nh02->hasValidCertificate());
    }

    public function test_batch_links_farmer_product_plan_item_and_order_items(): void
    {
        $batch = Batch::where('code', 'F2H-260917-RM-NH01')->firstOrFail();

        $this->assertSame('NH01', $batch->farmer->code);
        $this->assertSame('RM', $batch->product->code);
        $this->assertSame(1, $batch->planItem->id);
        $this->assertSame('2026-09-19', $batch->best_before->toDateString());
        $this->assertSame(4, (int) $batch->orderItems->sum('pivot.quantity'));
        $this->assertSame('2026-09-17', $batch->planItem->plan->delivery_date->toDateString());
    }

    public function test_misc_tables_and_casts(): void
    {
        $this->assertSame(45, Feedback::count());
        $this->assertCount(4, Basket::where('slug', 'gio-mini')->firstOrFail()->items);
        $this->assertSame(['rau sống'], Customer::findOrFail(1)->preferences);
        $this->assertSame(SubscriptionFrequency::Weekly, Subscription::findOrFail(1)->frequency);
        $this->assertSame(UserRole::Admin, User::where('email', 'admin@farm2home.vn')->firstOrFail()->role);
    }
}
```

Ghi chú về số `4` ở `orderItems->sum('pivot.quantity')`: lô `F2H-260917-RM-NH01` có 2,90 kg, đã gán 2,0 kg = 4 gói 0,5 kg, 0,9 kg còn lại nằm trong `waste_logs`.

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=SampleDataRelationsTest`
Expected: FAIL (`Class "App\Models\Order" not found`).

- [ ] **Step 3: Sửa `app/Models/User.php`**

Đổi dòng `#[Fillable(...)]` và thêm cast `role`:

```php
#[Fillable(['name', 'email', 'phone', 'role', 'password'])]
```

```php
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
```

Thêm `use App\Enums\UserRole;` ở đầu file.

- [ ] **Step 4: Viết các model. Bốn file mẫu đầy đủ:**

`app/Models/Order.php`:

```php
<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'customer_id', 'subscription_id', 'delivery_zone_id', 'order_type', 'channel',
    'delivery_date', 'time_slot', 'delivery_method', 'delivery_address',
    'subtotal', 'shipping_fee', 'discount', 'total',
    'payment_method', 'payment_status', 'status',
    'confirmed_at', 'delivered_at', 'cancel_reason', 'note',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'channel' => OrderChannel::class,
            'delivery_method' => DeliveryMethod::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'status' => OrderStatus::class,
            'delivery_date' => 'date',
            'confirmed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'subtotal' => 'integer',
            'shipping_fee' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function packagingReturns(): HasMany
    {
        return $this->hasMany(PackagingReturn::class);
    }
}
```

`app/Models/OrderItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['order_id', 'product_id', 'basket_id', 'quantity', 'weight_kg', 'unit_price', 'line_total', 'fulfilled_quantity'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'weight_kg' => 'decimal:3',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'fulfilled_quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'order_item_batches')->withPivot('quantity');
    }
}
```

`app/Models/Farmer.php`:

```php
<?php

namespace App\Models;

use App\Enums\FarmerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'phone', 'zalo_phone', 'address', 'growing_area', 'area_m2', 'farming_method',
    'certificate_name', 'certificate_file', 'certificate_expires_at', 'access_token', 'status', 'note',
])]
class Farmer extends Model
{
    protected function casts(): array
    {
        return [
            'status' => FarmerStatus::class,
            'certificate_expires_at' => 'date',
            'area_m2' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'farmer_products')->withPivot('typical_weekly_kg');
    }

    public function supplyReports(): HasMany
    {
        return $this->hasMany(SupplyReport::class);
    }

    public function harvestPlanItems(): HasMany
    {
        return $this->hasMany(HarvestPlanItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    // QR ≠ chứng nhận: chỉ coi là có chứng nhận khi có file và chưa hết hạn.
    public function hasValidCertificate(): bool
    {
        return filled($this->certificate_file)
            && ($this->certificate_expires_at === null || today()->lte($this->certificate_expires_at));
    }

    public function zaloLink(): string
    {
        return 'https://zalo.me/'.($this->zalo_phone ?: $this->phone);
    }
}
```

`app/Models/Feedback.php`:

```php
<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['batch_id', 'order_id', 'customer_id', 'rating', 'freshness_rating', 'comment', 'status', 'response', 'resolved_by', 'resolved_at'])]
class Feedback extends Model
{
    // Laravel would infer "feedback" (uncountable); the real table is "feedbacks".
    protected $table = 'feedbacks';

    protected function casts(): array
    {
        return [
            'status' => FeedbackStatus::class,
            'rating' => 'integer',
            'freshness_rating' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
```

- [ ] **Step 5: Viết 13 model còn lại** (`DeliveryZone`, `Product`, `Basket`, `BasketItem`, `Customer`, `Subscription`, `SupplyReport`, `Forecast`, `HarvestPlan`, `HarvestPlanItem`, `Batch`, `WasteLog`, `PackagingReturn`) theo đúng mẫu trên, lấy danh sách cột từ `CREATE TABLE` tương ứng trong `database/farm2home.sql` và quan hệ từ bảng ở đầu task. Hai điểm bắt buộc:

```php
// app/Models/BasketItem.php — bảng không có created_at/updated_at
public $timestamps = false;
```

```php
// app/Models/DeliveryZone.php
public function isPickup(): bool
{
    return $this->pickup_point !== null;
}
```

- [ ] **Step 6: Chạy test, xác nhận pass**

Run: `php artisan test --filter=SampleDataRelationsTest`
Expected: PASS (5 tests).

- [ ] **Step 7: Viết trait `tests/Support/BuildsCatalog.php`**

```php
<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\Basket;
use App\Models\DeliveryZone;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\User;

/**
 * Danh mục tối thiểu cho feature test: 2 cụm giao, 3 loại rau, 2 nông hộ, 1 giỏ, 2 user.
 */
trait BuildsCatalog
{
    protected DeliveryZone $zone;

    protected DeliveryZone $pickupZone;

    protected Product $rm;

    protected Product $cn;

    protected Product $hl;

    protected Farmer $nh01;

    protected Farmer $nh02;

    protected Basket $basket;

    protected User $admin;

    protected User $operator;

    protected function buildCatalog(): void
    {
        $this->zone = DeliveryZone::create(['name' => 'Cụm 1 – Trung tâm', 'shipping_fee' => 10000, 'free_ship_from' => 150000, 'is_active' => true]);
        $this->pickupZone = DeliveryZone::create(['name' => 'Điểm nhận tập trung', 'shipping_fee' => 0, 'pickup_point' => 'Cổng trường', 'is_active' => true]);

        $this->rm = $this->makeProduct('RM', 'Rau muống', 0.5, 12000, 14000, 2);
        $this->cn = $this->makeProduct('CN', 'Cải ngọt', 0.5, 15000, 18000, 3);
        $this->hl = $this->makeProduct('HL', 'Hành lá', 0.2, 8000, 30000, 5);

        $this->nh01 = Farmer::create([
            'code' => 'NH01', 'name' => 'Nguyễn Văn Tâm', 'phone' => '0911000001', 'zalo_phone' => '0911000001',
            'growing_area' => 'Xã Thân Cửu Nghĩa, Đồng Tháp', 'access_token' => str_repeat('a', 40), 'status' => 'active',
        ]);
        $this->nh02 = Farmer::create([
            'code' => 'NH02', 'name' => 'Trần Thị Hồng', 'phone' => '0911000002', 'zalo_phone' => '0911000002',
            'growing_area' => 'Xã Thân Cửu Nghĩa, Đồng Tháp', 'access_token' => str_repeat('b', 40), 'status' => 'active',
            'certificate_name' => 'VietGAP (mẫu)', 'certificate_file' => 'certificates/nh02.pdf', 'certificate_expires_at' => '2027-06-30',
        ]);
        $this->nh01->products()->attach([$this->rm->id => ['typical_weekly_kg' => 40], $this->hl->id => ['typical_weekly_kg' => 10]]);
        $this->nh02->products()->attach([$this->rm->id => ['typical_weekly_kg' => 30], $this->cn->id => ['typical_weekly_kg' => 35], $this->hl->id => ['typical_weekly_kg' => 12]]);

        $this->basket = Basket::create(['name' => 'Giỏ Mini', 'slug' => 'gio-mini', 'household_min' => 1, 'household_max' => 2, 'price' => 45000, 'is_active' => true]);
        foreach ([$this->rm, $this->cn, $this->hl] as $product) {
            $this->basket->items()->create(['product_id' => $product->id, 'quantity' => 1, 'allow_substitute' => true]);
        }

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => UserRole::Admin]);
        $this->operator = User::create(['name' => 'Operator', 'email' => 'operator@example.test', 'password' => 'password', 'role' => UserRole::Operator]);
    }

    private function makeProduct(string $code, string $name, float $packKg, int $price, int $costPerKg, int $shelfDays): Product
    {
        return Product::create([
            'code' => $code, 'name' => $name, 'slug' => str($name)->slug()->value(),
            'pack_weight_kg' => $packKg, 'price' => $price, 'cost_price_per_kg' => $costPerKg,
            'shelf_life_days' => $shelfDays, 'storage_guide' => 'Bảo quản ngăn mát 4–8°C.', 'is_active' => true,
        ]);
    }
}
```

`tests/Feature/Models/BuildsCatalogTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class BuildsCatalogTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    public function test_catalog_fixture_is_consistent(): void
    {
        $this->buildCatalog();

        $this->assertTrue($this->pickupZone->isPickup());
        $this->assertFalse($this->zone->isPickup());
        $this->assertCount(3, $this->basket->items);
        $this->assertSame('0.200', $this->hl->fresh()->pack_weight_kg);
        $this->assertSame(['CN', 'HL', 'RM'], $this->nh02->products->pluck('code')->sort()->values()->all());
        $this->assertTrue(password_verify('password', $this->admin->password));
    }
}
```

- [ ] **Step 8: Chạy toàn bộ test, commit**

Run: `php artisan test`
Expected: PASS toàn bộ.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: eloquent models and relationships for all domain tables"
```

---

### Task 1.3: Đăng nhập và phân quyền

**Files:**
- Create: `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Middleware/EnsureRole.php`, `app/Http/Controllers/Admin/DashboardController.php`, `resources/views/auth/login.blade.php`, `resources/views/admin/dashboard.blade.php`
- Modify: `bootstrap/app.php` (khối `withMiddleware`), `routes/web.php`
- Test: `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Auth/RoleMiddlewareTest.php`

**Interfaces:**
- Produces: route `login`, `login.store`, `logout`, `admin.dashboard`; middleware alias `role` nhận danh sách role phân cách bằng dấu phẩy (`role:admin,operator`).

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Auth/LoginTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_login_page_is_in_vietnamese(): void
    {
        $this->get('/login')->assertOk()->assertSee('Đăng nhập')->assertSee('Mật khẩu');
    }

    public function test_operator_can_log_in_and_reach_the_dashboard(): void
    {
        $this->post('/login', ['email' => 'operator@example.test', 'password' => 'password'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($this->operator);
        $this->get('/admin')->assertOk()->assertSee('Tổng quan');
    }

    public function test_wrong_password_shows_a_vietnamese_error_and_keeps_the_email(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'operator@example.test', 'password' => 'sai-mat-khau'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.'])
            ->assertSessionHasInput('email', 'operator@example.test');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->operator)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_logged_in_user_visiting_login_goes_to_dashboard(): void
    {
        $this->actingAs($this->admin)->get('/login')->assertRedirect('/admin');
    }
}
```

`tests/Feature/Auth/RoleMiddlewareTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
        Route::middleware(['web', 'auth', 'role:admin'])->get('/_test/admin-only', fn () => 'ok');
    }

    public function test_operator_is_forbidden_on_admin_only_routes(): void
    {
        $this->actingAs($this->operator)->get('/_test/admin-only')->assertForbidden();
    }

    public function test_admin_passes(): void
    {
        $this->actingAs($this->admin)->get('/_test/admin-only')->assertOk()->assertSee('ok');
    }

    public function test_both_roles_can_open_the_admin_area(): void
    {
        $this->actingAs($this->operator)->get('/admin')->assertOk();
        $this->actingAs($this->admin)->get('/admin')->assertOk();
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter="LoginTest|RoleMiddlewareTest"`
Expected: FAIL (404 cho `/admin`, `/login`).

- [ ] **Step 3: Viết middleware `app/Http/Middleware/EnsureRole.php`**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user !== null && in_array($user->role->value, $roles, true), 403);

        return $next($request);
    }
}
```

Trong `bootstrap/app.php`, thay `//` bên trong `withMiddleware` bằng:

```php
        $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
```

- [ ] **Step 4: Viết `app/Http/Controllers/Auth/LoginController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

- [ ] **Step 5: Viết `app/Http/Controllers/Admin/DashboardController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard');
    }
}
```

- [ ] **Step 6: Thay toàn bộ `routes/web.php`**

```php
<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin,operator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
});
```

- [ ] **Step 7: Viết hai view tối thiểu** (sẽ chuyển sang layout ở Task 1.4)

`resources/views/auth/login.blade.php` phải có: tiêu đề "Đăng nhập", form `POST {{ route('login.store') }}` với `@csrf`, input `email` (giữ `old('email')`), input `password` có nhãn "Mật khẩu", checkbox `remember` nhãn "Ghi nhớ đăng nhập", nút "Đăng nhập", và hiển thị `$errors->first('email')`.

`resources/views/admin/dashboard.blade.php` phải có: tiêu đề `<h1>Tổng quan</h1>` và form `POST {{ route('logout') }}` với nút "Đăng xuất".

- [ ] **Step 8: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter="LoginTest|RoleMiddlewareTest"`
Expected: PASS (9 tests).

Kiểm tra tay: mở `http://farm2home.test/login`, đăng nhập `operator@farm2home.vn` / `password`.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: session login and role middleware for the admin area"
```

---

### Task 1.4: Khung giao diện, định dạng tiền/kg, thông báo lỗi tiếng Việt

**Files:**
- Create: `app/Support/Format.php`, `resources/views/components/layouts/public.blade.php`, `.../layouts/farmer.blade.php`, `.../layouts/admin.blade.php`, `resources/views/components/field.blade.php`, `.../flash.blade.php`, `.../status-badge.blade.php`, `lang/vi/validation.php`
- Modify: `app/Providers/AppServiceProvider.php` (hàm `boot`), `resources/views/auth/login.blade.php`, `resources/views/admin/dashboard.blade.php`
- Test: `tests/Unit/Support/FormatTest.php`, `tests/Feature/Ui/LayoutTest.php`

**Interfaces:**
- Produces:
  - `App\Support\Format::vnd(int $amount): string` → `'12.000đ'`; `Format::kg(float|string $kg): string` → `'2,5 kg'`.
  - Blade directive `@vnd($amount)`, `@kg($kg)`.
  - Component `<x-layouts.public title="">`, `<x-layouts.farmer title="">`, `<x-layouts.admin title="">`, `<x-field name="" label="" type="text" :value="">`, `<x-flash />`, `<x-status-badge :status="$enum" />`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Unit/Support/FormatTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_vnd_uses_dot_thousands_and_dong_suffix(): void
    {
        $this->assertSame('12.000đ', Format::vnd(12000));
        $this->assertSame('1.150.000đ', Format::vnd(1150000));
        $this->assertSame('0đ', Format::vnd(0));
    }

    public function test_kg_uses_comma_decimal_and_trims_zeros(): void
    {
        $this->assertSame('2,5 kg', Format::kg('2.50'));
        $this->assertSame('3 kg', Format::kg('3.00'));
        $this->assertSame('0,25 kg', Format::kg(0.25));
        $this->assertSame('10 kg', Format::kg(10));
    }
}
```

`tests/Feature/Ui/LayoutTest.php`:

```php
<?php

namespace Tests\Feature\Ui;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    public function test_blade_directives_format_money_and_weight(): void
    {
        $this->assertSame('12.000đ | 2,5 kg', trim(Blade::render('@vnd($a) | @kg($b)', ['a' => 12000, 'b' => '2.50'])));
    }

    public function test_status_badge_shows_the_enum_label(): void
    {
        $html = Blade::render('<x-status-badge :status="$s" />', ['s' => OrderStatus::Delivering]);

        $this->assertStringContainsString('Đang giao', $html);
    }

    public function test_admin_layout_has_navigation_and_user_name(): void
    {
        $this->buildCatalog();

        $this->actingAs($this->operator)->get('/admin')
            ->assertOk()
            ->assertSee('FARM2HOME')
            ->assertSee('Đơn hàng')
            ->assertSee('Kế hoạch thu hoạch')
            ->assertSee('Operator')
            ->assertSee('Đăng xuất');
    }

    public function test_validation_messages_are_vietnamese(): void
    {
        $validator = Validator::make(['phone' => 'abc'], ['phone' => ['required', 'regex:/^0\d{9}$/'], 'name' => ['required']]);

        $this->assertSame('Số điện thoại không đúng định dạng.', $validator->errors()->first('phone'));
        $this->assertSame('Vui lòng nhập họ tên.', $validator->errors()->first('name'));
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter="FormatTest|LayoutTest"`
Expected: FAIL (`Class "App\Support\Format" not found`).

- [ ] **Step 3: Viết `app/Support/Format.php`**

```php
<?php

namespace App\Support;

final class Format
{
    public static function vnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'đ';
    }

    public static function kg(float|string $kg): string
    {
        return rtrim(rtrim(number_format((float) $kg, 2, ',', '.'), '0'), ',').' kg';
    }
}
```

- [ ] **Step 4: Đăng ký Blade directive trong `AppServiceProvider::boot()`**

```php
        Blade::directive('vnd', fn (string $expression) => "<?php echo e(\\App\\Support\\Format::vnd((int) ({$expression}))); ?>");
        Blade::directive('kg', fn (string $expression) => "<?php echo e(\\App\\Support\\Format::kg({$expression})); ?>");
```

Thêm `use Illuminate\Support\Facades\Blade;` ở đầu file.

- [ ] **Step 5: Viết `lang/vi/validation.php`**

```php
<?php

return [
    'required' => 'Vui lòng nhập :attribute.',
    'required_if' => 'Vui lòng nhập :attribute.',
    'accepted' => 'Bạn cần đồng ý :attribute.',
    'regex' => ':Attribute không đúng định dạng.',
    'email' => ':Attribute không phải email hợp lệ.',
    'integer' => ':Attribute phải là số nguyên.',
    'numeric' => ':Attribute phải là số.',
    'date' => ':Attribute không phải ngày hợp lệ.',
    'date_format' => ':Attribute không đúng định dạng ngày.',
    'after_or_equal' => ':Attribute phải từ ngày :date trở đi.',
    'before_or_equal' => ':Attribute phải trước hoặc bằng ngày :date.',
    'in' => ':Attribute không hợp lệ.',
    'enum' => ':Attribute không hợp lệ.',
    'exists' => ':Attribute không tồn tại.',
    'unique' => ':Attribute đã được dùng.',
    'array' => ':Attribute không hợp lệ.',
    'min' => [
        'numeric' => ':Attribute phải từ :min trở lên.',
        'string' => ':Attribute phải có ít nhất :min ký tự.',
        'array' => ':Attribute phải có ít nhất :min mục.',
    ],
    'max' => [
        'numeric' => ':Attribute không được lớn hơn :max.',
        'string' => ':Attribute không được dài quá :max ký tự.',
        'array' => ':Attribute không được quá :max mục.',
        'file' => ':Attribute không được lớn hơn :max KB.',
    ],
    'mimes' => ':Attribute phải là file dạng: :values.',

    'attributes' => [
        'name' => 'họ tên',
        'phone' => 'số điện thoại',
        'email' => 'email',
        'password' => 'mật khẩu',
        'address' => 'địa chỉ',
        'delivery_zone_id' => 'khu vực giao',
        'delivery_date' => 'ngày giao',
        'time_slot' => 'khung giờ giao',
        'order_type' => 'loại đơn',
        'basket_id' => 'giỏ rau',
        'items' => 'danh sách rau',
        'payment_method' => 'hình thức thanh toán',
        'data_consent' => 'việc dùng thông tin để xử lý đơn',
        'note' => 'ghi chú',
        'rating' => 'đánh giá',
        'freshness_rating' => 'độ tươi',
        'comment' => 'nhận xét',
        'cancel_reason' => 'lý do hủy',
        'final_kg' => 'số kg chốt',
        'adjust_reason' => 'lý do điều chỉnh',
        'received_kg' => 'số kg thực nhận',
        'quantity_kg' => 'số kg',
    ],
];
```

- [ ] **Step 6: Viết component**

`resources/views/components/status-badge.blade.php`:

```blade
@props(['status'])
@php
    $classes = match ($status->value) {
        'pending', 'draft', 'new' => 'bg-amber-100 text-amber-800',
        'confirmed', 'packing', 'delivering', 'sent', 'processing' => 'bg-sky-100 text-sky-800',
        'delivered', 'completed', 'passed', 'resolved', 'active', 'paid' => 'bg-emerald-100 text-emerald-800',
        'cancelled', 'rejected', 'inactive', 'refunded' => 'bg-rose-100 text-rose-800',
        default => 'bg-stone-100 text-stone-700',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', $classes]) }}>{{ $status->label() }}</span>
```

`resources/views/components/flash.blade.php`:

```blade
@if (session('status'))
    <div role="status" class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
@endif
@if ($errors->has('action'))
    <div role="alert" class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $errors->first('action') }}</div>
@endif
```

`resources/views/components/field.blade.php`:

```blade
@props(['name', 'label', 'type' => 'text', 'value' => null])
<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-stone-700">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
        {{ $attributes->class(['block w-full min-h-11 rounded-lg border px-3 text-base', 'border-rose-500' => $errors->has($name), 'border-stone-300' => ! $errors->has($name)]) }}>
    @error($name)
        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
    @enderror
</div>
```

`resources/views/components/layouts/admin.blade.php`:

```blade
@props(['title' => 'Quản trị'])
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · FARM2HOME</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-stone-50 text-stone-900">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('admin.dashboard') }}" class="font-semibold text-emerald-800">FARM2HOME</a>
            <nav class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                @foreach ([
                    'admin.dashboard' => 'Tổng quan',
                    'admin.orders.index' => 'Đơn hàng',
                    'admin.supply.index' => 'Sản lượng',
                    'admin.forecasts.index' => 'Dự báo',
                    'admin.harvest-plans.index' => 'Kế hoạch thu hoạch',
                    'admin.batches.index' => 'Lô hàng',
                    'admin.feedbacks.index' => 'Phản hồi',
                    'admin.waste.index' => 'Rau dư',
                ] as $routeName => $label)
                    @if (Route::has($routeName))
                        <a href="{{ route($routeName) }}" @class(['py-1', 'font-semibold text-emerald-800' => request()->routeIs($routeName)])>{{ $label }}</a>
                    @else
                        <span class="py-1 text-stone-400">{{ $label }}</span>
                    @endif
                @endforeach
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="ml-auto flex items-center gap-3 text-sm">
                @csrf
                <span>{{ auth()->user()->name }}</span>
                <button class="min-h-11 rounded-lg border border-stone-300 px-3">Đăng xuất</button>
            </form>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-6">
        <h1 class="mb-4 text-xl font-semibold">{{ $title }}</h1>
        <x-flash />
        {{ $slot }}
    </main>
</body>
</html>
```

`resources/views/components/layouts/public.blade.php` và `farmer.blade.php` cùng khung `<head>` như trên, khác phần thân:
- `public`: header có logo chữ "FARM2HOME" (link `route('home')`), hai link "Đặt hàng" và "Tra cứu đơn" (chỉ hiện khi `Route::has(...)`), `<main class="mx-auto max-w-xl px-4 py-6">`, footer một dòng "Điều phối nông sản theo nhu cầu, giảm dư thừa."
- `farmer`: không có menu, chỉ có header "FARM2HOME · Nông hộ", `<main class="mx-auto max-w-xl px-4 py-4 text-lg">` (chữ lớn hơn cho nông hộ).

- [ ] **Step 7: Chuyển 2 view của Task 1.3 sang layout**

`resources/views/admin/dashboard.blade.php` thành:

```blade
<x-layouts.admin title="Tổng quan">
    <p class="text-stone-600">Các chỉ số KPI sẽ hiển thị ở đây sau Phase 6.</p>
</x-layouts.admin>
```

`resources/views/auth/login.blade.php` bọc trong `<x-layouts.public title="Đăng nhập">`, dùng `<x-field name="email" label="Email" type="email" />` và `<x-field name="password" label="Mật khẩu" type="password" />`.

- [ ] **Step 8: Chạy toàn bộ test, build, commit**

Run: `php artisan test`
Expected: PASS toàn bộ (gồm `LoginTest` vẫn xanh sau khi đổi layout).

Run: `npm run build`
Expected: thành công.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: shared layouts, form components, money/weight formatting, vietnamese validation messages"
```

---

## Kết quả Phase 1

- [ ] 17 enum, 18 model, quan hệ khớp dữ liệu mẫu.
- [ ] Đăng nhập được bằng `admin@farm2home.vn` / `password` và `operator@farm2home.vn` / `password`.
- [ ] `/admin` chặn khách; route `role:admin` chặn operator.
- [ ] Ba layout và các component dùng chung sẵn sàng cho Phase 2.

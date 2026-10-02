# Phase 8 — Tích điểm thành viên: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Khách tích điểm khi đơn giao xong và khi trả bao bì, xem số dư bằng số điện thoại, chọn quà trên form đặt hàng; người điều phối duyệt quà trên trang đơn; dashboard hiện điểm đã cộng, đã đổi.

**Architecture:** Một sổ giao dịch chỉ-thêm `point_transactions`; số dư là `SUM(points)`. Mọi thao tác ghi sổ đi qua `App\Services\LoyaltyService`; `OrderService` và `PackagingReturnController` chỉ gọi nó. Danh mục quà nằm trong `config/farm2home.php` và được đọc qua value object `App\Support\Reward`.

**Tech Stack:** Laravel 13 · PHP 8.3 · MySQL 8.4 · Blade + Tailwind 4 + Alpine.js · PHPUnit 12. Không thêm package.

**Spec:** [`docs/plans/phase-8-tich-diem-design.md`](phase-8-tich-diem-design.md) (quyết định D19–D27). Nền chung: [`docs/CLAUDE.md`](../CLAUDE.md), [`docs/PLAN.md`](../PLAN.md).

## Global Constraints

- Khách **không có tài khoản**; định danh bằng `customers.phone` (regex `^0\d{9}$`).
- **Con người xác nhận:** không nơi nào tự trừ điểm; chỉ `LoyaltyService::redeem()` do người điều phối bấm mới trừ.
- Cộng điểm: **+5** khi đơn sang `delivered` và `subtotal >= 40000`; **+5** khi ghi nhận trả bao bì (một lần mỗi đơn; không mã đơn thì một lần mỗi khách mỗi `return_date`).
- Quà: `small_bunch` 20 điểm (1 gói rau 0đ) · `free_shipping` 30 điểm (`shipping_fee = 0`) · `discount_20k` 50 điểm (`discount` +20.000, không vượt `subtotal`) · `small_combo` 100 điểm (3 gói rau 0đ).
- Mỗi đơn đổi tối đa một quà; chỉ khi đơn `pending` hoặc `confirmed`; số dư không bao giờ âm; hủy đơn hoàn điểm.
- `total = subtotal + shipping_fee − discount`. Tiền là số nguyên VND, hiển thị `12.000đ`. Thao tác tiền và điểm bọc trong DB transaction.
- **CẤM `php artisan migrate:fresh` trên DB `farm2home`.** Trên DB dev chỉ chạy `php artisan migrate`. PHPUnit dùng `farm2home_test`.
- Giao diện tiếng Việt; code, tên biến tiếng Anh; comment tiếng Việt được phép cho quy tắc nghiệp vụ. Chỉ dùng chữ "đổi quà", không quy điểm ra tiền mặt.
- Mobile-first: dùng tốt ở 360px, nút và ô chọn cao tối thiểu 44px (`min-h-11`).
- Sửa file có sẵn theo kiểu diff nhỏ, không viết lại cả file. Mọi cấu trúc điều khiển PHP có ngoặc nhọn; mọi hàm có kiểu trả về.
- **Môi trường lệnh:** trong Bash chạy `source .superpowers/env.sh` trước (đưa php/composer/mysql của Laragon vào PATH). Laragon phải đang Start.
- Trước mỗi commit: `vendor/bin/pint --dirty --format agent`.
- `http://farm2home.test` chạy thẳng trên thư mục làm việc và DB dev, nên DB dev phải được migrate ngay ở Task 8.1 (trước khi có code ghi vào cột mới).

## Review Focus

Năm tình huống spec ngầm định mà dễ bị bỏ sót; mỗi dòng đã có test trong task ghi ở cuối dòng.

1. Người điều phối bấm "Áp dụng quà" hai lần liên tiếp → chỉ trừ điểm một lần, lần hai bị từ chối. (Task 8.3)
2. Khách có đúng 20 điểm đổi được quà 20 điểm; 19 điểm thì không. (Task 8.3)
3. Bao bì của đơn A được trả kèm số điện thoại của người khác → điểm về người có số điện thoại đó, và đơn A không được cộng lần hai. (Task 8.2)
4. Hủy đơn đã đổi quà → điểm hoàn lại đúng một lần và dùng được cho đơn kế tiếp. (Task 8.3)
5. Đơn đúng 40.000đ được điểm; đơn 39.000đ thì không. (Task 8.2)

## Cấu trúc file

```
app/
  Enums/PointReason.php                         (mới) 4 lý do + label()
  Models/PointTransaction.php                   (mới)
  Support/Reward.php                            (mới) đọc danh mục quà từ config
  Services/LoyaltyService.php                   (mới) nơi duy nhất ghi point_transactions
  Console/Commands/LoyaltyBackfill.php          (mới) loyalty:backfill
  Http/Controllers/Admin/LoyaltyController.php  (mới) index, show
  Services/OrderService.php                     (sửa) gọi LoyaltyService khi delivered / cancelled; lưu requested_reward
  Services/KpiService.php                       (sửa) thêm loyalty()
  Http/Controllers/Admin/OrderController.php    (sửa) show() thêm dữ liệu điểm; redeem()
  Http/Controllers/Admin/PackagingReturnController.php (sửa) cộng điểm
  Http/Controllers/Admin/DashboardController.php (sửa) truyền loyalty
  Http/Controllers/Public/OrderController.php   (sửa) truyền rewards
  Http/Controllers/Public/OrderLookupController.php (sửa) truyền số dư
  Http/Requests/StoreOrderRequest.php           (sửa) rule requested_reward
  Models/{Customer,Order,OrderItem}.php         (sửa) quan hệ, fillable, cast
  Console/Commands/DemoShiftDates.php           (sửa) thêm bảng point_transactions
config/farm2home.php                            (sửa) khối loyalty
database/
  migrations/*_add_loyalty_points.php           (mới)
  factories/PointTransactionFactory.php         (mới)
lang/vi/validation.php                          (sửa) 2 tên thuộc tính
resources/views/
  admin/loyalty/{index,show}.blade.php          (mới)
  admin/orders/show.blade.php                   (sửa) khối Điểm thành viên, nhãn quà
  admin/dashboard.blade.php                     (sửa) 2 ô số
  components/layouts/admin.blade.php            (sửa) mục menu
  public/orders/{create,thanks,lookup}.blade.php (sửa)
routes/web.php                                  (sửa) 3 route
tests/Feature/Loyalty/
  LoyaltyFoundationTest.php  LoyaltyEarningTest.php  LoyaltyRedeemTest.php
  LoyaltyPublicPagesTest.php  AdminRedeemTest.php  LoyaltyAdminPagesTest.php  LoyaltyBackfillTest.php
tests/Unit/Enums/EnumLabelsTest.php, tests/Feature/DemoShiftDatesTest.php, tests/Feature/SmokeTest.php (sửa)
```

---

### Task 8.1: Schema, enum, model, cấu hình quà

**Files:**
- Create: `database/migrations/*_add_loyalty_points.php`, `app/Enums/PointReason.php`, `app/Models/PointTransaction.php`, `database/factories/PointTransactionFactory.php`, `app/Support/Reward.php`
- Modify: `config/farm2home.php`, `app/Models/Customer.php`, `app/Models/Order.php`, `app/Models/OrderItem.php`, `tests/Unit/Enums/EnumLabelsTest.php`
- Test: `tests/Feature/Loyalty/LoyaltyFoundationTest.php`

**Interfaces:**
- Consumes: bảng `customers`, `orders`, `order_items`, `packaging_returns`, `users` có sẵn.
- Produces:
  - `App\Enums\PointReason` — case `OrderDelivered='order_delivered'`, `PackagingReturned='packaging_returned'`, `Redeemed='redeemed'`, `Refunded='refunded'`; `label(): string`.
  - `App\Models\PointTransaction` — fillable `customer_id, points, reason, order_id, packaging_return_id, reward, created_by`; quan hệ `customer()`, `order()`, `packagingReturn()`, `creator()`.
  - `Customer::pointTransactions(): HasMany`.
  - `App\Support\Reward` — thuộc tính `key, label, points, type, packs, amount`; `Reward::all(): list<Reward>`, `Reward::find(?string): ?Reward`, `Reward::keys(): list<string>`, `isGift(): bool`; hằng `TYPE_GIFT='gift'`, `TYPE_FREE_SHIPPING='free_shipping'`, `TYPE_DISCOUNT='discount'`.
  - Config `farm2home.loyalty.order_points` (5), `.packaging_points` (5), `.min_order_subtotal` (40000), `.rewards`.
  - Cột `orders.requested_reward`, `order_items.is_gift`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Loyalty/LoyaltyFoundationTest.php`:

```php
<?php

namespace Tests\Feature\Loyalty;

use App\Enums\PointReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Support\Reward;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoyaltyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_the_ledger_and_the_two_columns(): void
    {
        $this->assertTrue(Schema::hasTable('point_transactions'));
        $this->assertTrue(Schema::hasColumn('orders', 'requested_reward'));
        $this->assertTrue(Schema::hasColumn('order_items', 'is_gift'));
    }

    public function test_an_order_can_have_only_one_row_per_reason(): void
    {
        $order = Order::factory()->create();
        $attributes = ['customer_id' => $order->customer_id, 'order_id' => $order->id, 'reason' => PointReason::OrderDelivered];
        PointTransaction::factory()->create($attributes);
        PointTransaction::factory()->create(['reason' => PointReason::PackagingReturned] + $attributes);

        $this->expectException(UniqueConstraintViolationException::class);
        PointTransaction::factory()->create($attributes);
    }

    public function test_rows_without_an_order_are_not_limited_by_the_unique_key(): void
    {
        $customer = Customer::factory()->create();
        PointTransaction::factory()->count(2)->create(['customer_id' => $customer->id, 'reason' => PointReason::PackagingReturned]);

        $this->assertSame(10, (int) $customer->pointTransactions()->sum('points'));
    }

    public function test_model_casts_and_relations(): void
    {
        $order = Order::factory()->create();
        $row = PointTransaction::factory()->create(['customer_id' => $order->customer_id, 'order_id' => $order->id, 'points' => '-20', 'reason' => 'redeemed', 'reward' => 'small_bunch'])->refresh();

        $this->assertSame(-20, $row->points);
        $this->assertSame(PointReason::Redeemed, $row->reason);
        $this->assertTrue($row->customer->is($order->customer));
        $this->assertTrue($row->order->is($order));
        $this->assertNull($row->packagingReturn);
        $this->assertNull($row->creator);
    }

    public function test_reward_catalog_matches_the_agreed_tiers(): void
    {
        $this->assertSame(
            ['small_bunch' => 20, 'free_shipping' => 30, 'discount_20k' => 50, 'small_combo' => 100],
            collect(Reward::all())->mapWithKeys(fn (Reward $reward) => [$reward->key => $reward->points])->all(),
        );
        $this->assertSame(['small_bunch', 'free_shipping', 'discount_20k', 'small_combo'], Reward::keys());
        $this->assertSame([1, 3], [Reward::find('small_bunch')->packs, Reward::find('small_combo')->packs]);
        $this->assertSame(20000, Reward::find('discount_20k')->amount);
        $this->assertSame('Miễn phí 1 lần giao hàng', Reward::find('free_shipping')->label);
        $this->assertTrue(Reward::find('small_combo')->isGift());
        $this->assertFalse(Reward::find('free_shipping')->isGift());
        $this->assertNull(Reward::find('gold_bar'));
        $this->assertNull(Reward::find(null));
    }

    public function test_earning_rules_match_the_agreed_numbers(): void
    {
        $this->assertSame(
            [5, 5, 40000],
            [config('farm2home.loyalty.order_points'), config('farm2home.loyalty.packaging_points'), config('farm2home.loyalty.min_order_subtotal')],
        );
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --compact --filter=LoyaltyFoundationTest`
Expected: FAIL — `Class "App\Enums\PointReason" not found`.

- [ ] **Step 3: Tạo migration**

Run: `php artisan make:migration add_loyalty_points --no-interaction`

Thay toàn bộ nội dung file vừa tạo (`database/migrations/<timestamp>_add_loyalty_points.php`) bằng:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sổ điểm thành viên (D19) + yêu cầu đổi quà trên đơn (D24) + đánh dấu gói rau tặng (D23).
     */
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->integer('points');
            $table->enum('reason', ['order_delivered', 'packaging_returned', 'redeemed', 'refunded']);
            $table->foreignId('order_id')->nullable()->constrained('orders');
            $table->foreignId('packaging_return_id')->nullable()->constrained('packaging_returns');
            $table->string('reward', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // D20: mỗi đơn tối đa một dòng cho mỗi lý do.
            $table->unique(['order_id', 'reason']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('requested_reward', 40)->nullable()->after('note');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_gift')->default(false)->after('fulfilled_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('is_gift');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('requested_reward');
        });

        Schema::dropIfExists('point_transactions');
    }
};
```

- [ ] **Step 4: Tạo enum**

`app/Enums/PointReason.php`:

```php
<?php

namespace App\Enums;

enum PointReason: string
{
    case OrderDelivered = 'order_delivered';
    case PackagingReturned = 'packaging_returned';
    case Redeemed = 'redeemed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::OrderDelivered => 'Đơn đã giao',
            self::PackagingReturned => 'Trả bao bì',
            self::Redeemed => 'Đổi quà',
            self::Refunded => 'Hoàn điểm (hủy đơn)',
        };
    }
}
```

- [ ] **Step 5: Tạo model và factory**

`app/Models/PointTransaction.php`:

```php
<?php

namespace App\Models;

use App\Enums\PointReason;
use Database\Factories\PointTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'points', 'reason', 'order_id', 'packaging_return_id', 'reward', 'created_by'])]
class PointTransaction extends Model
{
    /** @use HasFactory<PointTransactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'reason' => PointReason::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function packagingReturn(): BelongsTo
    {
        return $this->belongsTo(PackagingReturn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

`database/factories/PointTransactionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PointTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointTransaction>
 */
class PointTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'points' => 5,
            'reason' => 'order_delivered',
        ];
    }
}
```

- [ ] **Step 6: Thêm khối cấu hình**

Trong `config/farm2home.php`, thêm sau khối `'batch' => [...]`:

```php
    'loyalty' => [
        // +điểm khi đơn giao xong và khi khách trả bao bì.
        'order_points' => 5,
        'packaging_points' => 5,
        // Đơn có tiền hàng dưới ngưỡng này không được cộng điểm đơn (D27).
        'min_order_subtotal' => 40000,
        'rewards' => [
            'small_bunch' => ['label' => 'Tặng bó rau nhỏ', 'points' => 20, 'type' => 'gift', 'packs' => 1],
            'free_shipping' => ['label' => 'Miễn phí 1 lần giao hàng', 'points' => 30, 'type' => 'free_shipping'],
            'discount_20k' => ['label' => 'Giảm 20.000đ cho đơn', 'points' => 50, 'type' => 'discount', 'amount' => 20000],
            // D26: combo = 3 gói rau do người điều phối chọn.
            'small_combo' => ['label' => 'Tặng combo rau nhỏ', 'points' => 100, 'type' => 'gift', 'packs' => 3],
        ],
    ],
```

- [ ] **Step 7: Tạo `Reward`**

`app/Support/Reward.php`:

```php
<?php

namespace App\Support;

/**
 * Một quà trong danh mục đổi điểm, đọc từ config('farm2home.loyalty.rewards').
 */
final readonly class Reward
{
    public const TYPE_GIFT = 'gift';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    public const TYPE_DISCOUNT = 'discount';

    public function __construct(
        public string $key,
        public string $label,
        public int $points,
        public string $type,
        public int $packs = 0,
        public int $amount = 0,
    ) {}

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        $rewards = [];

        foreach (config('farm2home.loyalty.rewards') as $key => $reward) {
            $rewards[] = new self($key, $reward['label'], $reward['points'], $reward['type'], $reward['packs'] ?? 0, $reward['amount'] ?? 0);
        }

        return $rewards;
    }

    public static function find(?string $key): ?self
    {
        foreach (self::all() as $reward) {
            if ($reward->key === $key) {
                return $reward;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(config('farm2home.loyalty.rewards'));
    }

    public function isGift(): bool
    {
        return $this->type === self::TYPE_GIFT;
    }
}
```

- [ ] **Step 8: Sửa ba model**

`app/Models/Customer.php` — thêm sau hàm `feedbacks()`:

```php
    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }
```

`app/Models/Order.php` — trong `#[Fillable([...])]`, đổi dòng cuối:

```php
    'confirmed_at', 'delivered_at', 'cancel_reason', 'note', 'requested_reward',
```

`app/Models/OrderItem.php` — đổi dòng `#[Fillable(...)]` và thêm một cast:

```php
#[Fillable(['order_id', 'product_id', 'basket_id', 'quantity', 'weight_kg', 'unit_price', 'line_total', 'fulfilled_quantity', 'is_gift'])]
```

```php
            'fulfilled_quantity' => 'integer',
            'is_gift' => 'boolean',
```

- [ ] **Step 9: Cập nhật test đếm enum**

`tests/Unit/Enums/EnumLabelsTest.php` — thêm `use App\Enums\PointReason;`, rồi:

```php
    public function test_there_are_18_enums_and_every_case_has_a_label(): void
    {
        $files = glob(dirname(__DIR__, 3).'/app/Enums/*.php');
        $this->assertCount(18, $files);
```

và thêm một dòng cuối hàm `test_values_match_the_mysql_enums`:

```php
        $this->assertSame(['order_delivered', 'packaging_returned', 'redeemed', 'refunded'], array_column(PointReason::cases(), 'value'));
```

- [ ] **Step 10: Chạy test, xác nhận pass**

Run: `php artisan test --compact --filter="LoyaltyFoundationTest|EnumLabelsTest|SchemaTest"`
Expected: PASS (6 + 2 + 3 test).

- [ ] **Step 11: Migrate DB dev (không phải `migrate:fresh`)**

Run: `php artisan migrate:status`
Expected: `2026_10_02_000000_create_farm2home_schema` ở trạng thái Ran, `..._add_loyalty_points` ở trạng thái Pending. Nếu baseline **không** Ran thì dừng và báo lại, không chạy bước dưới.

Run: `php artisan migrate --no-interaction`
Expected: `..._add_loyalty_points ... DONE`.

Run: `mysql -uroot farm2home -N -e "SELECT COUNT(*) FROM orders; SHOW COLUMNS FROM orders LIKE 'requested_reward'; SELECT COUNT(*) FROM point_transactions;"`
Expected: số đơn không đổi (từ 155 trở lên), có cột `requested_reward`, `point_transactions` có 0 dòng.

- [ ] **Step 12: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations database/factories/PointTransactionFactory.php app/Enums/PointReason.php app/Models app/Support/Reward.php config/farm2home.php tests/Feature/Loyalty/LoyaltyFoundationTest.php tests/Unit/Enums/EnumLabelsTest.php
git commit -m "feat: point ledger schema, reward catalog and model for loyalty points"
```

---

### Task 8.2: Cộng điểm khi đơn giao xong và khi trả bao bì

**Files:**
- Create: `app/Services/LoyaltyService.php`
- Modify: `app/Services/OrderService.php`, `app/Http/Controllers/Admin/PackagingReturnController.php`
- Test: `tests/Feature/Loyalty/LoyaltyEarningTest.php`

**Interfaces:**
- Consumes: `PointTransaction`, `PointReason`, config `farm2home.loyalty.*` (Task 8.1); `OrderService::place(array, OrderChannel): Order`, `OrderService::transition(Order, OrderStatus, ?string): Order`.
- Produces:
  - `LoyaltyService::balance(Customer $customer): int`
  - `LoyaltyService::awardForDeliveredOrder(Order $order): ?PointTransaction` — `null` khi không đủ điều kiện hoặc đã cộng.
  - `LoyaltyService::awardForPackagingReturn(PackagingReturn $return): ?PointTransaction`
  - `OrderService` nhận `LoyaltyService` qua constructor (luôn lấy `OrderService` bằng container: `app(OrderService::class)` hoặc type-hint).

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Loyalty/LoyaltyEarningTest.php`:

```php
<?php

namespace Tests\Feature\Loyalty;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PointReason;
use App\Models\Order;
use App\Models\PackagingReturn;
use App\Models\PointTransaction;
use App\Services\LoyaltyService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LoyaltyEarningTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private OrderService $orders;

    private LoyaltyService $loyalty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 20:00:00');
        $this->buildCatalog();
        $this->orders = app(OrderService::class);
        $this->loyalty = app(LoyaltyService::class);
    }

    /**
     * Không truyền $items → đơn Giỏ Mini 45.000đ.
     *
     * @param  list<array{product_id: int, quantity: int}>|null  $items
     */
    private function place(?array $items = null, string $phone = '0988000001'): Order
    {
        return $this->orders->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => $phone, 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-04', 'time_slot' => '17:00-19:00',
            'order_type' => $items === null ? 'basket' : 'single',
            'basket_id' => $items === null ? $this->basket->id : null,
            'items' => $items,
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
    }

    private function deliver(Order $order): Order
    {
        foreach ([OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Delivering, OrderStatus::Delivered] as $status) {
            $this->orders->transition($order, $status);
        }

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function returnPackaging(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->operator)->post('/admin/packaging-returns', array_merge([
            'return_date' => '2026-10-03', 'quantity_returned' => 2, 'quantity_reused' => 1,
        ], $overrides));
    }

    public function test_a_delivered_order_earns_five_points_exactly_once(): void
    {
        $order = $this->deliver($this->place());

        $row = PointTransaction::sole();
        $this->assertSame(
            [5, PointReason::OrderDelivered, $order->id, $order->customer_id],
            [$row->points, $row->reason, $row->order_id, $row->customer_id],
        );
        $this->assertTrue($row->created_at->equalTo($order->delivered_at));
        $this->assertSame(5, $this->loyalty->balance($order->customer));

        $this->assertNull($this->loyalty->awardForDeliveredOrder($order));
        $this->assertSame(1, PointTransaction::count());
    }

    public function test_the_minimum_subtotal_is_inclusive(): void
    {
        $atThreshold = $this->deliver($this->place([['product_id' => $this->hl->id, 'quantity' => 5]]));
        $below = $this->deliver($this->place([
            ['product_id' => $this->rm->id, 'quantity' => 2],
            ['product_id' => $this->cn->id, 'quantity' => 1],
        ], '0988000002'));

        $this->assertSame([40000, 39000], [$atThreshold->subtotal, $below->subtotal]);
        $this->assertSame(5, $this->loyalty->balance($atThreshold->customer));
        $this->assertSame(0, $this->loyalty->balance($below->customer));
    }

    public function test_an_order_that_is_not_delivered_earns_nothing(): void
    {
        $order = $this->place();
        $this->assertNull($this->loyalty->awardForDeliveredOrder($order));

        $this->orders->transition($order, OrderStatus::Cancelled, 'Khách đổi lịch');
        $this->assertNull($this->loyalty->awardForDeliveredOrder($order->refresh()));
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_returning_packaging_for_an_order_earns_five_points_once_per_order(): void
    {
        $order = $this->place();

        $this->returnPackaging(['order_code' => $order->code])
            ->assertSessionHas('status', 'Đã ghi nhận bao bì thu hồi. +5 điểm cho Nguyễn Thị Lan.');
        $this->returnPackaging(['order_code' => $order->code])
            ->assertSessionHas('status', 'Đã ghi nhận bao bì thu hồi.');

        $row = PointTransaction::sole();
        $this->assertSame(
            [5, PointReason::PackagingReturned, $order->id, PackagingReturn::orderBy('id')->firstOrFail()->id, $order->customer_id],
            [$row->points, $row->reason, $row->order_id, $row->packaging_return_id, $row->customer_id],
        );
        $this->assertSame(2, PackagingReturn::count());
    }

    public function test_delivered_and_returned_together_is_ten_points(): void
    {
        $order = $this->deliver($this->place());
        $this->returnPackaging(['order_code' => $order->code]);

        $this->assertSame(10, $this->loyalty->balance($order->customer));
    }

    public function test_return_without_an_order_code_earns_once_per_customer_per_day(): void
    {
        $customer = $this->place()->customer;

        $this->returnPackaging(['phone' => '0988000001']);
        $this->returnPackaging(['phone' => '0988000001']);
        $this->assertSame(5, $this->loyalty->balance($customer));

        $this->returnPackaging(['phone' => '0988000001', 'return_date' => '2026-10-02']);
        $this->assertSame(10, $this->loyalty->balance($customer));
    }

    public function test_return_without_a_known_customer_earns_nothing(): void
    {
        $this->returnPackaging()->assertSessionHas('status', 'Đã ghi nhận bao bì thu hồi.');
        $this->returnPackaging(['phone' => '0977000000'])->assertSessionHas('status', 'Đã ghi nhận bao bì thu hồi.');

        $this->assertSame(2, PackagingReturn::count());
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_points_go_to_the_phone_on_the_return_and_still_only_once_per_order(): void
    {
        $order = $this->place();
        $neighbour = $this->place(null, '0988000002')->customer;

        $this->returnPackaging(['order_code' => $order->code, 'phone' => '0988000002']);
        $this->returnPackaging(['order_code' => $order->code, 'phone' => '0988000001']);

        $this->assertSame(5, $this->loyalty->balance($neighbour));
        $this->assertSame(0, $this->loyalty->balance($order->customer));
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --compact --filter=LoyaltyEarningTest`
Expected: FAIL — `Class "App\Services\LoyaltyService" not found` (hoặc `Target class [App\Services\LoyaltyService] does not exist`).

- [ ] **Step 3: Tạo `LoyaltyService` (phần cộng điểm)**

`app/Services/LoyaltyService.php`:

```php
<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PointReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PackagingReturn;
use App\Models\PointTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Nơi duy nhất ghi vào point_transactions. Số dư = tổng các dòng của khách (D19).
 */
class LoyaltyService
{
    public function balance(Customer $customer): int
    {
        return (int) $customer->pointTransactions()->sum('points');
    }

    /**
     * Cộng điểm một lần cho đơn đã giao có tiền hàng đạt ngưỡng (D27).
     */
    public function awardForDeliveredOrder(Order $order): ?PointTransaction
    {
        if ($order->status !== OrderStatus::Delivered || $order->subtotal < (int) config('farm2home.loyalty.min_order_subtotal')) {
            return null;
        }

        if ($this->orderHas($order->id, PointReason::OrderDelivered)) {
            return null;
        }

        return $this->recordOnce(
            $order->customer_id,
            (int) config('farm2home.loyalty.order_points'),
            PointReason::OrderDelivered,
            ['order_id' => $order->id],
            $order->delivered_at,
        );
    }

    /**
     * Cộng điểm cho khách ghi trên lượt trả bao bì: một lần mỗi đơn; không có mã đơn thì một lần mỗi khách mỗi ngày.
     */
    public function awardForPackagingReturn(PackagingReturn $return): ?PointTransaction
    {
        if ($return->customer_id === null || $return->quantity_returned < 1) {
            return null;
        }

        return DB::transaction(function () use ($return) {
            // Khóa dòng khách để hai lần ghi nhận cùng lúc không cộng trùng.
            Customer::whereKey($return->customer_id)->lockForUpdate()->first();

            if ($this->packagingAlreadyCounted($return)) {
                return null;
            }

            return $this->recordOnce(
                $return->customer_id,
                (int) config('farm2home.loyalty.packaging_points'),
                PointReason::PackagingReturned,
                ['order_id' => $return->order_id, 'packaging_return_id' => $return->id],
                $return->created_at,
            );
        });
    }

    private function packagingAlreadyCounted(PackagingReturn $return): bool
    {
        if ($return->order_id !== null) {
            return $this->orderHas($return->order_id, PointReason::PackagingReturned);
        }

        return PointTransaction::where('customer_id', $return->customer_id)
            ->where('reason', PointReason::PackagingReturned)
            ->whereNull('order_id')
            ->whereHas('packagingReturn', fn ($query) => $query->whereDate('return_date', $return->return_date))
            ->exists();
    }

    private function orderHas(int $orderId, PointReason $reason): bool
    {
        return PointTransaction::where('order_id', $orderId)->where('reason', $reason)->exists();
    }

    /**
     * Như record(), nhưng trả null khi khóa unique (order_id, reason) báo đã có dòng (D20).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function recordOnce(int $customerId, int $points, PointReason $reason, array $attributes, ?CarbonInterface $at = null): ?PointTransaction
    {
        try {
            return $this->record($customerId, $points, $reason, $attributes, $at);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(int $customerId, int $points, PointReason $reason, array $attributes = [], ?CarbonInterface $at = null): PointTransaction
    {
        $transaction = new PointTransaction(['customer_id' => $customerId, 'points' => $points, 'reason' => $reason] + $attributes);
        // Thời điểm của dòng điểm = thời điểm của sự kiện gốc, để KPI theo kỳ vẫn đúng khi backfill.
        $transaction->created_at = $at ?? now();
        $transaction->updated_at = $transaction->created_at;
        $transaction->save();

        return $transaction;
    }
}
```

- [ ] **Step 4: Nối vào `OrderService`**

`app/Services/OrderService.php` — thêm constructor ngay sau dòng `class OrderService` + `{`:

```php
    public function __construct(private readonly LoyaltyService $loyalty) {}

```

và trong `transition()`, đổi đoạn cuối closure:

```php
            $order->save();

            if ($to === OrderStatus::Delivered) {
                $this->loyalty->awardForDeliveredOrder($order);
            }

            return $order;
```

Run: `grep -rn "new OrderService" app tests`
Expected: không có kết quả (mọi nơi đều lấy qua container).

- [ ] **Step 5: Nối vào `PackagingReturnController`**

`app/Http/Controllers/Admin/PackagingReturnController.php` — thêm `use App\Services\LoyaltyService;`, đổi chữ ký hàm và phần cuối `store()`:

```php
    public function store(Request $request, LoyaltyService $loyalty): RedirectResponse
```

```php
        $return = PackagingReturn::create([
            'return_date' => $data['return_date'],
            'customer_id' => $customer?->id ?? $order?->customer_id,
            'order_id' => $order?->id,
            'quantity_returned' => $data['quantity_returned'],
            'quantity_reused' => $data['quantity_reused'],
            'note' => $data['note'] ?? null,
        ]);

        $message = 'Đã ghi nhận bao bì thu hồi.';
        $awarded = $loyalty->awardForPackagingReturn($return);

        if ($awarded !== null) {
            $message .= " +{$awarded->points} điểm cho {$return->customer->name}.";
        }

        return back()->with('status', $message);
```

- [ ] **Step 6: Chạy test, xác nhận pass**

Run: `php artisan test --compact --filter="LoyaltyEarningTest|WasteAndPackagingTest|OrderServiceTest|SubscriptionServiceTest"`
Expected: PASS (8 test mới, các test cũ không đổi).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/LoyaltyService.php app/Services/OrderService.php app/Http/Controllers/Admin/PackagingReturnController.php tests/Feature/Loyalty/LoyaltyEarningTest.php
git commit -m "feat: earn points on delivered orders and packaging returns"
```

---

### Task 8.3: Đổi quà và hoàn điểm khi hủy đơn

**Files:**
- Modify: `app/Services/LoyaltyService.php`, `app/Services/OrderService.php`
- Test: `tests/Feature/Loyalty/LoyaltyRedeemTest.php`

**Interfaces:**
- Consumes: `Reward` (8.1); `LoyaltyService::balance`, `record`, `recordOnce`, `orderHas` (8.2).
- Produces:
  - `LoyaltyService::canRedeemOn(Order $order): bool` — đơn đang `pending` hoặc `confirmed`.
  - `LoyaltyService::redemptionFor(Order $order): ?PointTransaction` — dòng `redeemed` của đơn.
  - `LoyaltyService::redeem(Order $order, Reward $reward, array $productIds, User $by): PointTransaction` — ném `DomainException` (thông báo tiếng Việt) khi bị từ chối; `$productIds` là danh sách id sản phẩm, mỗi phần tử một gói, phần tử rỗng bị bỏ qua.
  - `LoyaltyService::refundForCancelledOrder(Order $order): ?PointTransaction`
  - Thông báo từ chối (test ở Task 8.5 dựa vào đúng chuỗi): `Khách chưa đủ điểm: có {số dư}, cần {điểm quà}.`

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Loyalty/LoyaltyRedeemTest.php`:

```php
<?php

namespace Tests\Feature\Loyalty;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PointReason;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Services\LoyaltyService;
use App\Services\OrderService;
use App\Support\Reward;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LoyaltyRedeemTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private OrderService $orders;

    private LoyaltyService $loyalty;

    /** 2 gói rau muống: tiền hàng 24.000đ + phí giao 10.000đ. */
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->orders = app(OrderService::class);
        $this->loyalty = app(LoyaltyService::class);
        $this->order = $this->place();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function place(array $overrides = []): Order
    {
        return $this->orders->place(array_merge([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], $overrides), OrderChannel::Website);
    }

    private function givePoints(int $points): void
    {
        PointTransaction::factory()->create(['customer_id' => $this->order->customer_id, 'points' => $points]);
    }

    /**
     * @param  list<int>  $productIds
     */
    private function redeem(string $key, array $productIds = [], ?Order $order = null): PointTransaction
    {
        return $this->loyalty->redeem($order ?? $this->order, Reward::find($key), $productIds, $this->operator);
    }

    private function balance(): int
    {
        return $this->loyalty->balance($this->order->customer);
    }

    private function assertRefused(string $needle, callable $attempt): void
    {
        try {
            $attempt();
        } catch (DomainException $e) {
            $this->assertStringContainsString($needle, $e->getMessage());

            return;
        }

        $this->fail("Expected a refusal containing \"{$needle}\".");
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function money(Order $order): array
    {
        $order->refresh();

        return [$order->subtotal, $order->shipping_fee, $order->discount, $order->total];
    }

    public function test_small_bunch_adds_one_free_pack_and_costs_twenty_points(): void
    {
        $this->givePoints(100);

        $row = $this->redeem('small_bunch', [$this->cn->id]);

        $this->assertSame(
            [-20, PointReason::Redeemed, 'small_bunch', $this->order->id, $this->operator->id],
            [$row->points, $row->reason, $row->reward, $row->order_id, $row->created_by],
        );
        $this->assertSame(80, $this->balance());

        $gift = $this->order->items()->where('is_gift', true)->sole();
        $this->assertSame(
            [$this->cn->id, 1, '0.500', 0, 0, null],
            [$gift->product_id, $gift->quantity, $gift->weight_kg, $gift->unit_price, $gift->line_total, $gift->basket_id],
        );
        $this->assertSame(1, $this->order->items()->where('is_gift', false)->count());
        $this->assertSame([24000, 10000, 0, 34000], $this->money($this->order));
        $this->assertSame('small_bunch', $this->loyalty->redemptionFor($this->order)->reward);
    }

    public function test_small_combo_takes_exactly_three_packs_and_merges_repeated_products(): void
    {
        $this->givePoints(100);

        $this->redeem('small_combo', [$this->rm->id, $this->hl->id, $this->rm->id]);

        $gifts = $this->order->items()->where('is_gift', true)->orderBy('product_id')->get();
        $this->assertSame(
            [[$this->rm->id, 2, '1.000'], [$this->hl->id, 1, '0.200']],
            $gifts->map(fn ($item) => [$item->product_id, $item->quantity, $item->weight_kg])->all(),
        );
        $this->assertSame(0, $this->balance());
    }

    public function test_gift_rewards_refuse_a_wrong_pack_count_or_a_product_not_on_sale(): void
    {
        $this->givePoints(100);
        $this->hl->forceFill(['is_active' => false])->save();

        $this->assertRefused('đúng 1 gói', fn () => $this->redeem('small_bunch', []));
        $this->assertRefused('đúng 1 gói', fn () => $this->redeem('small_bunch', [$this->rm->id, $this->cn->id]));
        $this->assertRefused('đúng 3 gói', fn () => $this->redeem('small_combo', [$this->rm->id, $this->cn->id]));
        $this->assertRefused('không còn bán', fn () => $this->redeem('small_bunch', [$this->hl->id]));
        $this->assertRefused('không còn bán', fn () => $this->redeem('small_bunch', [999999]));

        $this->assertSame(100, $this->balance());
        $this->assertSame(1, $this->order->items()->count());
    }

    public function test_free_shipping_zeroes_the_fee(): void
    {
        $this->givePoints(30);

        $this->redeem('free_shipping');

        $this->assertSame([24000, 0, 0, 24000], $this->money($this->order));
        $this->assertSame(0, $this->balance());
    }

    public function test_free_shipping_is_refused_when_the_order_has_no_fee(): void
    {
        $pickup = $this->place(['delivery_zone_id' => $this->pickupZone->id, 'address' => null, 'delivery_date' => '2026-10-04']);
        $this->givePoints(30);

        $this->assertRefused('không có phí giao', fn () => $this->redeem('free_shipping', [], $pickup));

        $this->assertSame(30, $this->balance());
    }

    public function test_discount_takes_twenty_thousand_off(): void
    {
        $this->givePoints(60);

        $this->redeem('discount_20k');

        $this->assertSame([24000, 10000, 20000, 14000], $this->money($this->order));
        $this->assertSame(10, $this->balance());
    }

    public function test_discount_is_capped_at_the_subtotal_but_still_costs_fifty_points(): void
    {
        $small = $this->place(['items' => [['product_id' => $this->rm->id, 'quantity' => 1]], 'delivery_date' => '2026-10-04']);
        $this->givePoints(50);

        $this->redeem('discount_20k', [], $small);

        $this->assertSame([12000, 10000, 12000, 10000], $this->money($small));
        $this->assertSame(0, $this->balance());
    }

    public function test_exactly_enough_points_is_enough_and_one_short_is_not(): void
    {
        $this->givePoints(19);
        $this->assertRefused('có 19, cần 20', fn () => $this->redeem('small_bunch', [$this->rm->id]));
        $this->assertSame(0, PointTransaction::where('reason', PointReason::Redeemed)->count());
        $this->assertSame(1, $this->order->items()->count());

        $this->givePoints(1);
        $this->redeem('small_bunch', [$this->rm->id]);
        $this->assertSame(0, $this->balance());
    }

    public function test_a_second_click_on_the_same_order_does_not_spend_points_twice(): void
    {
        $this->givePoints(100);
        $this->redeem('free_shipping');

        $this->assertRefused('đã đổi quà', fn () => $this->redeem('free_shipping'));
        $this->assertRefused('đã đổi quà', fn () => $this->redeem('discount_20k'));

        $this->assertSame(70, $this->balance());
        $this->assertSame([24000, 0, 0, 24000], $this->money($this->order));
    }

    public function test_points_cannot_be_spent_past_zero_across_orders(): void
    {
        $other = $this->place(['delivery_date' => '2026-10-04']);
        $this->givePoints(100);
        $this->redeem('small_combo', [$this->rm->id, $this->rm->id, $this->rm->id]);

        $this->assertRefused('có 0, cần 20', fn () => $this->redeem('small_bunch', [$this->rm->id], $other));
    }

    public function test_rewards_can_only_be_applied_before_packing(): void
    {
        $this->givePoints(100);
        $this->orders->transition($this->order, OrderStatus::Confirmed);
        $this->orders->transition($this->order, OrderStatus::Packing);
        $this->assertFalse($this->loyalty->canRedeemOn($this->order));
        $this->assertRefused('chờ xác nhận hoặc đã xác nhận', fn () => $this->redeem('free_shipping'));

        $confirmed = $this->place(['delivery_date' => '2026-10-04']);
        $this->orders->transition($confirmed, OrderStatus::Confirmed);
        $this->redeem('free_shipping', [], $confirmed);

        $this->assertSame(70, $this->balance());
    }

    public function test_cancelling_refunds_the_points_once_and_they_work_on_the_next_order(): void
    {
        $this->givePoints(30);
        $this->redeem('free_shipping');

        $this->orders->transition($this->order, OrderStatus::Cancelled, 'Khách đổi lịch');

        $refund = PointTransaction::where('reason', PointReason::Refunded)->sole();
        $this->assertSame([30, $this->order->id, 'free_shipping'], [$refund->points, $refund->order_id, $refund->reward]);
        $this->assertSame(30, $this->balance());
        $this->assertNull($this->loyalty->refundForCancelledOrder($this->order));

        $next = $this->place(['delivery_date' => '2026-10-04']);
        $this->redeem('free_shipping', [], $next);
        $this->assertSame(0, $this->balance());
    }

    public function test_cancelling_an_order_without_a_reward_writes_nothing(): void
    {
        $this->orders->transition($this->order, OrderStatus::Cancelled, 'Khách đổi lịch');

        $this->assertSame(0, PointTransaction::count());
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --compact --filter=LoyaltyRedeemTest`
Expected: FAIL — `Call to undefined method App\Services\LoyaltyService::redeem()`.

- [ ] **Step 3: Thêm phần đổi quà vào `LoyaltyService`**

`app/Services/LoyaltyService.php` — thêm các `use`:

```php
use App\Models\Product;
use App\Models\User;
use App\Support\Reward;
use DomainException;
```

Thêm các hàm sau ngay trước `private function packagingAlreadyCounted`:

```php
    /**
     * Chỉ đổi quà trước khi đóng gói và phân lô.
     */
    public function canRedeemOn(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Pending, OrderStatus::Confirmed], true);
    }

    public function redemptionFor(Order $order): ?PointTransaction
    {
        return PointTransaction::where('order_id', $order->id)->where('reason', PointReason::Redeemed)->first();
    }

    /**
     * Người điều phối duyệt một quà cho đơn: trừ điểm và sửa đơn trong cùng một transaction.
     *
     * @param  array<int, int|string|null>  $productIds  mỗi phần tử là một gói rau tặng; phần tử rỗng bị bỏ qua
     *
     * @throws DomainException khi đơn sai trạng thái, đã đổi quà, thiếu điểm hoặc quà không áp dụng được
     */
    public function redeem(Order $order, Reward $reward, array $productIds, User $by): PointTransaction
    {
        return DB::transaction(function () use ($order, $reward, $productIds, $by) {
            // Khóa dòng khách trước khi đọc số dư: hai lần duyệt cùng lúc không tiêu quá số điểm.
            $customer = Customer::whereKey($order->customer_id)->lockForUpdate()->firstOrFail();
            $order->refresh();

            if (! $this->canRedeemOn($order)) {
                throw new DomainException('Chỉ đổi quà được khi đơn đang chờ xác nhận hoặc đã xác nhận.');
            }

            if ($this->orderHas($order->id, PointReason::Redeemed)) {
                throw new DomainException('Đơn này đã đổi quà rồi.');
            }

            $balance = $this->balance($customer);

            if ($balance < $reward->points) {
                throw new DomainException("Khách chưa đủ điểm: có {$balance}, cần {$reward->points}.");
            }

            if ($reward->type === Reward::TYPE_GIFT) {
                $this->addGiftLines($order, $reward, $productIds);
            } elseif ($reward->type === Reward::TYPE_FREE_SHIPPING) {
                if ($order->shipping_fee === 0) {
                    throw new DomainException('Đơn này không có phí giao nên không cần đổi điểm miễn phí giao.');
                }

                // D22: miễn phí giao ghi vào shipping_fee, không ghi vào discount.
                $order->shipping_fee = 0;
            } elseif ($reward->type === Reward::TYPE_DISCOUNT) {
                $order->discount = min($order->subtotal, $order->discount + $reward->amount);
            } else {
                throw new DomainException('Loại quà không hợp lệ.');
            }

            $order->total = $order->subtotal + $order->shipping_fee - $order->discount;
            $order->save();

            return $this->record($customer->id, -$reward->points, PointReason::Redeemed, [
                'order_id' => $order->id,
                'reward' => $reward->key,
                'created_by' => $by->id,
            ]);
        });
    }

    /**
     * Hoàn đúng số điểm đã trừ khi đơn đã đổi quà bị hủy; gọi lại không hoàn lần hai.
     */
    public function refundForCancelledOrder(Order $order): ?PointTransaction
    {
        $redeemed = $this->redemptionFor($order);

        if ($redeemed === null || $this->orderHas($order->id, PointReason::Refunded)) {
            return null;
        }

        return $this->recordOnce($redeemed->customer_id, -$redeemed->points, PointReason::Refunded, [
            'order_id' => $order->id,
            'reward' => $redeemed->reward,
        ]);
    }

    /**
     * Gói rau tặng là dòng order_items giá 0đ, vẫn tính vào nhu cầu thu hoạch và phân lô (D23).
     *
     * @param  array<int, int|string|null>  $productIds
     */
    private function addGiftLines(Order $order, Reward $reward, array $productIds): void
    {
        $packs = collect($productIds)->filter()->map(fn ($id) => (int) $id)->countBy();

        if ($packs->sum() !== $reward->packs) {
            throw new DomainException("Quà này gồm đúng {$reward->packs} gói rau; vui lòng chọn đủ.");
        }

        $products = Product::where('is_active', true)->findMany($packs->keys());

        if ($products->count() !== $packs->count()) {
            throw new DomainException('Có loại rau tặng không còn bán.');
        }

        foreach ($products as $product) {
            $quantity = $packs[$product->id];
            $order->items()->create([
                'product_id' => $product->id,
                'basket_id' => null,
                'quantity' => $quantity,
                'weight_kg' => round($quantity * (float) $product->pack_weight_kg, 3),
                'unit_price' => 0,
                'line_total' => 0,
                'is_gift' => true,
            ]);
        }
    }
```

- [ ] **Step 4: Hoàn điểm khi hủy đơn**

`app/Services/OrderService.php` — trong `transition()`, đoạn sau `$order->save();` thành:

```php
            $order->save();

            if ($to === OrderStatus::Delivered) {
                $this->loyalty->awardForDeliveredOrder($order);
            }

            if ($to === OrderStatus::Cancelled) {
                $this->loyalty->refundForCancelledOrder($order);
            }

            return $order;
```

- [ ] **Step 5: Chạy test, xác nhận pass**

Run: `php artisan test --compact --filter="LoyaltyRedeemTest|LoyaltyEarningTest|OrderServiceTest|AdminOrderTest"`
Expected: PASS (13 test mới).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/LoyaltyService.php app/Services/OrderService.php tests/Feature/Loyalty/LoyaltyRedeemTest.php
git commit -m "feat: redeem points for rewards and refund them when an order is cancelled"
```

---

### Task 8.4: Phía khách — chọn quà trên form, xem điểm ở trang tra cứu

**Files:**
- Modify: `app/Http/Requests/StoreOrderRequest.php`, `app/Services/OrderService.php`, `app/Http/Controllers/Public/OrderController.php`, `app/Http/Controllers/Public/OrderLookupController.php`, `resources/views/public/orders/create.blade.php`, `resources/views/public/orders/thanks.blade.php`, `resources/views/public/orders/lookup.blade.php`, `lang/vi/validation.php`
- Test: `tests/Feature/Loyalty/LoyaltyPublicPagesTest.php`

**Interfaces:**
- Consumes: `Reward::all()`, `Reward::keys()`, `Reward::find()` (8.1); `LoyaltyService::balance()` (8.2); cột `orders.requested_reward` (8.1).
- Produces: trường form `requested_reward` (mã quà hoặc rỗng); `OrderService::place()` nhận khóa tùy chọn `requested_reward` trong `$data` và lưu vào đơn; view `public.orders.lookup` nhận thêm `points` (`?int`) và `rewards`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Loyalty/LoyaltyPublicPagesTest.php`:

```php
<?php

namespace Tests\Feature\Loyalty;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LoyaltyPublicPagesTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
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

    public function test_order_form_offers_the_four_rewards(): void
    {
        $this->get('/dat-hang')
            ->assertOk()
            ->assertSee('Đổi điểm lấy quà')
            ->assertSee('name="requested_reward"', false)
            ->assertSee('Tặng bó rau nhỏ — 20 điểm')
            ->assertSee('Miễn phí 1 lần giao hàng — 30 điểm')
            ->assertSee('Giảm 20.000đ cho đơn — 50 điểm')
            ->assertSee('Tặng combo rau nhỏ — 100 điểm');
    }

    public function test_requested_reward_is_stored_without_touching_points_or_totals(): void
    {
        $this->post('/dat-hang', $this->form(['requested_reward' => 'free_shipping']))->assertRedirect('/dat-hang/cam-on');

        $order = Order::firstOrFail();
        $this->assertSame('free_shipping', $order->requested_reward);
        $this->assertSame([10000, 34000], [$order->shipping_fee, $order->total]);
        $this->assertSame(0, PointTransaction::count());

        $this->get('/dat-hang/cam-on')
            ->assertOk()
            ->assertSee('Miễn phí 1 lần giao hàng')
            ->assertSee('Yêu cầu đổi quà sẽ được xác nhận cùng đơn hàng.');
    }

    public function test_order_without_a_reward_keeps_the_column_null(): void
    {
        $this->post('/dat-hang', $this->form(['requested_reward' => '']))->assertRedirect('/dat-hang/cam-on');

        $this->assertNull(Order::firstOrFail()->requested_reward);
        $this->get('/dat-hang/cam-on')->assertOk()->assertDontSee('Yêu cầu đổi quà');
    }

    public function test_unknown_reward_key_is_rejected_and_no_order_is_created(): void
    {
        $this->post('/dat-hang', $this->form(['requested_reward' => 'gold_bar']))->assertSessionHasErrors('requested_reward');

        $this->assertSame(0, Order::count());
    }

    public function test_lookup_shows_the_balance_and_which_rewards_are_within_reach(): void
    {
        $this->post('/dat-hang', $this->form());
        PointTransaction::factory()->create(['customer_id' => Customer::firstOrFail()->id, 'points' => 35]);

        $this->post('/tra-cuu', ['phone' => '0988000001'])
            ->assertOk()
            ->assertSee('Bạn đang có 35 điểm')
            ->assertSeeInOrder([
                'Tặng bó rau nhỏ', '20 điểm · đủ điểm',
                'Miễn phí 1 lần giao hàng', '30 điểm · đủ điểm',
                'Giảm 20.000đ cho đơn', '50 điểm',
                'Tặng combo rau nhỏ', '100 điểm',
            ])
            ->assertDontSee('50 điểm · đủ điểm')
            ->assertDontSee('Nguyễn Thị Lan')
            ->assertDontSee('129 Rạch Gầm');
    }

    public function test_a_customer_with_no_points_still_sees_how_to_earn_them(): void
    {
        $this->post('/dat-hang', $this->form());

        $this->post('/tra-cuu', ['phone' => '0988000001'])
            ->assertOk()
            ->assertSee('Bạn đang có 0 điểm')
            ->assertSee('40.000đ')
            ->assertDontSee('đủ điểm');
    }

    public function test_lookup_shows_no_points_block_for_an_unknown_phone_or_before_searching(): void
    {
        $this->post('/tra-cuu', ['phone' => '0977000000'])->assertOk()->assertDontSee('Bạn đang có');
        $this->get('/tra-cuu')->assertOk()->assertDontSee('Bạn đang có');
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --compact --filter=LoyaltyPublicPagesTest`
Expected: FAIL — test đầu tiên không thấy chữ "Đổi điểm lấy quà".

- [ ] **Step 3: Validation và tên thuộc tính**

`app/Http/Requests/StoreOrderRequest.php` — thêm `use App\Support\Reward;` và một rule sau dòng `'note'`:

```php
            'note' => ['nullable', 'string', 'max:500'],
            'requested_reward' => ['nullable', Rule::in(Reward::keys())],
```

`lang/vi/validation.php` — trong mảng `'attributes'`, thêm sau dòng `'note' => 'ghi chú',`:

```php
        'requested_reward' => 'quà đổi điểm',
        'reward' => 'quà đổi điểm',
```

- [ ] **Step 4: Lưu yêu cầu vào đơn**

`app/Services/OrderService.php` — trong PHPDoc của `place()`, thêm `requested_reward?: ?string` vào cuối array shape (sau `subscription_id?: ?int`), và trong mảng truyền cho `createWithCode()` thêm một dòng sau `'note'`:

```php
                'note' => $data['note'] ?? null,
                // D24: chỉ ghi nhận mong muốn của khách; điểm được trừ khi người điều phối duyệt.
                'requested_reward' => $data['requested_reward'] ?? null,
```

- [ ] **Step 5: Controller công khai**

`app/Http/Controllers/Public/OrderController.php` — thêm `use App\Support\Reward;`; trong mảng view của `create()` thêm:

```php
            'paymentMethods' => PaymentMethod::cases(),
            'rewards' => Reward::all(),
```

và trong `thanks()`:

```php
        return view('public.orders.thanks', ['order' => $order, 'requestedReward' => Reward::find($order->requested_reward)]);
```

`app/Http/Controllers/Public/OrderLookupController.php` — thêm `use App\Models\Customer;`, `use App\Services\LoyaltyService;`, `use App\Support\Reward;`, rồi sửa hai hàm:

```php
    public function show(): View
    {
        return view('public.orders.lookup', ['orders' => null, 'phone' => null, 'points' => null, 'rewards' => []]);
    }
```

```php
    public function search(Request $request, LoyaltyService $loyalty): View
    {
        $data = $request->validate(['phone' => ['required', 'regex:/^0\d{9}$/']]);

        $orders = Order::query()
            ->whereHas('customer', fn ($query) => $query->where('phone', $data['phone']))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'code', 'delivery_date', 'time_slot', 'status', 'total']);

        $customer = Customer::where('phone', $data['phone'])->first();

        return view('public.orders.lookup', [
            'orders' => $orders,
            'phone' => $data['phone'],
            // Quyết định D25: chỉ hiện số dư, không hiện lịch sử điểm.
            'points' => $customer === null ? null : $loyalty->balance($customer),
            'rewards' => Reward::all(),
        ]);
    }
```

- [ ] **Step 6: View form đặt hàng**

`resources/views/public/orders/create.blade.php` — chèn khối sau vào giữa khối `<div>` của ô "Ghi chú" và khối `<div>` của ô đồng ý dùng thông tin:

```blade
        <div>
            <label for="requested_reward" class="mb-1 block text-sm font-medium text-stone-700">Đổi điểm lấy quà (không bắt buộc)</label>
            <select id="requested_reward" name="requested_reward" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base">
                <option value="">Không đổi lần này</option>
                @foreach ($rewards as $reward)
                    <option value="{{ $reward->key }}" @selected(old('requested_reward') === $reward->key)>{{ $reward->label }} — {{ $reward->points }} điểm</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-stone-500">Chúng tôi kiểm tra điểm khi xác nhận đơn. Xem điểm của bạn ở trang <a href="{{ route('lookup.show') }}" class="underline">Tra cứu đơn</a>.</p>
            @error('requested_reward')
                <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </div>

```

- [ ] **Step 7: View cảm ơn**

`resources/views/public/orders/thanks.blade.php` — thêm ngay sau `@endif` của khối chuyển khoản (trước `</div>` đóng khung xanh):

```blade
        @if ($requestedReward)
            <p class="mt-3 text-sm text-emerald-900">Bạn muốn đổi điểm lấy: <span class="font-medium">{{ $requestedReward->label }}</span>. Yêu cầu đổi quà sẽ được xác nhận cùng đơn hàng.</p>
        @endif
```

- [ ] **Step 8: View tra cứu**

`resources/views/public/orders/lookup.blade.php` — chèn giữa `</form>` và `@if ($orders !== null)`:

```blade
    @if ($points !== null)
        <section class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4" aria-live="polite">
            <h2 class="text-base font-semibold text-emerald-900">Bạn đang có {{ $points }} điểm</h2>
            <p class="mt-1 text-sm text-emerald-900">Mỗi đơn từ @vnd(config('farm2home.loyalty.min_order_subtotal')) giao xong được +{{ config('farm2home.loyalty.order_points') }} điểm. Trả lại bao bì được +{{ config('farm2home.loyalty.packaging_points') }} điểm.</p>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($rewards as $reward)
                    <li class="flex items-center justify-between gap-3">
                        <span>{{ $reward->label }}</span>
                        <span @class(['shrink-0 font-medium', 'text-emerald-800' => $points >= $reward->points, 'text-stone-500' => $points < $reward->points])>{{ $reward->points }} điểm{{ $points >= $reward->points ? ' · đủ điểm' : '' }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-stone-600">Muốn đổi quà, hãy chọn quà khi đặt đơn kế tiếp. Chúng tôi xác nhận cùng đơn hàng.</p>
        </section>
    @endif

```

- [ ] **Step 9: Chạy test, xác nhận pass**

Run: `php artisan test --compact --filter="LoyaltyPublicPagesTest|PublicOrderFormTest|OrderLookupTest"`
Expected: PASS (7 test mới; test cũ của form và tra cứu không đổi).

- [ ] **Step 10: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/StoreOrderRequest.php app/Services/OrderService.php app/Http/Controllers/Public lang/vi/validation.php resources/views/public/orders tests/Feature/Loyalty/LoyaltyPublicPagesTest.php
git commit -m "feat: customers request a reward on the order form and see their points on lookup"
```

---

### Task 8.5: Người điều phối duyệt quà trên trang đơn

**Files:**
- Modify: `routes/web.php`, `app/Http/Controllers/Admin/OrderController.php`, `resources/views/admin/orders/show.blade.php`
- Test: `tests/Feature/Loyalty/AdminRedeemTest.php`

**Interfaces:**
- Consumes: `LoyaltyService::balance`, `canRedeemOn`, `redemptionFor`, `redeem` (8.2, 8.3); `Reward` (8.1); `orders.requested_reward` (8.4).
- Produces: route `admin.orders.redeem` — `POST /admin/orders/{order}/reward`, tham số `reward` (mã quà, bắt buộc) và `products[]` (id sản phẩm, mỗi phần tử một gói, cho phép phần tử rỗng). Thành công: redirect về trang đơn với flash `status = 'Đã áp dụng quà đổi điểm.'`. Bị từ chối: `withErrors(['action' => …])`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Loyalty/AdminRedeemTest.php`:

```php
<?php

namespace Tests\Feature\Loyalty;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\HarvestPlan;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Services\LoyaltyService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class AdminRedeemTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    /** 2 gói rau muống 24.000đ + phí giao 10.000đ; khách xin miễn phí giao. */
    private Order $order;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->order = app(OrderService::class)->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod', 'data_consent' => true, 'requested_reward' => 'free_shipping',
        ], OrderChannel::Website);
        $this->url = '/admin/orders/'.$this->order->id;
    }

    private function givePoints(int $points): void
    {
        PointTransaction::factory()->create(['customer_id' => $this->order->customer_id, 'points' => $points]);
    }

    private function balance(): int
    {
        return app(LoyaltyService::class)->balance($this->order->customer);
    }

    public function test_guests_cannot_apply_a_reward(): void
    {
        $this->post($this->url.'/reward', ['reward' => 'free_shipping'])->assertRedirect('/login');
    }

    public function test_order_page_shows_balance_request_and_shortfall(): void
    {
        $this->givePoints(25);

        $this->actingAs($this->operator)->get($this->url)
            ->assertOk()
            ->assertSee('Điểm thành viên')
            ->assertSee('<span class="font-semibold">25 điểm</span>', false)
            ->assertSee('Khách muốn đổi')
            ->assertSee('Miễn phí 1 lần giao hàng')
            ->assertSee('thiếu 5 điểm')
            ->assertSee('name="reward"', false)
            ->assertSee('Áp dụng quà');
    }

    public function test_order_page_hides_the_form_when_no_reward_is_within_reach(): void
    {
        $this->actingAs($this->operator)->get($this->url)
            ->assertOk()
            ->assertSee('<span class="font-semibold">0 điểm</span>', false)
            ->assertSee('Chưa đủ điểm để đổi quà')
            ->assertDontSee('name="reward"', false);
    }

    public function test_operator_applies_free_shipping(): void
    {
        $this->givePoints(30);

        $this->actingAs($this->operator)
            ->post($this->url.'/reward', ['reward' => 'free_shipping'])
            ->assertRedirect($this->url)
            ->assertSessionHas('status', 'Đã áp dụng quà đổi điểm.');

        $this->order->refresh();
        $this->assertSame([0, 24000], [$this->order->shipping_fee, $this->order->total]);
        $this->assertSame(0, $this->balance());

        $this->get($this->url)
            ->assertOk()
            ->assertSee('Đã áp dụng: Miễn phí 1 lần giao hàng')
            ->assertSee('-30 điểm')
            ->assertDontSee('name="reward"', false);
    }

    public function test_operator_applies_a_gift_and_the_line_is_tagged(): void
    {
        $this->givePoints(100);

        $this->actingAs($this->operator)
            ->post($this->url.'/reward', ['reward' => 'small_combo', 'products' => [$this->cn->id, $this->cn->id, $this->hl->id]])
            ->assertRedirect($this->url);

        $this->assertSame(3, (int) $this->order->items()->where('is_gift', true)->sum('quantity'));
        $this->get($this->url)->assertOk()->assertSee('Quà đổi điểm')->assertSee('Cải ngọt')->assertSee('Hành lá');
    }

    public function test_empty_gift_selects_are_ignored_and_a_missing_choice_is_refused(): void
    {
        $this->givePoints(100);

        $this->actingAs($this->operator)->followingRedirects()->from($this->url)
            ->post($this->url.'/reward', ['reward' => 'small_bunch', 'products' => ['', '', '']])
            ->assertOk()
            ->assertSee('Quà này gồm đúng 1 gói rau; vui lòng chọn đủ.');
        $this->assertSame(100, $this->balance());

        $this->actingAs($this->operator)
            ->post($this->url.'/reward', ['reward' => 'small_bunch', 'products' => [$this->rm->id, '', '']])
            ->assertRedirect($this->url)
            ->assertSessionHasNoErrors();
        $this->assertSame(80, $this->balance());
    }

    public function test_a_refusal_is_shown_on_the_order_page_and_changes_nothing(): void
    {
        $this->givePoints(10);

        $this->actingAs($this->operator)->followingRedirects()->from($this->url)
            ->post($this->url.'/reward', ['reward' => 'free_shipping'])
            ->assertOk()
            ->assertSee('Khách chưa đủ điểm: có 10, cần 30.');

        $this->assertSame(10000, $this->order->refresh()->shipping_fee);
        $this->assertSame(10, $this->balance());
    }

    public function test_an_unknown_reward_key_is_a_validation_error(): void
    {
        $this->givePoints(100);

        $this->actingAs($this->operator)->post($this->url.'/reward', ['reward' => 'gold_bar'])->assertSessionHasErrors('reward');
        $this->actingAs($this->operator)->post($this->url.'/reward', [])->assertSessionHasErrors('reward');

        $this->assertSame(100, $this->balance());
    }

    public function test_gift_warning_appears_only_when_the_harvest_plan_is_already_confirmed(): void
    {
        $this->givePoints(100);
        $plan = HarvestPlan::factory()->create(['delivery_date' => '2026-10-03', 'harvest_date' => '2026-10-03', 'status' => 'draft']);

        $this->actingAs($this->operator)->get($this->url)->assertOk()->assertDontSee('Kế hoạch thu hoạch ngày này đã chốt');

        $plan->forceFill(['status' => 'confirmed'])->save();

        $this->actingAs($this->operator)->get($this->url)->assertOk()->assertSee('Kế hoạch thu hoạch ngày này đã chốt');
    }

    public function test_an_order_past_confirmation_shows_no_form(): void
    {
        $this->givePoints(100);
        $orders = app(OrderService::class);
        $orders->transition($this->order, OrderStatus::Confirmed);
        $orders->transition($this->order, OrderStatus::Packing);

        $this->actingAs($this->operator)->get($this->url)
            ->assertOk()
            ->assertSee('Chỉ đổi quà được trước khi đóng gói.')
            ->assertDontSee('name="reward"', false);
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --compact --filter=AdminRedeemTest`
Expected: FAIL — `test_guests_cannot_apply_a_reward` nhận 404 thay vì redirect; các test trang đơn không thấy "Điểm thành viên".

- [ ] **Step 3: Route**

`routes/web.php` — thêm ngay sau dòng `Route::patch('orders/{order}/status', …)`:

```php
    Route::post('orders/{order}/reward', [AdminOrderController::class, 'redeem'])->name('orders.redeem');
```

- [ ] **Step 4: Controller**

`app/Http/Controllers/Admin/OrderController.php` — thêm các `use`:

```php
use App\Enums\HarvestPlanStatus;
use App\Models\HarvestPlan;
use App\Services\LoyaltyService;
use App\Support\Reward;
```

Thay hàm `show()` và thêm `redeem()`, `loyaltyPanel()`:

```php
    public function show(Order $order, LoyaltyService $loyalty): View
    {
        $order->load('customer', 'zone', 'items.product', 'items.batches');

        return view('admin.orders.show', ['order' => $order] + $this->loyaltyPanel($order, $loyalty));
    }

    /**
     * Người điều phối duyệt quà đổi điểm cho đơn (con người xác nhận, D24).
     */
    public function redeem(Request $request, Order $order, LoyaltyService $loyalty): RedirectResponse
    {
        $data = $request->validate([
            'reward' => ['required', Rule::in(Reward::keys())],
            'products' => ['nullable', 'array', 'max:10'],
            'products.*' => ['nullable', 'integer'],
        ]);

        try {
            $loyalty->redeem($order, Reward::find($data['reward']), $data['products'] ?? [], $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('status', 'Đã áp dụng quà đổi điểm.');
    }

    /**
     * @return array<string, mixed>
     */
    private function loyaltyPanel(Order $order, LoyaltyService $loyalty): array
    {
        $points = $loyalty->balance($order->customer);
        $rewards = Reward::all();
        $requested = Reward::find($order->requested_reward);
        $redemption = $loyalty->redemptionFor($order)?->load('creator');

        return [
            'points' => $points,
            'rewards' => $rewards,
            'requestedReward' => $requested,
            'redemption' => $redemption,
            'appliedReward' => Reward::find($redemption?->reward),
            'canRedeem' => $loyalty->canRedeemOn($order),
            'anyAffordable' => collect($rewards)->contains(fn (Reward $reward) => $points >= $reward->points),
            'defaultReward' => $requested !== null && $points >= $requested->points ? $requested->key : '',
            'packsByReward' => collect($rewards)->mapWithKeys(fn (Reward $reward) => [$reward->key => $reward->packs])->all(),
            'maxPacks' => (int) collect($rewards)->max('packs'),
            'giftProducts' => Product::where('is_active', true)->orderBy('id')->get(['id', 'name']),
            'harvestPlanLocked' => HarvestPlan::whereDate('delivery_date', $order->delivery_date)
                ->where('status', '!=', HarvestPlanStatus::Draft)
                ->exists(),
        ];
    }
```

- [ ] **Step 5: View trang đơn**

`resources/views/admin/orders/show.blade.php` — đổi ô tên rau trong bảng:

```blade
                                <td class="py-2 pr-3">
                                    {{ $item->product->name }}
                                    @if ($item->is_gift)
                                        <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900">Quà đổi điểm</span>
                                    @endif
                                </td>
```

và chèn khối sau trong `<aside>`, giữa `</section>` của "Khách và giao hàng" và `@if ($order->status->next() !== [])`:

```blade
            <section class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                <h2 class="mb-2 font-semibold">Điểm thành viên</h2>
                <p>Số dư: <span class="font-semibold">{{ $points }} điểm</span></p>

                @if ($requestedReward)
                    <p class="mt-1 text-stone-700">Khách muốn đổi: <span class="font-medium">{{ $requestedReward->label }}</span> ({{ $requestedReward->points }} điểm)</p>
                @endif

                @if ($redemption)
                    <p class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-emerald-900">
                        Đã áp dụng: {{ $appliedReward?->label ?? $redemption->reward }} ({{ $redemption->points }} điểm){{ $redemption->creator ? ' · '.$redemption->creator->name : '' }}
                    </p>
                @elseif (! $canRedeem)
                    <p class="mt-1 text-stone-600">Chỉ đổi quà được trước khi đóng gói.</p>
                @else
                    @if ($requestedReward && $points < $requestedReward->points)
                        <p class="mt-1 text-rose-700">Chưa đủ điểm cho quà khách chọn (thiếu {{ $requestedReward->points - $points }} điểm).</p>
                    @endif

                    @if (! $anyAffordable)
                        <p class="mt-1 text-stone-600">Chưa đủ điểm để đổi quà.</p>
                    @else
                        <form method="POST" action="{{ route('admin.orders.redeem', $order) }}" class="mt-3 space-y-3" x-data="{ reward: @js($defaultReward), packs: @js($packsByReward) }">
                            @csrf
                            <fieldset class="space-y-2">
                                <legend class="mb-1 text-stone-700">Chọn quà</legend>
                                @foreach ($rewards as $reward)
                                    @php($short = $reward->points - $points)
                                    <label @class(['flex min-h-11 items-center gap-2 rounded-lg border border-stone-300 px-3', 'opacity-50' => $short > 0])>
                                        <input type="radio" name="reward" value="{{ $reward->key }}" x-model="reward" @disabled($short > 0)>
                                        <span>{{ $reward->label }} · {{ $reward->points }} điểm{{ $short > 0 ? ' (thiếu '.$short.')' : '' }}</span>
                                    </label>
                                @endforeach
                            </fieldset>

                            <div x-show="(packs[reward] ?? 0) > 0" class="space-y-2">
                                <p class="text-stone-700">Chọn rau tặng</p>
                                @for ($i = 1; $i <= $maxPacks; $i++)
                                    <select name="products[]" aria-label="Rau tặng {{ $i }}" x-show="(packs[reward] ?? 0) >= {{ $i }}" :disabled="(packs[reward] ?? 0) < {{ $i }}" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                                        <option value="">— Chọn loại rau —</option>
                                        @foreach ($giftProducts as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                @endfor
                                @if ($harvestPlanLocked)
                                    <p class="text-amber-800">Kế hoạch thu hoạch ngày này đã chốt: rau tặng sẽ lấy từ phần biên an toàn hoặc hàng dư.</p>
                                @endif
                            </div>

                            <button class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Áp dụng quà</button>
                        </form>
                    @endif
                @endif
            </section>

```

- [ ] **Step 6: Chạy test, xác nhận pass**

Run: `php artisan test --compact --filter="AdminRedeemTest|AdminOrderTest"`
Expected: PASS (10 test mới; `AdminOrderTest` không đổi).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/web.php app/Http/Controllers/Admin/OrderController.php resources/views/admin/orders/show.blade.php tests/Feature/Loyalty/AdminRedeemTest.php
git commit -m "feat: operator approves a reward on the admin order page"
```

---

### Task 8.6: Trang "Điểm thành viên" trong admin và số liệu trên dashboard

**Files:**
- Create: `app/Http/Controllers/Admin/LoyaltyController.php`, `resources/views/admin/loyalty/index.blade.php`, `resources/views/admin/loyalty/show.blade.php`
- Modify: `routes/web.php`, `resources/views/components/layouts/admin.blade.php`, `app/Services/KpiService.php`, `app/Http/Controllers/Admin/DashboardController.php`, `resources/views/admin/dashboard.blade.php`
- Test: `tests/Feature/Loyalty/LoyaltyAdminPagesTest.php`

**Interfaces:**
- Consumes: `PointTransaction`, `PointReason`, `Customer::pointTransactions()` (8.1); `LoyaltyService::balance()` (8.2); `Reward::find()` (8.1).
- Produces:
  - Route `admin.loyalty.index` (`GET /admin/loyalty`), `admin.loyalty.show` (`GET /admin/loyalty/{customer}`), cho cả admin và operator.
  - `KpiService::loyalty(CarbonInterface $from, CarbonInterface $to): array{points_earned: int, points_redeemed: int, redemptions: int}` — lọc theo `point_transactions.created_at`; số đã đổi và số lượt đổi là số **ròng** sau khi trừ hoàn điểm.
  - View `admin.dashboard` nhận thêm biến `loyalty`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Loyalty/LoyaltyAdminPagesTest.php`:

```php
<?php

namespace Tests\Feature\Loyalty;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Services\KpiService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LoyaltyAdminPagesTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Customer $lan;

    private Customer $binh;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 21:00:00');
        $this->buildCatalog();

        $this->lan = Customer::factory()->create(['name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'delivery_zone_id' => $this->zone->id]);
        $this->binh = Customer::factory()->create(['name' => 'Trần Văn Bình', 'phone' => '0988000002', 'delivery_zone_id' => $this->zone->id]);
        Customer::factory()->create(['name' => 'Lê Thị Chưa Có Điểm', 'phone' => '0988000003', 'delivery_zone_id' => $this->zone->id]);
        $this->order = Order::factory()->create(['code' => 'DH261003-001', 'customer_id' => $this->lan->id, 'delivery_zone_id' => $this->zone->id]);

        // Lan: +5 +5 +5 +5 rồi đổi bó rau 20 điểm → số dư 0. Bình: +5 từ trước kỳ xem → số dư 5.
        $this->row($this->lan, 5, 'order_delivered', '2026-09-20 18:00:00');
        $this->row($this->lan, 5, 'order_delivered', '2026-09-27 18:00:00');
        $this->row($this->lan, 5, 'packaging_returned', '2026-09-27 19:00:00');
        $this->row($this->lan, 5, 'order_delivered', '2026-10-01 18:00:00');
        $this->row($this->lan, -20, 'redeemed', '2026-10-02 09:00:00', ['order_id' => $this->order->id, 'reward' => 'small_bunch', 'created_by' => $this->operator->id]);
        $this->row($this->binh, 5, 'order_delivered', '2026-08-15 18:00:00');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function row(Customer $customer, int $points, string $reason, string $at, array $extra = []): PointTransaction
    {
        return PointTransaction::factory()->create(['customer_id' => $customer->id, 'points' => $points, 'reason' => $reason, 'created_at' => $at, 'updated_at' => $at] + $extra);
    }

    /**
     * @return array{points_earned: int, points_redeemed: int, redemptions: int}
     */
    private function kpi(string $from, string $to): array
    {
        return app(KpiService::class)->loyalty(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/loyalty')->assertRedirect('/login');
        $this->get('/admin/loyalty/'.$this->lan->id)->assertRedirect('/login');
    }

    public function test_index_lists_customers_with_points_highest_balance_first(): void
    {
        $response = $this->actingAs($this->operator)->get('/admin/loyalty')
            ->assertOk()
            ->assertSee('Điểm thành viên')
            ->assertSeeInOrder(['Trần Văn Bình', '0988000002', 'Nguyễn Thị Lan', '0988000001'])
            ->assertDontSee('Lê Thị Chưa Có Điểm');

        $rows = collect($response->viewData('customers')->items())
            ->map(fn (Customer $customer) => [$customer->name, (int) $customer->earned, (int) $customer->balance])
            ->all();
        $this->assertSame([['Trần Văn Bình', 5, 5], ['Nguyễn Thị Lan', 20, 0]], $rows);
    }

    public function test_show_lists_the_history_newest_first(): void
    {
        $this->actingAs($this->operator)->get('/admin/loyalty/'.$this->lan->id)
            ->assertOk()
            ->assertSee('Điểm của Nguyễn Thị Lan')
            ->assertSee('Số dư: <span class="font-semibold">0 điểm</span>', false)
            ->assertSeeInOrder([
                '02/10/2026', 'Đổi quà', 'DH261003-001', 'Tặng bó rau nhỏ', '-20', 'Operator',
                '01/10/2026', 'Đơn đã giao', '+5',
                '27/09/2026', 'Trả bao bì', '+5',
                '27/09/2026', 'Đơn đã giao', '+5',
                '20/09/2026', 'Đơn đã giao', '+5',
            ]);
    }

    public function test_menu_has_the_loyalty_entry(): void
    {
        $this->actingAs($this->operator)->get('/admin/orders')->assertOk()->assertSee('href="'.route('admin.loyalty.index').'"', false);
    }

    public function test_dashboard_shows_points_for_the_period(): void
    {
        $this->actingAs($this->operator)->get('/admin?from=2026-09-10&to=2026-10-03')
            ->assertOk()
            ->assertSee('Điểm đã cộng')
            ->assertSee('Điểm đã đổi')
            ->assertSee('1 lượt đổi quà');

        $this->assertSame(['points_earned' => 20, 'points_redeemed' => 20, 'redemptions' => 1], $this->kpi('2026-09-10', '2026-10-03'));
    }

    public function test_a_refund_nets_out_of_the_redeemed_numbers(): void
    {
        $this->row($this->lan, 20, 'refunded', '2026-10-03 08:00:00', ['order_id' => $this->order->id, 'reward' => 'small_bunch']);

        $this->assertSame(['points_earned' => 20, 'points_redeemed' => 0, 'redemptions' => 0], $this->kpi('2026-09-10', '2026-10-03'));
    }

    public function test_period_boundaries_are_inclusive_and_an_empty_period_gives_zeros(): void
    {
        $this->assertSame(['points_earned' => 5, 'points_redeemed' => 0, 'redemptions' => 0], $this->kpi('2026-09-20', '2026-09-20'));
        $this->assertSame(['points_earned' => 0, 'points_redeemed' => 0, 'redemptions' => 0], $this->kpi('2026-11-01', '2026-11-07'));
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --compact --filter=LoyaltyAdminPagesTest`
Expected: FAIL — `/admin/loyalty` trả 404; `Call to undefined method App\Services\KpiService::loyalty()`.

- [ ] **Step 3: Route và menu**

`routes/web.php` — thêm `use App\Http\Controllers\Admin\LoyaltyController;` (giữ thứ tự chữ cái, sau `HarvestPlanController`), và thêm sau hai dòng `packaging-returns`:

```php
    Route::get('loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::get('loyalty/{customer}', [LoyaltyController::class, 'show'])->name('loyalty.show');
```

`resources/views/components/layouts/admin.blade.php` — trong mảng `$operations`, thêm sau dòng `'admin.packaging-returns.index' => 'Bao bì',`:

```php
        'admin.loyalty.index' => 'Điểm thành viên',
```

- [ ] **Step 4: Controller**

`app/Http/Controllers/Admin/LoyaltyController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PointReason;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\LoyaltyService;
use Illuminate\View\View;

class LoyaltyController extends Controller
{
    /**
     * Khách đã có giao dịch điểm, số dư cao nhất trước.
     */
    public function index(): View
    {
        $earnReasons = [PointReason::OrderDelivered, PointReason::PackagingReturned];

        return view('admin.loyalty.index', [
            'customers' => Customer::query()
                ->whereHas('pointTransactions')
                ->withSum('pointTransactions as balance', 'points')
                ->withSum(['pointTransactions as earned' => fn ($query) => $query->whereIn('reason', $earnReasons)], 'points')
                ->orderByDesc('balance')
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function show(Customer $customer, LoyaltyService $loyalty): View
    {
        return view('admin.loyalty.show', [
            'customer' => $customer,
            'points' => $loyalty->balance($customer),
            'transactions' => $customer->pointTransactions()
                ->with('order', 'creator')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(50),
        ]);
    }
}
```

- [ ] **Step 5: Hai view**

`resources/views/admin/loyalty/index.blade.php`:

```blade
<x-layouts.admin title="Điểm thành viên">
    <p class="mb-4 text-sm text-stone-600">
        Đơn từ @vnd(config('farm2home.loyalty.min_order_subtotal')) giao xong: +{{ config('farm2home.loyalty.order_points') }} điểm ·
        Trả bao bì: +{{ config('farm2home.loyalty.packaging_points') }} điểm. Đổi quà trên trang của từng đơn.
    </p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[520px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Khách</th>
                    <th class="px-3 py-2 font-medium">Điện thoại</th>
                    <th class="px-3 py-2 text-right font-medium">Đã cộng</th>
                    <th class="px-3 py-2 text-right font-medium">Đã đổi</th>
                    <th class="px-3 py-2 text-right font-medium">Số dư</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($customers as $customer)
                    <tr>
                        <td class="px-3 py-2"><a href="{{ route('admin.loyalty.show', $customer) }}" class="text-emerald-800 underline">{{ $customer->name }}</a></td>
                        <td class="px-3 py-2">{{ $customer->phone }}</td>
                        <td class="px-3 py-2 text-right">{{ (int) $customer->earned }}</td>
                        <td class="px-3 py-2 text-right">{{ (int) $customer->earned - (int) $customer->balance }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ (int) $customer->balance }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-stone-500">Chưa có khách nào có điểm.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.admin>
```

`resources/views/admin/loyalty/show.blade.php`:

```blade
<x-layouts.admin :title="'Điểm của '.$customer->name">
    <p class="mb-4 text-sm">{{ $customer->phone }} · Số dư: <span class="font-semibold">{{ $points }} điểm</span></p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Thời gian</th>
                    <th class="px-3 py-2 font-medium">Lý do</th>
                    <th class="px-3 py-2 font-medium">Đơn</th>
                    <th class="px-3 py-2 font-medium">Quà</th>
                    <th class="px-3 py-2 text-right font-medium">Điểm</th>
                    <th class="px-3 py-2 font-medium">Người duyệt</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($transactions as $row)
                    <tr>
                        <td class="px-3 py-2">{{ $row->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2">{{ $row->reason->label() }}</td>
                        <td class="px-3 py-2">
                            @if ($row->order)
                                <a href="{{ route('admin.orders.show', $row->order) }}" class="text-emerald-800 underline">{{ $row->order->code }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ \App\Support\Reward::find($row->reward)?->label ?? '—' }}</td>
                        <td class="px-3 py-2 text-right font-medium">{{ $row->points > 0 ? '+'.$row->points : $row->points }}</td>
                        <td class="px-3 py-2">{{ $row->creator?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-stone-500">Khách này chưa có giao dịch điểm.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>

    <p class="mt-4 text-sm"><a href="{{ route('admin.loyalty.index') }}" class="text-emerald-800 underline">← Điểm thành viên</a></p>
</x-layouts.admin>
```

- [ ] **Step 6: KPI**

`app/Services/KpiService.php` — thêm `use App\Enums\PointReason;` và `use App\Models\PointTransaction;`, rồi thêm hàm sau ngay trước `private function sumByWeek`:

```php
    /**
     * Điểm thành viên trong kỳ, lọc theo thời điểm ghi sổ. Số đã đổi và số lượt đổi là số ròng sau hoàn điểm.
     *
     * @return array{points_earned: int, points_redeemed: int, redemptions: int}
     */
    public function loyalty(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = PointTransaction::whereBetween('created_at', $this->moments($from, $to));
        $spent = [PointReason::Redeemed, PointReason::Refunded];

        return [
            'points_earned' => (int) (clone $rows)->whereNotIn('reason', $spent)->sum('points'),
            'points_redeemed' => -(int) (clone $rows)->whereIn('reason', $spent)->sum('points'),
            'redemptions' => (clone $rows)->where('reason', PointReason::Redeemed)->count()
                - (clone $rows)->where('reason', PointReason::Refunded)->count(),
        ];
    }
```

`app/Http/Controllers/Admin/DashboardController.php` — thêm một dòng vào mảng view:

```php
            'environment' => $kpi->environment($from, $to),
            'loyalty' => $kpi->loyalty($from, $to),
```

`resources/views/admin/dashboard.blade.php` — trong lưới của nhóm "Khách hàng", thêm sau ô "Phản hồi đã xử lý":

```blade
                    <x-stat label="Điểm đã cộng" :value="$loyalty['points_earned']" hint="đơn giao xong và trả bao bì" />
                    <x-stat label="Điểm đã đổi" :value="$loyalty['points_redeemed']" :hint="$loyalty['redemptions'].' lượt đổi quà'" />
```

- [ ] **Step 7: Chạy test, xác nhận pass**

Run: `php artisan test --compact --filter="LoyaltyAdminPagesTest|DashboardTest|KpiService|LayoutTest|RoleMiddlewareTest"`
Expected: PASS (7 test mới; các test dashboard và layout cũ không đổi).

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/web.php app/Http/Controllers/Admin/LoyaltyController.php app/Http/Controllers/Admin/DashboardController.php app/Services/KpiService.php resources/views/admin/loyalty resources/views/admin/dashboard.blade.php resources/views/components/layouts/admin.blade.php tests/Feature/Loyalty/LoyaltyAdminPagesTest.php
git commit -m "feat: admin loyalty pages and points figures on the dashboard"
```

---

### Task 8.7: Backfill dữ liệu mẫu, dịch ngày demo, smoke test, build

**Files:**
- Create: `app/Console/Commands/LoyaltyBackfill.php`
- Modify: `app/Console/Commands/DemoShiftDates.php`, `tests/Feature/DemoShiftDatesTest.php`, `tests/Feature/SmokeTest.php`
- Test: `tests/Feature/Loyalty/LoyaltyBackfillTest.php`

**Interfaces:**
- Consumes: `LoyaltyService::awardForDeliveredOrder`, `awardForPackagingReturn`, `balance` (8.2).
- Produces: lệnh `php artisan loyalty:backfill` — in `Đã cộng {điểm} điểm trong {số dòng} giao dịch.`; chạy lại in `Đã cộng 0 điểm trong 0 giao dịch.`

Số kỳ vọng trên dữ liệu mẫu (`database/farm2home.sql`): 131 đơn đã giao, trong đó 78 đơn có tiền hàng từ 40.000đ → 78 dòng × 5 = 390 điểm; 11 lượt trả bao bì, mỗi lượt một đơn khác nhau → 11 dòng × 5 = 55 điểm. Tổng 89 dòng, 445 điểm. Khách `0988000001` có 3 đơn đủ điều kiện và 1 lượt trả bao bì → 20 điểm.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Loyalty/LoyaltyBackfillTest.php`:

```php
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
```

Trong `tests/Feature/DemoShiftDatesTest.php`, thêm test sau vào cuối class:

```php
    public function test_point_transactions_move_with_the_rest(): void
    {
        $this->seed(DemoSeeder::class);
        $this->artisan('loyalty:backfill')->assertSuccessful();
        $this->travelTo('2026-11-20 09:00:00');

        $this->artisan('demo:shift-dates')->assertSuccessful();

        // Bao bì trả ngày 19/09 → 07/11 (dịch 49 ngày), dòng điểm đi theo.
        $this->assertSame('2026-11-07 18:00:00', DB::table('point_transactions')->where('packaging_return_id', 1)->value('created_at'));
        $this->assertSame(89, DB::table('point_transactions')->count());
    }
```

Trong `tests/Feature/SmokeTest.php`:

- cuối `setUp()` thêm: `$this->artisan('loyalty:backfill')->assertSuccessful();`
- trong `test_public_and_farmer_pages_render_with_sample_data`, đổi dòng cuối thành:

```php
        $this->post('/tra-cuu', ['phone' => '0988000001'])->assertOk()->assertSee('DH26')->assertSee('Bạn đang có 20 điểm');
```

- trong mảng `$urls` của test admin, thêm sau `'/admin/packaging-returns',`:

```php
            '/admin/loyalty',
            '/admin/loyalty/'.$order->customer_id,
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --compact --filter="LoyaltyBackfillTest|DemoShiftDatesTest|SmokeTest"`
Expected: FAIL — `The command "loyalty:backfill" does not exist.`

- [ ] **Step 3: Lệnh backfill**

`app/Console/Commands/LoyaltyBackfill.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PackagingReturn;
use App\Models\PointTransaction;
use App\Services\LoyaltyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loyalty:backfill')]
#[Description('Cộng điểm cho đơn đã giao và lượt trả bao bì chưa có trong sổ điểm (chạy lại không cộng trùng)')]
class LoyaltyBackfill extends Command
{
    public function handle(LoyaltyService $loyalty): int
    {
        $rows = 0;
        $points = 0;

        $count = function (?PointTransaction $transaction) use (&$rows, &$points): void {
            if ($transaction !== null) {
                $rows++;
                $points += $transaction->points;
            }
        };

        Order::where('status', OrderStatus::Delivered)->orderBy('id')
            ->each(fn (Order $order) => $count($loyalty->awardForDeliveredOrder($order)));

        PackagingReturn::whereNotNull('customer_id')->orderBy('id')
            ->each(fn (PackagingReturn $return) => $count($loyalty->awardForPackagingReturn($return)));

        $this->info("Đã cộng {$points} điểm trong {$rows} giao dịch.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Dịch ngày demo**

`app/Console/Commands/DemoShiftDates.php` — trong hằng `COLUMNS`, thêm sau dòng `'packaging_returns' => ['return_date'],`:

```php
        'point_transactions' => [],
```

- [ ] **Step 5: Chạy test, xác nhận pass**

Run: `php artisan test --compact --filter="LoyaltyBackfillTest|DemoShiftDatesTest|SmokeTest"`
Expected: PASS. Nếu `445`/`89`/`78` lệch, không sửa số trong test: kiểm tra lại quy tắc ngưỡng 40.000đ và "một lần mỗi đơn" trong `LoyaltyService`.

- [ ] **Step 6: Chạy toàn bộ test**

Run: `php artisan test --compact`
Expected: PASS toàn bộ — 238 test cũ + 53 test mới (52 trong `tests/Feature/Loyalty`: 6 + 8 + 13 + 7 + 10 + 7 + 1; và 1 trong `DemoShiftDatesTest`), không có test nào bị bỏ qua.

- [ ] **Step 7: Backfill DB dev và build giao diện**

Run: `php artisan loyalty:backfill`
Expected: `Đã cộng 445 điểm trong 89 giao dịch.` (số có thể lớn hơn nếu từ lúc viết kế hoạch đã có thêm đơn được giao trên DB dev).

Run: `npm run build`
Expected: build xong, `public/build/manifest.json` được ghi lại (các class Tailwind mới như `bg-amber-100`, `text-amber-900` có trong CSS).

- [ ] **Step 8: Kiểm tra bằng mắt**

Chụp màn hình bằng Edge headless theo đúng cách đã dùng ở Task 6.3 (xem dòng "Task 6.3 visual check" trong `.superpowers/sdd/PLAN/progress.md`): `"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --headless=new --screenshot=<file.png> --window-size=<rộng>,<cao> <url>`. Trang điện thoại phải bọc trong một trang có iframe rộng 360px vì Edge headless trên Windows không dàn trang hẹp hơn ~500px; trang admin và trang kết quả tra cứu (cần đăng nhập hoặc POST) được dựng thành file tĩnh tạm trong `public/_preview/` bằng một script PHP có `Auth::guard('web')->setUser($admin)`, xóa thư mục đó sau khi xem. Xem từng ảnh:

- `http://farm2home.test/dat-hang` ở 360px: ô "Đổi điểm lấy quà" không tràn ngang, cao tối thiểu 44px.
- `http://farm2home.test/tra-cuu` sau khi tra `0988000001` ở 360px: khối "Bạn đang có 20 điểm", bốn mốc quà không bị xuống dòng xấu.
- Trang một đơn `pending` của khách `0988000001` trong admin ở 1280px: khối "Điểm thành viên" nằm trong cột phải, form chọn quà hiện đúng.
- `http://farm2home.test/admin/loyalty` và dashboard ở 1280px.

Sửa lỗi dàn trang nếu có, chạy lại test của trang đã sửa.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/LoyaltyBackfill.php app/Console/Commands/DemoShiftDates.php tests/Feature/Loyalty/LoyaltyBackfillTest.php tests/Feature/DemoShiftDatesTest.php tests/Feature/SmokeTest.php
git commit -m "feat: backfill command for loyalty points; demo date shift and smoke test cover the ledger"
```

Sau đó ghi một dòng cho từng task 8.1–8.7 vào `.superpowers/sdd/PLAN/progress.md` (file không nằm trong git), kèm hai mục còn chờ nhóm xác nhận: D26 (combo = 3 gói) và D27 (ngưỡng 40.000đ).

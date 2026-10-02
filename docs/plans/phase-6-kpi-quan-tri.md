# Phase 6 — Rau dư, bao bì, dashboard KPI, quản trị dữ liệu gốc: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Operator chốt ngày (ghi rau dư, bao bì thu hồi), xem dashboard với đủ 5 nhóm KPI pilot; admin tự quản lý sản phẩm, giỏ, nông hộ, cụm giao, tài khoản.

**Architecture:** `KpiService` chỉ đọc, mỗi nhóm KPI một hàm trả mảng thuần; các con số kỳ vọng trong test đã được tính sẵn từ `database/farm2home.sql`. Dashboard vẽ biểu đồ bằng Chart.js qua một entry Vite riêng. CRUD dữ liệu gốc là resource controller chuẩn, không có xóa cứng (dùng `is_active`/`status`).

**Tech Stack:** Laravel 13 · Chart.js · Blade · PHPUnit 12.

**Spec:** [`docs/CLAUDE.md`](../CLAUDE.md) mục 5 (cuối), 6, 7 · [`docs/PLAN.md`](../PLAN.md) quyết định D7, D16.

## Global Constraints

Xem `docs/PLAN.md`. Riêng phase này: **không** hiển thị hay suy ra chỉ số CO₂; KPI rau dư tính bằng kg; mọi tỷ lệ hiển thị 1 chữ số thập phân với dấu phẩy.

---

### Task 6.1: Chốt ngày — ghi rau dư, và bao bì thu hồi

**Files:**
- Create: `app/Http/Controllers/Admin/WasteController.php`, `app/Http/Controllers/Admin/PackagingReturnController.php`, `resources/views/admin/waste/index.blade.php`, `resources/views/admin/packaging-returns/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Waste/WasteAndPackagingTest.php`

**Interfaces:**
- Consumes: `BatchService::remainingKg`, `BatchService::logWaste`.
- Produces: route `admin.waste.index` (GET `/admin/waste?date=`), `admin.waste.store` (POST: `batch_id`, `quantity_kg`, `reason`, `note`), `admin.packaging-returns.index`, `admin.packaging-returns.store` (POST: `return_date`, `phone`, `order_code`, `quantity_returned`, `quantity_reused`, `note`).

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Waste/WasteAndPackagingTest.php`:

```php
<?php

namespace Tests\Feature\Waste;

use App\Enums\OrderChannel;
use App\Enums\WasteReason;
use App\Models\Batch;
use App\Models\PackagingReturn;
use App\Models\WasteLog;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class WasteAndPackagingTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 20:00:00');
        $this->buildCatalog();
        $this->batch = Batch::create([
            'code' => 'F2H-261003-RM-NH01', 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => 5,
            'harvested_at' => '2026-10-03 05:30:00', 'best_before' => '2026-10-05', 'quality_status' => 'passed',
        ]);
        $this->actingAs($this->operator);
    }

    public function test_close_day_screen_lists_batches_with_leftover(): void
    {
        $this->get('/admin/waste?date=2026-10-03')
            ->assertOk()
            ->assertSee('F2H-261003-RM-NH01')
            ->assertSee('5 kg')
            ->assertSee('Dư thừa')
            ->assertSee('Bán giảm giá');
    }

    public function test_operator_logs_surplus_for_a_batch(): void
    {
        $this->post('/admin/waste', ['batch_id' => $this->batch->id, 'quantity_kg' => '2', 'reason' => 'surplus', 'note' => 'Không bán hết'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $log = WasteLog::firstOrFail();
        $this->assertSame(['2.00', WasteReason::Surplus, $this->operator->id, $this->rm->id], [$log->quantity_kg, $log->reason, $log->created_by, $log->product_id]);
        $this->get('/admin/waste?date=2026-10-03')->assertSee('3 kg')->assertSee('Không bán hết');
    }

    public function test_cannot_log_more_than_what_is_left(): void
    {
        $this->from('/admin/waste')
            ->post('/admin/waste', ['batch_id' => $this->batch->id, 'quantity_kg' => '6', 'reason' => 'surplus'])
            ->assertSessionHasErrors('action');

        $this->assertSame(0, WasteLog::count());
    }

    public function test_reason_must_be_a_known_value(): void
    {
        $this->post('/admin/waste', ['batch_id' => $this->batch->id, 'quantity_kg' => '1', 'reason' => 'stolen'])
            ->assertSessionHasErrors('reason');
    }

    public function test_packaging_return_is_linked_to_customer_and_order_when_known(): void
    {
        $order = app(OrderService::class)->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);

        $this->post('/admin/packaging-returns', [
            'return_date' => '2026-10-03', 'phone' => '0988000001', 'order_code' => $order->code,
            'quantity_returned' => 2, 'quantity_reused' => 1,
        ])->assertRedirect();

        $row = PackagingReturn::firstOrFail();
        $this->assertSame([$order->customer_id, $order->id, 2, 1], [$row->customer_id, $row->order_id, $row->quantity_returned, $row->quantity_reused]);
        $this->get('/admin/packaging-returns')->assertSee('Nguyễn Thị Lan')->assertSee($order->code);
    }

    public function test_reused_cannot_exceed_returned(): void
    {
        $this->post('/admin/packaging-returns', ['return_date' => '2026-10-03', 'quantity_returned' => 1, 'quantity_reused' => 2])
            ->assertSessionHasErrors('quantity_reused');
    }

    public function test_packaging_return_without_customer_is_allowed(): void
    {
        $this->post('/admin/packaging-returns', ['return_date' => '2026-10-03', 'quantity_returned' => 3, 'quantity_reused' => 3])->assertRedirect();

        $this->assertNull(PackagingReturn::firstOrFail()->customer_id);
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=WasteAndPackagingTest`
Expected: FAIL (404).

- [ ] **Step 3: Viết `app/Http/Controllers/Admin/WasteController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QualityStatus;
use App\Enums\WasteReason;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\WasteLog;
use App\Services\BatchService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WasteController extends Controller
{
    public function index(Request $request, BatchService $batches): View
    {
        $date = CarbonImmutable::parse($request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? today());

        // Lô thu hoạch trong 7 ngày đến hết ngày đang xem, chưa bị loại, còn tồn.
        $leftovers = Batch::with('product', 'farmer')
            ->where('quality_status', '!=', QualityStatus::Rejected)
            ->whereBetween('harvested_at', [$date->subDays(6)->startOfDay(), $date->endOfDay()])
            ->orderBy('best_before')
            ->get()
            ->map(fn (Batch $batch) => ['batch' => $batch, 'remaining' => $batches->remainingKg($batch)])
            ->filter(fn (array $row) => $row['remaining'] > 0)
            ->values();

        return view('admin.waste.index', [
            'date' => $date->toDateString(),
            'leftovers' => $leftovers,
            'reasons' => WasteReason::cases(),
            'logs' => WasteLog::with('product', 'batch', 'creator')->whereDate('log_date', $date->toDateString())->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, BatchService $batches): RedirectResponse
    {
        $data = $request->validate([
            'batch_id' => ['required', 'exists:batches,id'],
            'quantity_kg' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'reason' => ['required', Rule::enum(WasteReason::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $batches->logWaste(Batch::findOrFail($data['batch_id']), (float) $data['quantity_kg'], WasteReason::from($data['reason']), $request->user(), $data['note'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('status', 'Đã ghi nhận rau dư.');
    }
}
```

- [ ] **Step 4: Viết `app/Http/Controllers/Admin/PackagingReturnController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PackagingReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackagingReturnController extends Controller
{
    public function index(): View
    {
        return view('admin.packaging-returns.index', [
            'returns' => PackagingReturn::with('customer', 'order')->orderByDesc('return_date')->orderByDesc('id')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'return_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today()->toDateString()],
            'phone' => ['nullable', 'regex:/^0\d{9}$/'],
            'order_code' => ['nullable', 'string', 'exists:orders,code'],
            'quantity_returned' => ['required', 'integer', 'min:1', 'max:500'],
            'quantity_reused' => ['required', 'integer', 'min:0', 'lte:quantity_returned'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $order = filled($data['order_code'] ?? null) ? Order::where('code', $data['order_code'])->first() : null;
        $customer = filled($data['phone'] ?? null) ? Customer::where('phone', $data['phone'])->first() : null;

        PackagingReturn::create([
            'return_date' => $data['return_date'],
            'customer_id' => $customer?->id ?? $order?->customer_id,
            'order_id' => $order?->id,
            'quantity_returned' => $data['quantity_returned'],
            'quantity_reused' => $data['quantity_reused'],
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', 'Đã ghi nhận bao bì thu hồi.');
    }
}
```

Thêm vào nhóm admin trong `routes/web.php`:

```php
    Route::get('waste', [WasteController::class, 'index'])->name('waste.index');
    Route::post('waste', [WasteController::class, 'store'])->name('waste.store');
    Route::get('packaging-returns', [PackagingReturnController::class, 'index'])->name('packaging-returns.index');
    Route::post('packaging-returns', [PackagingReturnController::class, 'store'])->name('packaging-returns.store');
```

Thêm `'lte' => ':Attribute không được lớn hơn :value.'` vào `lang/vi/validation.php`, và `'quantity_returned' => 'số bao bì thu hồi'`, `'quantity_reused' => 'số bao bì tái sử dụng'`, `'reason' => 'lý do'` vào mảng `attributes`.

- [ ] **Step 5: Viết view**

`admin/waste/index.blade.php` (title "Rau dư cuối ngày"):
- Form GET chọn ngày; link "Bao bì thu hồi" → `admin.packaging-returns.index`.
- Khối "Lô còn tồn": mỗi lô một dòng với mã lô, tên rau, nông hộ, hạn dùng, còn lại `@kg($row['remaining'])`, và form `POST admin.waste.store`: input ẩn `batch_id`, input số `quantity_kg` (step 0.1, mặc định `$row['remaining']`), select `reason` (label của `WasteReason`), input `note`, nút "Ghi nhận". Dòng hướng dẫn: "Lô còn hạn có thể để lại bán ngày mai; chỉ ghi phần thực sự dư, hỏng, hết hạn hoặc bán giảm giá."
- Khi rỗng: "Không còn lô nào tồn."
- Bảng "Đã ghi trong ngày": rau, mã lô, `@kg`, lý do (label), ghi chú, người ghi.

`admin/packaging-returns/index.blade.php` (title "Bao bì thu hồi"):
- Form `POST admin.packaging-returns.store`: `return_date` (mặc định hôm nay), `phone`, `order_code`, `quantity_returned`, `quantity_reused`, `note`, nút "Ghi nhận".
- Bảng: ngày, tên khách (hoặc "—"), mã đơn (hoặc "—"), thu hồi, tái sử dụng, ghi chú; phân trang.

Thêm link "Bao bì" vào menu admin trong `components/layouts/admin.blade.php` (`'admin.packaging-returns.index' => 'Bao bì'`).

- [ ] **Step 6: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter=WasteAndPackagingTest`
Expected: PASS (7 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: end-of-day waste logging and packaging returns"
```

---

### Task 6.2: `KpiService`

**Files:**
- Create: `app/Services/KpiService.php`
- Modify: `app/Support/Format.php` (thêm `pct`)
- Test: `tests/Feature/Kpi/KpiServiceOnSampleDataTest.php`, `tests/Feature/Kpi/KpiServiceEmptyTest.php`, bổ sung `tests/Unit/Support/FormatTest.php`

**Interfaces:**
- Consumes: `ForecastService::accuracy`.
- Produces (mọi hàm nhận `CarbonInterface $from, CarbonInterface $to`, tính theo ngày giao trừ khi ghi khác):
  - `KpiService::forecast()` → `['accuracy' => array, 'plan_items' => int, 'adjusted_items' => int]`
  - `KpiService::customers()` → `['fill_rate_pct', 'customers', 'repeat_customers', 'repeat_rate_pct', 'feedback_total', 'feedback_resolved', 'avg_rating']`
  - `KpiService::economics()` → `['delivered_orders', 'revenue', 'avg_order_value', 'goods_revenue', 'purchase_cost', 'gross_margin_pct', 'shipping_per_order', 'revenue_by_day']`
  - `KpiService::farmers()` → `['active_farmers', 'advance_confirmed_pct']`
  - `KpiService::environment()` → `['waste_by_week', 'received_by_week', 'waste_kg', 'received_kg', 'waste_pct', 'packaging_returned', 'packaging_reused', 'packaging_reuse_pct']`
  - `Format::pct(?float $value): string` → `'98,3%'`, `'96%'`, `'—'` khi `null`.
  - Tỷ lệ là `?float` (1 chữ số thập phân), `null` khi mẫu số bằng 0.

- [ ] **Step 1: Viết test (sẽ fail)**

Thêm vào `tests/Unit/Support/FormatTest.php`:

```php
    public function test_pct_uses_comma_and_handles_null(): void
    {
        $this->assertSame('98,3%', Format::pct(98.3));
        $this->assertSame('96%', Format::pct(96.0));
        $this->assertSame('0%', Format::pct(0.0));
        $this->assertSame('—', Format::pct(null));
    }
```

`tests/Feature/Kpi/KpiServiceOnSampleDataTest.php` — mọi con số dưới đây được tính độc lập từ `database/farm2home.sql` cho khoảng 10/09–03/10/2026:

```php
<?php

namespace Tests\Feature\Kpi;

use App\Services\KpiService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiServiceOnSampleDataTest extends TestCase
{
    use RefreshDatabase;

    private KpiService $kpi;

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 21:00:00');
        $this->seed(DemoSeeder::class);
        $this->kpi = app(KpiService::class);
        $this->from = CarbonImmutable::parse('2026-09-10');
        $this->to = CarbonImmutable::parse('2026-10-03');
    }

    public function test_forecast_kpis(): void
    {
        $kpi = $this->kpi->forecast($this->from, $this->to);

        $this->assertSame(['n' => 105, 'mae' => 0.65, 'mape' => 51.4, 'wape' => 39.1], $kpi['accuracy']['moving_average']);
        $this->assertSame(112, $kpi['plan_items']);
        $this->assertSame(38, $kpi['adjusted_items']);
    }

    public function test_customer_kpis(): void
    {
        $this->assertSame([
            'fill_rate_pct' => 98.3,
            'customers' => 25,
            'repeat_customers' => 24,
            'repeat_rate_pct' => 96.0,
            'feedback_total' => 45,
            'feedback_resolved' => 38,
            'avg_rating' => 4.64,
        ], $this->kpi->customers($this->from, $this->to));
    }

    public function test_economic_kpis(): void
    {
        $kpi = $this->kpi->economics($this->from, $this->to);

        $this->assertSame(131, $kpi['delivered_orders']);
        $this->assertSame(7773000, $kpi['revenue']);
        $this->assertSame(59336, $kpi['avg_order_value']);
        $this->assertSame(6453000, $kpi['goods_revenue']);
        $this->assertSame(5634200, $kpi['purchase_cost']);
        $this->assertSame(12.7, $kpi['gross_margin_pct']);
        $this->assertSame(10076, $kpi['shipping_per_order']);
        $this->assertCount(22, $kpi['revenue_by_day']);
        $this->assertSame(7773000, array_sum($kpi['revenue_by_day']));
    }

    public function test_farmer_kpis(): void
    {
        $this->assertSame(['active_farmers' => 3, 'advance_confirmed_pct' => 100.0], $this->kpi->farmers($this->from, $this->to));
    }

    public function test_environment_kpis(): void
    {
        $kpi = $this->kpi->environment($this->from, $this->to);

        $this->assertSame(['2026-W37' => 2.6, '2026-W38' => 20.7, '2026-W39' => 36.2, '2026-W40' => 28.2], $kpi['waste_by_week']);
        $this->assertSame(['2026-W37', '2026-W38', '2026-W39', '2026-W40'], array_keys($kpi['received_by_week']));
        $this->assertSame(87.7, $kpi['waste_kg']);
        $this->assertSame(322.9, $kpi['received_kg']);
        $this->assertSame(27.2, $kpi['waste_pct']);
        $this->assertSame([12, 10, 83.3], [$kpi['packaging_returned'], $kpi['packaging_reused'], $kpi['packaging_reuse_pct']]);
    }

    public function test_range_is_respected(): void
    {
        $kpi = $this->kpi->economics(CarbonImmutable::parse('2026-09-10'), CarbonImmutable::parse('2026-09-10'));

        // 10/09 có 3 đơn đã giao: 34.000 + 35.000 + 59.000.
        $this->assertSame([3, 128000], [$kpi['delivered_orders'], $kpi['revenue']]);
    }
}
```

`tests/Feature/Kpi/KpiServiceEmptyTest.php`:

```php
<?php

namespace Tests\Feature\Kpi;

use App\Services\KpiService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiServiceEmptyTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_gives_nulls_and_zeros_not_division_errors(): void
    {
        $kpi = app(KpiService::class);
        $from = CarbonImmutable::parse('2026-10-01');
        $to = CarbonImmutable::parse('2026-10-07');

        $this->assertSame(['accuracy' => [], 'plan_items' => 0, 'adjusted_items' => 0], $kpi->forecast($from, $to));
        $this->assertNull($kpi->customers($from, $to)['fill_rate_pct']);
        $this->assertNull($kpi->customers($from, $to)['repeat_rate_pct']);
        $this->assertNull($kpi->customers($from, $to)['avg_rating']);
        $this->assertSame(0, $kpi->economics($from, $to)['revenue']);
        $this->assertNull($kpi->economics($from, $to)['avg_order_value']);
        $this->assertNull($kpi->economics($from, $to)['gross_margin_pct']);
        $this->assertNull($kpi->farmers($from, $to)['advance_confirmed_pct']);
        $this->assertSame([], $kpi->environment($from, $to)['waste_by_week']);
        $this->assertNull($kpi->environment($from, $to)['waste_pct']);
        $this->assertNull($kpi->environment($from, $to)['packaging_reuse_pct']);
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter="KpiService|FormatTest"`
Expected: FAIL (class / method not found).

- [ ] **Step 3: Thêm `Format::pct`**

```php
    public static function pct(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        $text = number_format($value, 1, ',', '.');

        return (str_ends_with($text, ',0') ? substr($text, 0, -2) : $text).'%';
    }
```

- [ ] **Step 4: Viết `app/Services/KpiService.php`**

```php
<?php

namespace App\Services;

use App\Enums\FarmerStatus;
use App\Enums\FeedbackStatus;
use App\Enums\OrderStatus;
use App\Models\Batch;
use App\Models\Farmer;
use App\Models\Feedback;
use App\Models\HarvestPlanItem;
use App\Models\Order;
use App\Models\PackagingReturn;
use App\Models\WasteLog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class KpiService
{
    public function __construct(private readonly ForecastService $forecasts) {}

    public function forecast(CarbonInterface $from, CarbonInterface $to): array
    {
        $items = $this->planItems($from, $to);

        return [
            'accuracy' => $this->forecasts->accuracy($from, $to),
            'plan_items' => (clone $items)->count(),
            // Số lần operator chốt khác số gợi ý.
            'adjusted_items' => (clone $items)->whereColumn('final_kg', '!=', 'suggested_kg')->count(),
        ];
    }

    public function customers(CarbonInterface $from, CarbonInterface $to): array
    {
        $dates = $this->dates($from, $to);

        $fill = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.delivery_date', $dates)
            ->whereNotNull('order_items.fulfilled_quantity')
            ->selectRaw('COALESCE(SUM(order_items.fulfilled_quantity), 0) as fulfilled, COALESCE(SUM(order_items.quantity), 0) as ordered')
            ->first();

        $ordersPerCustomer = DB::table('orders')
            ->whereBetween('delivery_date', $dates)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as orders')
            ->pluck('orders', 'customer_id');

        $repeat = $ordersPerCustomer->filter(fn ($orders) => $orders >= 2)->count();

        $feedbacks = Feedback::whereBetween('created_at', $this->moments($from, $to));
        $averageRating = (clone $feedbacks)->avg('rating');

        return [
            'fill_rate_pct' => self::pct((float) $fill->fulfilled, (float) $fill->ordered),
            'customers' => $ordersPerCustomer->count(),
            'repeat_customers' => $repeat,
            'repeat_rate_pct' => self::pct($repeat, $ordersPerCustomer->count()),
            'feedback_total' => (clone $feedbacks)->count(),
            'feedback_resolved' => (clone $feedbacks)->where('status', FeedbackStatus::Resolved)->count(),
            'avg_rating' => $averageRating === null ? null : round((float) $averageRating, 2),
        ];
    }

    public function economics(CarbonInterface $from, CarbonInterface $to): array
    {
        $delivered = DB::table('orders')
            ->whereBetween('delivery_date', $this->dates($from, $to))
            ->where('status', OrderStatus::Delivered->value);

        $count = (clone $delivered)->count();
        $revenue = (int) (clone $delivered)->sum('total');
        $goods = (int) (clone $delivered)->sum('subtotal') - (int) (clone $delivered)->sum('discount');
        $shipping = (int) (clone $delivered)->sum('shipping_fee');

        // Quyết định D16: giá vốn tính trên toàn bộ hàng đã nhận, nên rau dư làm giảm biên.
        $cost = (int) round((float) DB::table('batches')
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->whereBetween('batches.harvested_at', $this->moments($from, $to))
            ->sum(DB::raw('batches.quantity_kg * products.cost_price_per_kg')));

        return [
            'delivered_orders' => $count,
            'revenue' => $revenue,
            'avg_order_value' => $count > 0 ? (int) round($revenue / $count) : null,
            'goods_revenue' => $goods,
            'purchase_cost' => $cost,
            'gross_margin_pct' => self::pct($goods - $cost, $goods),
            'shipping_per_order' => $count > 0 ? (int) round($shipping / $count) : null,
            'revenue_by_day' => (clone $delivered)
                ->groupBy('delivery_date')
                ->orderBy('delivery_date')
                ->selectRaw('delivery_date as day, SUM(total) as total')
                ->pluck('total', 'day')
                ->map(fn ($total) => (int) $total)
                ->all(),
        ];
    }

    public function farmers(CarbonInterface $from, CarbonInterface $to): array
    {
        $items = $this->planItems($from, $to);
        $finalKg = (float) (clone $items)->sum('final_kg');
        $confirmedKg = (float) (clone $items)->whereNotNull('farmer_confirmed_at')->sum('final_kg');

        return [
            'active_farmers' => Farmer::where('status', FarmerStatus::Active)->count(),
            // % sản lượng nông hộ đã xác nhận trước khi thu hoạch.
            'advance_confirmed_pct' => self::pct($confirmedKg, $finalKg),
        ];
    }

    public function environment(CarbonInterface $from, CarbonInterface $to): array
    {
        $week = fn (CarbonInterface $date) => $date->format('o-\WW');
        $sumByWeek = fn ($rows, string $dateColumn) => $rows
            ->groupBy(fn ($row) => $week($row->{$dateColumn}))
            ->map(fn ($group) => round($group->sum(fn ($row) => (float) $row->quantity_kg), 2))
            ->sortKeys()
            ->all();

        $waste = WasteLog::whereBetween('log_date', $this->dates($from, $to))->get(['log_date', 'quantity_kg']);
        $batches = Batch::whereBetween('harvested_at', $this->moments($from, $to))->get(['harvested_at', 'quantity_kg']);
        $packaging = PackagingReturn::whereBetween('return_date', $this->dates($from, $to));

        $wasteKg = round($waste->sum(fn ($row) => (float) $row->quantity_kg), 2);
        $receivedKg = round($batches->sum(fn ($row) => (float) $row->quantity_kg), 2);
        $returned = (int) (clone $packaging)->sum('quantity_returned');
        $reused = (int) (clone $packaging)->sum('quantity_reused');

        return [
            'waste_by_week' => $sumByWeek($waste, 'log_date'),
            'received_by_week' => $sumByWeek($batches, 'harvested_at'),
            'waste_kg' => $wasteKg,
            'received_kg' => $receivedKg,
            'waste_pct' => self::pct($wasteKg, $receivedKg),
            'packaging_returned' => $returned,
            'packaging_reused' => $reused,
            'packaging_reuse_pct' => self::pct($reused, $returned),
        ];
    }

    private function planItems(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return HarvestPlanItem::whereNotNull('final_kg')
            ->whereHas('plan', fn ($query) => $query->whereBetween('delivery_date', $this->dates($from, $to)));
    }

    /** @return array{0: string, 1: string} */
    private function dates(CarbonInterface $from, CarbonInterface $to): array
    {
        return [$from->toDateString(), $to->toDateString()];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function moments(CarbonInterface $from, CarbonInterface $to): array
    {
        return [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay()];
    }

    private static function pct(float|int $part, float|int $whole): ?float
    {
        return $whole > 0 ? round(100 * $part / $whole, 1) : null;
    }
}
```

Đảm bảo `WasteLog` cast `'log_date' => 'date'`, `Batch` cast `'harvested_at' => 'datetime'`, `PackagingReturn` cast `'return_date' => 'date'`.

- [ ] **Step 5: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter="KpiService|FormatTest"`
Expected: PASS.

Nếu một con số lệch so với kỳ vọng, **không sửa số trong test**: các số đó được tính độc lập từ file SQL. Hãy kiểm tra lại điều kiện lọc (trạng thái đơn, khoảng ngày, cột ngày dùng để lọc).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: KPI service for forecast, customers, economics, farmers and environment"
```

---

### Task 6.3: Dashboard KPI

**Files:**
- Create: `resources/js/dashboard.js`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`, `resources/views/admin/dashboard.blade.php`, `vite.config.js` (thêm entry), `app/Providers/AppServiceProvider.php` (directive `@pct`)
- Test: `tests/Feature/Kpi/DashboardTest.php`

**Interfaces:**
- Consumes: toàn bộ `KpiService`, `Format::pct`.
- Produces: `GET /admin?from=Y-m-d&to=Y-m-d` (mặc định 28 ngày gần nhất); Blade directive `@pct($value)`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Kpi/DashboardTest.php`:

```php
<?php

namespace Tests\Feature\Kpi;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 21:00:00');
    }

    public function test_dashboard_shows_all_five_kpi_groups_for_the_sample_period(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'operator@farm2home.vn')->firstOrFail());

        $this->get('/admin?from=2026-09-10&to=2026-10-03')
            ->assertOk()
            // Dự báo
            ->assertSee('Dự báo')->assertSee('WAPE')->assertSee('39,1%')->assertSee('38 / 112')
            // Khách hàng
            ->assertSee('Khách hàng')->assertSee('98,3%')->assertSee('96%')->assertSee('4,64')->assertSee('38 / 45')
            // Kinh tế
            ->assertSee('Kinh tế')->assertSee('7.773.000đ')->assertSee('59.336đ')->assertSee('12,7%')
            // Nông hộ
            ->assertSee('Nông hộ')->assertSee('100%')
            // Môi trường
            ->assertSee('Môi trường')->assertSee('87,7 kg')->assertSee('27,2%')->assertSee('83,3%')
            ->assertDontSee('CO₂')->assertDontSee('CO2');
    }

    public function test_default_range_is_the_last_28_days(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@farm2home.vn')->firstOrFail());

        $this->get('/admin')->assertOk()->assertSee('value="2026-09-06"', false)->assertSee('value="2026-10-03"', false);
    }

    public function test_empty_database_renders_dashes_instead_of_errors(): void
    {
        $user = User::create(['name' => 'Op', 'email' => 'op@example.test', 'password' => 'password', 'role' => 'operator']);

        $this->actingAs($user)->get('/admin')->assertOk()->assertSee('—')->assertSee('Chưa có dữ liệu trong khoảng này');
    }

    public function test_invalid_range_is_rejected(): void
    {
        $user = User::create(['name' => 'Op', 'email' => 'op@example.test', 'password' => 'password', 'role' => 'operator']);

        $this->actingAs($user)->get('/admin?from=2026-10-03&to=2026-10-01')->assertSessionHasErrors('to');
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=DashboardTest`
Expected: FAIL (trang hiện tại chỉ có câu giữ chỗ).

- [ ] **Step 3: Viết lại `DashboardController`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ForecastMethod;
use App\Http\Controllers\Controller;
use App\Services\KpiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, KpiService $kpi): View
    {
        $range = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $to = CarbonImmutable::parse($range['to'] ?? today());
        $from = CarbonImmutable::parse($range['from'] ?? $to->subDays(27));

        return view('admin.dashboard', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'forecast' => $kpi->forecast($from, $to),
            'customers' => $kpi->customers($from, $to),
            'economics' => $kpi->economics($from, $to),
            'farmers' => $kpi->farmers($from, $to),
            'environment' => $kpi->environment($from, $to),
            'methods' => ForecastMethod::cases(),
        ]);
    }
}
```

Thêm vào `lang/vi/validation.php`: không cần key mới (`after_or_equal` đã có); thêm `'from' => 'từ ngày'`, `'to' => 'đến ngày'` vào `attributes`.

Trong `AppServiceProvider::boot()` thêm:

```php
        Blade::directive('pct', fn (string $expression) => "<?php echo e(\\App\\Support\\Format::pct({$expression})); ?>");
```

- [ ] **Step 4: Viết `resources/js/dashboard.js` và đăng ký entry**

```js
import Chart from 'chart.js/auto';

const read = (id) => JSON.parse(document.getElementById(id).textContent);

const wasteCanvas = document.getElementById('chart-waste');
if (wasteCanvas) {
    const data = read('chart-waste-data');
    new Chart(wasteCanvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                { label: 'Nhận từ nông hộ (kg)', data: data.received, backgroundColor: '#a7f3d0' },
                { label: 'Rau dư (kg)', data: data.waste, backgroundColor: '#fb7185' },
            ],
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } },
    });
}

const revenueCanvas = document.getElementById('chart-revenue');
if (revenueCanvas) {
    const data = read('chart-revenue-data');
    new Chart(revenueCanvas, {
        type: 'line',
        data: { labels: data.labels, datasets: [{ label: 'Doanh thu (đ)', data: data.values, borderColor: '#047857', tension: 0.2 }] },
        options: { responsive: true, scales: { y: { beginAtZero: true } } },
    });
}
```

Trong `vite.config.js`, đổi `input` thành:

```js
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/dashboard.js'],
```

- [ ] **Step 5: Viết `resources/views/admin/dashboard.blade.php`**

Layout `<x-layouts.admin title="Tổng quan">`, có slot `head` chứa `@vite('resources/js/dashboard.js')`.

- Form GET: hai input `date` tên `from`, `to` (giá trị `$from`, `$to`), nút "Xem"; lỗi `@error('to')`.
- Khi `$economics['delivered_orders'] === 0 && $forecast['plan_items'] === 0`: hiện dòng "Chưa có dữ liệu trong khoảng này." phía trên các thẻ.
- Năm khối, mỗi khối là lưới thẻ số (nhãn nhỏ + số lớn):
  1. **Dự báo:** bảng theo `$methods` có trong `$forecast['accuracy']` (label, n, MAE `@kg`, MAPE `@pct`, WAPE `@pct`); thẻ "Số lần operator điều chỉnh": `{{ $forecast['adjusted_items'] }} / {{ $forecast['plan_items'] }}`.
  2. **Khách hàng:** "Tỷ lệ giao đủ" `@pct($customers['fill_rate_pct'])`; "Khách mua lại" `@pct($customers['repeat_rate_pct'])` (phụ: `repeat_customers / customers`); "Điểm đánh giá" `{{ $customers['avg_rating'] === null ? '—' : number_format($customers['avg_rating'], 2, ',', '.') }}`; "Phản hồi đã xử lý" `{{ $customers['feedback_resolved'] }} / {{ $customers['feedback_total'] }}`.
  3. **Kinh tế:** "Doanh thu" `@vnd($economics['revenue'])`; "Giá trị đơn trung bình" (`@vnd` hoặc "—" khi null); "Biên gộp" `@pct($economics['gross_margin_pct'])` kèm chú thích "tiền hàng đã giao trừ giá mua toàn bộ hàng đã nhận"; "Phí giao thu / đơn" (`@vnd` hoặc "—"); biểu đồ `<canvas id="chart-revenue">`.
  4. **Nông hộ:** "Nông hộ đang hợp tác" `{{ $farmers['active_farmers'] }}`; "Sản lượng được xác nhận trước" `@pct($farmers['advance_confirmed_pct'])`.
  5. **Môi trường:** "Rau dư" `@kg($environment['waste_kg'])`; "Tỷ lệ rau dư / hàng nhận" `@pct($environment['waste_pct'])`; "Bao bì thu hồi" `{{ $environment['packaging_returned'] }}`; "Tỷ lệ tái sử dụng" `@pct($environment['packaging_reuse_pct'])`; biểu đồ `<canvas id="chart-waste">`. Không có bất kỳ chỉ số CO₂ nào.
- Dữ liệu biểu đồ đặt trong thẻ JSON:

```blade
<script type="application/json" id="chart-waste-data">@json([
    'labels' => array_keys($environment['received_by_week']),
    'received' => array_values($environment['received_by_week']),
    'waste' => array_map(fn ($week) => $environment['waste_by_week'][$week] ?? 0, array_keys($environment['received_by_week'])),
])</script>
<script type="application/json" id="chart-revenue-data">@json([
    'labels' => array_keys($economics['revenue_by_day']),
    'values' => array_values($economics['revenue_by_day']),
])</script>
```

- [ ] **Step 6: Chạy test, build, commit**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (4 tests).

Run: `npm run build`
Expected: thành công, có file `dashboard-*.js` trong `public/build/assets`.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: KPI dashboard with waste and revenue charts"
```

---

### Task 6.4: Quản trị dữ liệu gốc (chỉ role admin)

**Files:**
- Create: `app/Http/Controllers/Admin/{ProductController,BasketController,FarmerController,DeliveryZoneController,UserController}.php`, view `resources/views/admin/{products,baskets,farmers,zones,users}/{index,form}.blade.php`
- Modify: `routes/web.php`, `components/layouts/admin.blade.php` (menu "Danh mục" chỉ hiện cho admin)
- Test: `tests/Feature/Admin/MasterDataTest.php`

**Interfaces:**
- Produces: resource route `admin.products.*`, `admin.baskets.*`, `admin.farmers.*`, `admin.zones.*`, `admin.users.*` (chỉ `index, create, store, edit, update`); `admin.farmers.token` (POST `/admin/farmers/{farmer}/token`).

Quy tắc chung: không có xóa cứng (dữ liệu lịch sử tham chiếu tới chúng); ngừng dùng bằng `is_active = false` hoặc `status`. View `form.blade.php` dùng chung cho tạo và sửa.

| Thực thể | Field và rule |
|---|---|
| Product | `code` required, max:10, viết hoa, unique `products.code` (bỏ qua bản ghi đang sửa) · `name` required max:150 · `slug` tự sinh `Str::slug(name)`, unique · `pack_weight_kg` required numeric min:0.05 max:10 · `price` required integer min:0 · `cost_price_per_kg` required integer min:0 · `shelf_life_days` required integer min:1 max:30 · `storage_guide` nullable string · `is_active` boolean |
| Basket | `name` required max:150 · `slug` tự sinh, unique · `description` nullable · `household_min`, `household_max` required integer min:1 max:20, `household_max` gte:household_min · `price` required integer min:0 · `is_active` boolean · `items` required array min:1, `items.*.product_id` exists, `items.*.quantity` integer min:0 max:20 (dòng 0 bị bỏ), `items.*.allow_substitute` boolean |
| Farmer | `code` required max:20 unique · `name` required max:150 · `phone`, `zalo_phone` regex `^0\d{9}$` (`zalo_phone` nullable) · `address`, `growing_area`, `farming_method` nullable max:255/255/150 · `area_m2` nullable integer min:0 · `certificate_name` nullable max:150 · `certificate` nullable file mimes:pdf,jpg,jpeg,png max:4096 · `certificate_expires_at` nullable date · `status` enum `FarmerStatus` · `note` nullable · `products` array `[product_id => typical_weekly_kg]` numeric min:0 (rỗng thì không gắn) |
| DeliveryZone | `name` required max:100 · `description` nullable max:255 · `shipping_fee` required integer min:0 · `free_ship_from` nullable integer min:0 · `pickup_point` nullable max:255 · `is_active` boolean |
| User | `name` required max:255 · `email` required email unique · `phone` nullable regex · `role` enum `UserRole` · `password` required khi tạo, nullable khi sửa, min:8 |

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Admin/MasterDataTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Basket;
use App\Models\DeliveryZone;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
    }

    public function test_operator_cannot_manage_master_data(): void
    {
        $this->actingAs($this->operator);

        foreach (['products', 'baskets', 'farmers', 'zones', 'users'] as $resource) {
            $this->get("/admin/{$resource}")->assertForbidden();
        }
        $this->post('/admin/products', [])->assertForbidden();
    }

    public function test_admin_sees_every_index(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/products')->assertOk()->assertSee('Rau muống')->assertSee('12.000đ');
        $this->get('/admin/baskets')->assertOk()->assertSee('Giỏ Mini');
        $this->get('/admin/farmers')->assertOk()->assertSee('Nguyễn Văn Tâm')->assertSee('/nong-ho/'.$this->nh01->access_token);
        $this->get('/admin/zones')->assertOk()->assertSee('Cụm 1 – Trung tâm');
        $this->get('/admin/users')->assertOk()->assertSee('operator@example.test');
    }

    public function test_admin_creates_and_updates_a_product(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/products', [
            'code' => 'mt', 'name' => 'Mồng tơi', 'pack_weight_kg' => '0.5', 'price' => 12000,
            'cost_price_per_kg' => 14000, 'shelf_life_days' => 2, 'storage_guide' => 'Ngăn mát', 'is_active' => '1',
        ])->assertRedirect('/admin/products');

        $product = Product::where('code', 'MT')->firstOrFail();
        $this->assertSame(['mong-toi', '0.500', true], [$product->slug, $product->pack_weight_kg, $product->is_active]);

        $this->put('/admin/products/'.$product->id, [
            'code' => 'MT', 'name' => 'Mồng tơi', 'pack_weight_kg' => '0.5', 'price' => 13000,
            'cost_price_per_kg' => 14000, 'shelf_life_days' => 2,
        ])->assertRedirect('/admin/products');

        $this->assertSame([13000, false], [$product->refresh()->price, $product->is_active]);
    }

    public function test_product_code_must_be_unique(): void
    {
        $this->actingAs($this->admin)->post('/admin/products', [
            'code' => 'RM', 'name' => 'Rau muống 2', 'pack_weight_kg' => '0.5', 'price' => 1, 'cost_price_per_kg' => 1, 'shelf_life_days' => 2,
        ])->assertSessionHasErrors('code');
    }

    public function test_admin_creates_a_basket_with_items(): void
    {
        $this->actingAs($this->admin)->post('/admin/baskets', [
            'name' => 'Giỏ Gia Đình', 'household_min' => 3, 'household_max' => 5, 'price' => 115000, 'is_active' => '1',
            'items' => [
                ['product_id' => $this->rm->id, 'quantity' => 2, 'allow_substitute' => '1'],
                ['product_id' => $this->cn->id, 'quantity' => 0],
                ['product_id' => $this->hl->id, 'quantity' => 1],
            ],
        ])->assertRedirect('/admin/baskets');

        $basket = Basket::where('slug', 'gio-gia-dinh')->firstOrFail();
        $this->assertSame([$this->rm->id => 2, $this->hl->id => 1], $basket->items->pluck('quantity', 'product_id')->all());
    }

    public function test_basket_needs_at_least_one_item_and_a_valid_household_range(): void
    {
        $this->actingAs($this->admin)->post('/admin/baskets', [
            'name' => 'Giỏ rỗng', 'household_min' => 5, 'household_max' => 3, 'price' => 1,
            'items' => [['product_id' => $this->rm->id, 'quantity' => 0]],
        ])->assertSessionHasErrors(['household_max', 'items']);
    }

    public function test_admin_creates_a_farmer_with_token_products_and_certificate(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post('/admin/farmers', [
            'code' => 'NH03', 'name' => 'Lê Văn Phúc', 'phone' => '0911000003', 'status' => 'active',
            'growing_area' => 'Gò Công, Đồng Tháp',
            'certificate_name' => 'VietGAP', 'certificate_expires_at' => '2027-12-31',
            'certificate' => UploadedFile::fake()->create('vietgap.pdf', 200, 'application/pdf'),
            'products' => [$this->rm->id => '30', $this->cn->id => ''],
        ])->assertRedirect('/admin/farmers');

        $farmer = Farmer::where('code', 'NH03')->firstOrFail();
        $this->assertSame(40, strlen($farmer->access_token));
        $this->assertSame([$this->rm->id], $farmer->products->pluck('id')->all());
        $this->assertTrue($farmer->hasValidCertificate());
        Storage::disk('public')->assertExists($farmer->certificate_file);
    }

    public function test_regenerating_the_token_invalidates_the_old_link(): void
    {
        $old = $this->nh01->access_token;

        $this->actingAs($this->admin)->post('/admin/farmers/'.$this->nh01->id.'/token')->assertRedirect();

        $this->assertNotSame($old, $this->nh01->refresh()->access_token);
        auth()->logout();
        $this->get('/nong-ho/'.$old)->assertNotFound();
        $this->get('/nong-ho/'.$this->nh01->access_token)->assertOk();
    }

    public function test_admin_manages_zones(): void
    {
        $this->actingAs($this->admin)->post('/admin/zones', [
            'name' => 'Cụm 2', 'shipping_fee' => 15000, 'free_ship_from' => 200000, 'is_active' => '1',
        ])->assertRedirect('/admin/zones');

        $this->assertSame(15000, DeliveryZone::where('name', 'Cụm 2')->firstOrFail()->shipping_fee);
    }

    public function test_admin_creates_a_user_and_cannot_demote_themselves(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Vận hành 2', 'email' => 'op2@example.test', 'role' => 'operator', 'password' => 'mat-khau-dai',
        ])->assertRedirect('/admin/users');

        $created = User::where('email', 'op2@example.test')->firstOrFail();
        $this->assertSame(UserRole::Operator, $created->role);
        $this->assertTrue(password_verify('mat-khau-dai', $created->password));

        $this->from('/admin/users/'.$this->admin->id.'/edit')
            ->put('/admin/users/'.$this->admin->id, ['name' => 'Admin', 'email' => 'admin@example.test', 'role' => 'operator'])
            ->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Admin, $this->admin->refresh()->role);
    }

    public function test_short_password_is_rejected_and_blank_password_keeps_the_old_one(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/users', ['name' => 'X', 'email' => 'x@example.test', 'role' => 'operator', 'password' => '123'])
            ->assertSessionHasErrors('password');

        $hash = $this->operator->password;
        $this->put('/admin/users/'.$this->operator->id, ['name' => 'Operator mới', 'email' => 'operator@example.test', 'role' => 'operator', 'password' => ''])
            ->assertRedirect('/admin/users');

        $this->assertSame([$hash, 'Operator mới'], [$this->operator->refresh()->password, $this->operator->name]);
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=MasterDataTest`
Expected: FAIL (404).

- [ ] **Step 3: Thêm route**

Trong nhóm `admin`, thêm nhóm con:

```php
    Route::middleware('role:admin')->group(function () {
        $actions = ['index', 'create', 'store', 'edit', 'update'];

        Route::resource('products', ProductController::class)->only($actions);
        Route::resource('baskets', BasketController::class)->only($actions);
        Route::resource('farmers', FarmerController::class)->only($actions);
        Route::post('farmers/{farmer}/token', [FarmerController::class, 'regenerateToken'])->name('farmers.token');
        Route::resource('zones', DeliveryZoneController::class)->only($actions)->parameters(['zones' => 'zone']);
        Route::resource('users', UserController::class)->only($actions);
    });
```

- [ ] **Step 4: Viết `ProductController` (mẫu cho các controller còn lại)**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', ['products' => Product::orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => new Product(['is_active' => true, 'pack_weight_kg' => 0.5, 'shelf_life_days' => 3])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Product::create($this->validated($request, new Product));

        return redirect()->route('admin.products.index')->with('status', 'Đã thêm sản phẩm.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', ['product' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));

        return redirect()->route('admin.products.index')->with('status', 'Đã cập nhật sản phẩm.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Product $product): array
    {
        $request->merge(['code' => Str::upper((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('products', 'code')->ignore($product->id)],
            'name' => ['required', 'string', 'max:150'],
            'pack_weight_kg' => ['required', 'numeric', 'min:0.05', 'max:10'],
            'price' => ['required', 'integer', 'min:0'],
            'cost_price_per_kg' => ['required', 'integer', 'min:0'],
            'shelf_life_days' => ['required', 'integer', 'min:1', 'max:30'],
            'storage_guide' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['slug'] = $this->uniqueSlug($data['name'], $product);

        return $data;
    }

    private function uniqueSlug(string $name, Product $product): string
    {
        $slug = Str::slug($name);
        $taken = Product::where('slug', $slug)->where('id', '!=', $product->id ?? 0)->exists();

        return $taken ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
    }
}
```

- [ ] **Step 5: Viết 4 controller còn lại theo cùng mẫu, với các điểm riêng sau**

`BasketController` — lưu trong transaction, thay toàn bộ dòng giỏ:

```php
    private function save(Request $request, Basket $basket): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'household_min' => ['required', 'integer', 'min:1', 'max:20'],
            'household_max' => ['required', 'integer', 'min:1', 'max:20', 'gte:household_min'],
            'price' => ['required', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:20'],
            'items.*.allow_substitute' => ['nullable', 'boolean'],
        ]);

        $lines = collect($data['items'])->filter(fn (array $line) => (int) $line['quantity'] > 0)->unique('product_id');

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Giỏ phải có ít nhất một loại rau.']);
        }

        DB::transaction(function () use ($basket, $data, $lines, $request) {
            $basket->fill([
                'name' => $data['name'],
                'slug' => $basket->exists ? $basket->slug : Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'household_min' => $data['household_min'],
                'household_max' => $data['household_max'],
                'price' => $data['price'],
                'is_active' => $request->boolean('is_active'),
            ])->save();

            $basket->items()->delete();
            foreach ($lines as $line) {
                $basket->items()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => (int) $line['quantity'],
                    'allow_substitute' => (bool) ($line['allow_substitute'] ?? true),
                ]);
            }
        });
    }
```

Thêm `'gte' => ':Attribute phải lớn hơn hoặc bằng :value.'` vào `lang/vi/validation.php`.

`FarmerController` — token, file chứng nhận, sản phẩm:

```php
    private function save(Request $request, Farmer $farmer): void
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('farmers', 'code')->ignore($farmer->id)],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^0\d{9}$/'],
            'zalo_phone' => ['nullable', 'regex:/^0\d{9}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'growing_area' => ['nullable', 'string', 'max:255'],
            'area_m2' => ['nullable', 'integer', 'min:0'],
            'farming_method' => ['nullable', 'string', 'max:150'],
            'certificate_name' => ['nullable', 'string', 'max:150'],
            'certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'certificate_expires_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(FarmerStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'products' => ['nullable', 'array'],
            'products.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        DB::transaction(function () use ($request, $farmer, $data) {
            $farmer->fill(Arr::except($data, ['certificate', 'products']));
            $farmer->zalo_phone ??= $data['phone'];
            $farmer->access_token ??= bin2hex(random_bytes(20));

            if ($request->hasFile('certificate')) {
                $farmer->certificate_file = $request->file('certificate')->store('certificates', 'public');
            }

            $farmer->save();

            $farmer->products()->sync(
                collect($data['products'] ?? [])
                    ->filter(fn ($kg) => $kg !== null && $kg !== '')
                    ->map(fn ($kg) => ['typical_weekly_kg' => (float) $kg])
                    ->all()
            );
        });
    }

    public function regenerateToken(Farmer $farmer): RedirectResponse
    {
        $farmer->update(['access_token' => bin2hex(random_bytes(20))]);

        return back()->with('status', 'Đã cấp link mới. Link cũ không còn dùng được; hãy gửi link mới cho nông hộ.');
    }
```

`DeliveryZoneController` — rule theo bảng; `is_active` lấy bằng `$request->boolean('is_active')`.

`UserController` — không cho admin tự hạ quyền mình, mật khẩu trống khi sửa thì giữ nguyên:

```php
    private function validated(Request $request, User $user): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^0\d{9}$/'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$user->exists ? 'nullable' : 'required', 'string', 'min:8'],
        ]);

        if ($user->exists && $user->is($request->user()) && $data['role'] !== $user->role->value) {
            throw ValidationException::withMessages(['role' => 'Bạn không thể tự đổi vai trò của chính mình.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
```

- [ ] **Step 6: Viết view**

Mỗi thực thể có `index.blade.php` (bảng + link "Thêm" + link "Sửa" từng dòng) và `form.blade.php` (form `POST` khi tạo, `@method('PUT')` khi sửa; dùng `<x-field>` cho input chữ/số; checkbox `is_active` value `1`). Cột hiển thị:

- `products/index`: mã, tên, `@kg` gói, `@vnd` giá, giá mua/kg, hạn dùng (ngày), đang bán (Có/Không).
- `baskets/index`: tên, `@vnd` giá, số người, danh sách rau, đang bán. `baskets/form`: mỗi sản phẩm đang bán một dòng với input ẩn `items[i][product_id]`, input số `items[i][quantity]` (giá trị hiện tại hoặc 0), checkbox `items[i][allow_substitute]`.
- `farmers/index`: mã, tên, SĐT, khu vực trồng, `<x-status-badge>`, chứng nhận ("Có, đến dd/mm/yyyy" / "Hết hạn" / "Không"), link báo sản lượng `route('farmer.show', $farmer->access_token)`, form `POST admin.farmers.token` nút "Cấp link mới". `farmers/form`: `enctype="multipart/form-data"`; input file `certificate`; với mỗi sản phẩm một input số `products[{{ $product->id }}]` (kg/tuần ước tính, để trống = không trồng). Ghi chú cạnh ô chứng nhận: "Chỉ điền khi có giấy tờ thật. Trang QR chỉ hiện chứng nhận khi có file và còn hạn."
- `zones/index`: tên, mô tả, `@vnd` phí, miễn phí từ, điểm nhận, đang dùng.
- `users/index`: tên, email, vai trò (label).

Menu admin: thêm nhóm "Danh mục" (Sản phẩm, Giỏ rau, Nông hộ, Cụm giao, Tài khoản) chỉ hiện khi `auth()->user()->role === \App\Enums\UserRole::Admin`.

Chạy một lần để file chứng nhận truy cập được từ web: `php artisan storage:link`.

- [ ] **Step 7: Chạy toàn bộ test, commit**

Run: `php artisan test`
Expected: PASS toàn bộ.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: admin CRUD for products, baskets, farmers, delivery zones and users"
```

---

## Kết quả Phase 6

- [ ] Operator ghi rau dư theo lô, không vượt quá tồn; ghi bao bì thu hồi.
- [ ] Dashboard hiện đủ 5 nhóm KPI, đúng số trên dữ liệu mẫu, không có chỉ số CO₂.
- [ ] Admin quản lý được dữ liệu gốc; operator bị chặn (403).
- [ ] Cấp lại link nông hộ làm link cũ hết hiệu lực.

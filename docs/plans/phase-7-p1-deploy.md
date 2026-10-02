# Phase 7 — Giỏ định kỳ, tác vụ định kỳ, dữ liệu demo, deploy: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Giỏ định kỳ tự sinh đơn, các tác vụ hằng ngày chạy theo lịch, dữ liệu demo luôn "gần hôm nay", và ứng dụng chạy được trên Railway.

**Architecture:** Lịch giao của giỏ định kỳ là hàm thuần (`SubscriptionSchedule`). `SubscriptionService` tái dùng `OrderService::place()` nên đơn định kỳ đi qua đúng một đường tạo đơn. Scheduler chỉ gọi các lệnh artisan đã có nút bấm tương đương trong admin.

**Tech Stack:** Laravel 13 · MySQL 8.4 · Railway.

**Spec:** [`docs/CLAUDE.md`](../CLAUDE.md) mục 3, 4 (subscriptions), 6 (P1) · [`docs/PLAN.md`](../PLAN.md) quyết định D15.

## Global Constraints

Xem `docs/PLAN.md`. Riêng phase này: `subscriptions.delivery_weekday` 1 = Thứ Hai … 7 = Chủ nhật; `QUEUE_CONNECTION=sync`; không seed dữ liệu demo lên môi trường pilot thật.

---

### Task 7.1: Giỏ định kỳ tự sinh đơn

**Files:**
- Create: `app/Support/SubscriptionSchedule.php`, `app/Services/SubscriptionService.php`, `app/Console/Commands/SubscriptionsGenerate.php`, `app/Http/Controllers/Admin/SubscriptionController.php`, `resources/views/admin/subscriptions/{index,form}.blade.php`
- Modify: `routes/web.php`, menu admin
- Test: `tests/Unit/Support/SubscriptionScheduleTest.php`, `tests/Feature/Subscriptions/SubscriptionServiceTest.php`

**Interfaces:**
- Consumes: `OrderService::place`, `OrderService::transition`.
- Produces:
  - `SubscriptionSchedule::isDue(SubscriptionFrequency $frequency, int $weekday, CarbonImmutable $start, ?CarbonImmutable $end, CarbonImmutable $date): bool` — `weekly` mỗi tuần; `biweekly` cách tuần tính từ lần giao đầu; `monthly` mỗi 4 tuần tính từ lần giao đầu.
  - `SubscriptionService::generateOrders(CarbonInterface $deliveryDate): int` — tạo đơn `confirmed` cho các giỏ đến lịch; chạy lại không tạo trùng.
  - Lệnh `php artisan subscriptions:generate {date?}` (mặc định ngày mai).
  - Route `admin.subscriptions.index|create|store|edit|update`, `admin.subscriptions.generate` (POST, body `date`).

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Unit/Support/SubscriptionScheduleTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Enums\SubscriptionFrequency;
use App\Support\SubscriptionSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SubscriptionScheduleTest extends TestCase
{
    private function d(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'Asia/Ho_Chi_Minh');
    }

    public function test_weekly_is_due_every_matching_weekday(): void
    {
        // 15/09/2026 là Thứ Ba (weekday 2).
        $this->assertTrue(SubscriptionSchedule::isDue(SubscriptionFrequency::Weekly, 2, $this->d('2026-09-15'), null, $this->d('2026-10-06')));
        $this->assertFalse(SubscriptionSchedule::isDue(SubscriptionFrequency::Weekly, 2, $this->d('2026-09-15'), null, $this->d('2026-10-07')));
    }

    public function test_not_due_before_start_or_after_end(): void
    {
        $this->assertFalse(SubscriptionSchedule::isDue(SubscriptionFrequency::Weekly, 2, $this->d('2026-09-15'), null, $this->d('2026-09-08')));
        $this->assertFalse(SubscriptionSchedule::isDue(SubscriptionFrequency::Weekly, 2, $this->d('2026-09-15'), $this->d('2026-09-30'), $this->d('2026-10-06')));
    }

    public function test_biweekly_counts_from_the_first_delivery(): void
    {
        // Bắt đầu Thứ Năm 10/09, giao Thứ Bảy (6): lần đầu 12/09, rồi 26/09.
        $start = $this->d('2026-09-10');

        $this->assertTrue(SubscriptionSchedule::isDue(SubscriptionFrequency::Biweekly, 6, $start, null, $this->d('2026-09-12')));
        $this->assertFalse(SubscriptionSchedule::isDue(SubscriptionFrequency::Biweekly, 6, $start, null, $this->d('2026-09-19')));
        $this->assertTrue(SubscriptionSchedule::isDue(SubscriptionFrequency::Biweekly, 6, $start, null, $this->d('2026-09-26')));
    }

    public function test_monthly_means_every_four_weeks(): void
    {
        $start = $this->d('2026-09-10');

        $this->assertFalse(SubscriptionSchedule::isDue(SubscriptionFrequency::Monthly, 6, $start, null, $this->d('2026-09-26')));
        $this->assertTrue(SubscriptionSchedule::isDue(SubscriptionFrequency::Monthly, 6, $start, null, $this->d('2026-10-10')));
    }

    public function test_sunday_is_weekday_seven(): void
    {
        $this->assertTrue(SubscriptionSchedule::isDue(SubscriptionFrequency::Weekly, 7, $this->d('2026-09-10'), null, $this->d('2026-09-13')));
    }
}
```

`tests/Feature/Subscriptions/SubscriptionServiceTest.php`:

```php
<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $subscriptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->seed(DemoSeeder::class);
        $this->subscriptions = app(SubscriptionService::class);
    }

    public function test_generates_confirmed_basket_orders_for_due_active_subscriptions(): void
    {
        // Dữ liệu mẫu: Thứ Bảy (6) có 4 giỏ đang chạy (id 3, 5, 6, 7) và 1 giỏ tạm dừng (id 9).
        $count = $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-10'));

        $this->assertSame(4, $count);

        $orders = Order::whereDate('delivery_date', '2026-10-10')->orderBy('id')->get();
        $this->assertSame(['DH261010-001', 'DH261010-002', 'DH261010-003', 'DH261010-004'], $orders->pluck('code')->all());
        $this->assertEquals([3, 5, 6, 7], $orders->pluck('subscription_id')->sort()->values()->all());

        $order = $orders->firstWhere('subscription_id', 3);
        $subscription = Subscription::with('basket', 'customer')->findOrFail(3);
        $this->assertSame([OrderStatus::Confirmed, OrderType::Basket, OrderChannel::Website], [$order->status, $order->order_type, $order->channel]);
        $this->assertSame([$subscription->customer_id, $subscription->time_slot, (int) $subscription->basket->price], [$order->customer_id, $order->time_slot, $order->subtotal]);
        $this->assertNotNull($order->confirmed_at);
        $this->assertSame($subscription->basket->items()->count(), $order->items()->count());
    }

    public function test_running_again_does_not_duplicate(): void
    {
        $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-10'));

        $this->assertSame(0, $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-10')));
        $this->assertSame(4, Order::whereDate('delivery_date', '2026-10-10')->count());
    }

    public function test_other_weekdays_use_their_own_subscriptions(): void
    {
        // Thứ Ba 06/10: giỏ id 1. Thứ Năm 08/10: giỏ id 2, 4, 8. Thứ Tư 07/10: không có.
        $this->assertSame(1, $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-06')));
        $this->assertSame(3, $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-08')));
        $this->assertSame(0, $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-07')));
    }

    public function test_paused_ended_or_inactive_basket_subscriptions_are_skipped(): void
    {
        Subscription::findOrFail(3)->update(['status' => 'cancelled']);
        Subscription::findOrFail(5)->update(['end_date' => '2026-10-09']);
        Subscription::findOrFail(6)->basket->update(['is_active' => false]);

        $count = $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-10'));

        // Giỏ 6 và 7 dùng chung basket 3 trong dữ liệu mẫu → cả hai bị bỏ qua.
        $this->assertSame(0, $count);
    }

    public function test_artisan_command_defaults_to_tomorrow(): void
    {
        // Hôm nay 05/10 (Thứ Hai) → ngày mai Thứ Ba 06/10.
        $this->artisan('subscriptions:generate')->assertSuccessful();

        $this->assertSame(1, Order::whereDate('delivery_date', '2026-10-06')->whereNotNull('subscription_id')->count());
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter="SubscriptionScheduleTest|SubscriptionServiceTest"`
Expected: FAIL (class not found).

- [ ] **Step 3: Viết `app/Support/SubscriptionSchedule.php`**

```php
<?php

namespace App\Support;

use App\Enums\SubscriptionFrequency;
use Carbon\CarbonImmutable;

final class SubscriptionSchedule
{
    public static function isDue(SubscriptionFrequency $frequency, int $weekday, CarbonImmutable $start, ?CarbonImmutable $end, CarbonImmutable $date): bool
    {
        $date = $date->startOfDay();
        $start = $start->startOfDay();

        if ($date->dayOfWeekIso !== $weekday || $date->lt($start) || ($end !== null && $date->gt($end->startOfDay()))) {
            return false;
        }

        // Lần giao đầu tiên: đúng thứ giao, vào hoặc sau ngày bắt đầu. Carbon::next() nhận 0 = Chủ nhật.
        $first = $start->dayOfWeekIso === $weekday ? $start : $start->next($weekday % 7);
        $weeks = intdiv((int) $first->diffInDays($date), 7);

        return match ($frequency) {
            SubscriptionFrequency::Weekly => true,
            SubscriptionFrequency::Biweekly => $weeks % 2 === 0,
            SubscriptionFrequency::Monthly => $weeks % 4 === 0,
        };
    }
}
```

- [ ] **Step 4: Viết `app/Services/SubscriptionService.php`**

```php
<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\Subscription;
use App\Support\SubscriptionSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SubscriptionService
{
    public function __construct(private readonly OrderService $orders) {}

    public function generateOrders(CarbonInterface $deliveryDate): int
    {
        $date = CarbonImmutable::parse($deliveryDate)->startOfDay();
        $created = 0;

        $subscriptions = Subscription::with('customer', 'basket')
            ->where('status', SubscriptionStatus::Active)
            ->where('delivery_weekday', $date->dayOfWeekIso)
            ->orderBy('id')
            ->get();

        foreach ($subscriptions as $subscription) {
            $due = SubscriptionSchedule::isDue(
                $subscription->frequency,
                (int) $subscription->delivery_weekday,
                CarbonImmutable::parse($subscription->start_date),
                $subscription->end_date === null ? null : CarbonImmutable::parse($subscription->end_date),
                $date,
            );

            if (! $due || ! $subscription->basket->is_active || $subscription->customer->delivery_zone_id === null) {
                continue;
            }

            if (Order::where('subscription_id', $subscription->id)->whereDate('delivery_date', $date->toDateString())->exists()) {
                continue;
            }

            $order = $this->orders->place([
                'name' => $subscription->customer->name,
                'phone' => $subscription->customer->phone,
                'address' => $subscription->customer->address,
                'delivery_zone_id' => $subscription->customer->delivery_zone_id,
                'delivery_date' => $date->toDateString(),
                'time_slot' => $subscription->time_slot,
                'order_type' => OrderType::Basket->value,
                'basket_id' => $subscription->basket_id,
                'payment_method' => PaymentMethod::Cod->value,
                'subscription_id' => $subscription->id,
            ], OrderChannel::Website);

            // Giỏ định kỳ là cam kết trước nên xác nhận luôn.
            $this->orders->transition($order, OrderStatus::Confirmed);
            $created++;
        }

        return $created;
    }
}
```

Đảm bảo `Subscription::casts()` có `'start_date' => 'date'`, `'end_date' => 'date'`, `'frequency' => SubscriptionFrequency::class`, `'status' => SubscriptionStatus::class`, và `Basket::casts()` có `'is_active' => 'boolean'`, `'price' => 'integer'`.

- [ ] **Step 5: Viết lệnh `app/Console/Commands/SubscriptionsGenerate.php`**

```php
<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SubscriptionsGenerate extends Command
{
    protected $signature = 'subscriptions:generate {date? : Ngày giao (Y-m-d), mặc định ngày mai}';

    protected $description = 'Sinh đơn cho các giỏ định kỳ đến lịch giao';

    public function handle(SubscriptionService $subscriptions): int
    {
        $date = CarbonImmutable::parse($this->argument('date') ?? today()->addDay());
        $count = $subscriptions->generateOrders($date);

        $this->info("Đã tạo {$count} đơn giỏ định kỳ cho ngày {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Chạy test, xác nhận pass**

Run: `php artisan test --filter="SubscriptionScheduleTest|SubscriptionServiceTest"`
Expected: PASS (10 tests).

- [ ] **Step 7: Màn hình quản lý giỏ định kỳ**

`SubscriptionController` (nhóm `admin`, mọi operator dùng được) theo mẫu `ProductController` của Phase 6, với:

```php
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^0\d{9}$/', 'exists:customers,phone'],
            'basket_id' => ['required', Rule::exists('baskets', 'id')->where('is_active', true)],
            'frequency' => ['required', Rule::enum(SubscriptionFrequency::class)],
            'delivery_weekday' => ['required', 'integer', 'between:1,7'],
            'time_slot' => ['required', Rule::in(config('farm2home.order.time_slots'))],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
        ]);

        $data['customer_id'] = Customer::where('phone', $data['phone'])->value('id');
        unset($data['phone']);

        return $data;
    }

    public function generate(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today()->toDateString()]]);
        $count = $subscriptions->generateOrders(CarbonImmutable::parse($data['date']));

        return back()->with('status', "Đã tạo {$count} đơn giỏ định kỳ.");
    }
```

Route trong nhóm `admin`:

```php
    Route::post('subscriptions/generate', [SubscriptionController::class, 'generate'])->name('subscriptions.generate');
    Route::resource('subscriptions', SubscriptionController::class)->only(['index', 'create', 'store', 'edit', 'update']);
```

View `admin/subscriptions/index.blade.php` (title "Giỏ định kỳ"): form `POST admin.subscriptions.generate` với input `date` (mặc định ngày mai) và nút "Sinh đơn cho ngày này"; bảng: khách (tên + SĐT), giỏ, tần suất (label), thứ giao ("Thứ Hai"… "Chủ nhật" từ 1…7), khung giờ, từ ngày – đến ngày, `<x-status-badge>`, link "Sửa". `form.blade.php`: các field theo rule; khi sửa, ô `phone` điền sẵn SĐT của khách. Thêm `'admin.subscriptions.index' => 'Giỏ định kỳ'` vào menu admin; thêm `'between' => ['numeric' => ':Attribute phải từ :min đến :max.']` vào `lang/vi/validation.php`.

Thêm vào `SubscriptionServiceTest`:

```php
    public function test_operator_generates_from_the_admin_screen(): void
    {
        $this->actingAs(\App\Models\User::where('email', 'operator@farm2home.vn')->firstOrFail());

        $this->get('/admin/subscriptions')->assertOk()->assertSee('Giỏ Xanh Tiết Kiệm')->assertSee('Hằng tuần');
        $this->post('/admin/subscriptions/generate', ['date' => '2026-10-10'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Đã tạo 4 đơn giỏ định kỳ.');
    }
```

- [ ] **Step 8: Chạy toàn bộ test, commit**

Run: `php artisan test`
Expected: PASS toàn bộ.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: subscriptions generate confirmed basket orders on schedule"
```

---

### Task 7.2: Lịch chạy tự động

**Files:**
- Modify: `routes/console.php`
- Test: `tests/Feature/ScheduleTest.php`

**Interfaces:**
- Consumes: lệnh `subscriptions:generate`, `forecast:run`, `forecast:backfill`.
- Produces: lịch hằng ngày theo giờ Việt Nam.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/ScheduleTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_daily_jobs_are_scheduled_in_the_right_order(): void
    {
        // Gọi một lệnh artisan để console kernel nạp routes/console.php trước khi đọc lịch.
        Artisan::call('schedule:list');

        $events = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($event) => [str($event->command)->after('artisan')->trim("' \"")->value() => $event->expression]);

        // Sinh đơn giỏ định kỳ trước giờ chốt 18:00, dự báo ngay sau giờ chốt, điền số thực tế cuối ngày.
        $this->assertSame('0 12 * * *', $events['subscriptions:generate']);
        $this->assertSame('15 18 * * *', $events['forecast:run']);
        $this->assertSame('0 21 * * *', $events['forecast:backfill']);
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=ScheduleTest`
Expected: FAIL (`Undefined array key "subscriptions:generate"`).

- [ ] **Step 3: Thêm vào cuối `routes/console.php`**

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:generate')->dailyAt('12:00');
Schedule::command('forecast:run')->dailyAt('18:15');
Schedule::command('forecast:backfill')->dailyAt('21:00');
```

(Đưa dòng `use` lên đầu file cùng các `use` khác.)

- [ ] **Step 4: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter=ScheduleTest`
Expected: PASS.

Run: `php artisan schedule:list`
Expected: liệt kê 3 lệnh với giờ 12:00, 18:15, 21:00.

Trên máy dev, lịch chỉ chạy khi mở `php artisan schedule:work`. Không bắt buộc: cả ba việc đều có nút bấm trong admin.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: daily schedule for subscription orders and forecasts"
```

---

### Task 7.3: Dịch ngày dữ liệu demo về gần hôm nay

**Files:**
- Create: `app/Console/Commands/DemoShiftDates.php`
- Test: `tests/Feature/DemoShiftDatesTest.php`

**Interfaces:**
- Produces: lệnh `php artisan demo:shift-dates {--force}` — dịch mọi cột ngày của dữ liệu mẫu đi một số **tuần nguyên** sao cho ngày giao muộn nhất không vượt quá ngày mai; dựng lại mã đơn và mã lô theo ngày mới. Từ chối chạy ở `production` nếu không có `--force`.

Vì sao dịch theo tuần nguyên: giỏ định kỳ gắn với thứ trong tuần, và nhu cầu mẫu cao vào Thứ Bảy; dịch lệch thứ sẽ làm dữ liệu vô nghĩa.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/DemoShiftDatesTest.php`:

```php
<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoShiftDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_everything_moves_by_whole_weeks_and_codes_follow(): void
    {
        $this->seed(DemoSeeder::class);
        // Ngày giao muộn nhất trong mẫu: 03/10/2026. Ngày mai = 21/11/2026 → dịch đúng 49 ngày (7 tuần).
        $this->travelTo('2026-11-20 09:00:00');

        $this->artisan('demo:shift-dates')->expectsOutputToContain('49')->assertSuccessful();

        $this->assertSame('2026-11-21', DB::table('orders')->max('delivery_date'));
        $this->assertSame('2026-10-29', DB::table('orders')->min('delivery_date'));
        $this->assertSame(155, DB::table('orders')->count());
        $this->assertDatabaseHas('orders', ['code' => 'DH261121-013', 'delivery_date' => '2026-11-21']);
        $this->assertDatabaseMissing('orders', ['code' => 'DH261003-013']);

        // Lô 17/09 → 05/11, mã lô và hạn dùng đi theo.
        $this->assertDatabaseHas('batches', ['code' => 'F2H-261105-RM-NH01', 'harvested_at' => '2026-11-05 05:30:00', 'best_before' => '2026-11-07']);
        $this->assertSame(146, DB::table('batches')->count());

        $this->assertSame('2026-11-21', DB::table('harvest_plans')->max('delivery_date'));
        $this->assertSame(17, DB::table('harvest_plans')->count());
        $this->assertSame(308, DB::table('forecasts')->count());
        $this->assertSame('2026-11-19', DB::table('forecasts')->max('generated_on'));
        $this->assertSame(377, DB::table('supply_reports')->count());
        $this->assertSame('2026-11-19', DB::table('waste_logs')->max('log_date'));
        $this->assertSame('2026-11-03', DB::table('subscriptions')->where('id', 1)->value('start_date'));
    }

    public function test_nothing_moves_when_less_than_a_week_has_passed(): void
    {
        $this->seed(DemoSeeder::class);
        $this->travelTo('2026-10-08 09:00:00');

        $this->artisan('demo:shift-dates')->expectsOutputToContain('Không cần dịch')->assertSuccessful();

        $this->assertSame('2026-10-03', DB::table('orders')->max('delivery_date'));
    }

    public function test_refuses_to_run_in_production_without_force(): void
    {
        $this->seed(DemoSeeder::class);
        $this->travelTo('2026-11-20 09:00:00');
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('demo:shift-dates')->assertFailed();

        $this->assertSame('2026-10-03', DB::table('orders')->max('delivery_date'));
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=DemoShiftDatesTest`
Expected: FAIL (lệnh `demo:shift-dates` chưa tồn tại).

- [ ] **Step 3: Viết `app/Console/Commands/DemoShiftDates.php`**

```php
<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoShiftDates extends Command
{
    protected $signature = 'demo:shift-dates {--force : Cho phép chạy ở môi trường production}';

    protected $description = 'Dịch ngày của dữ liệu demo về gần hôm nay (theo tuần nguyên)';

    /**
     * Cột ngày nghiệp vụ của từng bảng. Cột đầu tiên dùng để sắp thứ tự cập nhật,
     * tránh đụng khóa unique giữa chừng.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'customers' => [],
        'subscriptions' => ['start_date', 'end_date'],
        'orders' => ['delivery_date', 'confirmed_at', 'delivered_at'],
        'order_items' => [],
        'supply_reports' => ['available_date'],
        'forecasts' => ['target_date', 'generated_on'],
        'harvest_plans' => ['delivery_date', 'harvest_date', 'confirmed_at'],
        'harvest_plan_items' => ['farmer_confirmed_at'],
        'batches' => ['harvested_at', 'received_at', 'processed_at', 'packed_at', 'best_before'],
        'feedbacks' => ['resolved_at'],
        'waste_logs' => ['log_date'],
        'packaging_returns' => ['return_date'],
    ];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Không chạy ở production nếu thiếu --force.');

            return self::FAILURE;
        }

        $latest = DB::table('orders')->max('delivery_date');

        if ($latest === null) {
            $this->info('Không có dữ liệu để dịch.');

            return self::SUCCESS;
        }

        $gap = (int) CarbonImmutable::parse($latest)->startOfDay()->diffInDays(CarbonImmutable::tomorrow(), false);
        $days = intdiv(max(0, $gap), 7) * 7;

        if ($days === 0) {
            $this->info('Không cần dịch: dữ liệu demo cách hôm nay chưa tới một tuần.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($days) {
            foreach (self::COLUMNS as $table => $columns) {
                $all = [...$columns, 'created_at', 'updated_at'];
                $sets = array_map(fn (string $column) => "`{$column}` = DATE_ADD(`{$column}`, INTERVAL {$days} DAY)", $all);

                // MySQL gán SET từ trái sang phải, nên biểu thức mã dùng giá trị ngày đã dịch.
                if ($table === 'orders') {
                    $sets[] = "`code` = CONCAT('DH', DATE_FORMAT(`delivery_date`, '%y%m%d'), RIGHT(`code`, 4))";
                }
                if ($table === 'batches') {
                    $sets[] = "`code` = CONCAT('F2H-', DATE_FORMAT(`harvested_at`, '%y%m%d'), SUBSTRING(`code`, 11))";
                }

                // Dịch tiến: cập nhật dòng muộn nhất trước để không trùng khóa unique.
                $order = implode(', ', array_map(fn (string $column) => "`{$column}` DESC", $columns === [] ? ['id'] : array_slice($columns, 0, 2)));

                DB::statement("UPDATE `{$table}` SET ".implode(', ', $sets)." ORDER BY {$order}");
            }
        });

        $this->info("Đã dịch dữ liệu demo {$days} ngày.");

        return self::SUCCESS;
    }
}
```

Ghi chú: `DATE_ADD(NULL, …)` trả `NULL`, nên các cột ngày để trống vẫn trống. `RIGHT(code, 4)` là `-001`; `SUBSTRING(code, 11)` là phần sau `F2H-yymmdd`, ví dụ `-RM-NH01`.

- [ ] **Step 4: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter=DemoShiftDatesTest`
Expected: PASS (3 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: command to shift demo data dates close to today"
```

---

### Task 7.4: Deploy Railway

**Files:**
- Modify: `bootstrap/app.php` (trust proxies), `.env.example` (ghi chú biến production), `README.md` (thay nội dung mặc định của Laravel bằng hướng dẫn chạy dự án)
- Test: `tests/Feature/ProxyTest.php`

**Interfaces:**
- Produces: URL sinh ra (kể cả URL trong mã QR) dùng `https` khi chạy sau proxy của Railway.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/ProxyTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProxyTest extends TestCase
{
    public function test_urls_are_https_behind_the_platform_proxy(): void
    {
        Route::get('/_test/url', fn () => route('home'));

        $this->get('/_test/url', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'farm2home.example'])
            ->assertOk()
            ->assertSee('https://farm2home.example');
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=ProxyTest`
Expected: FAIL (URL trả về bắt đầu bằng `http://`).

- [ ] **Step 3: Tin proxy của nền tảng**

Trong khối `withMiddleware` của `bootstrap/app.php`, thêm:

```php
        $middleware->trustProxies(at: '*');
```

- [ ] **Step 4: Chạy test, xác nhận pass**

Run: `php artisan test --filter=ProxyTest`
Expected: PASS.

- [ ] **Step 5: Viết lại `README.md`**

Nội dung gồm đúng các mục sau (văn bản tiếng Việt):

1. **FARM2HOME AI** — một câu mô tả, link tới `docs/CLAUDE.md` và `docs/PLAN.md`.
2. **Chạy trên máy (Laragon):** `composer install`; `npm install`; copy `.env.example` → `.env`; `php artisan key:generate`; tạo DB `farm2home` và `farm2home_test`; `php artisan migrate`; `php artisan db:seed` (dữ liệu demo); `php artisan storage:link`; `npm run dev`; mở `http://farm2home.test`.
3. **Tài khoản demo:** `admin@farm2home.vn` / `password`, `operator@farm2home.vn` / `password` — **chỉ dùng cho demo**.
4. **Test:** `php artisan test` (chạy trên DB `farm2home_test`).
5. **Lệnh hữu ích:** `forecast:run`, `forecast:backfill`, `subscriptions:generate`, `demo:shift-dates`.
6. **Cảnh báo:** không chạy `php artisan migrate:fresh` trên DB có dữ liệu thật.

- [ ] **Step 6: Đẩy mã lên GitHub và tạo dịch vụ trên Railway**

Đây là thao tác tay, không có test tự động. Tên mục trong giao diện Railway có thể thay đổi; đối chiếu tài liệu Railway hiện hành khi làm.

1. Tạo repo GitHub **private** (file SQL chứa token mẫu của nông hộ và hash mật khẩu mẫu), `git push`.
2. Railway → New Project → Deploy from GitHub repo → chọn repo.
3. Thêm dịch vụ **MySQL** vào cùng project.
4. Đặt biến môi trường cho dịch vụ web:

```dotenv
APP_NAME=Farm2Home
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # dán kết quả của: php artisan key:generate --show
APP_URL=https://<domain Railway cấp>
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_LOCALE=vi
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stderr
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

5. Lệnh build: `composer install --no-dev --optimize-autoloader && npm ci && npm run build`.
6. Lệnh chạy trước mỗi lần deploy: `php artisan migrate --force && php artisan storage:link && php artisan config:cache && php artisan route:cache && php artisan view:cache`. Migration baseline sẽ dựng 24 bảng trên DB trống của Railway.
7. Gắn một **Volume** vào đường dẫn `storage/app/public` để file chứng nhận không mất sau mỗi lần deploy.
8. (Tùy chọn) Thêm một dịch vụ cron chạy `php artisan schedule:run` mỗi phút. Bỏ qua cũng được vì có nút bấm trong admin.

- [ ] **Step 7: Nạp dữ liệu ban đầu — chọn MỘT trong hai**

**(a) Bản demo cho buổi thuyết trình** — chạy trong shell của dịch vụ web:

```bash
php artisan db:seed --class=DemoSeeder --force
php artisan demo:shift-dates --force
```

Ngay sau đó đăng nhập admin, vào Tài khoản và đổi mật khẩu của cả hai tài khoản mẫu; vào Nông hộ và bấm "Cấp link mới" cho từng nông hộ. Mật khẩu `password` và token mẫu nằm trong mã nguồn nên không được để nguyên trên một URL công khai.

**(b) Bản pilot thật** — không seed demo. Tạo tài khoản admin đầu tiên:

```bash
php artisan tinker --execute="App\Models\User::create(['name'=>'Quản trị','email'=>'<email thật>','password'=>'<mật khẩu mạnh>','role'=>'admin']);"
```

rồi đăng nhập và nhập sản phẩm, giỏ, cụm giao, nông hộ thật qua màn hình Danh mục.

- [ ] **Step 8: Kiểm tra sau deploy (bằng điện thoại, mạng 4G)**

- [ ] `https://<domain>/` mở được, không cảnh báo chứng chỉ.
- [ ] Đặt một đơn thử, thấy mã đơn.
- [ ] Đăng nhập admin, xác nhận đơn.
- [ ] Mở link nông hộ, báo sản lượng.
- [ ] Tạo gợi ý, chốt kế hoạch, nhận hàng, đánh dấu lô đạt, in tem.
- [ ] Quét QR trên tem bằng camera: mở đúng trang lô với địa chỉ `https://`.
- [ ] Gửi phản hồi từ trang lô, thấy trong hộp phản hồi admin.
- [ ] Dashboard mở được, không lỗi.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "chore: trust platform proxies and document local setup and deployment"
```

---

### Task 7.5 (đề xuất, cần team đồng ý trước khi làm): Phương pháp "trung bình cùng thứ"

**Lý do:** dữ liệu mẫu cho thấy Thứ Bảy có 20–29 kg còn ngày thường 4–10 kg. Trung bình trượt 7 ngày trộn cả hai nên dự báo ngày thường quá cao. Trung bình của **cùng thứ trong 4 tuần gần nhất** là baseline đơn giản hơn mà sát hơn. Spec quy định "mô hình chỉ tốt hơn khi thắng baseline trên sai số thật", nên task này chỉ **thêm** phương pháp để so sánh; việc đổi mặc định do số WAPE trên dashboard quyết định.

Task này thay đổi schema (thêm giá trị ENUM) và làm thay đổi ba con số trong test của Phase 1 và 4; các thay đổi đó liệt kê ở Step 5.

**Files:**
- Create: `database/migrations/2026_10_20_000000_add_weekday_average_to_forecast_method.php`
- Modify: `app/Enums/ForecastMethod.php`, `app/Support/ForecastCalculator.php`, `app/Services/ForecastService.php`
- Test: bổ sung `tests/Unit/Support/ForecastCalculatorTest.php`, `tests/Feature/Forecast/ForecastServiceTest.php`

**Interfaces:**
- Produces: `ForecastMethod::WeekdayAverage` (`'weekday_average'`, label "Trung bình cùng thứ (4 tuần)"); `ForecastCalculator::weekdayAverage(array $historyByDate, CarbonInterface $target, int $weeks = 4): array{predicted, lower, upper}` với `$historyByDate` dạng `['Y-m-d' => kg]`.

- [ ] **Step 1: Viết test (sẽ fail)**

Thêm vào `ForecastCalculatorTest`:

```php
    public function test_weekday_average_only_uses_the_same_weekday(): void
    {
        // 03/10/2026 là Thứ Bảy. Các Thứ Bảy trước: 12/09 = 20, 19/09 = 28, 26/09 = 24.
        $history = [
            '2026-09-12' => 20.0, '2026-09-14' => 2.0, '2026-09-19' => 28.0,
            '2026-09-23' => 5.0, '2026-09-26' => 24.0, '2026-10-01' => 4.0,
        ];

        $result = \App\Support\ForecastCalculator::weekdayAverage($history, \Carbon\CarbonImmutable::parse('2026-10-03'));

        $this->assertSame(24.0, $result['predicted']);
        $this->assertSame([20.73, 27.27], [$result['lower'], $result['upper']]);
    }

    public function test_weekday_average_without_matching_days_is_zero(): void
    {
        $result = \App\Support\ForecastCalculator::weekdayAverage(['2026-10-01' => 4.0], \Carbon\CarbonImmutable::parse('2026-10-03'));

        $this->assertSame(['predicted' => 0.0, 'lower' => 0.0, 'upper' => 0.0], $result);
    }
```

(Độ lệch chuẩn của 20, 28, 24 là √(32/3) ≈ 3,27.)

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=ForecastCalculatorTest`
Expected: FAIL (`Call to undefined method …::weekdayAverage()`).

- [ ] **Step 3: Viết code**

`ForecastCalculator`:

```php
    /**
     * @param  array<string, float|int>  $historyByDate  ['Y-m-d' => kg]
     * @return array{predicted: float, lower: float, upper: float}
     */
    public static function weekdayAverage(array $historyByDate, \Carbon\CarbonInterface $target, int $weeks = 4): array
    {
        $sameWeekday = [];

        foreach ($historyByDate as $date => $kg) {
            if (\Carbon\CarbonImmutable::parse($date)->dayOfWeekIso === $target->dayOfWeekIso) {
                $sameWeekday[] = $kg;
            }
        }

        return self::movingAverage($sameWeekday, $weeks);
    }
```

`ForecastMethod`: thêm `case WeekdayAverage = 'weekday_average';` và nhánh label `'Trung bình cùng thứ (4 tuần)'`.

Migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `forecasts` MODIFY `method` ENUM('moving_average','exp_smoothing','tree_regression','manual','weekday_average') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM `forecasts` WHERE `method` = 'weekday_average'");
        DB::statement("ALTER TABLE `forecasts` MODIFY `method` ENUM('moving_average','exp_smoothing','tree_regression','manual') NOT NULL");
    }
};
```

`ForecastService::run()`: giữ mảng lịch sử có khóa ngày (`$historyByDate = …demandHistory(…)`, `$history = array_values($historyByDate)`), và trong vòng lặp `for ($offset …)` thêm phương pháp thứ ba cho từng ngày đích:

```php
                $perTarget = $models + [
                    ForecastMethod::WeekdayAverage->value => ForecastCalculator::weekdayAverage($historyByDate, $target),
                ];

                foreach ($perTarget as $method => $model) {
```

- [ ] **Step 4: Chạy migration trên DB dev**

Run: `php artisan migrate`
Expected: chạy đúng một migration mới; dữ liệu `forecasts` giữ nguyên.

- [ ] **Step 5: Cập nhật ba kỳ vọng cũ bị ảnh hưởng**

- `tests/Unit/Enums/EnumLabelsTest.php`: danh sách value của `ForecastMethod` thêm `'weekday_average'` ở cuối.
- `tests/Feature/Forecast/ForecastServiceTest.php`: `42` → `63` (3 sản phẩm × 7 ngày × 3 phương pháp) ở các chỗ đếm dòng; `6` → `9` trong test backfill và test lệnh artisan.
- `tests/Feature/Forecast/ForecastScreenTest.php`: `98` → `147`.

- [ ] **Step 6: Chạy toàn bộ test, commit**

Run: `php artisan test`
Expected: PASS toàn bộ.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: weekday-average forecast method for comparison against the baselines"
```

- [ ] **Step 7: So sánh trên dữ liệu thật rồi mới đổi mặc định**

Sau ít nhất 2 tuần pilot, mở Dự báo → bảng độ chính xác. Chỉ đổi `config('farm2home.forecast.default_method')` sang `weekday_average` nếu WAPE của nó thấp hơn `moving_average`.

---

## Kết quả Phase 7

- [ ] Giỏ định kỳ sinh đơn đúng lịch, không trùng.
- [ ] Ba tác vụ hằng ngày có lịch và có nút bấm thay thế.
- [ ] Dữ liệu demo dịch được về gần hôm nay, mã đơn và mã lô đi theo.
- [ ] Ứng dụng chạy trên Railway với `https`, QR quét được, mật khẩu và token mẫu đã thay.

# Phase 3 — Nông hộ báo sản lượng: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Nông hộ mở link Zalo (không đăng nhập) để báo số kg có thể thu hoạch trong 7 ngày tới; operator nhập hộ khi nông hộ báo qua tin nhắn.

**Architecture:** `SupplyReportService::save()` là nơi duy nhất ghi `supply_reports`, dùng chung cho link nông hộ và màn hình admin. Route nông hộ bind theo `farmers.access_token`.

**Tech Stack:** Laravel 13 · Blade · PHPUnit 12.

**Spec:** [`docs/CLAUDE.md`](../CLAUDE.md) mục 1 (Inclusive design), 4 (Supply), 6 · [`docs/PLAN.md`](../PLAN.md).

## Global Constraints

Xem `docs/PLAN.md`. Riêng phase này: nông hộ **không** cần tài khoản; trang nông hộ chữ lớn, ít phải gõ; `supply_reports` unique theo `(farmer_id, product_id, available_date)`.

---

### Task 3.1: `SupplyReportService` và link nông hộ

**Files:**
- Create: `app/Services/SupplyReportService.php`, `app/Http/Requests/StoreSupplyRequest.php`, `app/Http/Controllers/Farmer/FarmerPortalController.php`, `resources/views/farmer/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Farmer/FarmerPortalTest.php`

**Interfaces:**
- Consumes: model `Farmer`, `SupplyReport`, `HarvestPlan`; enum `SupplySource`, `HarvestPlanStatus`, `FarmerStatus`; layout `x-layouts.farmer`.
- Produces:
  - `SupplyReportService::save(Farmer $farmer, array $supply, SupplySource $source): int` — `$supply` dạng `['Y-m-d' => [product_id => kg|null|'']]`; trả số dòng đã lưu; kg rỗng hoặc ≤ 0 thì xóa dòng cũ; ném `ValidationException` (key `supply`) khi loại rau không thuộc nông hộ hoặc ngày đã chốt kế hoạch.
  - `SupplyReportService::isLocked(string $date): bool` — `true` khi đã có `harvest_plans` của ngày đó với status khác `draft`.
  - Route `farmer.show`, `farmer.supply.store`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Farmer/FarmerPortalTest.php`:

```php
<?php

namespace Tests\Feature\Farmer;

use App\Enums\HarvestPlanStatus;
use App\Enums\SupplySource;
use App\Models\HarvestPlan;
use App\Models\SupplyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class FarmerPortalTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->url = '/nong-ho/'.$this->nh01->access_token;
    }

    public function test_farmer_sees_own_products_for_the_next_seven_days_without_login(): void
    {
        $this->get($this->url)
            ->assertOk()
            ->assertSee('Nguyễn Văn Tâm')
            ->assertSee('Rau muống')
            ->assertSee('Hành lá')
            ->assertDontSee('Cải ngọt')
            ->assertSee('03/10')
            ->assertSee('09/10')
            ->assertDontSee('10/10');
    }

    public function test_unknown_token_and_inactive_farmer_get_404(): void
    {
        $this->get('/nong-ho/'.str_repeat('z', 40))->assertNotFound();

        $this->nh01->update(['status' => 'inactive']);
        $this->get($this->url)->assertNotFound();
    }

    public function test_farmer_reports_supply(): void
    {
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '6.5', $this->hl->id => '']]])
            ->assertRedirect($this->url)
            ->assertSessionHas('status');

        $this->assertSame(1, SupplyReport::count());
        $report = SupplyReport::firstOrFail();
        $this->assertSame('6.50', $report->quantity_kg);
        $this->assertSame(SupplySource::Form, $report->source);
        $this->assertSame('2026-10-03', $report->available_date->toDateString());
    }

    public function test_reporting_again_updates_instead_of_duplicating(): void
    {
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '6.5']]]);
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '4']]]);

        $this->assertSame(1, SupplyReport::count());
        $this->assertSame('4.00', SupplyReport::firstOrFail()->quantity_kg);
    }

    public function test_blank_or_zero_removes_the_report(): void
    {
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '6.5']]]);
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '0']]]);

        $this->assertSame(0, SupplyReport::count());
    }

    public function test_product_the_farmer_does_not_grow_is_rejected(): void
    {
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->cn->id => '5']]])
            ->assertSessionHasErrors('supply');

        $this->assertSame(0, SupplyReport::count());
    }

    public function test_dates_outside_the_window_are_rejected(): void
    {
        foreach (['2026-10-02', '2026-10-10', 'khong-phai-ngay'] as $date) {
            $this->post($this->url.'/san-luong', ['supply' => [$date => [$this->rm->id => '5']]])
                ->assertSessionHasErrors('supply');
        }

        $this->assertSame(0, SupplyReport::count());
    }

    public function test_quantity_must_be_between_0_and_500(): void
    {
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '-1']]])->assertSessionHasErrors();
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '501']]])->assertSessionHasErrors();
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => 'nhiều']]])->assertSessionHasErrors();
    }

    public function test_date_with_a_confirmed_plan_is_locked(): void
    {
        HarvestPlan::create(['harvest_date' => '2026-10-03', 'delivery_date' => '2026-10-03', 'safety_margin_pct' => 10, 'status' => HarvestPlanStatus::Confirmed]);

        $this->get($this->url)->assertSee('Đã chốt');

        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '5']]])
            ->assertSessionHasErrors('supply');

        $this->assertSame(0, SupplyReport::count());
    }

    public function test_existing_reports_are_prefilled(): void
    {
        SupplyReport::create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-04', 'quantity_kg' => 7.5, 'source' => 'zalo']);

        $this->get($this->url)->assertSee('value="7.5"', false);
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=FarmerPortalTest`
Expected: FAIL (404 cho mọi URL).

- [ ] **Step 3: Viết `app/Services/SupplyReportService.php`**

```php
<?php

namespace App\Services;

use App\Enums\HarvestPlanStatus;
use App\Enums\SupplySource;
use App\Models\Farmer;
use App\Models\HarvestPlan;
use App\Models\SupplyReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplyReportService
{
    public function isLocked(string $date): bool
    {
        return HarvestPlan::whereDate('delivery_date', $date)
            ->where('status', '!=', HarvestPlanStatus::Draft)
            ->exists();
    }

    /**
     * @param  array<string, array<int|string, string|float|null>>  $supply  ['Y-m-d' => [product_id => kg]]
     */
    public function save(Farmer $farmer, array $supply, SupplySource $source): int
    {
        $ownProductIds = $farmer->products()->pluck('products.id')->all();

        return DB::transaction(function () use ($farmer, $supply, $source, $ownProductIds) {
            $saved = 0;

            foreach ($supply as $date => $byProduct) {
                if ($this->isLocked($date)) {
                    $label = CarbonImmutable::parse($date)->format('d/m');
                    throw ValidationException::withMessages(['supply' => "Kế hoạch ngày {$label} đã chốt, không sửa được sản lượng."]);
                }

                foreach ($byProduct as $productId => $kg) {
                    if (! in_array((int) $productId, $ownProductIds, true)) {
                        throw ValidationException::withMessages(['supply' => 'Loại rau không thuộc danh sách của nông hộ.']);
                    }

                    $key = ['farmer_id' => $farmer->id, 'product_id' => (int) $productId, 'available_date' => $date];

                    if ($kg === null || $kg === '' || (float) $kg <= 0) {
                        SupplyReport::where($key)->delete();

                        continue;
                    }

                    SupplyReport::updateOrCreate($key, ['quantity_kg' => round((float) $kg, 2), 'source' => $source]);
                    $saved++;
                }
            }

            return $saved;
        });
    }
}
```

- [ ] **Step 4: Viết `app/Http/Requests/StoreSupplyRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supply' => ['required', 'array'],
            'supply.*' => ['array'],
            'supply.*.*' => ['nullable', 'numeric', 'min:0', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['supply.*.*' => 'số kg'];
    }
}
```

- [ ] **Step 5: Viết `app/Http/Controllers/Farmer/FarmerPortalController.php`**

```php
<?php

namespace App\Http\Controllers\Farmer;

use App\Enums\FarmerStatus;
use App\Enums\SupplySource;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplyRequest;
use App\Models\Farmer;
use App\Services\SupplyReportService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FarmerPortalController extends Controller
{
    public function show(Farmer $farmer, SupplyReportService $supply): View
    {
        $this->ensureActive($farmer);
        $dates = $this->dates();

        $reports = $farmer->supplyReports()
            ->whereDate('available_date', '>=', $dates[0])
            ->get()
            ->groupBy(fn ($report) => $report->available_date->toDateString())
            ->map(fn ($group) => $group->pluck('quantity_kg', 'product_id'));

        return view('farmer.show', [
            'farmer' => $farmer,
            'products' => $farmer->products()->where('is_active', true)->orderBy('products.id')->get(),
            'dates' => $dates,
            'reports' => $reports,
            'locked' => collect($dates)->mapWithKeys(fn ($date) => [$date => $supply->isLocked($date)]),
        ]);
    }

    public function storeSupply(StoreSupplyRequest $request, Farmer $farmer, SupplyReportService $supply): RedirectResponse
    {
        $this->ensureActive($farmer);
        $data = $request->validated('supply');

        if (array_diff(array_keys($data), $this->dates()) !== []) {
            throw ValidationException::withMessages(['supply' => 'Chỉ báo được sản lượng cho 7 ngày tới.']);
        }

        $supply->save($farmer, $data, SupplySource::Form);

        return redirect()->route('farmer.show', $farmer->access_token)->with('status', 'Đã lưu sản lượng. Cảm ơn cô chú!');
    }

    private function ensureActive(Farmer $farmer): void
    {
        abort_if($farmer->status === FarmerStatus::Inactive, 404);
    }

    /** @return list<string> ngày mai .. 7 ngày tới, dạng Y-m-d */
    private function dates(): array
    {
        $tomorrow = CarbonImmutable::tomorrow();

        return collect(CarbonPeriod::create($tomorrow, $tomorrow->addDays(6)))
            ->map(fn ($date) => $date->toDateString())
            ->all();
    }
}
```

Thêm vào `routes/web.php` (ngoài nhóm admin):

```php
Route::prefix('nong-ho/{farmer:access_token}')->name('farmer.')->middleware('throttle:30,1')->group(function () {
    Route::get('/', [FarmerPortalController::class, 'show'])->name('show');
    Route::post('/san-luong', [FarmerPortalController::class, 'storeSupply'])->name('supply.store');
});
```

với `use App\Http\Controllers\Farmer\FarmerPortalController;`.

- [ ] **Step 6: Viết view `resources/views/farmer/show.blade.php`** (layout `x-layouts.farmer`, title "Báo sản lượng")

- Lời chào: "Chào {{ $farmer->name }}" và một câu hướng dẫn: "Nhập số kg có thể thu hoạch mỗi ngày. Để trống nếu không có."
- `<x-flash />` và lỗi `$errors->first('supply')` / lỗi đầu tiên của `supply.*.*`.
- Một form `POST route('farmer.supply.store', $farmer->access_token)`, `@csrf`.
- Với mỗi `$date`: một thẻ, tiêu đề `{{ \Carbon\Carbon::parse($date)->isoFormat('dddd, DD/MM') }}`.
  - Nếu `$locked[$date]`: hiện nhãn "Đã chốt" và in số đã báo dạng chữ (không có input).
  - Nếu chưa chốt: với mỗi sản phẩm một dòng gồm tên rau và `<input type="number" inputmode="decimal" step="0.5" min="0" max="500" name="supply[{{ $date }}][{{ $product->id }}]" value="{{ old("supply.$date.$product->id", isset($reports[$date][$product->id]) ? (float) $reports[$date][$product->id] : '') }}">` kèm chữ "kg". Input cao tối thiểu 48px, chữ 18px.
- Nút "Lưu sản lượng" dính cuối màn hình (`sticky bottom-0`), rộng toàn chiều ngang.

Ghi chú: `(float) '7.50'` in ra `7.5`, khớp test `value="7.5"`.

- [ ] **Step 7: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter=FarmerPortalTest`
Expected: PASS (10 tests).

Kiểm tra tay: mở `http://farm2home.test/nong-ho/d1519ec801233c422771d362d652619a384cb0d5` (token của NH01 trong dữ liệu mẫu) ở khổ 360px.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: tokenized farmer link for reporting supply"
```

---

### Task 3.2: Operator nhập sản lượng hộ nông hộ

**Files:**
- Create: `app/Http/Controllers/Admin/SupplyController.php`, `resources/views/admin/supply/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Farmer/AdminSupplyTest.php`

**Interfaces:**
- Consumes: `SupplyReportService::save`, `SupplyReportService::isLocked`.
- Produces: route `admin.supply.index` (GET `/admin/supply?date=Y-m-d`), `admin.supply.store` (POST `/admin/supply`, body `date`, `source` ∈ `zalo|operator`, `supply[farmer_id][product_id] = kg`).

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Farmer/AdminSupplyTest.php`:

```php
<?php

namespace Tests\Feature\Farmer;

use App\Enums\SupplySource;
use App\Models\SupplyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class AdminSupplyTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
    }

    public function test_grid_shows_each_farmer_with_their_products_and_link(): void
    {
        SupplyReport::create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 6.5, 'source' => 'form']);

        $this->actingAs($this->operator)->get('/admin/supply?date=2026-10-03')
            ->assertOk()
            ->assertSee('Nguyễn Văn Tâm')
            ->assertSee('Trần Thị Hồng')
            ->assertSee('value="6.5"', false)
            ->assertSee('/nong-ho/'.$this->nh01->access_token)
            ->assertSee('Tổng nguồn cung');
    }

    public function test_operator_saves_supply_reported_over_zalo(): void
    {
        $this->actingAs($this->operator)->post('/admin/supply', [
            'date' => '2026-10-03',
            'source' => 'zalo',
            'supply' => [
                $this->nh01->id => [$this->rm->id => '8', $this->hl->id => '2'],
                $this->nh02->id => [$this->cn->id => '7.5'],
            ],
        ])->assertRedirect('/admin/supply?date=2026-10-03');

        $this->assertSame(3, SupplyReport::count());
        $this->assertSame(SupplySource::Zalo, SupplyReport::firstOrFail()->source);
    }

    public function test_source_must_be_zalo_or_operator_and_date_not_in_the_past(): void
    {
        $this->actingAs($this->operator)
            ->post('/admin/supply', ['date' => '2026-10-03', 'source' => 'form', 'supply' => []])
            ->assertSessionHasErrors('source');

        $this->actingAs($this->operator)
            ->post('/admin/supply', ['date' => '2026-10-01', 'source' => 'zalo', 'supply' => []])
            ->assertSessionHasErrors('date');
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/supply')->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=AdminSupplyTest`
Expected: FAIL (404).

- [ ] **Step 3: Viết `app/Http/Controllers/Admin/SupplyController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FarmerStatus;
use App\Enums\SupplySource;
use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\SupplyReport;
use App\Services\SupplyReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplyController extends Controller
{
    public function index(Request $request, SupplyReportService $supply): View
    {
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? today()->addDay()->toDateString();

        $reports = SupplyReport::whereDate('available_date', $date)->get();

        return view('admin.supply.index', [
            'date' => $date,
            'locked' => $supply->isLocked($date),
            'farmers' => Farmer::with(['products' => fn ($query) => $query->orderBy('products.id')])
                ->where('status', '!=', FarmerStatus::Inactive)
                ->orderBy('code')
                ->get(),
            'reports' => $reports->groupBy('farmer_id')->map(fn ($group) => $group->pluck('quantity_kg', 'product_id')),
            'totals' => $reports->groupBy('product_id')->map(fn ($group) => $group->sum(fn ($report) => (float) $report->quantity_kg)),
        ]);
    }

    public function store(Request $request, SupplyReportService $supply): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today()->toDateString()],
            'source' => ['required', Rule::in([SupplySource::Zalo->value, SupplySource::Operator->value])],
            'supply' => ['nullable', 'array'],
            'supply.*' => ['array'],
            'supply.*.*' => ['nullable', 'numeric', 'min:0', 'max:500'],
        ]);

        $farmers = Farmer::findMany(array_keys($data['supply'] ?? []))->keyBy('id');

        foreach ($data['supply'] ?? [] as $farmerId => $byProduct) {
            if ($farmers->has($farmerId)) {
                $supply->save($farmers[$farmerId], [$data['date'] => $byProduct], SupplySource::from($data['source']));
            }
        }

        return redirect()->route('admin.supply.index', ['date' => $data['date']])->with('status', 'Đã lưu sản lượng.');
    }
}
```

Thêm vào nhóm `admin` trong `routes/web.php`:

```php
    Route::get('supply', [SupplyController::class, 'index'])->name('supply.index');
    Route::post('supply', [SupplyController::class, 'store'])->name('supply.store');
```

- [ ] **Step 4: Viết view `admin/supply/index.blade.php`** (title "Sản lượng nông hộ báo")

- Form GET chọn ngày (`date`), nút "Xem".
- Nếu `$locked`: cảnh báo "Kế hoạch ngày này đã chốt, sản lượng chỉ để xem." và vô hiệu hóa input.
- Form `POST route('admin.supply.store')`: input ẩn `date`; select `source` với 2 lựa chọn "Zalo" (`zalo`), "Operator nhập" (`operator`).
- Mỗi nông hộ một khối: mã + tên, link sao chép được `{{ route('farmer.show', $farmer->access_token) }}` (nhãn "Link báo sản lượng"), nút mở Zalo `{{ $farmer->zaloLink() }}`; với mỗi sản phẩm của nông hộ: `<input type="number" step="0.5" min="0" max="500" name="supply[{{ $farmer->id }}][{{ $product->id }}]" value="{{ isset($reports[$farmer->id][$product->id]) ? (float) $reports[$farmer->id][$product->id] : '' }}">`.
- Khối "Tổng nguồn cung": với mỗi loại rau có báo, in tên và `@kg($totals[$productId])`.
- Nút "Lưu".

- [ ] **Step 5: Chạy toàn bộ test, commit**

Run: `php artisan test`
Expected: PASS toàn bộ.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: operator entry of farmer supply reports"
```

---

## Kết quả Phase 3

- [ ] Nông hộ báo được sản lượng 7 ngày tới qua link, không đăng nhập.
- [ ] Báo lại thì cập nhật, không sinh dòng trùng; để trống thì xóa.
- [ ] Ngày đã chốt kế hoạch không sửa được.
- [ ] Operator nhập hộ và xem tổng nguồn cung theo loại rau.

# Phase 11 — Nhật ký sửa lô, cảnh báo dữ liệu cũ

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Làm cho code khớp 2 khẳng định trong hồ sơ Chung kết FPT (`docs/FARM2HOME_HO_SO_CHUNG_KET_FPT_2026_TIENG_VIET.docx`): hồ sơ lô có lịch sử sửa, dữ liệu cũ phát cảnh báo.

> **Hoãn (2026-11-23):** mức tin cậy dự báo theo loại rau — chờ hỏi giáo viên. Bản nháp: nhãn Cao/TB/Thấp/Chưa đủ dữ liệu theo WAPE 14 ngày từng loại rau (Cao ≤ 20%, TB ≤ 35%, cần ≥ 7 điểm).

**Architecture:** (1) Một hook `updated` trên model `Batch` ghi mọi thay đổi vào bảng mới `batch_changes`, nên mọi đường sửa lô (form sửa, ảnh, nhận lại hàng, chất lượng) đều được ghi ở một chỗ. (2) `HarvestPlanService::staleWarnings()` so dữ liệu đơn/sản lượng với lúc tạo gợi ý, hiện hộp cảnh báo trên kế hoạch nháp.

**Tech Stack:** Laravel 13, PHP 8.3, MySQL, Blade + Tailwind, PHPUnit.

**Spec:** Đánh giá phần công nghệ hồ sơ (cuộc trò chuyện 2026-11-23) — mục "Hồ sơ ghi quá so với code".

## Global Constraints

- Hạn chót: trước 12h 24/11/2026. Mỗi task một commit riêng, chỉ commit khi người dùng bảo.
- Không sửa `database/farm2home.sql`; thay đổi schema bằng migration mới (`php artisan make:migration --no-interaction`).
- Không chạy `migrate:fresh` trên DB dev `farm2home`.
- Chữ giao diện tiếng Việt; tên code, biến, comment tiếng Anh (comment luật nghiệp vụ được viết tiếng Việt).
- Ngưỡng đọc từ `config/farm2home.php`, không viết cứng.
- Trang QR công khai không hiện tên người sửa, số điện thoại hay dữ liệu khách.
- Chạy `vendor/bin/pint --dirty --format agent` trước mỗi commit.

## Review Focus

1. Lưu form sửa lô mà không đổi gì → không sinh dòng nhật ký rỗng.
2. Sửa lô khi không có người đăng nhập (console, seeder, `demo:orders`) → vẫn ghi, `user_id` = null, không lỗi.
3. Kế hoạch đã chốt → không hiện cảnh báo dữ liệu cũ (không còn tạo lại được).
4. HTX `paused`/`inactive` → không bị liệt kê là "chưa báo sản lượng".
5. Hai đơn hoặc hai báo cung cùng một HTX sửa sau khi tạo gợi ý → tên HTX chỉ xuất hiện một lần.

---

### Task 1: Nhật ký sửa lô

**Files:**
- Create: `database/migrations/2026_11_23_xxxxxx_create_batch_changes_table.php`
- Create: `app/Models/BatchChange.php`
- Modify: `app/Models/Batch.php` (thêm `booted()` + quan hệ `changes()`)
- Modify: `app/Http/Controllers/Admin/BatchController.php:32-39` (eager load `changes.user`)
- Modify: `resources/views/admin/batches/show.blade.php` (mục "Lịch sử sửa")
- Modify: `resources/views/public/batches/show.blade.php` (một dòng "Thông tin lô đã sửa N lần, lần cuối …")
- Test: `tests/Feature/Batches/BatchChangeLogTest.php`

**Interfaces:**
- Produces: bảng `batch_changes(id, batch_id FK cascade, user_id nullable FK users set null, changes JSON, created_at)`; `Batch::changes(): HasMany` (mới nhất trước); `BatchChange::user(): BelongsTo`; `BatchChange::FIELD_LABELS` (array<string,string>).

**Quyết định:**
- Trường được theo dõi (chỉ những trường này): `quantity_kg, grower_name, harvested_at, processed_at, packed_at, processing_note, storage_guide, best_before, quality_status, qc_note, photo`. `updated_at`, `qr_key` không ghi.
- `changes` dạng `{"field": ["cũ", "mới"], ...}` lấy từ `getChanges()` + `getRawOriginal()` trong sự kiện `updated` (lúc đó original chưa sync). Không có trường theo dõi nào đổi → không ghi.
- `user_id = auth()->id()`.
- Nhãn tiếng Việt trong `BatchChange::FIELD_LABELS` (vd `quantity_kg` → "Khối lượng (kg)", `photo` → "Ảnh lô"). Ảnh hiện "đã thay ảnh" thay vì đường dẫn file.
- Trang QR chỉ hiện số lần sửa và thời điểm sửa cuối; không hiện người sửa, không hiện nội dung.

- [ ] **Step 1: Viết test hỏng** — `BatchChangeLogTest` (dùng `BuildsCatalog`, tạo lô qua `BatchService::receive`):
  - `test_editing_a_batch_records_who_changed_what`: admin PATCH `/admin/batches/{id}` đổi `processing_note` từ null → "Rửa 2 lần" và `quality_status` pending → passed ⇒ `assertDatabaseCount('batch_changes', 1)`; `changes['processing_note'] === [null, 'Rửa 2 lần']`; `user_id === $admin->id`.
  - `test_saving_without_changes_records_nothing`: PATCH với đúng giá trị hiện tại ⇒ `assertDatabaseCount('batch_changes', 0)`.
  - `test_creating_a_batch_is_not_a_change`: chỉ `receive()` ⇒ 0 dòng; `receive()` lần 2 với kg khác ⇒ 1 dòng có `quantity_kg`, `user_id` null (không đăng nhập).
  - `test_admin_page_lists_changes_and_public_page_shows_only_count`: sau 2 lần sửa, `/admin/batches/{id}` `assertSee('Lịch sử sửa')` và thấy tên admin; trang QR công khai `assertSee('đã sửa 2 lần')` và `assertDontSee($admin->name)`.
- [ ] **Step 2: Chạy** `php artisan test --compact tests/Feature/Batches/BatchChangeLogTest.php` → FAIL (bảng không tồn tại).
- [ ] **Step 3: Migration + model `BatchChange`** (`$timestamps = false`, `created_at` dùng `useCurrent()`, cast `changes` → array, `created_at` → datetime).
- [ ] **Step 4: Hook trong `Batch::booted()`** — `static::updated(fn (Batch $batch) => ...)` ghi theo quyết định trên; thêm `changes(): HasMany` sắp `latest('id')`.
- [ ] **Step 5: View admin + view công khai** theo quyết định trên; controller `show()` load thêm `changes.user`; `BatchPageController` dùng `$batch->changes()->count()` và `max('created_at')`.
- [ ] **Step 6: Chạy lại test file** → PASS; chạy `tests/Feature/Batches` → PASS.
- [ ] **Step 7: Commit** `feat: record every batch edit (who, when, before/after)`

### Task 2: Cảnh báo dữ liệu cũ trên kế hoạch nháp

**Files:**
- Modify: `app/Services/HarvestPlanService.php` (thêm `staleWarnings()`)
- Modify: `app/Http/Controllers/Admin/HarvestPlanController.php:47-65` (truyền `staleWarnings`)
- Modify: `resources/views/admin/harvest-plans/show.blade.php` (hộp vàng cạnh cảnh báo `$pendingOrders` ở dòng ~26, kèm nút "Tạo lại gợi ý" dùng form `admin.harvest-plans.generate` sẵn có)
- Test: `tests/Feature/Harvest/HarvestPlanStaleWarningTest.php`

**Interfaces:**
- Produces: `HarvestPlanService::staleWarnings(HarvestPlan $plan): list<string>` — rỗng khi kế hoạch không phải `Draft`.

**Quyết định:** "Lúc tạo gợi ý" = `$plan->items()->min('created_at')` (các dòng được tạo lại mỗi lần `generateDraft`). Ba cảnh báo, theo thứ tự:
1. `"{n} đơn mới hoặc vừa sửa sau lúc tạo gợi ý ({H:i d/m})."` — đơn của ngày giao, không hủy, `updated_at` > lúc tạo gợi ý.
2. `"HTX {tên, cách nhau dấu phẩy} báo hoặc sửa sản lượng sau lúc tạo gợi ý."` — `supply_reports` có `available_date` = ngày giao, `updated_at` > lúc tạo gợi ý.
3. `"HTX chưa báo sản lượng cho ngày này: {tên}."` — HTX `Active` có ít nhất một sản phẩm trong `farmer_products`, không có `supply_reports` nào cho ngày giao.
Cảnh báo 1 và 2 bỏ qua khi kế hoạch chưa có dòng nào.

- [ ] **Step 1: Viết test hỏng** — `HarvestPlanStaleWarningTest` (dùng `BuildsCatalog`; `travelTo` để tách thời điểm):
  - `test_fresh_draft_has_no_warnings`: tạo đơn + báo cung cho mọi HTX active, `generateDraft` ⇒ `[]`.
  - `test_order_placed_after_generation_is_flagged`: `generateDraft`, `travel(5)->minutes()`, đặt 1 đơn ⇒ phần tử chứa `'1 đơn mới'`.
  - `test_supply_change_after_generation_names_the_cooperative`: sửa 2 dòng báo cung của cùng HTX sau khi tạo ⇒ chứa tên HTX đúng 1 lần (`substr_count`) và `'sau lúc tạo gợi ý'`.
  - `test_active_cooperative_without_report_is_listed_but_paused_one_is_not`: HTX A active không báo, HTX B paused không báo ⇒ chứa tên A, không chứa tên B.
  - `test_confirmed_plan_has_no_warnings`: sau `confirm()` ⇒ `[]`.
  - `test_show_page_renders_warning_box`: GET `/admin/harvest-plans/{id}` ⇒ `assertSee('Dữ liệu đã thay đổi')` khi có cảnh báo.
- [ ] **Step 2: Chạy** `php artisan test --compact tests/Feature/Harvest/HarvestPlanStaleWarningTest.php` → FAIL (method chưa có).
- [ ] **Step 3: Viết `staleWarnings()`** theo quyết định trên.
- [ ] **Step 4: Controller + view**: tiêu đề hộp "Dữ liệu đã thay đổi so với gợi ý", liệt kê từng câu, nút "Tạo lại gợi ý".
- [ ] **Step 5: Chạy lại test file và `tests/Feature/Harvest`** → PASS.
- [ ] **Step 6: Commit** `feat: warn on draft harvest plans when orders or supply changed after the suggestion`

### Task 3: Kiểm tra cuối

- [ ] `php artisan migrate --no-interaction` trên DB dev (chỉ chạy migration mới).
- [ ] `php artisan test --compact` → toàn bộ PASS.
- [ ] `npm run build` → thành công (có lớp Tailwind mới).
- [ ] Xem bằng mắt: `/admin/batches/{id}`, trang QR một lô, `/admin/harvest-plans/{id}` bản nháp.
- [ ] Ghi chú cho hồ sơ Word: 2 khẳng định đã có thật trong code; vẫn phải sửa trong Word các chỗ "phiên bản mô hình" → "phương pháp dự báo", Phụ lục 2 (tên trường), "làm tròn lên 0,5 kg", và "độ tin cậy / mức tin cậy" → "khoảng dao động dự báo" cho tới khi có quyết định về mức tin cậy.

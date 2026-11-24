# Phase 12 — Dự báo đúng chỗ cần đoán, so với cách tính thông thường

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Trạng thái (24/11/2026):** đã làm xong trong phiên này, chưa commit. Toàn bộ test qua.

**Goal:** Làm theo 5 góp ý của thầy Nguyên về phần AI: (1) chỉ dự báo phần khách đặt thêm, (2) mô tả đúng công nghệ, (3) so với cách tính thông thường qua nhiều buổi, (4) ghi đủ kết quả từng buổi, (5) tính lợi ích sau khi trừ chi phí.

**Architecture:** Thêm phương pháp `extra_average` (đơn đã có + mức đặt thêm trung bình các buổi cùng thứ, đo từ cùng thời điểm) chạy chung trong `ForecastService::run()`. Chấm sai số trên phần đặt thêm, ở mốc trước buổi giao `evaluation_lead_days` ngày. Trang mới `/admin/sessions` gộp đối chiếu từng buổi và kết quả buổi giao (yêu cầu, giao được, dư, hết hàng, tiền).

**Tech Stack:** Laravel 13, PHP 8.3, MySQL, Blade + Tailwind, PHPUnit.

**Spec:** Góp ý thầy Nguyên (cuộc trò chuyện 2026-11-24). Báo cáo kết quả: `docs/BAO-CAO-GOP-Y-AI.md`.

## Global Constraints

- Không sửa `database/farm2home.sql`; schema mới bằng migration mới. Không chạy `migrate:fresh` trên DB dev.
- Tham số đọc từ `config/farm2home.php`.
- Chữ giao diện tiếng Việt; code, tên biến tiếng Anh.
- Mỗi task một commit, chỉ commit khi người dùng bảo. Chạy `vendor/bin/pint --dirty --format agent` trước commit.
- Dữ liệu mô phỏng không phải bằng chứng; mọi con số trong hồ sơ phải ghi rõ nguồn và ngày chạy.

## Review Focus

1. Qua giờ chốt (18:00 hôm trước) gợi ý thu hoạch không cộng phần dự báo; trước giờ chốt vẫn cộng.
2. `extra_average` chỉ dùng đơn tạo trước mốc (cùng giờ, trước buổi giao bằng đúng số ngày đoán trước), không lộ số thực tế.
3. Sai số chấm trên `max(0, actual − confirmed)`; đơn hủy sau lúc đoán không làm phần đặt thêm âm.
4. File mẫu `farm2home.sql` chỉ có dự báo trước 1 ngày: các test dùng dữ liệu mẫu phải đặt `evaluation_lead_days = 1`.
5. "% đoán đúng" có thể âm khi WAPE > 100%: giao diện và cảnh báo đều chặn ở 0.

---

### Task 1: Không cộng dự báo sau giờ chốt

**Files:**
- Modify: `app/Services/HarvestPlanService.php` (`generateDraft()`)
- Test: `tests/Feature/Harvest/HarvestPlanServiceTest.php`

**Quyết định:** `OrderWindow::earliest(now, cutoff_hour) > ngày giao` ⇒ đơn đã đóng ⇒ `forecast_extra_kg = 0`. Đơn gọi điện operator nhập sau giờ chốt vẫn được cảnh báo "kế hoạch cũ" (phase 11) để tạo lại gợi ý.

- [x] Test `test_forecast_extra_is_added_on_top_of_placed_orders` (17:00, trước chốt) và `test_forecast_extra_is_ignored_once_orders_for_that_day_are_closed` (19:00).
- [x] Sửa `generateDraft()`.

### Task 2: Cách tính thông thường `extra_average`

**Files:**
- Create: `database/migrations/2026_11_24_195054_add_extra_average_to_forecast_method.php`
- Modify: `app/Enums/ForecastMethod.php`, `config/farm2home.php` (`baseline_sessions = 4`)
- Modify: `app/Services/ForecastService.php` (`run()`, `pastExtras()`)
- Test: `tests/Feature/Forecast/ForecastServiceTest.php`, `tests/Unit/Enums/EnumLabelsTest.php`

**Quyết định:**
- Với mỗi ngày giao D đoán trước L ngày: lấy tối đa 4 buổi cùng thứ trước D (không trước đơn đầu tiên), mỗi buổi d tính `kg đơn tạo sau (d − L ngày, cùng giờ lúc chạy)`. Dự báo = đã đặt + trung bình; dải ± độ lệch chuẩn.
- Một truy vấn đơn mỗi loại rau mỗi lần chạy (không truy vấn theo từng buổi) để `demo:orders` replay 77 ngày vẫn chạy ~40 giây.

- [x] Test `test_baseline_adds_the_usual_late_orders_of_the_same_weekday` (đơn tạo sau mốc ⇒ thêm 2 kg; đổi `created_at` về trước mốc ⇒ 0).
- [x] Cập nhật số dòng mỗi lần chạy (3 → 4 phương pháp).

### Task 3: Chấm phần đặt thêm, trước 2 ngày

**Files:**
- Modify: `app/Models/Forecast.php` (`actualExtraKg()`), `app/Services/ForecastService.php` (`accuracy()`, `worstDays()`, `evaluated()`), `config/farm2home.php` (`evaluation_lead_days = 2`)
- Modify: `app/Http/Controllers/Admin/ForecastController.php` (chặn "% đoán đúng" ở 0), `resources/views/admin/forecasts/index.blade.php`, `resources/views/admin/dashboard.blade.php` (chữ giải thích)
- Test: `ForecastServiceTest`, `ForecastAccuracyOnSampleDataTest`, `ForecastScreenTest`, `KpiServiceOnSampleDataTest`, `DashboardTest`

- [x] Test `test_accuracy_scores_only_the_extra_part_at_the_evaluation_lead`.
- [x] Test dữ liệu mẫu đặt `evaluation_lead_days = 1`, số mới: MA WAPE 172,9%, ES 186,3% (phần đặt thêm sau 18:15 rất nhỏ nên % lớn).

### Task 4: Trang Đối chiếu buổi giao

**Files:**
- Create: `database/migrations/2026_11_24_195057_create_unmet_demands_table.php`, `app/Models/UnmetDemand.php`, `database/factories/UnmetDemandFactory.php`
- Create: `app/Http/Controllers/Admin/SessionReportController.php`, `resources/views/admin/sessions/index.blade.php`
- Modify: `app/Services/ForecastService.php` (`sessionComparison()`), `app/Services/KpiService.php` (`sessions()`), `routes/web.php`, `resources/views/components/layouts/admin.blade.php` (menu)
- Test: `tests/Feature/Forecast/SessionReportTest.php`

**Interfaces:**
- `unmet_demands(id, delivery_date, product_id FK, quantity_kg, note, created_by FK users null, timestamps, unique(delivery_date, product_id))`.
- `GET /admin/sessions?from&to` (mặc định 28 ngày), `POST /admin/sessions/unmet` (0 kg = xóa dòng).
- `ForecastService::sessionComparison($from, $to, ForecastMethod $model, ?int $leadDays)`: Collection các dòng `{date, product, confirmed, baseline, model, actual, winner}`; lệch nhau < 0,05 kg là hòa.
- `KpiService::sessions($from, $to)`: `{date, product, requested_kg, delivered_kg (null = chưa ghi đủ), surplus_kg, unmet_kg, surplus_vnd, missed_margin_vnd}`.

**Quyết định:** tiền rau dư = kg × giá thu mua; lãi bỏ lỡ = kg hết hàng × (giá bán/kg − giá thu mua). Chi phí chạy hệ thống và công nhập không có trong app, nhóm điền trong hồ sơ.

- [x] Test số liệu buổi (2 kg yêu cầu, 1,5 kg giao, 0,5 kg dư = 7.000đ, 1 kg hết hàng = 10.000đ lãi).
- [x] Test trang hiện tỉ số thắng và ghi/xóa lượng hết hàng.

### Task 5: Số liệu và báo cáo

- [x] `php artisan migrate`, `php artisan demo:orders --ahead=16` (24/11/2026 21:35, đơn đặt trước tới 10/12) để có dự báo trước 2 ngày và cách thường trên dữ liệu mô phỏng.
- [x] Kết quả (trước 2 ngày, buổi giao 10/09 → 23/11): cách thường WAPE 46,9% thắng TB cùng thứ 61,9%, TB trượt 72,4%, làm mượt 77,5%; TB trượt gần hơn 241/666 lần.
- [x] `docs/BAO-CAO-GOP-Y-AI.md`, cập nhật `docs/CHUC-NANG-AI.md` mục 3.1 và 5.

## Việc còn mở

- [ ] Khi có dữ liệu thật: nếu sau ≥ 3–4 tuần mô hình vẫn thua cách thường, đổi `forecast.default_method` sang `extra_average` (hoặc làm chức năng tự chọn theo WAPE N buổi gần nhất).
- [ ] Điền chi phí thật (máy chủ, công nhập) vào mục 5 báo cáo.

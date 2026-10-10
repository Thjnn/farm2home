# FARM2HOME AI — Kế hoạch code tổng thể (Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây web điều phối "nhu cầu → thu hoạch" cho chuỗi rau ngắn ở Mỹ Tho: nhận đơn, nhận báo sản lượng của nông hộ, dự báo 3–7 ngày, gợi ý kế hoạch thu hoạch để operator chốt, truy xuất lô bằng QR, nhận phản hồi và hiển thị KPI pilot.

**Architecture:** Laravel monolith, Blade + Tailwind + một ít Alpine.js. Nghiệp vụ nằm trong service class (`app/Services`), phần toán thuần nằm trong `app/Support` để unit test không cần DB. Schema lấy nguyên từ `database/farm2home.sql` qua một migration baseline có guard; test chạy trên MySQL riêng (`farm2home_test`) để cùng engine với production.

**Tech Stack:** Laravel 13.34 · PHP 8.3 · MySQL 8.4 · Blade · Tailwind 4 (Vite 8) · Alpine.js · Chart.js · endroid/qr-code · PHPUnit 12.

**Spec:** [`docs/CLAUDE.md`](CLAUDE.md) (bản mô tả dự án) và `database/farm2home.sql` (schema + dữ liệu mẫu). Người thực thi phải đọc cả hai trước khi làm bất kỳ phase nào.

## Global Constraints

Mọi task đều phải tuân thủ các ràng buộc sau (chép từ spec):

- **Human-in-the-loop:** AI chỉ gợi ý. Luôn lưu cả `suggested_kg` và `final_kg`; không gửi gì cho nông hộ khi operator chưa chốt.
- **Không bắt cài app / tạo tài khoản:** nông hộ dùng link `/nong-ho/{access_token}`; khách định danh bằng số điện thoại.
- **QR ≠ chứng nhận:** trang QR chỉ hiện dữ kiện của lô. Chỉ hiện chứng nhận khi `farmers.certificate_file` có giá trị và chưa hết hạn. Cấm câu quảng cáo kiểu "100% sạch".
- **Không tư vấn sức khỏe**, không tuyên bố giảm CO₂.
- **Ngôn ngữ:** giao diện tiếng Việt; code, tên biến, comment tiếng Anh (comment tiếng Việt được phép cho quy tắc nghiệp vụ).
- **Tiền:** số nguyên VND, hiển thị `12.000đ`. **Khối lượng:** `DECIMAL` kg; `order_items.weight_kg = quantity × products.pack_weight_kg`.
- **Mã:** đơn `DH{yymmdd}-{seq:03d}` theo ngày giao; lô `F2H-{yymmdd}-{product.code}-{farmer.code}`.
- **Số điện thoại:** regex `^0\d{9}$`.
- **Enum:** PHP backed enum trong `app/Enums`. **Validation:** Form Request. **Tiền/tồn kho:** bọc trong DB transaction.
- **Múi giờ** `Asia/Ho_Chi_Minh`, **locale** `vi`, **`QUEUE_CONNECTION=sync`** (không có bảng `jobs`).
- **CẤM `php artisan migrate:fresh` trên DB `farm2home`.** Lệnh này chỉ được chạy trên `farm2home_test` (PHPUnit tự làm).
- **Sửa file có sẵn theo kiểu diff nhỏ**, không viết lại cả file.
- **Stack đơn giản:** không SPA framework, không thêm package ngoài danh sách ở Tech Stack.
- **Mobile-first:** form của khách và nông hộ phải dùng tốt trên màn hình 360px, nút bấm cao tối thiểu 44px.
- **Môi trường lệnh:** chạy `php`, `composer`, `npm`, `mysql` trong **Laragon Terminal** (ngoài terminal này chúng không có trên PATH). Laragon phải đang Start (MySQL chạy).

## Quyết định thiết kế đã chốt trong kế hoạch này

Các điểm dưới đây spec chưa nói rõ; kế hoạch chọn một phương án để code được. Mục có dấu ⚠ cần team xác nhận, nhưng không chặn việc code.

| # | Vấn đề | Quyết định |
|---|---|---|
| D1 | Schema cho test và deploy | Xóa 3 migration mặc định. Thêm 1 migration baseline đọc phần schema của `database/farm2home.sql`; bỏ qua nếu bảng `orders` đã tồn tại (DB dev đã import). |
| D2 | DB cho test | MySQL `farm2home_test` (không dùng sqlite) để ENUM/DECIMAL/DATE/`lockForUpdate` hành xử như production. |
| D3 | `confirmed_order_kg` nghĩa là gì | Tổng kg của mọi đơn **chưa hủy** cho ngày giao (gồm cả `pending`). Đã kiểm chứng: khớp dữ liệu mẫu (kế hoạch 03/10, rau muống = 7,50 kg). |
| D4 | `available_stock_kg` lấy từ đâu | Tồn của các lô `passed`, thu hoạch trước ngày giao, `best_before >= ngày giao`: `quantity_kg − kg đã gán cho đơn − kg trong waste_logs`. |
| D5 | Chia cho nhiều nông hộ | (Đổi 2026-10-10) Chia theo tỉ lệ sản lượng đã báo, mỗi phần làm tròn xuống 0,5 kg; nhà được chia dưới `min_allocation_kg` (2 kg) nhường phần cho nhà khác nếu các nhà còn lại đủ rau. Trước đây: nông hộ báo nhiều nhất trước. Dòng đầu mang số `confirmed/extra/stock`; dòng tách thêm để 0. Phần thiếu tạo dòng `farmer_id = null` và phải xử lý trước khi chốt. |
| D6 | Dự báo ghi vào `forecasts` | `predicted_total_kg = max(mô hình, confirmed)`, `predicted_extra_kg = total − confirmed`, `lower = max(lower, confirmed)`. Lịch sử 28 ngày, ngày không có đơn tính 0. |
| D7 | Đo sai số | Báo cáo MAE, MAPE và thêm **WAPE** (Σ\|sai số\| / Σ thực tế) vì khối lượng từng loại quá nhỏ làm MAPE phóng đại. |
| D8 | Nội dung mã QR | URL đầy đủ `{APP_URL}/lo/{code}` (camera điện thoại mở thẳng trang), không phải chỉ chuỗi mã lô. |
| D9 ⚠ | Giờ chốt đơn | Đơn cho ngày mai đóng lúc **18:00** hôm nay (`config('farm2home.order.cutoff_hour')`); đặt trước tối đa 7 ngày. Operator nhập đơn hộ thì không bị chặn. |
| D10 ⚠ | Khung giờ giao | `06:00-08:00`, `10:00-12:00`, `17:00-19:00` (theo dữ liệu mẫu). Lưu ý: khung 06:00 mâu thuẫn với giờ thu hoạch mẫu 05:30 và đóng gói 10:00; team cần quyết có giữ khung sáng không. |
| D11 | Tra cứu đơn bằng SĐT | Chỉ hiện mã đơn, ngày giao, trạng thái, tổng tiền của 5 đơn gần nhất. Không hiện địa chỉ, không hiện tên đầy đủ. |
| D12 | Ưu tiên khi thiếu hàng | Gán lô cho đơn giỏ định kỳ trước, sau đó theo thời điểm đặt. |
| D13 | Hủy đơn đã gán lô | Gỡ liên kết `order_item_batches` để trả tồn về lô. |
| D14 | Đăng nhập | Tự viết bằng `Auth::attempt` (1 controller, 1 view). Không cài starter kit. |
| D15 | Tác vụ định kỳ | Mọi tác vụ đều có nút bấm thủ công trong admin; scheduler là phần thêm ở Phase 7. |
| D16 ⚠ | Biên gộp | `(Σ subtotal − discount của đơn đã giao) − (Σ kg lô đã nhận × cost_price_per_kg)`. Tính trên hàng đã mua, nên rau dư làm giảm biên. |
| D17 ⚠ | "Chi phí giao / đơn" | Schema không có cột chi phí giao thực tế. Dashboard hiện **phí giao thu được / đơn** làm số thay thế và ghi rõ nhãn như vậy. |
| D18 | Form Request | Form công khai (đặt hàng, báo sản lượng, phản hồi) và form tạo đơn của admin dùng Form Request. Các form admin nhỏ (lọc, đổi trạng thái, CRUD danh mục) validate ngay trong controller để bớt file; rule vẫn đầy đủ. |

## Cấu trúc file sẽ tạo

```
app/
  Enums/                17 enum (xem Phase 1, Task 1.1)
  Models/               User + 17 model nghiệp vụ (Task 1.2)
  Support/
    SqlFile.php         tách farm2home.sql thành câu lệnh schema / sample
    Format.php          vnd(), kg()
    OrderWindow.php     ngày giao sớm nhất / muộn nhất theo giờ chốt
    ShippingCalculator.php
    ForecastCalculator.php   movingAverage, expSmoothing, errors (thuần)
    HarvestMath.php     ceilToHalf, suggestedKg, allocate (thuần)
    SubscriptionSchedule.php isDue (thuần)
  Services/
    OrderService.php  SupplyReportService.php  ForecastService.php
    HarvestPlanService.php  BatchService.php  FeedbackService.php
    QrCodeService.php  KpiService.php  SubscriptionService.php
  Http/
    Middleware/EnsureRole.php
    Requests/           StoreOrderRequest, StoreAdminOrderRequest, StoreSupplyRequest, StoreFeedbackRequest, ...
    Controllers/
      Auth/LoginController.php
      Public/           HomeController, OrderController, OrderLookupController, BatchPageController, FeedbackController
      Farmer/           FarmerPortalController
      Admin/            DashboardController, OrderController, SupplyController, ForecastController,
                        HarvestPlanController, BatchController, FeedbackController, WasteController,
                        PackagingReturnController, ProductController, BasketController, FarmerController,
                        DeliveryZoneController, UserController, SubscriptionController
  Console/Commands/     ForecastRun, ForecastBackfill, SubscriptionsGenerate, DemoShiftDates
config/farm2home.php
database/migrations/2026_10_02_000000_create_farm2home_schema.php
database/seeders/DemoSeeder.php
resources/views/
  components/layouts/{public,farmer,admin}.blade.php
  components/{field,flash,status-badge}.blade.php
  auth/ public/ farmer/ admin/
lang/vi/validation.php
tests/Support/BuildsCatalog.php
tests/Unit/...  tests/Feature/...
```

## Bảng route (tên route là hợp đồng giữa các phase)

| Tên | Method + URL | Phase |
|---|---|---|
| `home` | GET `/` | 2 |
| `orders.create` / `orders.store` | GET / POST `/dat-hang` | 2 |
| `orders.thanks` | GET `/dat-hang/cam-on` (đọc mã đơn từ session, không nhận mã trên URL) | 2 |
| `lookup.show` / `lookup.search` | GET / POST `/tra-cuu` | 2 |
| `farmer.show` | GET `/nong-ho/{farmer:access_token}` | 3 |
| `farmer.supply.store` | POST `/nong-ho/{farmer:access_token}/san-luong` | 3 |
| `farmer.harvest.confirm` | POST `/nong-ho/{farmer:access_token}/thu-hoach/{plan}` | 4 |
| `batches.public` | GET `/lo/{batch:code}` | 5 |
| `feedbacks.store` | POST `/lo/{batch:code}/phan-hoi` | 5 |
| `login` / `login.store` / `logout` | GET, POST `/login` · POST `/logout` | 1 |
| `admin.dashboard` | GET `/admin` | 1 (khung), 6 (KPI) |
| `admin.orders.*` (`index, create, store, show`) + `admin.orders.transition` | `/admin/orders…` · PATCH `/admin/orders/{order}/status` | 2 |
| `admin.supply.index` / `admin.supply.store` | GET / POST `/admin/supply` | 3 |
| `admin.forecasts.index` / `.run` / `.backfill` | GET `/admin/forecasts` · POST `/run` · POST `/backfill` | 4 |
| `admin.harvest-plans.index` / `.generate` / `.show` / `.confirm` / `.sent` / `.complete` | `/admin/harvest-plans…` | 4 |
| `admin.harvest-plan-items.update` / `.receive` | PATCH `/admin/harvest-plan-items/{item}` · POST `…/receive` | 4, 5 |
| `admin.batches.index` / `.show` / `.update` / `.labels` / `.allocate` | `/admin/batches…` | 5 |
| `admin.feedbacks.index` / `.update` | `/admin/feedbacks…` | 5 |
| `admin.waste.index` / `.store` | `/admin/waste` | 6 |
| `admin.packaging-returns.index` / `.store` | `/admin/packaging-returns` | 6 |
| `admin.products.*`, `admin.baskets.*`, `admin.farmers.*`, `admin.zones.*`, `admin.users.*` (chỉ role admin) | resource | 6 |
| `admin.farmers.token` | POST `/admin/farmers/{farmer}/token` | 6 |
| `admin.subscriptions.*` + `admin.subscriptions.generate` | resource · POST `/admin/subscriptions/generate` | 7 |

Nhóm `/admin` dùng middleware `auth` + `role:admin,operator`; nhóm CRUD dữ liệu gốc dùng `role:admin`.

## Các phase

Mỗi phase là một file kế hoạch riêng, chạy xong là có phần mềm dùng được và test xanh. Làm đúng thứ tự vì phase sau dùng interface của phase trước.

| Phase | File | Kết quả bàn giao | Ưu tiên | Ước tính |
|---|---|---|---|---|
| 0 | [plans/phase-0-nen-tang.md](plans/phase-0-nen-tang.md) | Git, cấu hình đúng spec, schema baseline, DB test, seeder dữ liệu mẫu | P0 | 0,5 ngày |
| 1 | [plans/phase-1-domain-auth.md](plans/phase-1-domain-auth.md) | Enum, model, đăng nhập, phân quyền, khung giao diện | P0 | 1,5 ngày |
| 2 | [plans/phase-2-don-hang.md](plans/phase-2-don-hang.md) | Khách đặt hàng, tra cứu đơn; operator quản lý đơn | P0 | 3 ngày |
| 3 | [plans/phase-3-nong-ho.md](plans/phase-3-nong-ho.md) | Link nông hộ báo sản lượng; operator nhập hộ | P0 | 1 ngày |
| 4 | [plans/phase-4-du-bao-thu-hoach.md](plans/phase-4-du-bao-thu-hoach.md) | Dự báo, gợi ý kế hoạch, chốt, tin Zalo, nông hộ xác nhận | P0 | 3 ngày |
| 5 | [plans/phase-5-lo-qr-phan-hoi.md](plans/phase-5-lo-qr-phan-hoi.md) | Nhận hàng tạo lô, gán lô cho đơn, tem QR, trang QR, phản hồi | P0 | 2,5 ngày |
| 6 | [plans/phase-6-kpi-quan-tri.md](plans/phase-6-kpi-quan-tri.md) | Chốt ngày/rau dư, bao bì, dashboard KPI, CRUD dữ liệu gốc | P1 | 2,5 ngày |
| 7 | [plans/phase-7-p1-deploy.md](plans/phase-7-p1-deploy.md) | Giỏ định kỳ tự sinh đơn, scheduler, dữ liệu demo, deploy Railway | P1 | 1,5 ngày |

Tổng ước tính khoảng 15–16 ngày làm việc cho một người. Con số này là ước lượng thô, chưa tính thời gian chỉnh giao diện theo góp ý của team.

## Quy ước chung cho mọi task

- **Chu trình:** viết test fail → chạy thấy fail → code tối thiểu → chạy thấy pass → commit.
- **Chạy một test:** `php artisan test --filter=TenClassTest`. **Chạy tất cả:** `php artisan test`.
- **Format code:** `vendor/bin/pint --dirty` trước mỗi commit.
- **Model:** dùng attribute `#[Fillable([...])]` và hàm `casts()` như `app/Models/User.php` hiện có.
- **Service ném `DomainException`** với thông báo tiếng Việt khi vi phạm quy tắc nghiệp vụ; controller bắt và `back()->withErrors(['action' => $e->getMessage()])`.
- **View:** kế hoạch mô tả view bằng hợp đồng (route, field, chuỗi phải hiển thị) và khóa bằng feature test `assertSee`. Markup Tailwind do người thực thi viết theo layout ở Task 1.4.
- **Test có dữ liệu:** dùng trait `Tests\Support\BuildsCatalog` (danh mục nhỏ) hoặc `$this->seed(DemoSeeder::class)` (toàn bộ dữ liệu mẫu, các con số kỳ vọng trong kế hoạch đã được tính sẵn từ file SQL).
- **Thời gian trong test:** luôn `$this->travelTo('2026-10-02 10:00:00')` khi logic phụ thuộc "hôm nay".

## Review Focus

Năm tình huống spec ngầm định, dễ làm người dùng thật gặp lỗi nhất. Mỗi dòng đã có test gắn vào task sở hữu đoạn code đó.

1. **Khách đặt sau giờ chốt cho ngày mai** → form phải từ chối và chỉ ra ngày sớm nhất còn nhận (Task 2.3, `test_order_after_cutoff_for_tomorrow_is_rejected`).
2. **Khách cũ đặt lại với cùng SĐT nhưng tên/địa chỉ khác** → không tạo khách trùng, cập nhật thông tin mới (Task 2.2, `test_returning_phone_reuses_customer`).
3. **Ngày không có đơn nào trong lịch sử** → phải tính là 0 kg chứ không bỏ qua, nếu không trung bình bị đẩy cao (Task 4.2, `test_days_without_orders_count_as_zero`).
4. **Không nông hộ nào báo sản lượng cho một loại rau** → kế hoạch vẫn sinh ra, có dòng "chưa có nguồn", và không cho chốt khi dòng đó còn kg (Task 4.4, `test_confirm_is_blocked_while_a_row_has_no_farmer`).
5. **Chứng nhận của nông hộ hết hạn** → trang QR không hiện chứng nhận (Task 5.3, `test_expired_certificate_is_not_shown`).

## Việc để sau MVP (không nằm trong các phase)

- **Sinh lại dữ liệu mẫu bằng chính service.** Dữ liệu mẫu hiện cho thấy rau dư tăng từ 7,7% lên 31,8% sau ngày bật dự báo 17/09, ngược với câu chuyện pitch. Sau Phase 5 nên viết lệnh "replay" từng ngày (dự báo → gợi ý → nhận → gán → chốt ngày) để dữ liệu demo phản ánh thuật toán thật.
- **Biên an toàn trên tổng thay vì từng dòng**, và so sánh với công thức hiện tại bằng dữ liệu pilot thật.
- **Đổi rau tương đương khi thiếu** (`basket_items.allow_substitute`): cột đã có và form giỏ đã lưu, nhưng chưa có luồng đề xuất rau thay thế khi gán lô bị thiếu.
- Cá nhân hóa giỏ, Zalo OA API, mô hình cây hồi quy (dịch vụ Python riêng), PWA.

## Định nghĩa "xong" cho toàn dự án

- [ ] `php artisan test` xanh toàn bộ.
- [ ] Luồng P0 chạy được trên điện thoại: đặt đơn → operator xác nhận → nông hộ báo sản lượng → chạy dự báo → tạo và chốt kế hoạch → nhận hàng tạo lô → gán lô → in tem → quét QR → gửi phản hồi → operator xử lý.
- [ ] Dashboard hiển thị đủ 5 nhóm KPI ở mục 7 của spec.
- [ ] Trang QR không chứa câu quảng cáo; chứng nhận chỉ hiện khi hợp lệ.
- [ ] Các mục ⚠ (D9, D10, D16, D17) và 4 câu hỏi mở ở mục 9 của spec đã được team trả lời.

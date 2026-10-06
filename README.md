# FARM2HOME AI

> Điều phối nông sản theo nhu cầu, giảm dư thừa.

FARM2HOME AI là dự án khởi nghiệp sinh viên dự thi **Business Challenge 2026 — "AI for Inclusive Growth and Sustainable Communities"** (lĩnh vực nông nghiệp bền vững và thông minh), do nhóm 4 sinh viên Trường Đại học Tiền Giang thực hiện.

## Dự án này là gì

FARM2HOME AI **không phải** một cửa hàng rau online. Đây là công cụ điều phối **nhu cầu → thu hoạch** cho chuỗi cung ứng rau ngắn ở Mỹ Tho và vùng lân cận.

Vấn đề cần giải: nhu cầu của người mua và quyết định thu hoạch của nông hộ nhỏ không khớp nhau, nên nông hộ hay cắt dư và rau bị bỏ. Hệ thống biến dữ liệu đơn hàng thành một quyết định vận hành cụ thể: **3–7 ngày tới cần thu hoạch bao nhiêu kg mỗi loại rau, từ nông hộ nào**.

```
Đơn đặt trước (Zalo / form / web) ─┐
Nông hộ báo sản lượng ─────────────┼─► Dự báo (3–7 ngày) ─► Gợi ý thu hoạch ─► Operator chốt
Giỏ rau định kỳ ───────────────────┘                                              │
        ▲                                                                         ▼
  Phản hồi theo lô ◄── QR truy xuất từng lô ◄── Đóng gói & giao ◄── Nông hộ thu hoạch
```

## Ai dùng, làm được gì

| Người dùng | Cách vào | Chức năng |
|---|---|---|
| **Khách** | Không cần tài khoản, định danh bằng số điện thoại | Đặt giỏ rau hoặc chọn lẻ theo ngày giao, khung giờ, khu vực · tra cứu đơn bằng SĐT · quét QR xem thông tin lô và gửi phản hồi |
| **Nông hộ** | Link riêng gửi qua Zalo, không cần cài app hay đăng nhập | Báo sản lượng có thể thu cho những ngày tới · xem và xác nhận danh sách thu hoạch |
| **Operator** | Đăng nhập | Quản lý đơn · chạy dự báo · tạo, chỉnh và chốt kế hoạch thu hoạch · soạn tin Zalo cho từng nông hộ · nhận hàng tạo lô, gán lô cho đơn, in tem QR · xử lý phản hồi · ghi rau dư và bao bì thu hồi · quản lý giỏ định kỳ · xem dashboard KPI |
| **Admin** | Đăng nhập | Mọi việc của operator + quản lý sản phẩm, giỏ rau, nông hộ, cụm giao, tài khoản |

## Nguyên tắc thiết kế

- **Con người quyết định cuối cùng.** Hệ thống chỉ *gợi ý*; operator luôn xem lại và chốt trước khi gửi gì cho nông hộ. Số gợi ý (`suggested_kg`) và số chốt (`final_kg`) đều được lưu, kèm lý do khi hai số khác nhau.
- **Ai cũng dùng được.** Nông hộ và khách không phải cài app hay tạo tài khoản; giao diện ưu tiên điện thoại, nút bấm lớn, ít phải gõ.
- **QR không phải chứng nhận.** Trang QR chỉ hiện dữ kiện của lô: nông hộ, khu vực trồng, giờ thu hoạch / sơ chế / đóng gói, cách bảo quản. Chứng nhận chỉ hiện khi nông hộ có file chứng nhận còn hạn. Không có câu quảng cáo kiểu "100% sạch".
- **Công nghệ vừa đủ.** Một ứng dụng Laravel, không SPA, không hàng đợi, không phụ thuộc dịch vụ ML bên ngoài.
- **Không tư vấn sức khỏe, không tuyên bố giảm CO₂.** Dự án không đo phát thải vận chuyển nên không nói về nó.
- **Đo được.** Mỗi chức năng đều cấp số liệu cho bộ KPI của pilot.

## Dự báo và gợi ý thu hoạch

**Dự báo** tính cho từng loại rau, từng ngày giao, từ lịch sử 28 ngày (ngày không có đơn tính là 0 kg):

- Trung bình trượt 7 ngày, kèm khoảng dao động ± độ lệch chuẩn.
- Làm mượt hàm mũ, α = 0,3.

Mỗi lần chạy được lưu lại; sau ngày giao hệ thống điền số thực tế để tính **MAE, MAPE và WAPE** cho từng phương pháp. Một mô hình chỉ được coi là tốt hơn khi nó thắng baseline trên sai số thật.

**Gợi ý thu hoạch:**

```
kg gợi ý = làm_tròn_lên_0,5( max(0, kg đơn đã có + kg dự báo thêm − kg tồn) × (1 + biên an toàn) )
```

Biên an toàn mặc định 10%. Số kg được chia cho nông hộ theo sản lượng họ đã báo (hộ báo nhiều nhất trước). Loại rau chưa có nông hộ nào báo vẫn xuất hiện trong kế hoạch với dòng "chưa có nguồn", và kế hoạch không chốt được khi dòng đó còn kg. Sau khi chốt, hệ thống soạn sẵn tin nhắn cho từng nông hộ để operator sao chép hoặc mở Zalo; bản MVP chưa dùng Zalo OA API.

## KPI của pilot

Dashboard `/admin` hiển thị 5 nhóm số liệu:

| Nhóm | Chỉ số |
|---|---|
| Dự báo | MAE / MAPE / WAPE theo phương pháp, số lần operator chỉnh khác gợi ý |
| Khách hàng | Tỷ lệ giao đủ, tỷ lệ khách mua lại, điểm đánh giá, phản hồi đã xử lý |
| Kinh tế | Doanh thu, giá trị đơn trung bình, biên gộp, phí giao thu được / đơn |
| Nông hộ | Số nông hộ đang hợp tác, tỷ lệ sản lượng được xác nhận trước |
| Môi trường | Kg rau dư theo tuần, tỷ lệ thu hồi và tái sử dụng bao bì |

**Phạm vi pilot dự kiến:** 4 tuần tại Mỹ Tho · 2–3 nông hộ (Thân Cửu Nghĩa, Gò Công) · 20–30 hộ khách · 5–7 loại rau.

## Công nghệ

Laravel 13 (PHP 8.3) · MySQL 8 · Blade + Tailwind CSS 4 (Vite) · Alpine.js · Chart.js · `endroid/qr-code` · PHPUnit 12.

```
app/
  Enums/       enum cho mọi cột ENUM của DB, kèm nhãn tiếng Việt
  Models/      model Eloquent cho các bảng nghiệp vụ
  Support/     phần toán thuần, test không cần DB (dự báo, làm tròn, phí giao, lịch giỏ định kỳ…)
  Services/    nghiệp vụ: đơn hàng, sản lượng, dự báo, kế hoạch thu hoạch, lô, phản hồi, QR, KPI, giỏ định kỳ
  Http/        controller cho Public · Farmer · Admin, Form Request, middleware phân quyền
  Console/     lệnh artisan chạy theo lịch
config/farm2home.php    giờ chốt đơn, khung giờ giao, biên an toàn, tham số dự báo
database/farm2home.sql  schema 24 bảng + dữ liệu mẫu
docs/                   mô tả dự án và kế hoạch code
```

Tài liệu chi tiết:

- Hướng dẫn sử dụng từng màn hình, kèm bài thực hành: [`docs/HUONG-DAN-SU-DUNG.md`](docs/HUONG-DAN-SU-DUNG.md)
- Mô tả dự án, quy ước dữ liệu và nghiệp vụ: [`docs/CLAUDE.md`](docs/CLAUDE.md)
- Kế hoạch code và các quyết định thiết kế: [`docs/PLAN.md`](docs/PLAN.md)

## Dữ liệu mẫu

`database/farm2home.sql` chứa dữ liệu **giả lập** từ 10/09/2026 đến 03/10/2026 để demo và kiểm thử, không phải số liệu pilot thật. Danh sách rau, giá, giỏ, cụm giao và thông tin nông hộ đều là giá trị tạm; chứng nhận VietGAP của nông hộ NH02 trong dữ liệu mẫu là giả, chỉ để thử giao diện.

## Chạy trên máy (Laragon)

Chạy các lệnh trong **Laragon Terminal** (PHP, Composer, MySQL nằm trên PATH của nó), Laragon đang Start.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

mysql -u root -e "CREATE DATABASE IF NOT EXISTS farm2home CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS farm2home_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

php artisan migrate        # dựng 24 bảng từ database/farm2home.sql (bỏ qua nếu DB đã import sẵn)
php artisan db:seed        # nạp dữ liệu demo, chỉ chạy được trên DB trống
php artisan storage:link
npm run dev                # hoặc: npm run build
```

Mở `http://farm2home.test`.

## Tài khoản demo

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Quản trị | `admin@farm2home.vn` | `password` |
| Vận hành | `operator@farm2home.vn` | `password` |

Chỉ dùng cho demo. Khi đưa lên một địa chỉ công khai, hãy đổi mật khẩu và bấm "Cấp link mới" cho từng nông hộ, vì mật khẩu và token mẫu nằm trong mã nguồn.

## Các đường dẫn chính

| Ai dùng | Đường dẫn |
|---|---|
| Khách | `/` · `/dat-hang` · `/tra-cuu` · `/lo/{mã lô}` (trang QR + phản hồi) |
| Nông hộ | `/nong-ho/{token}` (báo sản lượng, xác nhận thu hoạch; không cần đăng nhập) |
| Operator / Admin | `/login` → `/admin` |

## Test

```bash
php artisan test
```

Test chạy trên DB `farm2home_test` (cấu hình trong `phpunit.xml`), không đụng tới DB `farm2home`.

## Lệnh hữu ích

| Lệnh | Việc làm |
|---|---|
| `php artisan forecast:run {ngày?}` | Chạy dự báo cho 7 ngày giao kế tiếp |
| `php artisan forecast:backfill {ngày?}` | Điền số thực tế cho các dự báo đã qua ngày giao |
| `php artisan subscriptions:generate {ngày?}` | Sinh đơn cho giỏ định kỳ (mặc định ngày mai) |
| `php artisan demo:shift-dates` | Dịch ngày của dữ liệu demo về gần hôm nay (theo tuần nguyên) |
| `php artisan schedule:work` | Chạy lịch tự động trên máy dev: sinh đơn giỏ định kỳ 12:00, dự báo 18:15, điền số thực tế 21:00 (không bắt buộc; mọi việc đều có nút bấm trong admin) |

## Cảnh báo

- **Không chạy `php artisan migrate:fresh`** trên DB có dữ liệu thật: lệnh này xóa toàn bộ bảng.
- `php artisan db:seed` và `demo:shift-dates` chỉ dành cho bản demo, không dùng trên dữ liệu pilot thật.

## Deploy

Các bước deploy Railway (biến môi trường, lệnh build, volume cho file chứng nhận, nạp dữ liệu ban đầu) nằm ở [`docs/plans/phase-7-p1-deploy.md`](docs/plans/phase-7-p1-deploy.md), Task 7.4.

## Trạng thái

Bản MVP đã có đủ luồng chính: đặt đơn → quản lý đơn → nông hộ báo sản lượng → dự báo và kế hoạch thu hoạch → lô và trang QR → phản hồi, cùng dashboard KPI, giỏ định kỳ tự sinh đơn và thu hồi bao bì. Ứng dụng chưa được deploy và chưa chạy pilot thật.

Còn chờ nhóm quyết định trước khi chạy pilot:

- Danh sách rau, quy cách gói, giá bán; nội dung và giá các giỏ.
- Cụm giao, điểm nhận và phí giao thực tế.
- Thông tin và chứng nhận thật của nông hộ.
- Giờ chốt đơn (đang là 18:00), các khung giờ giao, cách tính biên gộp và chi phí giao mỗi đơn.

Để sau MVP: gợi ý rau thay thế khi thiếu hàng, cá nhân hóa giỏ, tự động hóa qua Zalo OA, mô hình dự báo học máy, PWA.

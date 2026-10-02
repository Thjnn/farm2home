# FARM2HOME AI

Công cụ điều phối "nhu cầu → thu hoạch" cho chuỗi rau ngắn ở Mỹ Tho: nhận đơn, nhận báo sản lượng của nông hộ, dự báo nhu cầu, gợi ý kế hoạch thu hoạch để operator chốt, truy xuất lô bằng QR và đo KPI pilot.

- Mô tả dự án: [`docs/CLAUDE.md`](docs/CLAUDE.md)
- Kế hoạch code và các quyết định thiết kế: [`docs/PLAN.md`](docs/PLAN.md)

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
| `php artisan schedule:work` | Chạy lịch tự động trên máy dev (không bắt buộc; mọi việc đều có nút bấm trong admin) |

## Cảnh báo

- **Không chạy `php artisan migrate:fresh`** trên DB có dữ liệu thật: lệnh này xóa toàn bộ bảng.
- `php artisan db:seed` và `demo:shift-dates` chỉ dành cho bản demo, không dùng trên dữ liệu pilot thật.

## Deploy

Các bước deploy Railway (biến môi trường, lệnh build, volume cho file chứng nhận, nạp dữ liệu ban đầu) nằm ở [`docs/plans/phase-7-p1-deploy.md`](docs/plans/phase-7-p1-deploy.md), Task 7.4.

# Ghi chú: deploy miễn phí và chuyển sang PostgreSQL

Ghi ngày 2026-10-23. **Chưa làm.** Đây là ghi chú để quyết định sau. Hiện dự án vẫn dùng MySQL (đã chốt ngày 17/10, giữ tới sau cuộc thi).

Giá và giới hạn các gói free có thể đã đổi. Xem lại trang giá trước khi làm.

## 1. Deploy miễn phí bằng Render + Supabase

| Dịch vụ | Dùng cho | Ghi chú |
|---|---|---|
| **Render** (free) | Chạy Laravel bằng Dockerfile | Ngủ sau ~15 phút không ai vào, lần mở đầu mất 30–60 giây. Ổ đĩa bị xóa sau mỗi lần deploy. Không có cron |
| **Supabase** (free) | Postgres (500 MB) + Storage lưu ảnh | Tạm dừng nếu 7 ngày không có ai dùng. Kết nối bằng **Session pooler** (Render free không chắc có IPv6) |
| **Vercel** | Không dùng | Không chạy PHP sẵn, serverless không ghi file được |

Lưu ý:
- **Cả 3 dịch vụ đều không có MySQL**, nên phải chuyển sang Postgres (mục 2).
- **Ảnh** (nông hộ, lô, chứng nhận) phải lưu lên Supabase Storage. Cần thêm gói `league/flysystem-aws-s3-v3` (đổi dependency, cần đồng ý). Không làm thì mất ảnh sau mỗi lần deploy.
- **Tác vụ hằng ngày:** không có cron, nên người vận hành bấm 3 nút trong admin mỗi ngày (tạo đơn giỏ định kỳ, chạy dự báo, ghi số thực tế).
- **Trước khi thuyết trình:** mở trang trước vài phút để Render thức dậy.
- Các mục về `APP_KEY`, biến `BANK_*`, `GEMINI_*` và bảo mật dữ liệu mẫu trong [`TRUOC-KHI-DEPLOY.md`](TRUOC-KHI-DEPLOY.md) vẫn áp dụng.

Cách khác:
- **Railway:** chỉ có khoản dùng thử, sau đó khoảng 5 USD/tháng. Không phải sửa code.
- **Oracle Cloud Always Free:** miễn phí thật và giữ được MySQL, nhưng phải tự cài server.

## 2. Chuyển MySQL sang PostgreSQL

### Code ứng dụng: khoảng 5–6 chỗ

| Chỗ | Sửa gì |
|---|---|
| `app/Services/ForecastService.php` (2 chỗ `whereRaw`) | `DATEDIFF(target_date, generated_on)` đổi thành `(target_date - generated_on)` |
| `app/Console/Commands/DemoShiftDates.php` | `DATE_FORMAT`, dấu `` ` ``, `UPDATE ... ORDER BY` đều phải viết lại theo cú pháp Postgres |
| Migration `2026_10_06_163704_add_weekday_average_to_forecast_method` | `ALTER ... MODIFY ENUM` đổi sang cú pháp Postgres |
| Migration `2026_10_02_000000_create_farm2home_schema` | Bỏ `SET FOREIGN_KEY_CHECKS = 0` |
| `phpunit.xml` | `DB_CONNECTION` đổi thành `pgsql` |

Controller, view và các service khác dùng Eloquent/Query Builder nên không phải sửa.

### Phần nặng: `database/farm2home.sql`

- File dài ~2.700 dòng, gồm 24 lệnh `INSERT` và 17 cột kiểu `ENUM`.
- Cấu trúc bảng phải viết lại: `ENUM` thành `varchar` kèm `CHECK`, `AUTO_INCREMENT` thành `GENERATED ... AS IDENTITY` (hoặc `bigserial`), kiểu `JSON`, bỏ dấu `` ` ``, bỏ `ENGINE=...`.
- Dữ liệu mẫu giữ nguyên, chỉ chỉnh cú pháp.
- **Sau khi nạp dữ liệu phải đặt lại bộ đếm id** (`setval`). Nếu không, lần thêm dòng mới đầu tiên sẽ báo lỗi trùng khóa.

### Rủi ro ẩn (chỉ lộ ra khi chạy test)

- `GROUP BY` phải liệt kê đủ các cột được `SELECT`.
- `LIKE` phân biệt hoa thường (dùng `ILIKE` nếu cần).
- So sánh chữ với số sẽ báo lỗi, không tự ép kiểu như MySQL.
- Cột boolean là `true`/`false`, không phải `1`/`0`.

Bộ test hiện có khoảng 28 file, bắt được phần lớn các lỗi trên nhưng không chắc bắt được hết.

### Ước tính

- Chuyển cơ sở dữ liệu và chạy test xanh: khoảng **một buổi** (4–6 tiếng).
- Dockerfile và lưu ảnh lên Supabase Storage: thêm khoảng **nửa buổi**.
- Trên máy: tạo lại cơ sở dữ liệu bằng PostgreSQL 18 / pgAdmin (đã cài sẵn).

### Cách làm an toàn

1. Làm trên **nhánh git riêng**, để nếu trục trặc gần ngày thi thì quay về MySQL ngay.
2. Chuyển cơ sở dữ liệu, rồi chạy lại toàn bộ test.
3. Viết Dockerfile và build thử bằng Docker trên máy.
4. Tạo Supabase (Postgres + Storage), rồi tạo Render Web Service và khai báo các biến môi trường.
5. Kiểm tra trên điện thoại theo mục 6 của [`TRUOC-KHI-DEPLOY.md`](TRUOC-KHI-DEPLOY.md).

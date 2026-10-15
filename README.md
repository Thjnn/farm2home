<p align="center">
  <img src="public/images/icon-f2h-removebg-preview.png" alt="Logo Farm2Home" width="160">
</p>

<h1 align="center">FARM2HOME AI</h1>

<p align="center"><em>Nông sản sạch đến từng mái ấm</em><br>Điều phối nông sản theo nhu cầu, giảm dư thừa.</p>

Dự án khởi nghiệp sinh viên dự thi **Business Challenge 2026 — "AI for Inclusive Growth and Sustainable Communities"** (lĩnh vực nông nghiệp bền vững và thông minh), do nhóm 4 sinh viên Trường Đại học Tiền Giang thực hiện.

## Dự án này là gì

FARM2HOME AI **không phải** cửa hàng rau online. Đây là công cụ điều phối **nhu cầu → thu hoạch** cho chuỗi cung ứng rau sạch ngắn ở Mỹ Tho và vùng lân cận (tỉnh Đồng Tháp, trước là Tiền Giang).

Vấn đề: người mua cần bao nhiêu và nông hộ nhỏ cắt bao nhiêu không khớp nhau, nên rau hay bị cắt dư rồi bỏ. Hệ thống biến dữ liệu đơn hàng thành một quyết định cụ thể: **3–7 ngày tới cần thu hoạch bao nhiêu kg mỗi loại rau, từ nông hộ nào**.

```
Đơn đặt trước (Zalo / form / web) ─┐
Nông hộ báo sản lượng ─────────────┼─► Dự báo (7 ngày) ─► Gợi ý thu hoạch ─► Người điều phối chốt
Giỏ rau định kỳ ───────────────────┘                                              │
        ▲                                                                         ▼
  Phản hồi theo lô ◄── QR truy xuất từng lô ◄── Đóng gói & giao ◄── Nông hộ thu hoạch
```

Hiện bán 5 loại rau: **Rau muống, Cải xanh, Cải ngọt, Xà lách, Hành lá** (Mồng tơi và Rau dền đã ngưng bán, vẫn giữ trong DB để tra đơn cũ).

## Ai dùng, làm được gì

| Người dùng | Cách vào | Chức năng |
|---|---|---|
| **Khách** | Không cần tài khoản, định danh bằng số điện thoại | Đặt giỏ rau hoặc rau lẻ theo ngày, khung giờ, cụm giao · tra cứu đơn và điểm thưởng bằng SĐT · đổi điểm lấy quà khi đặt · hỏi chatbot · quét QR xem thông tin lô và gửi phản hồi |
| **Nông hộ** | Link riêng gửi qua Zalo, không cài app, không đăng nhập | Báo sản lượng có thể thu những ngày tới · xem nhu cầu dự kiến · xem và xác nhận danh sách thu hoạch |
| **Người điều phối** (operator) | Đăng nhập | Quản lý đơn · chạy dự báo · xem lịch dự báo · tạo, chỉnh, chốt kế hoạch thu hoạch · soạn tin Zalo cho từng nông hộ · nhận hàng, tạo lô, in tem QR · xử lý phản hồi · ghi rau dư, bao bì thu hồi · giỏ định kỳ · điểm thành viên · dashboard KPI |
| **Quản trị** (admin) | Đăng nhập | Mọi việc của người điều phối + quản lý sản phẩm, giỏ rau, nông hộ, cụm giao, tài khoản |

Trang quản trị có menu bên trái chia 2 nhóm *Vận hành* và *Danh mục*, thu gọn được thành cột icon. Trên điện thoại menu mở bằng nút ☰.

**Thông báo không cần F5:** trang quản trị tự hỏi máy chủ mỗi 15 giây và hiện thông báo ở góc màn hình khi có đơn mới, đơn đổi trạng thái, nông hộ báo sản lượng hoặc xác nhận thu hoạch, phản hồi mới. Số đỏ cạnh *Đơn hàng* (đơn chờ xác nhận) và *Phản hồi* (chưa xử lý) tự cập nhật.

**Cài như app (PWA):** trên HTTPS, điện thoại hiện "Cài đặt ứng dụng" (iPhone: *Chia sẻ → Thêm vào MH chính*). Một app chung cho mọi vai trò, mở ở `/app`: đã đăng nhập → quản trị; máy từng mở link nông hộ → trang nông hộ đó; còn lại → trang khách. Mất mạng thì hiện trang "Chưa có mạng".

## Nguyên tắc thiết kế

- **Con người quyết định cuối cùng.** Hệ thống chỉ *gợi ý*; người điều phối luôn xem lại và chốt trước khi gửi gì cho nông hộ. Lưu cả số gợi ý (`suggested_kg`) và số chốt (`final_kg`), kèm lý do khi khác nhau.
- **Ai cũng dùng được.** Nông hộ và khách không cài app, không tạo tài khoản. Giao diện ưu tiên điện thoại, nút lớn, ít phải gõ.
- **QR không phải chứng nhận.** Trang QR chỉ hiện dữ kiện của lô: nông hộ, vùng trồng, giờ thu hoạch / sơ chế / đóng gói, cách bảo quản. Chứng nhận chỉ hiện khi nông hộ có file chứng nhận còn hạn. Không có câu kiểu "100% sạch".
- **Công nghệ vừa đủ.** Một ứng dụng Laravel, không SPA, không hàng đợi, không dịch vụ ML riêng.
- **Không tư vấn sức khỏe, không tuyên bố giảm CO₂.** Dự án không đo phát thải vận chuyển.
- **Đo được.** Mỗi chức năng đều cấp số liệu cho bộ KPI của pilot.

## Phần "AI"

### Dự báo nhu cầu

Tính cho từng loại rau, từng ngày giao trong 7 ngày tới, từ lịch sử 28 ngày đơn không bị hủy. Có 3 cách đoán, mỗi lần chạy lưu cả 3 để so:

| Cách đoán | Ý tưởng |
|---|---|
| Trung bình trượt 7 ngày | Trung bình 7 ngày gần nhất, kèm khoảng ± độ lệch chuẩn. **Đang dùng cho kế hoạch thu hoạch** |
| Làm mượt hàm mũ (α = 0,3) | Ngày gần đây nặng ký hơn ngày xa |
| Trung bình cùng thứ | Lấy trung bình các ngày cùng thứ trong tuần (bắt được nhịp cuối tuần) |

Dự báo không bao giờ thấp hơn số kg khách đã đặt. Sau ngày giao, hệ thống điền số thực tế để chấm **MAE, MAPE, WAPE** và **"% đoán đúng" = 100 − WAPE** (chỉ chấm con số đoán trước 1 ngày, là con số dùng để quyết định thu hoạch). Một cách đoán chỉ được coi là tốt hơn khi thắng trên sai số thật.

Trang **Dự báo** (`/admin/forecasts`) viết bằng lời dễ hiểu:

- Vòng "% đoán đúng" 14 ngày qua; dưới 80% thì cảnh báo và liệt kê những ngày lệch nhiều nhất.
- **Lịch tháng**: ngày đã giao tô xanh / vàng / đỏ theo mức đoán đúng, ngày sắp tới hiện số kg máy đoán. Bấm vào một ngày để xem từng loại rau (khách đã đặt, máy đoán, thực tế).
- Biểu đồ 4 tuần đã bán + 7 ngày dự báo, lọc được theo loại rau.
- Bảng so sánh kỹ thuật các cách đoán (gập lại).

### Gợi ý thu hoạch

```
kg gợi ý = làm_tròn_lên_0,5( max(0, kg đơn đã có + kg dự báo thêm − kg tồn) × (1 + biên an toàn) )
```

- Biên an toàn mặc định 10%.
- Số kg **chia cho các nông hộ theo tỉ lệ sản lượng họ đã báo**. Nhà nào được chia dưới 2 kg thì nhường phần cho nhà khác.
- Loại rau chưa có nông hộ báo vẫn hiện trong kế hoạch với dòng "chưa có nguồn"; kế hoạch không chốt được khi dòng đó còn kg.
- Sau khi chốt, hệ thống soạn sẵn tin nhắn cho từng nông hộ để sao chép hoặc mở Zalo (`https://zalo.me/{sđt}`). Bản MVP chưa dùng Zalo OA API.

### Chatbot hỏi đáp

Ô hỏi đáp ở trang đặt hàng trả lời về giá, giỏ rau, giờ giao, cách tra đơn, nguồn gốc lô và nhu cầu dự kiến. Dùng Gemini (`GEMINI_API_KEY`) khi có; không có key hoặc gọi lỗi thì trả lời theo kịch bản soạn sẵn. Chatbot chỉ gợi ý mua sắm, không tư vấn sức khỏe.

Giải thích chi tiết (công thức, file, giới hạn khi thuyết trình): [`docs/CHUC-NANG-AI.md`](docs/CHUC-NANG-AI.md).

## Điểm thành viên

Khách được cộng 5 điểm mỗi đơn giao xong (tiền hàng từ 50.000đ) và 5 điểm mỗi lần trả bao bì. Đổi điểm ngay khi đặt hàng:

| Quà | Điểm |
|---|---|
| Tặng bó rau nhỏ | 20 |
| Miễn phí 1 lần giao | 30 |
| Giảm 20.000đ | 50 |
| Tặng combo rau nhỏ (3 gói) | 100 |

Form đặt hàng hiện số điểm của khách và làm mờ quà chưa đủ điểm; máy chủ kiểm tra lại khi lưu đơn.

## KPI của pilot

Dashboard `/admin`:

| Nhóm | Chỉ số |
|---|---|
| Dự báo | MAE / MAPE / WAPE theo cách đoán, số lần người điều phối chỉnh khác gợi ý |
| Khách hàng | Tỷ lệ giao đủ, tỷ lệ mua lại, điểm đánh giá, phản hồi đã xử lý |
| Kinh tế | Doanh thu, giá trị đơn trung bình, biên gộp, phí giao thu được / đơn |
| Nông hộ | Số nông hộ đang hợp tác, tỷ lệ sản lượng được xác nhận trước |
| Môi trường | Kg rau dư theo tuần, tỷ lệ thu hồi và tái sử dụng bao bì |

**Phạm vi pilot dự kiến:** 4 tuần tại Mỹ Tho · 2–3 nông hộ (ưu tiên Châu Thành, Cai Lậy, Chợ Gạo và vùng lân cận) · 20–30 hộ khách · 5 loại rau.

## Công nghệ

Laravel 13 (PHP 8.3) · MySQL 8 · Blade + Tailwind CSS 4 (Vite) · Alpine.js · Chart.js · `endroid/qr-code` · Gemini API (tùy chọn) · PHPUnit 12.

```
app/
  Enums/       enum cho mọi cột ENUM của DB, kèm nhãn tiếng Việt
  Models/      model Eloquent
  Support/     phần toán thuần, test không cần DB (dự báo, làm tròn, phí giao, lịch giỏ định kỳ…)
  Services/    nghiệp vụ: đơn hàng, sản lượng, dự báo, kế hoạch thu hoạch, lô, phản hồi, QR, KPI,
               giỏ định kỳ, điểm thưởng, chatbot
  Http/        controller Public · Farmer · Admin, Form Request, middleware phân quyền
  Console/     lệnh artisan
config/farm2home.php    giờ chốt đơn, khung giờ giao, biên an toàn, tham số dự báo, điểm thưởng
database/farm2home.sql  schema 24 bảng + dữ liệu mẫu
database/migrations/    migration dựng schema từ file SQL + các thay đổi sau đó
docs/                   tài liệu dự án
```

Tài liệu:

- Hướng dẫn từng màn hình, kèm bài thực hành: [`docs/HUONG-DAN-SU-DUNG.md`](docs/HUONG-DAN-SU-DUNG.md)
- Mỗi vai trò làm được gì: [`docs/CHUC-NANG-THEO-VAI-TRO.md`](docs/CHUC-NANG-THEO-VAI-TRO.md)
- Phần AI: [`docs/CHUC-NANG-AI.md`](docs/CHUC-NANG-AI.md)
- Mô tả dự án, quy ước dữ liệu và nghiệp vụ: [`docs/CLAUDE.md`](docs/CLAUDE.md)
- Kế hoạch code và các quyết định thiết kế: [`docs/PLAN.md`](docs/PLAN.md)

## Chạy trên máy (Laragon)

Mở **Laragon Terminal** (có sẵn PHP, Composer, MySQL, Node trên PATH) và bấm Start Laragon.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

mysql -u root -e "CREATE DATABASE IF NOT EXISTS farm2home CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS farm2home_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

php artisan migrate        # dựng 24 bảng từ database/farm2home.sql (bỏ qua nếu DB đã import sẵn)
php artisan db:seed        # nạp dữ liệu mẫu, chỉ chạy trên DB trống
php artisan demo:orders    # (tùy chọn) sinh ~2.000 đơn mô phỏng 12 tuần để dự báo và lịch có số
php artisan storage:link
npm run build              # hoặc npm run dev khi đang sửa giao diện
```

Mở `http://farm2home.test`.

Muốn bật chatbot Gemini thì điền `GEMINI_API_KEY` trong `.env` (`GEMINI_MODEL` mặc định `gemini-flash-lite-latest`).

### Gửi link demo tạm thời

`link-tam-thoi.bat` chạy app và mở một link công khai tạm thời qua Cloudflare Quick Tunnel (không cần tài khoản, cần cài `cloudflared`). Link được chép sẵn để dán vào Zalo; nhấn phím bất kỳ trong cửa sổ để tắt. Chạy `npm run build` trước, và **đổi mật khẩu mẫu** trước khi gửi link cho người ngoài.

## Tài khoản demo

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Quản trị | `admin@farm2home.vn` | `password` |
| Người điều phối | `operator@farm2home.vn` | `password` |

Chỉ dùng cho demo. Khi đưa lên địa chỉ công khai, hãy đổi mật khẩu và bấm "Cấp link mới" cho từng nông hộ, vì mật khẩu và token mẫu nằm trong mã nguồn.

## Các đường dẫn chính

| Ai dùng | Đường dẫn |
|---|---|
| Khách | `/` · `/dat-hang` · `/tra-cuu` · `/lo/{mã lô}` (trang QR + phản hồi) |
| Nông hộ | `/nong-ho/{token}` (báo sản lượng, xác nhận thu hoạch) |
| Người điều phối / Quản trị | `/login` → `/admin` |

## Dữ liệu mẫu

`database/farm2home.sql` chứa dữ liệu **giả lập** từ 10/09 đến 03/10/2026 để demo và kiểm thử; `demo:orders` sinh thêm đơn mô phỏng có nhịp cuối tuần. Đây không phải số liệu pilot thật. Giá, giỏ, cụm giao và thông tin nông hộ đều là giá trị tạm; chứng nhận VietGAP của nông hộ NH02 là giả, chỉ để thử giao diện.

## Lệnh hữu ích

| Lệnh | Việc làm |
|---|---|
| `php artisan forecast:run {ngày?}` | Chạy dự báo cho 7 ngày giao kế tiếp |
| `php artisan forecast:backfill {ngày?}` | Điền số thực tế cho các dự báo đã qua ngày giao |
| `php artisan subscriptions:generate {ngày?}` | Sinh đơn cho giỏ định kỳ (mặc định ngày mai) |
| `php artisan demo:orders` | Sinh đơn mô phỏng rồi chạy lại dự báo cho từng ngày đã qua (`--count`, `--days`, `--seed`) |
| `php artisan demo:shift-dates` | Dịch ngày của dữ liệu demo về gần hôm nay (theo tuần nguyên) |
| `php artisan loyalty:backfill` | Cộng điểm cho đơn đã giao và lượt trả bao bì còn thiếu (chạy lại không cộng trùng) |
| `php artisan schedule:work` | Chạy lịch tự động: sinh đơn giỏ định kỳ 12:00, dự báo 18:15, điền số thực tế 21:00 (không bắt buộc; việc nào cũng có nút trong admin) |

## Test

```bash
php artisan test
```

Test chạy trên DB `farm2home_test` (cấu hình trong `phpunit.xml`), không đụng tới DB `farm2home`.

## Cảnh báo

- **Không chạy `php artisan migrate:fresh`** trên DB có dữ liệu thật: lệnh này xóa toàn bộ bảng.
- `db:seed`, `demo:orders` và `demo:shift-dates` chỉ dành cho bản demo, không dùng trên dữ liệu pilot thật.
- Sửa giao diện xong mà không thấy thay đổi thì chạy lại `npm run build`.

## Deploy

Các bước deploy Railway (biến môi trường, lệnh build, volume cho file chứng nhận, nạp dữ liệu ban đầu) nằm ở [`docs/plans/phase-7-p1-deploy.md`](docs/plans/phase-7-p1-deploy.md), Task 7.4. Nhớ thêm `GEMINI_API_KEY` / `GEMINI_MODEL` nếu dùng chatbot.

## Trạng thái

Bản MVP đã có đủ luồng chính: đặt đơn → quản lý đơn → nông hộ báo sản lượng → dự báo và kế hoạch thu hoạch → lô và trang QR → phản hồi. Ngoài ra có dashboard KPI, giỏ định kỳ tự sinh đơn, thu hồi bao bì, điểm thành viên, chatbot, lịch dự báo, thông báo không cần F5 và cài như app (PWA). Ứng dụng chưa deploy và chưa chạy pilot thật.

Còn chờ nhóm quyết định trước khi chạy pilot:

- Quy cách gói, giá bán; nội dung và giá các giỏ.
- Cụm giao, điểm nhận và phí giao thực tế.
- Thông tin và chứng nhận thật của nông hộ.
- Giờ chốt đơn (đang là 18:00), các khung giờ giao, cách tính biên gộp và chi phí giao mỗi đơn.
- Có chuyển kế hoạch thu hoạch sang cách đoán "trung bình cùng thứ" không (trên dữ liệu mô phỏng nó đoán sát hơn trung bình trượt).

Để sau MVP: gợi ý rau thay thế khi thiếu hàng, cá nhân hóa giỏ, tự động hóa qua Zalo OA, mô hình dự báo học máy, thông báo đẩy khi app đang đóng.

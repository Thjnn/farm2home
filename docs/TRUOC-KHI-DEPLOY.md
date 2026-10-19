# Ghi chú trước khi deploy

Đọc lại file này mỗi lần đưa bản mới lên Railway. Các bước chi tiết (lệnh build, dịch vụ MySQL, nạp dữ liệu, kiểm tra sau deploy) nằm ở [`plans/phase-7-p1-deploy.md`](plans/phase-7-p1-deploy.md), Task 7.4.

## 1. Biến môi trường trên Railway (phần Variables)

File `.env` trên máy **không** được đưa lên Railway. Biến nào cần dùng thì phải thêm tay.

- [ ] Nhóm cơ bản: `APP_*`, `DB_*`, `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION=sync`. Danh sách đầy đủ có ở Task 7.4, bước 6.
- [ ] `APP_DEBUG=false`, `APP_ENV=production`.
- [ ] `APP_URL` là domain `https://` mà Railway cấp.
- [ ] Chatbot: `GEMINI_API_KEY`, `GEMINI_MODEL`. Không có thì chatbot trả lời theo kịch bản soạn sẵn.
- [ ] Chuyển khoản: `BANK_ID`, `BANK_NAME`, `BANK_ACCOUNT_NUMBER`, `BANK_ACCOUNT_HOLDER`. Không có thì trang cảm ơn không hiện mã QR.
  - `BANK_ID` là mã VietQR (`vcb`, `agribank`, `mbbank`...), **không phải** mã SWIFT (`BFTV`).
  - Giá trị có dấu cách thì để trong ngoặc kép. Không để dấu cách thừa ở cuối dòng.

## 2. `APP_KEY`: tạo một lần, không đổi nữa

- Số tài khoản ngân hàng của nông hộ (`farmers.bank_account_number`) được **mã hóa bằng `APP_KEY`**.
- Đổi `APP_KEY` sau khi đã có dữ liệu thì không đọc lại được các số đó, và mọi người dùng bị đăng xuất.
- Nếu chuyển dữ liệu từ máy lên Railway (dump MySQL) thì phải dùng **cùng `APP_KEY`** với máy. Nếu không, phải nhập lại số tài khoản nông hộ.
- Cất `APP_KEY` production ở chỗ an toàn. Không commit nó lên git.

## 3. File tải lên phải nằm trên Volume

Ảnh nông hộ, ảnh lô và file chứng nhận được lưu ở `storage/app/public`.

- [ ] Gắn một **Volume** của Railway vào `storage/app/public`. Không có Volume thì mỗi lần deploy là mất hết ảnh và file chứng nhận.
- [ ] Chạy `php artisan storage:link` (đã có trong lệnh trước deploy ở Task 7.4).

## 4. Bảo mật dữ liệu mẫu

- [ ] Repo GitHub phải để **private**, vì file SQL có token mẫu của nông hộ và hash mật khẩu mẫu.
- [ ] Nếu nạp dữ liệu demo thì ngay sau đó **đổi mật khẩu** `admin@farm2home.vn` và `operator@farm2home.vn` (mật khẩu đang là `password`).
- [ ] Bấm **"Cấp link mới"** cho từng nông hộ, vì token mẫu nằm trong mã nguồn.
- [ ] **Không chạy** `php artisan migrate:fresh` trên DB có dữ liệu thật.

## 5. Tác vụ hằng ngày

Lịch chạy trong `routes/console.php`: tạo đơn giỏ định kỳ lúc 12:00, chạy dự báo lúc 18:15, ghi số thực tế lúc 21:00.

- [ ] Có dịch vụ cron chạy `php artisan schedule:run` mỗi phút, **hoặc** người vận hành bấm nút tương ứng trong admin mỗi ngày.

## 6. Kiểm tra nhanh sau deploy (dùng điện thoại, mạng 4G)

- [ ] Trang chủ mở bằng `https://`, không có cảnh báo chứng chỉ.
- [ ] Đặt một đơn **chuyển khoản**: trang cảm ơn hiện mã QR. Quét bằng app ngân hàng thì thấy đúng người nhận, đúng số tiền, nội dung là mã đơn (đừng bấm chuyển).
- [ ] Trong admin, đơn đó hiện nút "Đã nhận tiền".
- [ ] Quét QR trên tem lô thì mở đúng trang lô, ảnh hiện được.
- [ ] Gửi phản hồi từ trang lô thì thấy trong admin. Trả lời xong thì khách xem được câu trả lời ở trang Tra cứu đơn.
- [ ] Các mục còn lại xem Task 7.4, bước 8.

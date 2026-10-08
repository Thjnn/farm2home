# Chức năng theo từng vai trò trong FARM2HOME AI

Tài liệu này trả lời một câu hỏi: **mỗi người dùng làm được gì, và không làm được gì** trên hệ thống. Cách thao tác từng bước nằm ở [HUONG-DAN-SU-DUNG.md](HUONG-DAN-SU-DUNG.md); phần dự báo và gợi ý thu hoạch nằm ở [CHUC-NANG-AI.md](CHUC-NANG-AI.md).

Hệ thống có 5 vai trò:

| Vai trò | Vào hệ thống bằng | Cần tài khoản? |
|---|---|---|
| [Khách hàng](#1-khách-hàng) | Trang web công khai, số điện thoại | Không |
| [Nông hộ](#2-nông-hộ) | Link riêng `/nong-ho/{token}` gửi qua Zalo | Không |
| [Người vận hành (operator)](#3-người-vận-hành-operator) | Đăng nhập `/login` | Có |
| [Quản trị (admin)](#4-quản-trị-admin) | Đăng nhập `/login` | Có |
| [Hệ thống tự động](#5-hệ-thống-tự-động) | Lịch chạy `php artisan schedule:work` | — |

Nguyên tắc chung cho cả hệ thống:

- **AI chỉ gợi ý, con người quyết định.** Hệ thống tính dự báo và số kg nên thu, nhưng chỉ operator mới chốt kế hoạch và gửi cho nông hộ.
- **Khách và nông hộ không phải cài app hay tạo tài khoản.** Khách được nhận ra bằng số điện thoại, nông hộ bằng link riêng.
- **Không xoá dữ liệu.** Không màn hình nào có nút xoá: đơn thì hủy, sản phẩm / giỏ / cụm giao thì bỏ chọn **Đang bán** / **Đang dùng**, nông hộ thì chuyển trạng thái **Ngừng**.

---

## 1. Khách hàng

Người mua rau ở Mỹ Tho và vùng lân cận.

### Làm được

| Việc | Ở đâu |
|---|---|
| Xem giới thiệu, ảnh và giá từng loại rau, danh sách giỏ rau đang bán | Trang chủ `/` |
| Đặt **giỏ rau** đóng sẵn hoặc **chọn lẻ** từng loại (tối đa 20 gói mỗi loại) | `/dat-hang` |
| Chọn ngày giao (sớm nhất ngày mai, sau 18:00 thì ngày kia, xa nhất 7 ngày), khung giờ, khu vực giao hoặc điểm nhận tập trung | `/dat-hang` |
| Chọn thanh toán **Tiền mặt khi nhận** hoặc **Chuyển khoản**, ghi chú cho đơn | `/dat-hang` |
| Xin **đổi điểm lấy quà** ngay trong đơn (tặng bó rau, miễn phí giao, giảm 20.000đ, combo rau) | `/dat-hang` |
| Tra cứu 5 đơn gần nhất và **số điểm** đang có bằng số điện thoại | `/tra-cuu` |
| Quét mã QR trên gói rau để xem thông tin lô: nông hộ, khu vực trồng, giờ thu hoạch / sơ chế / đóng gói, hạn dùng, cách bảo quản | `/lo/{mã lô}` |
| Chấm điểm lô rau (1–5), độ tươi, viết nhận xét | `/lo/{mã lô}` |

### Được tích điểm khi

- Đơn có tiền hàng từ 40.000đ được giao xong: **+5 điểm**.
- Trả bao bì: **+5 điểm** (mỗi đơn một lần).

### Không làm được

- Sửa hay hủy đơn đã gửi. Muốn đổi thì nhắn Zalo / gọi để operator xử lý.
- Tự trừ điểm. Quà chỉ được áp dụng khi operator duyệt.
- Xem tên, địa chỉ trên trang tra cứu. Trang này cố ý ẩn hai thông tin đó, vì ai biết số điện thoại cũng tra được.
- Mở trang QR của lô chưa qua kiểm tra chất lượng.

---

## 2. Nông hộ

Hộ trồng rau ở Thân Cửu Nghĩa, Gò Công. Mỗi hộ có một link riêng, operator gửi qua Zalo.

### Làm được

| Việc | Chi tiết |
|---|---|
| **Báo sản lượng** cho 7 ngày tới | Nhập số kg có thể thu cho từng loại rau mình trồng, bước 0,5 kg |
| Sửa số đã báo | Được sửa cho tới khi kế hoạch của ngày đó được chốt |
| Xem **danh sách thu hoạch** | Mỗi ngày, mỗi loại rau, số kg cần thu (chỉ hiện khi kế hoạch đã chốt) |
| Bấm **Tôi sẽ thu hoạch** để xác nhận | Operator thấy giờ xác nhận trong kế hoạch |

### Không làm được

- Sửa số kg sau khi kế hoạch đã chốt (ngày đó hiện **Đã chốt**).
- Xem đơn hàng, giá bán, thông tin khách hay của nông hộ khác.
- Vào hệ thống khi đang ở trạng thái **Ngừng**: link không mở được.
- Dùng tiếp link cũ sau khi admin **cấp link mới**.

---

## 3. Người vận hành (operator)

Người điều phối hằng ngày: nhận đơn, làm việc với nông hộ, nhận hàng, đóng gói, giao. Đăng nhập rồi dùng thanh menu.

### Làm được

| Menu | Việc |
|---|---|
| **Tổng quan** `/admin` | Xem KPI pilot theo khoảng ngày: doanh thu, giá trị đơn TB, biên gộp, tỷ lệ giao đủ, khách mua lại, điểm đánh giá, rau dư, bao bì, sai số dự báo, số lần chỉnh gợi ý |
| **Đơn hàng** `/admin/orders` | Lọc đơn theo ngày giao / trạng thái, xem tổng kg từng loại rau trong ngày |
| | **Tạo đơn hộ khách** đặt qua Zalo, điện thoại (không bị chặn giờ chốt 18:00, tối đa 50 gói mỗi loại) |
| | Chuyển trạng thái: Chờ xác nhận → Đã xác nhận → Đang đóng gói → Đang giao → Đã giao, hoặc **Hủy** (bắt buộc ghi lý do) |
| | **Duyệt đổi quà** cho đơn còn Chờ xác nhận / Đã xác nhận, chọn loại rau tặng |
| **Giỏ định kỳ** `/admin/subscriptions` | Thêm, sửa, tạm dừng giỏ đặt lặp lại của khách; **sinh đơn** cho một ngày giao |
| **Sản lượng** `/admin/supply` | Xem nông hộ đã báo gì, **nhập hộ** khi nông hộ báo miệng / Zalo, sao chép link nông hộ, mở Zalo |
| **Dự báo** `/admin/forecasts` | Chạy dự báo 7 ngày tới, cập nhật số thực tế, so sánh 3 phương pháp qua MAE / MAPE / WAPE, xem biểu đồ |
| **Kế hoạch thu hoạch** `/admin/harvest-plans` | Tạo gợi ý thu hoạch → sửa số kg chốt (ghi lý do nếu khác gợi ý), đổi nông hộ → **Chốt kế hoạch** → gửi tin Zalo soạn sẵn → **Đánh dấu đã gửi** |
| | Khi hàng về: nhập **kg thực nhận** (hệ thống tạo lô), rồi **Hoàn tất kế hoạch** |
| **Lô hàng** `/admin/batches` | Kiểm chất lượng (Đạt / Không đạt), ghi giờ sơ chế, đóng gói, cách sơ chế |
| | **Gán lô cho đơn** của một ngày giao, **in tem QR**, mở trang QR công khai |
| **Phản hồi** `/admin/feedbacks` | Đọc phản hồi của khách, ghi cách xử lý, chuyển Mới → Đang xử lý → Đã xử lý |
| **Rau dư** `/admin/waste` | Ghi kg rau dư / hư / hết hạn / bán giảm giá theo từng lô |
| **Bao bì** `/admin/packaging-returns` | Ghi số bao bì thu hồi và số tái sử dụng được (cộng điểm cho khách) |
| **Điểm thành viên** `/admin/loyalty` | Xem điểm đã cộng, đã đổi, số dư của từng khách và lịch sử từng lần |

### Không làm được

- Các menu chỉ dành cho admin: **Sản phẩm, Giỏ rau, Nông hộ, Cụm giao, Tài khoản**.
- Sửa số kg chốt sau khi kế hoạch đã chốt.
- Gán lô chưa **Đạt** cho đơn, hoặc gán lô cho đơn còn **Chờ xác nhận**.
- Ghi rau dư nhiều hơn số kg lô còn lại.
- Đổi quà cho đơn đã đóng gói trở đi, hoặc đổi quá số điểm khách có.
- Chọn phương pháp dự báo dùng cho kế hoạch thu hoạch từ trang Dự báo (trang này chỉ để xem, so sánh).

---

## 4. Quản trị (admin)

Người cài đặt dữ liệu gốc cho cả hệ thống. Admin **làm được mọi việc của operator**, cộng thêm:

| Menu | Việc |
|---|---|
| **Sản phẩm** `/admin/products` | Thêm, sửa loại rau: mã (dùng trong mã lô), tên, khối lượng gói, giá bán, giá thu mua/kg, hạn dùng, hướng dẫn bảo quản, bật/tắt **Đang bán** |
| **Giỏ rau** `/admin/baskets` | Thêm, sửa giỏ: giá, số người, mô tả, thành phần, cho đổi rau tương đương, bật/tắt **Đang bán** |
| **Nông hộ** `/admin/farmers` | Thêm, sửa nông hộ: liên hệ, khu vực trồng, cách canh tác, loại rau trồng + kg/tuần, trạng thái, **file chứng nhận** và hạn |
| | **Cấp link mới** cho nông hộ khi link cũ bị lộ (link cũ hết hiệu lực ngay) |
| **Cụm giao** `/admin/zones` | Thêm, sửa khu vực giao: phường/xã, phí giao, mức miễn phí giao, điểm nhận tập trung |
| **Tài khoản** `/admin/users` | Thêm, sửa tài khoản admin / operator, đặt lại mật khẩu |

### Không làm được

- Tự đổi vai trò của chính mình.
- Tải ảnh rau lên từ giao diện. Ảnh đặt ở `public/images/products/` và đường dẫn lưu trong cột `products.image`.
- Cho trang QR hiện chứng nhận chỉ bằng cách gõ tên chứng nhận: phải có file chứng nhận và file còn hạn.

---

## 5. Hệ thống tự động

Các việc chạy theo giờ khi máy mở `php artisan schedule:work`. Không mở thì operator bấm tay ở màn hình tương ứng, kết quả như nhau.

| Giờ | Lệnh | Việc | Nút bấm tay |
|---|---|---|---|
| 12:00 | `subscriptions:generate` | Sinh đơn **Đã xác nhận** cho giỏ định kỳ đến lịch (không tạo trùng) | Giỏ định kỳ → **Sinh đơn cho ngày này** |
| 18:15 | `forecast:run` | Chạy dự báo 7 ngày giao kế tiếp sau giờ chốt đơn | Dự báo → **Chạy dự báo hôm nay** |
| 21:00 | `forecast:backfill` | Điền nhu cầu thực tế để tính sai số dự báo | Dự báo → **Cập nhật số thực tế** |

Ngoài ra, hệ thống tự làm khi có thao tác của người dùng:

- Tạo mã đơn `DH{yymmdd}-{số}` và mã lô `F2H-{yymmdd}-{mã rau}-{mã nông hộ}`.
- Tính phí giao theo cụm, miễn phí khi đủ mức.
- Nhận ra khách cũ theo số điện thoại, cập nhật tên và địa chỉ theo lần đặt mới nhất.
- Cộng điểm khi đơn giao xong và khi ghi bao bì; hoàn điểm khi hủy đơn đã đổi quà.
- Trả số gói về lô khi hủy đơn đã gán lô.

Hệ thống **không** tự chốt kế hoạch, không tự gửi tin cho nông hộ (MVP chưa có Zalo OA) và không tự trừ điểm của khách.

---

## 6. Bảng tóm tắt quyền

| Chức năng | Khách | Nông hộ | Operator | Admin |
|---|:-:|:-:|:-:|:-:|
| Đặt hàng trên web | ✓ | | | |
| Tra cứu đơn, xem điểm | ✓ | | | |
| Xem trang QR, gửi phản hồi | ✓ | | | |
| Báo sản lượng | | ✓ | ✓ (nhập hộ) | ✓ (nhập hộ) |
| Xác nhận sẽ thu hoạch | | ✓ | | |
| Xem dashboard KPI | | | ✓ | ✓ |
| Tạo đơn hộ khách, chuyển trạng thái đơn | | | ✓ | ✓ |
| Duyệt đổi quà | | | ✓ | ✓ |
| Giỏ định kỳ | | | ✓ | ✓ |
| Chạy dự báo | | | ✓ | ✓ |
| Tạo, chỉnh, chốt kế hoạch thu hoạch | | | ✓ | ✓ |
| Nhận hàng, kiểm chất lượng, gán lô, in tem QR | | | ✓ | ✓ |
| Xử lý phản hồi | | | ✓ | ✓ |
| Ghi rau dư, bao bì | | | ✓ | ✓ |
| Xem điểm thành viên | | | ✓ | ✓ |
| Quản lý sản phẩm, giỏ rau, cụm giao | | | | ✓ |
| Quản lý nông hộ, cấp link mới | | | | ✓ |
| Quản lý tài khoản | | | | ✓ |

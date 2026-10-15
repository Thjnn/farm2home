# Hướng dẫn sử dụng FARM2HOME AI

Tài liệu này mô tả từng màn hình của web và cách thao tác, kèm một bài thực hành chạy trên dữ liệu mẫu. Các tên nút, tên mục ghi **in đậm** là đúng chữ đang hiện trên giao diện.

Mục lục:

1. [Chuẩn bị](#1-chuẩn-bị)
2. [Bản đồ trang web](#2-bản-đồ-trang-web)
3. [Một ngày làm việc diễn ra thế nào](#3-một-ngày-làm-việc-diễn-ra-thế-nào)
4. [Phần của khách](#4-phần-của-khách)
5. [Phần của nông hộ](#5-phần-của-nông-hộ)
6. [Phần của operator](#6-phần-của-operator)
7. [Phần riêng của admin](#7-phần-riêng-của-admin)
8. [Bài thực hành với dữ liệu mẫu](#8-bài-thực-hành-với-dữ-liệu-mẫu)
9. [Bảng trạng thái](#9-bảng-trạng-thái)
10. [Thông báo lỗi thường gặp](#10-thông-báo-lỗi-thường-gặp)
11. [Lệnh hỗ trợ](#11-lệnh-hỗ-trợ)

---

## 1. Chuẩn bị

1. Mở Laragon, bấm **Start All** (cần cả web server và MySQL).
2. Mở trình duyệt, vào `http://farm2home.test`.

Tài khoản mẫu:

| Vai trò | Email | Mật khẩu | Thấy được gì |
|---|---|---|---|
| Quản trị (admin) | `admin@farm2home.vn` | `password` | Toàn bộ menu |
| Vận hành (operator) | `operator@farm2home.vn` | `password` | Mọi menu trừ Sản phẩm, Giỏ rau, Nông hộ, Cụm giao, Tài khoản |

Khách và nông hộ **không có tài khoản**: khách được nhận ra bằng số điện thoại, nông hộ vào bằng một link riêng.

Dữ liệu mẫu trải từ 10/09/2026 đến 03/10/2026 và là số liệu giả lập. Mọi thao tác bạn làm trên web đều ghi thật vào cơ sở dữ liệu `farm2home` trên máy.

---

## 2. Bản đồ trang web

| Ai dùng | Địa chỉ | Trang |
|---|---|---|
| Khách | `/` | Trang chủ: giới thiệu, danh sách giỏ rau |
| Khách | `/dat-hang` | Form đặt hàng |
| Khách | `/dat-hang/cam-on` | Xác nhận đã nhận đơn (chỉ hiện ngay sau khi gửi đơn) |
| Khách | `/tra-cuu` | Tra cứu đơn bằng số điện thoại |
| Khách | `/lo/{mã lô}` | Trang QR của lô hàng + form phản hồi |
| Nông hộ | `/nong-ho/{token}` | Báo sản lượng, xác nhận thu hoạch |
| Operator, admin | `/login` | Đăng nhập |
| Operator, admin | `/admin` | **Tổng quan** (dashboard KPI) |
| Operator, admin | `/admin/orders` | **Đơn hàng** |
| Operator, admin | `/admin/subscriptions` | **Giỏ định kỳ** |
| Operator, admin | `/admin/supply` | **Sản lượng** |
| Operator, admin | `/admin/forecasts` | **Dự báo** |
| Operator, admin | `/admin/harvest-plans` | **Kế hoạch thu hoạch** |
| Operator, admin | `/admin/batches` | **Lô hàng** |
| Operator, admin | `/admin/feedbacks` | **Phản hồi** |
| Operator, admin | `/admin/waste` | **Rau dư** |
| Operator, admin | `/admin/packaging-returns` | **Bao bì** |
| Chỉ admin | `/admin/products` · `/admin/baskets` · `/admin/farmers` · `/admin/zones` · `/admin/users` | **Sản phẩm · Giỏ rau · Nông hộ · Cụm giao · Tài khoản** |

Sau khi đăng nhập, tất cả các trang admin nằm trên thanh menu ở đầu trang.

---

## 3. Một ngày làm việc diễn ra thế nào

Ví dụ cho rau giao **ngày mai**:

| Lúc nào | Ai | Việc | Màn hình |
|---|---|---|---|
| Cả ngày, trước 18:00 | Khách | Đặt đơn cho ngày mai | `/dat-hang` |
| Cả ngày | Operator | Xác nhận đơn, nhập hộ đơn khách nhắn qua Zalo / điện thoại | Đơn hàng |
| Cả ngày | Nông hộ | Báo số kg có thể thu cho những ngày tới | Link nông hộ |
| 12:00 (tự động) hoặc bấm tay | Operator | Sinh đơn cho giỏ định kỳ đến lịch | Giỏ định kỳ |
| 18:00 | — | Đóng nhận đơn cho ngày mai | — |
| 18:15 (tự động) hoặc bấm tay | Operator | Chạy dự báo | Dự báo |
| Tối | Operator | Tạo gợi ý thu hoạch, chỉnh, **chốt**, gửi tin Zalo cho nông hộ | Kế hoạch thu hoạch |
| Tối | Nông hộ | Xem danh sách, bấm **Tôi sẽ thu hoạch** | Link nông hộ |
| Sáng hôm sau | Operator | Nhận hàng, nhập kg thực nhận (tạo lô) | Kế hoạch thu hoạch |
| Sáng | Operator | Kiểm chất lượng, ghi sơ chế / đóng gói, gán lô cho đơn, in tem QR | Lô hàng |
| Trong ngày | Operator | Chuyển đơn: đóng gói → đang giao → đã giao | Đơn hàng |
| Sau khi nhận hàng | Khách | Quét QR xem thông tin lô, gửi phản hồi | `/lo/{mã lô}` |
| Cuối ngày | Operator | Ghi rau dư, bao bì thu hồi, xử lý phản hồi | Rau dư · Bao bì · Phản hồi |
| 21:00 (tự động) hoặc bấm tay | Operator | Cập nhật số thực tế để tính sai số dự báo | Dự báo |

Các việc "tự động" chỉ chạy khi máy đang mở `php artisan schedule:work`. Không mở cũng không sao: việc nào cũng có nút bấm tay.

---

## 4. Phần của khách

### 4.1. Trang chủ `/`

Hiện phần giới thiệu, ba bước "Cách hoạt động" và danh sách giỏ rau đang bán (tên, giá, thành phần). Có nút **Đặt hàng** và **Tra cứu đơn**.

### 4.2. Đặt hàng `/dat-hang`

Điền từ trên xuống:

1. **Bạn muốn đặt gì?** Chọn **Giỏ rau** (giỏ đóng sẵn) hoặc **Chọn lẻ** (tự chọn từng loại).
   - Giỏ rau: chọn một giỏ. Dữ liệu mẫu có Giỏ Mini 50.000đ, Giỏ Xanh Tiết Kiệm 70.000đ, Giỏ Gia Đình 115.000đ.
   - Chọn lẻ: bấm **+** / **−** hoặc gõ số gói cho từng loại rau, tối đa 20 gói mỗi loại. Ô để trống tính là 0.
2. **Ngày giao:** chỉ hiện những ngày còn nhận đơn. Sớm nhất là ngày mai; sau 18:00 thì sớm nhất là ngày kia. Đặt trước tối đa 7 ngày.
3. **Khung giờ giao:** 06:00-08:00, 10:00-12:00 hoặc 17:00-19:00.
4. **Khu vực giao:** mỗi cụm có phí giao riêng; chọn **Điểm nhận tập trung** thì không mất phí và không cần nhập địa chỉ.
5. **Họ tên**, **Số điện thoại** (10 số, bắt đầu bằng 0), **Địa chỉ giao**.
6. **Thanh toán:** **Tiền mặt khi nhận** hoặc **Chuyển khoản**.
7. **Ghi chú** (không bắt buộc).
8. Đánh dấu ô đồng ý cho dùng thông tin để xử lý và giao đơn (bắt buộc).
9. Xem khung **Tiền hàng / Phí giao / Tạm tính** rồi bấm **Gửi đơn**.

Phí giao trong dữ liệu mẫu:

| Khu vực | Phí giao | Miễn phí khi tiền hàng từ |
|---|---|---|
| Cụm 1 – Trung tâm Mỹ Tho | 10.000đ | 150.000đ |
| Cụm 2 – Đạo Thạnh, Trung An | 15.000đ | 200.000đ |
| Cụm 3 – Mỹ Phong, Thới Sơn | 15.000đ | 200.000đ |
| Điểm nhận tập trung | 0đ | — |

Gửi xong, khách thấy trang **Đã nhận đơn của bạn** với mã đơn (dạng `DH261003-001`: DH + ngày giao + số thứ tự), ngày giao, hình thức thanh toán và tổng tiền. Đơn mới ở trạng thái **Chờ xác nhận**.

Nếu khách đặt lại bằng số điện thoại cũ, hệ thống không tạo khách mới mà cập nhật tên và địa chỉ theo lần đặt mới nhất.

### 4.3. Tra cứu đơn `/tra-cuu`

Nhập **Số điện thoại đã đặt hàng**, bấm **Tra cứu**. Trang hiện tối đa 5 đơn gần nhất: mã đơn, trạng thái, ngày giao, khung giờ, tổng tiền. Trang này cố ý không hiện tên và địa chỉ.

Thử với số mẫu `0988000001`.

### 4.4. Trang QR của lô `/lo/{mã lô}`

Đây là trang mở ra khi khách quét mã QR trên tem. Thử: `/lo/F2H-260930-RM-NH01`.

Mã lô có dạng `F2H-{ngày thu hoạch}-{mã rau}-{mã nông hộ}`.

Trang hiện:

- Tên rau, mã lô.
- **Nông hộ**, **Khu vực trồng**, **Cách canh tác (theo nông hộ khai)**.
- Thời điểm **Thu hoạch**, **Sơ chế** (kèm cách sơ chế), **Đóng gói**.
- **Nên dùng trước** và hướng dẫn **Bảo quản**.
- Khung chứng nhận: chỉ hiện tên chứng nhận khi nông hộ có file chứng nhận còn hạn; ngược lại ghi "Chưa có chứng nhận được xác thực".

Cuối trang là form **Gửi phản hồi**:

1. Chấm điểm lô rau từ 1 đến 5 (bắt buộc).
2. Chấm **Độ tươi** từ 1 đến 5 (không bắt buộc).
3. **Nhận xét** (không bắt buộc).
4. **Số điện thoại đặt hàng** (không bắt buộc; có thì phản hồi được gắn với khách và đơn).
5. Bấm **Gửi phản hồi**.

Trang QR chỉ mở được khi lô đã được đánh dấu **Đạt** ở khâu kiểm tra chất lượng.

---

## 5. Phần của nông hộ

Mỗi nông hộ có một link riêng dạng `/nong-ho/{token}`, operator gửi qua Zalo. Link của hộ NH01 trong dữ liệu mẫu:

```
http://farm2home.test/nong-ho/d1519ec801233c422771d362d652619a384cb0d5
```

Link của các hộ khác xem ở menu **Sản lượng** (có nút **Sao chép**) hoặc menu **Nông hộ**.

Trang gồm hai phần:

**Danh sách thu hoạch** (chỉ hiện khi có kế hoạch đã chốt): từng ngày, từng loại rau, số kg cần thu. Nông hộ bấm **Tôi sẽ thu hoạch** để xác nhận; sau đó nút đổi thành **Đã xác nhận** và operator thấy giờ xác nhận trong kế hoạch.

**Báo sản lượng:** với mỗi ngày trong 7 ngày tới, nhập số kg có thể thu cho từng loại rau mà hộ đó trồng (bước 0,5 kg; để trống nếu không có), rồi bấm **Lưu sản lượng**. Ngày nào kế hoạch đã chốt sẽ ghi **Đã chốt** và không sửa được nữa.

Nông hộ ở trạng thái **Ngừng** thì link không mở được.

---

## 6. Phần của operator

Đăng nhập tại `/login`. Thanh menu theo đúng thứ tự công việc.

### 6.1. Tổng quan `/admin`

Dashboard KPI. Chọn **Từ ngày**, **Đến ngày** rồi bấm **Xem**; mặc định là 28 ngày gần nhất.

| Nhóm | Ô số liệu | Ý nghĩa |
|---|---|---|
| Kinh tế | Doanh thu (đơn đã giao) | Tổng tiền các đơn đã giao trong khoảng |
| | Giá trị đơn trung bình | Doanh thu chia số đơn đã giao |
| | Biên gộp | Tiền hàng đã giao trừ giá mua toàn bộ hàng đã nhận, nên rau dư làm giảm biên |
| | Phí giao thu / đơn | Phí giao thu của khách; chưa có số chi phí giao thực tế |
| Môi trường | Rau dư, Tỷ lệ rau dư / hàng nhận | Kg đã ghi ở màn Rau dư so với kg nhận từ nông hộ |
| | Bao bì thu hồi, Tỷ lệ tái sử dụng bao bì | Số liệu từ màn Bao bì |
| Khách hàng | Tỷ lệ giao đủ | Số gói thực giao / số gói đặt |
| | Khách mua lại | Khách có từ 2 đơn trở lên |
| | Điểm đánh giá, Phản hồi đã xử lý | Từ form phản hồi trên trang QR |
| Nông hộ | Nông hộ đang hợp tác, Sản lượng được xác nhận trước | Phần sản lượng nông hộ đã bấm xác nhận |
| Dự báo | Số lần operator điều chỉnh | Số dòng kế hoạch có số chốt khác số gợi ý |
| | Bảng MAE / MAPE / WAPE | Sai số của từng phương pháp dự báo |

Hai biểu đồ: **Doanh thu theo ngày giao** (đường) và **Hàng nhận và rau dư theo tuần** (cột). Bấm **Xem dạng bảng** dưới mỗi biểu đồ để xem số cụ thể.

### 6.2. Đơn hàng `/admin/orders`

**Danh sách:** chọn **Ngày giao** và **Trạng thái**, bấm **Lọc**. Phía trên bảng có dòng **Nhu cầu trong ngày (đơn chưa hủy)**: tổng kg từng loại rau của ngày đó.

**Xem một đơn:** bấm vào mã đơn. Trang hiện từng dòng rau (số gói, khối lượng, thành tiền, **Thực giao**, **Lô**), tiền hàng, phí giao, tổng, và thông tin khách.

**Chuyển trạng thái:** ở khung **Chuyển trạng thái** chỉ hiện những bước hợp lệ kế tiếp:

```
Chờ xác nhận → Đã xác nhận → Đang đóng gói → Đang giao → Đã giao
       └────────────┴───────────────┴────────────┴──→ Đã hủy (phải ghi Lý do hủy)
```

Hủy một đơn đã gán lô sẽ trả số gói đó về lại lô.

**Tạo đơn hộ khách:** bấm **Tạo đơn**. Form giống form của khách, thêm mục **Kênh nhận đơn** (Zalo, Form, Website, Điện thoại). Khác với form công khai:

- Không bị chặn bởi giờ chốt 18:00 (chỉ không được chọn ngày đã qua).
- Tối đa 50 gói mỗi loại.
- Ô "Khách đã đồng ý cho dùng thông tin" không bắt buộc.

### 6.3. Giỏ định kỳ `/admin/subscriptions`

Danh sách khách đặt giỏ lặp lại: khách, giỏ, **Tần suất** (Hằng tuần, 2 tuần/lần, 4 tuần/lần), **Thứ giao**, khung giờ, hiệu lực, trạng thái.

- **Thêm giỏ định kỳ** / **Sửa:** nhập số điện thoại khách (khách phải đã từng đặt hàng), chọn giỏ, tần suất, thứ giao, khung giờ, ngày bắt đầu, ngày kết thúc (không bắt buộc), trạng thái.
- **Sinh đơn cho ngày này:** chọn **Ngày giao** rồi bấm nút. Hệ thống tạo đơn **Đã xác nhận** cho các giỏ đang chạy đến lịch giao ngày đó. Bấm lại không tạo trùng.

### 6.4. Sản lượng `/admin/supply`

Chọn **Ngày thu hoạch** để xem nông hộ đã báo gì cho ngày đó.

- **Tổng nguồn cung ngày …:** tổng kg từng loại rau.
- Mỗi nông hộ một khung: link báo sản lượng (nút **Sao chép**), **Mở Zalo**, và các ô kg theo loại rau.
- Nông hộ báo miệng hoặc nhắn Zalo thì operator nhập hộ: điền kg, chọn **Nguồn thông tin** (Zalo hoặc Operator nhập), bấm **Lưu**.
- Khi kế hoạch của ngày đó đã chốt, trang chỉ còn để xem.

### 6.5. Dự báo `/admin/forecasts`

- **Chạy dự báo hôm nay:** tính dự báo cho 7 ngày giao kế tiếp, từng loại rau.
- **Cập nhật số thực tế:** điền nhu cầu thật cho các ngày đã qua để tính sai số.

- **Ba nút phương pháp** (Trung bình trượt 7 ngày, Làm mượt hàm mũ, Trung bình cùng thứ): đổi phương pháp đang xem. Nút có chữ "đang dùng cho kế hoạch" là phương pháp kế hoạch thu hoạch dùng; trang này không đổi được phương pháp đó.

Bảng **Độ chính xác 14 ngày gần nhất** so sánh ba phương pháp qua MAE, MAPE, WAPE. Phương pháp có WAPE thấp nhất có nhãn **Sai số thấp nhất**. WAPE là tổng sai số tuyệt đối chia tổng thực tế; nên nhìn WAPE vì MAPE bị phóng đại khi khối lượng mỗi loại rau nhỏ.

Biểu đồ **Nhu cầu 28 ngày qua và dự báo 7 ngày tới** vẽ đường thực tế, đường dự báo nét đứt và vùng khoảng thấp–cao. Ô chọn bên phải để xem một loại rau hoặc tất cả.

Muốn có nhiều số liệu để demo, chạy `php artisan demo:orders`: lệnh sinh 2.000 đơn mô phỏng và chạy lại dự báo cho các ngày đã qua. Dữ liệu này chỉ để demo, không dùng để chọn phương pháp.

Bên dưới là dự báo theo từng ngày giao:

| Cột | Ý nghĩa |
|---|---|
| Đã đặt | Kg của các đơn chưa hủy đã có cho ngày đó |
| Dự báo thêm | Kg hệ thống đoán sẽ còn phát sinh |
| Tổng dự báo | Đã đặt + Dự báo thêm |
| Khoảng | Mức thấp – cao của dự báo |

### 6.6. Kế hoạch thu hoạch `/admin/harvest-plans`

Đây là màn hình quan trọng nhất. Hệ thống chỉ gợi ý; operator xem lại, chỉnh và chốt.

**Tạo:** chọn **Ngày giao**, bấm **Tạo gợi ý thu hoạch**. Kế hoạch mới ở trạng thái **Nháp**.

**Đọc bảng:**

| Cột | Ý nghĩa |
|---|---|
| Loại rau, Nông hộ | Thu rau gì, của hộ nào. "Chưa có nguồn" = chưa hộ nào báo sản lượng loại này |
| Đã đặt | Kg đơn đã có |
| Dự báo thêm | Kg dự báo sẽ phát sinh |
| Tồn | Kg lô cũ còn hạn, dùng tiếp được |
| Gợi ý | (Đã đặt + Dự báo thêm − Tồn) × 1,1, làm tròn lên 0,5 kg, chia theo sản lượng nông hộ đã báo |
| Chốt | Số operator quyết định |
| Thực nhận | Kg nhận được khi hàng về (hiện sau khi chốt) |

**Khi còn Nháp**, với từng dòng:

1. Sửa **số kg chốt** nếu cần.
2. Nếu số chốt khác số gợi ý, ghi **Lý do** (thời tiết, chất lượng…). Bắt buộc.
3. Đổi **Nông hộ** nếu cần; dòng "Chưa có nguồn" phải chọn nông hộ hoặc đặt số chốt về 0.
4. Bấm **Lưu** ở dòng đó.

Nút **Tính lại gợi ý** tính lại từ đầu theo đơn và sản lượng mới nhất (mất các chỉnh sửa trước đó).

**Chốt và gửi:**

1. Bấm **Chốt kế hoạch**. Trạng thái thành **Đã chốt**; từ đây không sửa số chốt được nữa, và nông hộ thấy danh sách trên link của họ.
2. Ở khung **Tin nhắn cho nông hộ**, mỗi hộ có một tin soạn sẵn: sao chép hoặc bấm **Mở Zalo** để gửi.
3. Gửi xong bấm **Đánh dấu đã gửi**. Trạng thái thành **Đã gửi nông hộ**.
4. Khi nông hộ bấm xác nhận, dòng đó hiện "Nông hộ xác nhận" kèm giờ.

**Khi hàng về:**

1. Ở cột **Thực nhận**, nhập số kg thật rồi bấm **Nhận**. Hệ thống tạo một lô hàng với mã `F2H-…` (bấm vào mã để mở lô). Nhập sai thì sửa số và bấm **Sửa**.
2. Nhận đủ mọi dòng rồi bấm **Hoàn tất kế hoạch**.

### 6.7. Lô hàng `/admin/batches`

Chọn **Ngày thu hoạch** để xem các lô của ngày: mã lô, rau, nông hộ, **Đã nhận**, **Còn lại**, **Chất lượng**, **Hạn dùng**.

**Mở một lô** (bấm **Xem**), ở khung **Sơ chế, đóng gói, kiểm chất lượng**:

1. Chọn **Kết quả kiểm tra**: Chờ kiểm, Đạt, Không đạt.
2. Ghi **Ghi chú kiểm tra**, **Thời điểm sơ chế**, **Thời điểm đóng gói**, **Cách sơ chế** (dòng này hiện trên trang QR).
3. Bấm **Lưu**.

Chỉ lô **Đạt** mới được gán cho đơn, mở trang QR và in tem.

**Gán lô cho đơn:** ở trang danh sách, bấm **Gán lô cho đơn ngày này**. Hệ thống gán các lô đạt cho các đơn **Đã xác nhận** hoặc **Đang đóng gói** của ngày giao đó (đơn còn **Chờ xác nhận** không được gán), theo thứ tự: lô hết hạn sớm nhất dùng trước; đơn giỏ định kỳ được phục vụ trước, rồi đến đơn đặt sớm hơn. Thông báo cho biết đã gán bao nhiêu gói và còn thiếu bao nhiêu. Bấm lại không gán trùng.

**In tem QR:** trong trang lô, nhập **Số tem** (mặc định 12, tối đa 200), bấm **In tem QR**. Trang tem mở ở tab mới; bấm **In**. Mỗi tem có mã QR, tên rau, mã lô, nông hộ, ngày đóng gói, hạn dùng.

Nút **Xem trang QR công khai** mở đúng trang khách sẽ thấy khi quét tem.

Cuối trang lô có **Đơn đã gán** và **Rau dư đã ghi**.

### 6.8. Phản hồi `/admin/feedbacks`

Lọc theo **Tất cả / Mới / Đang xử lý / Đã xử lý**. Mỗi phản hồi hiện điểm, độ tươi, nhận xét, lô, nông hộ, khách và đơn (nếu khách có để lại số điện thoại).

Xử lý: ghi **Cách xử lý**, chọn **Trạng thái**, bấm **Lưu**. Muốn chuyển sang **Đã xử lý** thì bắt buộc phải ghi cách xử lý.

### 6.9. Rau dư `/admin/waste`

Chọn **Ngày**. Khung **Lô còn tồn** liệt kê các lô còn hàng, kèm hạn dùng và số kg còn lại.

Với phần thực sự dư: nhập **Số kg**, chọn **Lý do** (Dư thừa, Hư hỏng, Hết hạn, Bán giảm giá), **Ghi chú**, bấm **Ghi nhận**. Không ghi được nhiều hơn số kg lô còn lại.

Lô còn hạn có thể để lại bán ngày mai; không cần ghi dư cho phần đó.

Bảng **Đã ghi trong ngày** liệt kê những gì đã ghi và người ghi.

### 6.10. Bao bì `/admin/packaging-returns`

Điền **Ngày thu hồi**, **SĐT khách** và **Mã đơn** (đều không bắt buộc), **Số bao bì thu hồi**, **Số tái sử dụng được**, **Ghi chú**, bấm **Ghi nhận**.

---

## 7. Phần riêng của admin

Chỉ tài khoản admin thấy các menu này. Mỗi trang có danh sách, nút thêm mới và nút **Sửa**.

| Menu | Trường quan trọng |
|---|---|
| **Sản phẩm** | Mã (dùng trong mã lô, ví dụ RM), tên, khối lượng 1 gói (kg), giá bán / gói, giá thu mua / kg, hạn dùng (ngày), hướng dẫn bảo quản (hiện trên trang QR), ô **Đang bán** |
| **Giỏ rau** | Tên, giá, số người tối thiểu – tối đa, mô tả, thành phần (số gói từng loại; 0 = không có trong giỏ), ô **Cho đổi rau tương đương**, ô **Đang bán** |
| **Nông hộ** | Mã (ví dụ NH01), họ tên, số điện thoại, số Zalo, địa chỉ (không hiện công khai), khu vực trồng và cách canh tác (hiện trên trang QR), trạng thái, loại rau trồng kèm kg/tuần ước tính, chứng nhận |
| **Cụm giao** | Tên, phường/xã thuộc cụm, phí giao, mức miễn phí giao, điểm nhận tập trung (điền thì cụm này là nhận tại điểm, không phí), ô **Đang dùng** |
| **Tài khoản** | Tên, email đăng nhập, số điện thoại, vai trò, mật khẩu (tối thiểu 8 ký tự; khi sửa để trống nếu không đổi) |

Lưu ý về nông hộ:

- **Chứng nhận:** chỉ điền khi có giấy tờ thật. Trang QR chỉ hiện chứng nhận khi có file (PDF/JPG/PNG, tối đa 4 MB) và còn hạn.
- **Cấp link mới:** tạo link báo sản lượng mới cho nông hộ. Link cũ lập tức hết hiệu lực, nên phải gửi link mới cho họ. Dùng khi link bị lộ.
- Chứng nhận VietGAP của hộ NH02 trong dữ liệu mẫu là giả, chỉ để thử giao diện.

---

## 8. Bài thực hành với dữ liệu mẫu

Bài này đi trọn một vòng trong khoảng 15 phút. Dữ liệu mẫu đang ở thời điểm **02/10/2026**: kế hoạch ngày 02/10 đã chốt, kế hoạch ngày 03/10 còn nháp.

**A. Khách đặt đơn**

1. Mở `/dat-hang`, chọn **Chọn lẻ**, lấy 2 gói Rau muống và 1 gói Cải ngọt.
2. Chọn ngày giao, khung giờ, **Cụm 1 – Trung tâm Mỹ Tho**.
3. Nhập họ tên, số điện thoại `0988000001`, địa chỉ; đánh dấu ô đồng ý; bấm **Gửi đơn**.
4. Ghi lại mã đơn. Tiền hàng 39.000đ + phí giao 10.000đ = 49.000đ.
5. Mở `/tra-cuu`, nhập `0988000001`: đơn vừa đặt nằm trên cùng, trạng thái **Chờ xác nhận**.

**B. Operator xác nhận đơn**

1. Đăng nhập `/login` bằng `operator@farm2home.vn`.
2. Vào **Đơn hàng**, chọn đúng ngày giao, bấm **Lọc**, mở đơn vừa đặt.
3. Ở **Chuyển trạng thái**, bấm **Đã xác nhận**.

**C. Nông hộ báo sản lượng**

1. Mở link NH01 ở [mục 5](#5-phần-của-nông-hộ) trong một tab khác.
2. Sửa vài số kg cho ngày 04/10, bấm **Lưu sản lượng**.
3. Quay lại admin, vào **Sản lượng**, chọn ngày 04/10: số vừa nhập đã hiện.

**D. Dự báo và kế hoạch**

1. Vào **Dự báo**, bấm **Chạy dự báo hôm nay**.
2. Vào **Kế hoạch thu hoạch**, mở kế hoạch ngày **03/10/2026** (đang **Nháp**).
3. Chọn một dòng, tăng số chốt thêm 0,5 kg, ghi lý do "thử nghiệm", bấm **Lưu**.
4. Bấm **Chốt kế hoạch**. Nếu bị báo "Còn dòng chưa có nông hộ", chọn nông hộ cho dòng đó hoặc đặt số chốt về 0 rồi chốt lại.
5. Xem khung **Tin nhắn cho nông hộ**, bấm **Đánh dấu đã gửi**.
6. Tải lại link NH01: **Danh sách thu hoạch** có thêm ngày 03/10; bấm **Tôi sẽ thu hoạch**.

**E. Nhận hàng, lô và tem**

1. Trong kế hoạch 03/10, ở một dòng, nhập kg thực nhận rồi bấm **Nhận**. Thông báo cho biết mã lô vừa tạo.
2. Bấm vào mã lô, chọn **Kết quả kiểm tra: Đạt**, điền thời điểm sơ chế và đóng gói, bấm **Lưu**.
3. Bấm **Xem trang QR công khai** để xem trang khách sẽ thấy.
4. Nhập **Số tem** là 6, bấm **In tem QR**.
5. Vào **Lô hàng**, chọn ngày 03/10, bấm **Gán lô cho đơn ngày này**.

**F. Phản hồi và cuối ngày**

1. Ở trang QR của lô, chấm 4 điểm, viết nhận xét, bấm **Gửi phản hồi**.
2. Trong admin, vào **Phản hồi** → **Mới**: phản hồi vừa gửi đang ở đó. Ghi cách xử lý, chọn **Đã xử lý**, bấm **Lưu**.
3. Vào **Rau dư**, chọn ngày 03/10, ghi 0,5 kg **Dư thừa** cho một lô, bấm **Ghi nhận**.
4. Vào **Bao bì**, ghi 3 bao bì thu hồi, 2 tái sử dụng được.
5. Về **Tổng quan**, chọn từ 10/09/2026 đến 03/10/2026, bấm **Xem**: các con số đã đổi theo thao tác vừa làm.

---

## 9. Bảng trạng thái

| Đối tượng | Trạng thái (theo thứ tự) |
|---|---|
| Đơn hàng | Chờ xác nhận → Đã xác nhận → Đang đóng gói → Đang giao → Đã giao · hoặc Đã hủy |
| Kế hoạch thu hoạch | Nháp → Đã chốt → Đã gửi nông hộ → Hoàn tất |
| Lô hàng (chất lượng) | Chờ kiểm → Đạt · hoặc Không đạt |
| Phản hồi | Mới → Đang xử lý → Đã xử lý |
| Giỏ định kỳ | Đang chạy · Tạm dừng · Đã hủy |
| Nông hộ | Đang hợp tác · Tạm nghỉ · Ngừng |
| Lý do rau dư | Dư thừa · Hư hỏng · Hết hạn · Bán giảm giá |

---

## 10. Thông báo lỗi thường gặp

| Thông báo | Nguyên nhân | Cách xử lý |
|---|---|---|
| Ngày giao không chọn được hoặc bị từ chối | Đã quá 18:00 nên hết nhận đơn cho ngày mai, hoặc ngày xa hơn 7 ngày | Chọn ngày khác; operator vẫn nhập hộ được ở **Tạo đơn** |
| Số điện thoại không đúng định dạng | Không phải 10 số bắt đầu bằng 0 | Nhập lại, không có dấu cách |
| Số gói không được lớn hơn 20 | Vượt giới hạn mỗi loại ở form khách | Giảm số gói, hoặc operator nhập hộ (giới hạn 50) |
| Không thể chuyển đơn từ "…" sang "…" | Nhảy cóc trạng thái | Đi lần lượt từng bước |
| Cần nhập lý do hủy đơn | Hủy mà chưa ghi lý do | Điền **Lý do hủy** |
| Cần ghi lý do khi số chốt khác số gợi ý | Sửa số chốt mà để trống lý do | Ghi lý do rồi **Lưu** |
| Còn dòng chưa có nông hộ. Hãy chọn nông hộ hoặc đặt số chốt về 0. | Có loại rau chưa hộ nào báo sản lượng | Chọn nông hộ cho dòng đó hoặc đặt 0 |
| Chỉ sửa được khi kế hoạch còn ở trạng thái nháp | Kế hoạch đã chốt | Không sửa được nữa; số thực tế nhập ở cột **Thực nhận** |
| Kế hoạch ngày này đã chốt, không thể tạo lại gợi ý | Bấm tạo gợi ý cho ngày đã chốt | Mở kế hoạch có sẵn |
| Chỉ nhận hàng cho kế hoạch đã chốt | Bấm **Nhận** khi kế hoạch còn nháp hoặc đã hoàn tất | Chốt kế hoạch trước |
| Còn dòng chưa nhập số kg thực nhận | Bấm **Hoàn tất kế hoạch** khi chưa nhận đủ | Nhận hết các dòng trước |
| Chỉ in tem cho lô đã đạt kiểm tra chất lượng | Lô còn **Chờ kiểm** hoặc **Không đạt** | Đánh dấu **Đạt** trong trang lô |
| Lô này chỉ còn … kg | Ghi rau dư nhiều hơn số còn lại | Nhập số nhỏ hơn |
| Cần ghi cách xử lý trước khi đóng phản hồi | Chọn **Đã xử lý** mà chưa ghi gì | Điền **Cách xử lý** |
| Trang QR báo không tìm thấy | Lô chưa đạt kiểm tra, hoặc sai mã lô | Kiểm tra lại lô trong admin |
| Vào `/admin` bị đưa về trang đăng nhập | Chưa đăng nhập hoặc hết phiên | Đăng nhập lại |
| Không thấy menu Sản phẩm, Nông hộ… | Đang dùng tài khoản operator | Dùng tài khoản admin |

---

## 11. Lệnh hỗ trợ

Chạy trong **Laragon Terminal**, tại thư mục dự án:

| Lệnh | Việc làm |
|---|---|
| `php artisan forecast:run` | Chạy dự báo (giống nút **Chạy dự báo hôm nay**) |
| `php artisan forecast:backfill` | Cập nhật số thực tế (giống nút **Cập nhật số thực tế**) |
| `php artisan subscriptions:generate` | Sinh đơn giỏ định kỳ cho ngày mai |
| `php artisan demo:shift-dates` | Dịch ngày của dữ liệu mẫu về gần hôm nay theo tuần nguyên; dùng khi demo sau này, lúc dữ liệu mẫu đã cũ hơn một tuần |
| `php artisan schedule:work` | Bật lịch tự động: 12:00 sinh đơn giỏ định kỳ, 18:15 dự báo, 21:00 cập nhật số thực tế |
| `php artisan test` | Chạy toàn bộ test (trên DB `farm2home_test`, không đụng dữ liệu của bạn) |

**Không chạy `php artisan migrate:fresh`**: lệnh này xóa toàn bộ dữ liệu.

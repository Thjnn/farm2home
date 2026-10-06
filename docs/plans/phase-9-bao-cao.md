# Báo cáo Phase 9: Hoàn thiện chức năng "Dự báo hôm nay"

**Ngày:** 06/10/2026 · **Kế hoạch:** [`phase-9-du-bao-hoan-chinh.md`](phase-9-du-bao-hoan-chinh.md) · **Nhánh:** `main` (chưa push)

## Kết luận

**Đã làm xong cả 5 task.** Toàn bộ 303/303 test đều qua. Cả 7 điều kiện chấp nhận trong kế hoạch đã đạt. Riêng việc **đổi phương pháp mặc định** thì kế hoạch ghi rõ là không làm trong phase này (xem mục 5).

## 1. Các task

| Task | Nội dung | Commit | Trạng thái |
|---|---|---|---|
| 9.1 | Phương pháp **Trung bình cùng thứ (4 tuần)** (`weekday_average`): công thức, enum, migration ENUM `forecasts.method` | `5249a0b` | Xong |
| 9.2 | Tham số `knownAt` cho `run()` / `confirmedKg()`: khi replay chỉ đếm đơn đã tạo tới thời điểm chạy | `5249a0b` | Xong |
| 9.3 | Lệnh `php artisan demo:orders`: sinh 2.000 đơn mô phỏng, replay dự báo, điền số thực tế | `355cefb` | Xong |
| 9.4 | Trang `/admin/forecasts`: chọn phương pháp, nhãn **Sai số thấp nhất**, biểu đồ 28 ngày qua + 7 ngày tới | `1d277e6` | Xong |
| 9.5 | Cập nhật `docs/CHUC-NANG-AI.md` và `docs/HUONG-DAN-SU-DUNG.md` | `8471f85` | Xong |

## 2. Thay đổi chính

**Dự báo.** Mỗi lần chạy giờ ghi `7 loại rau × 7 ngày × 3 phương pháp = 147` dòng (trước đây là 98). Trung bình cùng thứ tính riêng cho từng ngày giao, dựa trên 4 ngày cùng thứ gần nhất, nên bắt được nhịp cuối tuần. Kế hoạch thu hoạch vẫn dùng phương pháp trong config (`moving_average`).

**Dữ liệu mô phỏng.** `demo:orders` sinh đơn trong 84 ngày qua và 6 ngày tới, có hệ số theo thứ (Thứ Bảy 1,8, Thứ Ba 0,7), xu hướng tăng và nhiễu. Khách mô phỏng có số `0999000xxx`, ghi chú `[mô phỏng]`. Mỗi lần chạy xóa dữ liệu mô phỏng cũ rồi sinh lại, 163 đơn mẫu giữ nguyên. Lệnh bị chặn ở production nếu không có `--force`.

**Trang Dự báo.**
- Ba nút đổi phương pháp đang xem (`?method=`). Nút của phương pháp kế hoạch đang dùng có chữ "đang dùng cho kế hoạch".
- Bảng độ chính xác 14 ngày gắn nhãn **Sai số thấp nhất** cho phương pháp có WAPE nhỏ nhất.
- Biểu đồ Chart.js gồm đường thực tế, đường dự báo nét đứt và vùng khoảng thấp–cao. Ô chọn để xem một loại rau hoặc tất cả.

## 3. Kiểm thử

| Điều kiện chấp nhận (mục 6 của kế hoạch) | Kết quả |
|---|---|
| 1. `demo:orders` sinh đúng 2.000 đơn và in số dòng replay | Đạt: 2.000 đơn, 11.319 dòng dự báo trong 77 ngày, 10.584 dòng có số thực tế |
| 2. Chạy lại không làm tăng số đơn | Đạt (`DemoOrdersTest`) |
| 3. Bảng độ chính xác có 3 phương pháp, một dòng có nhãn "Sai số thấp nhất" | Đạt: mỗi phương pháp 98 điểm (14 ngày × 7 loại rau) |
| 4. **Chạy dự báo hôm nay** ra 147 dòng | Đạt (`ForecastScreenTest`) |
| 5. Biểu đồ: thực tế và `weekday_average` có đỉnh Thứ Bảy, `moving_average` là đường phẳng | Đạt, đã xem ảnh chụp màn hình |
| 6. Gợi ý thu hoạch vẫn chạy với phương pháp mặc định | Đạt (bộ test kế hoạch thu hoạch nằm trong 303 test) |
| 7. `php artisan test` xanh toàn bộ | Đạt: 303/303 |

Test mới hoặc đã sửa: `ForecastCalculatorTest`, `ForecastServiceTest` (có test `knownAt`), `ForecastScreenTest` (đổi phương pháp, phương pháp không hợp lệ thì quay về mặc định, nhãn sai số thấp nhất, dữ liệu biểu đồ), `EnumLabelsTest`, `DemoOrdersTest`.

Giao diện đã xem bằng headless Edge ở 1280px và 360px, không bị tràn ngang.

## 4. Kết quả trên dữ liệu mô phỏng (DB dev, 14 ngày, dự báo trước 1 ngày)

| Phương pháp | MAE | MAPE | WAPE |
|---|---|---|---|
| Trung bình trượt 7 ngày | 1,76 kg | 27,7% | 15,3% |
| Làm mượt hàm mũ | 1,99 kg | 30,7% | 17,4% |
| **Trung bình cùng thứ (4 tuần)** | **0,48 kg** | **7,6%** | **4,2%** |

> **Không dùng bảng này làm bằng chứng khi pitch.** Dữ liệu được sinh với hệ số theo thứ, nên Trung bình cùng thứ thắng là điều đã biết trước. Bảng chỉ cho thấy hệ thống đo và so sánh được các phương pháp.

## 5. Việc còn lại (cần team)

1. **Có đổi phương pháp mặc định không.** Sau ít nhất 2 tuần pilot với đơn thật, xem bảng độ chính xác. Chỉ đổi `farm2home.forecast.default_method` sang `weekday_average` nếu WAPE trên đơn thật thấp hơn.
2. **Push `main` lên GitHub** và deploy (Railway, Task 7.4 bước 6–8, vẫn còn mở từ trước).

## 6. Giới hạn đã biết

- Bảng chỉ đo dự báo trước 1 ngày. Lúc đó đơn đã gần đủ, nên sai số chủ yếu là phần dự báo thừa. Dự báo xa hơn (3–7 ngày) chưa được đo riêng.
- Chưa tính lễ tết, thời tiết, khuyến mãi.
- Trung bình cùng thứ cần khoảng 4 tuần dữ liệu. Nếu chưa có ngày cùng thứ nào thì ra 0, khi đó số đã đặt vẫn làm chặn dưới.
- Khi chọn **Tất cả loại rau**, vùng thấp–cao trên biểu đồ là tổng khoảng của từng loại nên rộng hơn khoảng thật.
- `demo:orders` không tạo lô hàng, kế hoạch thu hoạch, rau dư hay điểm thành viên cho đơn mô phỏng, nên các KPI đó vẫn chỉ phản ánh dữ liệu mẫu.

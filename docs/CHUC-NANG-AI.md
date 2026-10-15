# Các chức năng liên quan đến AI trong FARM2HOME AI

Tài liệu này giải thích phần "AI" trong dự án: AI đang làm gì, tính bằng công thức nào, nằm ở file nào, hiện trên màn hình nào, và những giới hạn cần nói thật khi thuyết trình.

> **Nói ngắn gọn:** "AI" trong bản MVP là **dự báo nhu cầu bằng thống kê** (trung bình trượt, làm mượt hàm mũ) cộng với **thuật toán gợi ý thu hoạch theo luật**. Phần dự báo chưa dùng học máy. Đây là chủ ý: pilot 4 tuần chưa có đủ dữ liệu để huấn luyện mô hình, và một mô hình chỉ được coi là "tốt hơn" khi nó có sai số thực tế thấp hơn baseline. Mô hình ngôn ngữ (LLM, Gemini) chỉ được dùng cho **chatbot hỏi đáp** ở trang đặt hàng, không tham gia quyết định thu hoạch.

---

## 1. Bức tranh tổng thể

```
Đơn đặt trước ──┐
Giỏ định kỳ ────┼─► [1] Dự báo nhu cầu ─► [2] Gợi ý thu hoạch ─► [3] Operator duyệt & chốt
(sinh đơn tự động)                               ▲                         │
                                                 │                         ▼
Nông hộ báo sản lượng ───────────────────────────┘                Tin nhắn Zalo cho nông hộ
                                                                           │
[5] KPI: sai số dự báo, số lần điều chỉnh, rau dư ◄── [4] Đo sai số ◄── Giao hàng xong
```

| # | Chức năng | Vai trò của AI | File chính |
|---|---|---|---|
| 1 | Dự báo nhu cầu | Đoán số kg mỗi loại rau cho 7 ngày giao tới | `app/Services/ForecastService.php`, `app/Support/ForecastCalculator.php` |
| 2 | Gợi ý thu hoạch | Đổi dự báo thành số kg cần thu và chia cho từng nông hộ | `app/Services/HarvestPlanService.php`, `app/Support/HarvestMath.php` |
| 3 | Human-in-the-loop | AI chỉ gợi ý, operator chốt và phải ghi lý do khi sửa số | `HarvestPlanService::updateItem()`, `confirm()` |
| 4 | Đo độ chính xác | So dự báo với thực tế bằng MAE, MAPE, WAPE | `ForecastService::backfillActuals()`, `accuracy()` |
| 5 | KPI trên dashboard | Theo dõi AI có giúp giảm rau dư hay không | `app/Services/KpiService.php` |
| 6 | Chatbot hỏi đáp | Trả lời khách về giá, giỏ, giờ giao, tra đơn, nguồn gốc lô, nhu cầu dự kiến; gợi ý rau theo món. Dùng Gemini khi có `GEMINI_API_KEY`, không thì trả lời theo kịch bản | `app/Services/ChatbotService.php` |

Tất cả tham số nằm trong `config/farm2home.php`:

```php
'safety_margin_pct' => 10,
'forecast' => [
    'default_method' => 'moving_average', // phương pháp dùng cho kế hoạch thu hoạch
    'window_days'    => 7,                 // cửa sổ trung bình trượt
    'alpha'          => 0.3,               // hệ số làm mượt hàm mũ
    'horizon_days'   => 7,                 // dự báo bao nhiêu ngày tới
    'history_days'   => 28,                // dùng bao nhiêu ngày lịch sử
],
```

---

## 2. Dự báo nhu cầu

**Màn hình:** `/admin/forecasts` (menu **Dự báo**)
**Lệnh:** `php artisan forecast:run [Y-m-d]`, chạy tự động lúc **18:15** mỗi ngày, tức sau giờ chốt đơn 18:00

### 2.1. Dữ liệu đầu vào

Nhu cầu của một loại rau trong một ngày là **tổng `order_items.weight_kg` của mọi đơn chưa hủy** có `delivery_date` bằng ngày đó.

- Lịch sử lấy **28 ngày** gần nhất, tính đến hết ngày chạy.
- Ngày không có đơn được tính là **0 kg**. Ngày bán ế cũng là dữ liệu.
- Những ngày **trước đơn đầu tiên** trong hệ thống thì bị bỏ qua. Nếu tính chúng là 0, trung bình sẽ bị kéo xuống sai.
- Đơn sinh từ **giỏ định kỳ** (lệnh `subscriptions:generate`, chạy lúc 12:00) cũng là đơn thật, nên cũng được tính.

### 2.2. Phương pháp 1: Trung bình trượt 7 ngày (mặc định)

```
dự báo  = trung bình 7 ngày gần nhất
khoảng  = dự báo ± độ lệch chuẩn của 7 ngày đó (mức thấp không âm)
```

Ví dụ, rau muống 7 ngày qua bán `10, 12, 8, 15, 11, 13, 15` kg:
- Trung bình = 84 / 7 = **12 kg**
- Độ lệch chuẩn ≈ 2,4 kg, nên khoảng là **9,6 – 14,4 kg**

### 2.3. Phương pháp 2: Làm mượt hàm mũ (α = 0,3)

```
mức_0   = ngày đầu tiên
mức_t   = 0,3 × thực_tế_t + 0,7 × mức_(t-1)
dự báo  = mức cuối cùng
khoảng  = dự báo ± RMSE của các lần đoán trước 1 bước
```

Phương pháp này cho ngày gần đây trọng số cao hơn, nên bắt xu hướng tăng hoặc giảm nhanh hơn trung bình trượt. Khoảng dao động được tính từ chính sai số mà mô hình đã mắc trên dữ liệu cũ.

### 2.4. Phương pháp 3: Trung bình cùng thứ (4 tuần)

```
dự báo  = trung bình 4 ngày CÙNG THỨ gần nhất với ngày giao (ví dụ 4 Thứ Bảy trước)
khoảng  = dự báo ± độ lệch chuẩn của 4 ngày đó
```

Khác hai phương pháp trên, phương pháp này cho **mỗi ngày giao một con số riêng**, nên bắt được nhịp cuối tuần đông, ngày thường vắng.

Ví dụ, 4 Thứ Bảy trước rau muống bán `20, 28, 24, 26` kg:
- Dự báo = 98 / 4 = **24,5 kg**
- Độ lệch chuẩn ≈ 2,96 kg, nên khoảng là **21,54 – 27,46 kg**

Cùng lịch sử đó, trung bình trượt 7 ngày trộn cả ngày thường 5–8 kg nên chỉ ra khoảng 9–10 kg, thiếu cho Thứ Bảy và thừa cho Thứ Ba.

### 2.5. Kết hợp với đơn đã đặt

Với mỗi ngày giao từ +1 đến +7:

| Cột | Công thức | Ý nghĩa |
|---|---|---|
| Đã đặt (`confirmed_kg`) | Tổng kg đơn chưa hủy của ngày đó, kể cả đơn `pending` | Nhu cầu chắc chắn |
| Tổng dự báo (`predicted_total_kg`) | `max(dự báo mô hình, đã đặt)` | Không bao giờ thấp hơn số đã đặt |
| Dự báo thêm (`predicted_extra_kg`) | `tổng − đã đặt` | Kg dự kiến khách còn đặt thêm |
| Khoảng (`lower_kg` – `upper_kg`) | Khoảng của mô hình, chặn dưới bằng số đã đặt | Mức thấp – cao hợp lý |

Ví dụ: mô hình đoán 12 kg, ngày mai đã có đơn 8 kg, nên **Dự báo thêm = 4 kg**. Nếu đơn đã lên tới 14 kg thì **Tổng = 14 kg, Dự báo thêm = 0**.

### 2.6. Lưu trữ

Mỗi lần chạy ghi vào bảng `forecasts` một dòng cho mỗi tổ hợp **loại rau × ngày giao × phương pháp**. Khóa là `product_id + target_date + generated_on + method`, nên chạy lại trong cùng ngày thì cập nhật dòng cũ, không tạo dòng trùng. Cả ba phương pháp đều được lưu (7 loại rau × 7 ngày × 3 phương pháp = 147 dòng mỗi lần chạy). Kế hoạch thu hoạch chỉ dùng phương pháp mặc định trong config.

### 2.7. Trang Dự báo

- Ba nút chọn phương pháp để xem (`?method=`). Nút của phương pháp mặc định có chữ "đang dùng cho kế hoạch".
- Bảng độ chính xác 14 ngày đánh dấu phương pháp có WAPE thấp nhất bằng nhãn **Sai số thấp nhất**.
- Biểu đồ **Nhu cầu 28 ngày qua và dự báo 7 ngày tới**: đường thực tế, đường dự báo nét đứt, vùng khoảng thấp–cao. Chọn một loại rau hoặc **Tất cả** (cộng các loại; vùng khoảng khi đó là tổng khoảng từng loại nên rộng hơn thực tế).

### 2.8. Dữ liệu mô phỏng để demo: `php artisan demo:orders`

Sinh **2.000 đơn mô phỏng** trong 84 ngày qua + 6 ngày tới, có nhịp trong tuần (Thứ Bảy đông nhất), xu hướng tăng dần và nhiễu. Khách mô phỏng có số `0999000xxx`, ghi chú `[mô phỏng]`. Mỗi lần chạy xóa dữ liệu mô phỏng cũ rồi sinh lại (cùng `--seed` thì cùng dữ liệu); 163 đơn mẫu không bị đụng tới. Bị chặn ở production nếu không có `--force`.

Sau khi sinh đơn, lệnh **chạy lại dự báo cho từng ngày đã qua** (replay) với tham số `knownAt`: chỉ đếm những đơn đã tạo tới 18:15 hôm đó, nên "đã đặt" không lộ trước số thực tế. Nhờ vậy bảng MAE/MAPE/WAPE có ngay khoảng 80 ngày số liệu.

> **Dữ liệu mô phỏng không phải bằng chứng.** Dữ liệu được sinh với hệ số theo thứ, nên Trung bình cùng thứ thắng là điều hiển nhiên (WAPE ~4% so với ~15%). Quyết định đổi phương pháp mặc định phải dựa trên WAPE của **đơn thật** trong pilot.

Enum `ForecastMethod` còn có hai giá trị **chưa dùng**: `tree_regression` (cây hồi quy) và `manual` (nhập tay). Hai giá trị này được giữ chỗ cho giai đoạn sau.

---

## 3. Gợi ý thu hoạch

**Màn hình:** `/admin/harvest-plans`, chọn ngày giao rồi bấm **Tạo gợi ý**
Khi bấm nút này, hệ thống chạy lại dự báo trước, sau đó mới tạo gợi ý, nên số liệu luôn mới nhất.

### 3.1. Công thức

```
suggested_kg = làm_tròn_lên_0,5( max(0, đã_đặt + dự_báo_thêm − tồn_kho) × (1 + 10%) )
```

| Thành phần | Lấy từ đâu |
|---|---|
| Đã đặt | `ForecastService::confirmedKg()`, tính tại thời điểm tạo gợi ý |
| Dự báo thêm | Dự báo **mới nhất** của ngày đó, trừ đi số đã đặt hiện tại |
| Tồn kho | `BatchService::availableStockKg()`: các lô **đạt chất lượng**, thu hoạch **trước** ngày giao, **còn hạn**, trừ phần đã phân bổ cho đơn và phần đã ghi rau dư |
| Biên an toàn | `safety_margin_pct` = 10%, có thể chỉnh theo từng kế hoạch |

Ví dụ: đã đặt 8 kg, dự báo thêm 4 kg, tồn kho 1,5 kg. Ta có (8 + 4 − 1,5) × 1,1 = 11,55, làm tròn lên thành **12 kg**.

### 3.2. Chia cho nông hộ (`HarvestMath::allocate`)

Hệ thống dùng sản lượng mà nông hộ đã tự báo qua link `/nong-ho/{token}` cho ngày đó (bảng `supply_reports`), và chỉ tính nông hộ đang `active`:

1. Sản lượng đã báo được làm tròn xuống 0,5 kg. Đây là mức tối đa mỗi nhà có thể nhận.
2. Nếu tổng sản lượng báo **không đủ**, mọi nhà thu hết số mình báo, phần thiếu trở thành một **dòng chưa có nông hộ** để operator tự chọn người thu hoặc đặt số về 0.
3. Nếu **đủ**, nhu cầu được chia **theo tỉ lệ sản lượng đã báo** để nhà nhỏ cũng có phần (tăng trưởng bao trùm, và giảm rủi ro khi một nhà gặp sự cố). Mỗi phần làm tròn xuống 0,5 kg; phần lẻ còn lại trao từng 0,5 kg cho nhà bị làm tròn hụt nhiều nhất.
4. Nhà được chia dưới `min_allocation_kg` (mặc định 2 kg, trong `config/farm2home.php`) nhường phần cho các nhà khác, để không ai phải đi giao chỉ nửa ký. Chỉ nhường khi các nhà còn lại vẫn đủ rau.

Ví dụ: cần 30 kg, NH01 báo 40 kg và NH02 báo 15 kg. Theo tỉ lệ là 21,8 và 8,2 kg, nên NH01 thu **22 kg**, NH02 thu **8 kg**.
Ví dụ thiếu: cần 12 kg, NH01 báo 7,3 kg và NH02 báo 3 kg. NH01 thu 7 kg, NH02 thu 3 kg, còn **2 kg chưa có nông hộ**.

Số "đã đặt / dự báo thêm / tồn" chỉ được ghi ở dòng đầu tiên của mỗi loại rau, để khi cộng theo sản phẩm thì không bị nhân đôi.

---

## 4. Human-in-the-loop: AI gợi ý, con người quyết định

Đây là nguyên tắc cốt lõi khi pitch. Code ép buộc nguyên tắc này như sau:

| Bước | Quy tắc trong code |
|---|---|
| Tạo gợi ý | `final_kg` để trống, AI chỉ điền `suggested_kg` |
| Operator sửa số | Nếu `final_kg ≠ suggested_kg` thì **bắt buộc ghi `adjust_reason`** (thời tiết, chất lượng…), nếu không sẽ báo lỗi |
| Chốt kế hoạch | Dòng nào chưa sửa thì lấy đúng số gợi ý. Nếu còn dòng có kg > 0 mà chưa có nông hộ thì không cho chốt |
| Sau khi chốt | Không sửa được nữa, không tạo lại gợi ý được, nông hộ không sửa được sản lượng đã báo |
| Gửi nông hộ | Hệ thống soạn sẵn tin nhắn cho từng nông hộ, operator **tự bấm sao chép và gửi qua Zalo**. Không có gửi tự động |

Nhờ lưu cả `suggested_kg` lẫn `final_kg` kèm lý do, nhóm có thể chỉ ra **AI đúng ở đâu và con người phải sửa ở đâu**, và dùng chính dữ liệu này để cải thiện mô hình sau này.

---

## 5. Đo độ chính xác của dự báo

**Lệnh:** `php artisan forecast:backfill [Y-m-d]`, chạy tự động lúc **21:00**, hoặc bấm **Cập nhật số thực tế** trên trang Dự báo

1. **Backfill:** với các dự báo đã tới hoặc qua ngày giao mà chưa có `actual_kg`, hệ thống điền vào đó tổng kg đơn chưa hủy của ngày giao.
2. **So sánh:** chỉ lấy các dự báo được tạo **trước ngày giao đúng 1 ngày**, vì đó là con số dùng để ra quyết định thu hoạch.

| Chỉ số | Công thức | Đọc thế nào |
|---|---|---|
| **MAE** | trung bình \|dự báo − thực tế\| | Trung bình mỗi lần lệch bao nhiêu kg |
| **MAPE** | trung bình \|sai số\| / thực tế, bỏ qua ngày thực tế = 0 | Lệch bao nhiêu %, nhưng **bị phóng đại** khi mỗi loại chỉ bán vài kg |
| **WAPE** | Σ\|sai số\| / Σ thực tế | **Chỉ số nên dùng khi pitch**: tỷ lệ sai trên tổng khối lượng |

Bảng so sánh các phương pháp xuất hiện ở hai nơi:
- Trang **Dự báo**: 14 ngày gần nhất.
- **Dashboard** (`/admin`): theo khoảng ngày đang lọc.

---

## 6. KPI trên dashboard liên quan đến AI

| KPI | Nguồn | Trả lời câu hỏi |
|---|---|---|
| MAE / MAPE / WAPE theo phương pháp | `KpiService::forecast()` | Dự báo đúng tới đâu? Phương pháp nào tốt hơn? |
| Số lần operator điều chỉnh (x / tổng dòng) | Dòng kế hoạch có `final_kg ≠ suggested_kg` | Con người còn phải sửa AI bao nhiêu? |
| Rau dư theo tuần và % rau dư trên hàng nhận | `waste_logs`, `batches` | Rau dư có giảm sau khi bắt đầu dự báo (từ 17/09) không? |
| Tỷ lệ đáp ứng (fill rate) | Σ `fulfilled_quantity` / Σ `quantity` | Thu ít đi có làm thiếu hàng cho khách không? |
| % sản lượng nông hộ xác nhận trước | `farmer_confirmed_at` | Nông hộ có làm theo kế hoạch không? |

Rau dư và fill rate là hai mặt của cùng một bài toán. Dự báo tốt phải **giảm được rau dư mà không làm giảm fill rate**.

---

## 7. Lịch chạy tự động trong một ngày

| Giờ | Việc | Lệnh |
|---|---|---|
| 12:00 | Sinh đơn từ giỏ định kỳ | `subscriptions:generate` |
| 18:00 | Đóng nhận đơn cho ngày mai | (quy tắc `order.cutoff_hour`) |
| 18:15 | Chạy dự báo cho 7 ngày tới | `forecast:run` |
| sau 18:15 | Operator tạo gợi ý thu hoạch, duyệt, chốt, gửi Zalo | thao tác tay |
| 21:00 | Điền số thực tế, tính sai số | `forecast:backfill` |

Để lịch này chạy, server cần có `php artisan schedule:run` mỗi phút (cron trên Railway, hoặc `php artisan schedule:work` khi dev).

---

## 8. Giới hạn hiện tại (nên nói thật khi thuyết trình)

1. **Dự báo chưa có học máy.** Hiện chỉ có thống kê cơ bản, là baseline có chủ đích. LLM (Gemini) chỉ nằm ở chatbot: nó trả lời dựa trên danh sách rau, giỏ và dự báo lấy từ hệ thống, chỉ gợi ý mua sắm, không tư vấn sức khỏe. Gemini lỗi hoặc không có key thì chatbot dùng câu trả lời soạn sẵn.
2. **Chưa tính lễ tết, thời tiết.** Trung bình trượt và làm mượt hàm mũ cho cùng một số cho cả 7 ngày tới. Trung bình cùng thứ bắt được nhịp trong tuần nhưng cần đủ 4 tuần dữ liệu và chưa biết gì về lễ tết hay mưa bão.
3. **"Thực tế" là kg khách đặt, không phải kg đã giao.** Vì vậy sai số đo được là sai số dự báo *nhu cầu*. Nhu cầu bị cắt do thiếu hàng sẽ không hiện ra.
4. **Đơn `pending` được tính là nhu cầu chắc chắn.** Nếu nhiều đơn bị hủy sau giờ chốt, gợi ý sẽ thừa.
5. **Dữ liệu ít.** Pilot 20–30 hộ và 5–7 loại rau cho ra vài kg mỗi loại mỗi ngày, nên MAPE dao động mạnh. Hãy xem WAPE.
6. **Đổi phương pháp mặc định cho kế hoạch phải sửa config** (`farm2home.forecast.default_method`). Trang Dự báo chỉ cho xem và so sánh, không đổi phương pháp của kế hoạch. Đây là cố ý: việc đổi cần team quyết dựa trên WAPE của đơn thật.
7. **Chưa có gợi ý giỏ rau cá nhân hóa.** Cột `customers.preferences` đã có, nhưng chức năng này để ở mức P2. Khi làm, chỉ được gợi ý mua sắm, **không** đưa lời khuyên sức khỏe.

---

## 9. Hướng phát triển (theo thứ tự hợp lý)

| Giai đoạn | Việc | Điều kiện |
|---|---|---|
| ~~Ngắn hạn~~ | ~~Trung bình cùng thứ trong tuần~~ | **Đã làm** (phase 9) |
| Ngắn hạn | Team đổi phương pháp mặc định nếu một phương pháp thắng rõ về WAPE trên **đơn thật** | Ít nhất 2–3 tuần số thực tế của pilot |
| Trung hạn | **Cây hồi quy** (`tree_regression`) chạy thành service Python riêng, đặc trưng gồm thứ, thời tiết, số giỏ định kỳ, `adjust_reason` | Vài tháng dữ liệu, và phải **thắng baseline về WAPE** |
| Trung hạn | Dùng `adjust_reason` để học xem operator hay sửa theo hướng nào | Đủ số lần điều chỉnh có lý do |
| Dài hạn | Gợi ý giỏ cá nhân hóa từ `preferences` và lịch sử mua (P2) | Có `data_consent` của khách |
| Dài hạn | Zalo OA API gửi tin tự động | Vẫn giữ bước operator duyệt trước khi gửi |

---

## 10. Tra cứu nhanh file code

| File | Nội dung |
|---|---|
| `app/Support/ForecastCalculator.php` | Công thức trung bình trượt, làm mượt hàm mũ, trung bình cùng thứ, MAE/MAPE/WAPE (hàm thuần, dễ test) |
| `app/Services/ChatbotService.php` | Chatbot: gọi Gemini kèm dữ liệu rau, giỏ, dự báo; câu trả lời dự phòng theo kịch bản |
| `app/Services/ForecastService.php` | Lấy lịch sử, chạy dự báo (có `knownAt` cho replay), backfill, tính độ chính xác, dữ liệu biểu đồ |
| `app/Console/Commands/DemoOrders.php` | Lệnh `demo:orders`: sinh đơn mô phỏng và replay dự báo |
| `app/Support/HarvestMath.php` | Công thức `suggested_kg`, làm tròn 0,5 kg, chia cho nông hộ |
| `app/Services/HarvestPlanService.php` | Tạo gợi ý, sửa số, chốt, soạn tin Zalo |
| `app/Services/BatchService.php` | Tính tồn kho dùng được (`availableStockKg`) |
| `app/Services/KpiService.php` | KPI dự báo, rau dư, fill rate cho dashboard |
| `app/Http/Controllers/Admin/ForecastController.php` | Trang `/admin/forecasts` |
| `app/Http/Controllers/Admin/HarvestPlanController.php` | Trang kế hoạch thu hoạch |
| `app/Console/Commands/ForecastRun.php`, `ForecastBackfill.php` | Lệnh chạy theo lịch |
| `resources/js/dashboard.js` | Biểu đồ dashboard và biểu đồ dự báo (Chart.js) |
| `routes/console.php` | Lịch chạy tự động |
| `config/farm2home.php` | Tham số dự báo và biên an toàn |

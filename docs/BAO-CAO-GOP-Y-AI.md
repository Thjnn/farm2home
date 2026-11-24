# Báo cáo chỉnh sửa phần AI theo góp ý của thầy Nguyên

> Cập nhật ngày 24/11/2026. Mỗi mục gồm: thầy góp ý gì, hệ thống trước đây làm gì, nhóm đã sửa gì, kết quả chạy thử và những việc còn phải làm.
> **Toàn bộ số liệu trong báo cáo là dữ liệu mô phỏng, chưa phải dữ liệu thật.** Nhóm chưa chạy thử với khách và HTX thật.

---

## Tóm tắt

| # | Góp ý | Trạng thái |
|---|---|---|
| 1 | Nói rõ AI dự báo phần nào | **Đã sửa code.** Dự báo chỉ dùng cho phần khách đặt thêm, khi còn 2–3 ngày trước buổi giao. Sau giờ chốt đơn, gợi ý thu hoạch không cộng thêm phần dự báo nữa |
| 2 | Ghi đúng công nghệ đã làm được | **Viết lại cho đúng.** Hiện chỉ có thống kê đơn giản, chưa có học máy, chưa có buổi giao thật |
| 3 | So AI với cách tính thông thường | **Đã làm.** Mỗi lần chạy lưu cùng lúc dự báo của mô hình và của cách tính thông thường, có bảng đối chiếu từng buổi |
| 4 | Ghi đủ kết quả từng buổi | **Đã làm.** Trang "Đối chiếu buổi giao" ghi lượng khách yêu cầu, lượng giao được, lượng dư, lượng hết hàng và số lần đối chiếu |
| 5 | Tính lợi ích sau khi trừ chi phí | **Có công thức và số tiền dư/thiếu từng buổi.** Chi phí chạy hệ thống và công nhập liệu do nhóm điền khi chạy thử |

**Kết quả đáng chú ý nhất (dữ liệu mô phỏng):** khi dự báo trước 2 ngày, **cách tính thông thường đoán gần hơn cả ba mô hình**. Vì vậy nhóm **chưa** thể nói "AI giúp dự báo tốt hơn". Xem mục 3.

---

## 1. AI cần dự báo phần nào?

### Trước đây
- Đơn cho ngày mai **chốt lúc 18:00** hôm nay. Dự báo chạy lúc **18:15**.
- Khi lên kế hoạch thu hoạch cho ngày mai, hệ thống đã biết đủ đơn. Dù vậy, gợi ý thu hoạch vẫn cộng thêm phần "dự báo khách đặt thêm".
- Trên dữ liệu mô phỏng, mô hình trung bình trượt cộng thừa trung bình **1,99 kg mỗi loại rau mỗi buổi** (600 lần đo, sau giờ chốt khách thực tế đặt thêm 0 kg). Tức là hệ thống đề nghị HTX thu dư rau.
- Bảng "% đoán đúng" chỉ chấm dự báo làm trước 1 ngày. Lúc đó đơn gần như đã đủ, nên con số này chủ yếu phản ánh đơn đã có, chưa cho thấy dự báo giỏi tới đâu.

### Cách hiểu đúng
Một buổi giao có hai phần:

```
Lượng cần chuẩn bị = Đơn đã có (biết chắc)  +  Phần khách sẽ đặt thêm (phải đoán)
```

| Thời điểm | Đơn đã có | Phần phải đoán | Quyết định cần ra |
|---|---|---|---|
| Trước buổi giao 2–3 ngày | Một phần | **Có** | Báo HTX giữ rau, xếp người thu hoạch, không bán hết cho thương lái |
| Hôm trước, sau 18:00 | Đủ | **Không còn** | Chốt số kg thu hoạch = đơn đã có + biên an toàn 10% |

Ví dụ: Thứ Năm lúc 18:15 đã có đơn 6 kg rau muống cho Thứ Bảy, khách còn đặt được tới 18:00 Thứ Sáu. Dự báo cần ước lượng phần khách đặt thêm trong khoảng đó, để HTX biết từ Thứ Năm là nên giữ khoảng bao nhiêu kg.

### Đã sửa trong code
- **Kế hoạch thu hoạch:** đã qua giờ chốt đơn của ngày giao thì phần dự báo thêm bằng 0. Gợi ý chỉ còn `đơn đã có × (1 + 10%)`, đã trừ tồn kho (`app/Services/HarvestPlanService.php`). Nếu operator nhập thêm đơn gọi điện sau giờ chốt, cảnh báo "kế hoạch đã cũ" có sẵn sẽ báo để tạo lại gợi ý.
- **Chấm điểm dự báo:** chỉ chấm dự báo làm **trước 2 ngày** (`evaluation_lead_days = 2` trong `config/farm2home.php`), và chỉ chấm **phần khách đặt thêm** (thực tế − đơn đã có lúc dự báo), không chấm tổng.

---

## 2. Công nghệ thực sự đã làm được

### Đang chạy được (có test tự động)
| Thành phần | Mô tả đúng |
|---|---|
| Trung bình trượt 7 ngày | Lấy trung bình lượng bán 7 ngày gần nhất. **Đây là cách đang dùng cho kế hoạch** |
| Làm mượt hàm mũ (α = 0,3) | Trung bình có trọng số, ngày gần đây nặng hơn |
| Trung bình cùng thứ 4 tuần | Trung bình các ngày cùng thứ trong 4 tuần trước |
| **Cách tính thông thường** (mới) | Đơn đã có + mức khách thường đặt thêm sau thời điểm này ở 4 buổi cùng thứ trước. Dùng làm mốc so sánh |
| Gợi ý thu hoạch | `làm tròn lên 0,5 kg [ max(0, đơn + dự báo thêm − tồn) × 1,1 ]`, chia cho HTX theo sản lượng đã báo. Operator duyệt và sửa trước khi gửi |
| Lịch chạy | 18:15 chạy dự báo 7 ngày tới, 21:00 điền số thực tế |
| Chatbot | Gọi Gemini khi có khóa API; không có thì trả lời theo kịch bản soạn sẵn. Chỉ dùng cho chatbot, **không** dùng để dự báo |

### Chưa có (chỉ là dự kiến)
- **Học máy (cây hồi quy):** chưa làm. Trong hệ thống mới chỉ có một giá trị `tree_regression` để giữ chỗ.
- **Thời tiết, lễ tết:** chưa đưa vào dự báo.
- **Dữ liệu thật:** chưa có buổi giao thật nào. Toàn bộ dữ liệu là dữ liệu mẫu do nhóm tự nhập và 2.000 đơn mô phỏng do máy sinh.

### Kết quả chạy thử có ngày giờ
- **Lúc chạy:** 24/11/2026, 21:35 (lệnh `php artisan demo:orders --ahead=16`, seed 2026)
- **Dữ liệu:** 2.000 đơn mô phỏng, buổi giao từ 02/09 đến 10/12/2026 (từ 25/11 trở đi là đơn khách đã đặt trước), 9 loại rau. Hệ thống chạy lại dự báo cho từng ngày đã qua, mỗi lần chỉ dùng những đơn đã có tới 18:15 hôm đó.
- **Số buổi đối chiếu:** 74 buổi giao cho mỗi loại rau (dự báo trước 2 ngày).
- **Lưu ý:** dữ liệu mô phỏng được sinh theo nhịp trong tuần, nên **không phải bằng chứng** cho chuyện ngoài thực tế.

> **Câu nên dùng trong hồ sơ:** "Hệ thống hiện dùng các phương pháp thống kê cơ bản (trung bình trượt, làm mượt hàm mũ, trung bình cùng thứ) để ước lượng phần khách đặt thêm. Mô hình học máy chưa được triển khai và chỉ được xem xét khi có đủ dữ liệu thật và thắng được cách tính thông thường."

---

## 3. So mô hình với cách tính thông thường

### Cách làm
- Mỗi tối lúc 18:15, hệ thống ghi **cùng lúc** dự báo của tất cả các cách vào bảng `forecasts`, **trước khi biết kết quả**. Không sửa lại sau.
- Sau buổi giao, hệ thống điền số thực tế và so **phần khách đặt thêm** mà mỗi cách đoán với phần khách thực sự đặt thêm.
- Trang **Đối chiếu buổi giao** (`/admin/sessions`) hiện từng lần đối chiếu: buổi, loại rau, đơn đã có lúc đoán, cách thường đoán, mô hình đoán, thực tế, bên nào gần hơn. **Giữ cả những buổi mô hình đoán kém hơn.**

### Kết quả trên dữ liệu mô phỏng (dự báo trước 2 ngày, buổi giao 10/09 → 23/11/2026; số liệu chạy lại 21:35)

| Cách đoán phần đặt thêm | Số lần | Lệch trung bình | WAPE |
|---|---|---|---|
| **Cách tính thông thường** | 666 | **2,71 kg** | **46,9%** |
| Trung bình cùng thứ | 671 | 3,58 kg | 61,9% |
| Trung bình trượt 7 ngày (đang dùng) | 671 | 4,19 kg | 72,4% |
| Làm mượt hàm mũ | 671 | 4,49 kg | 77,5% |

**Đối chiếu từng buổi**, trung bình trượt 7 ngày (đang dùng) so với cách thường, 666 lần:
- Mô hình gần hơn **241** lần
- Cách thường gần hơn **413** lần
- Hòa 12 lần

Khi dự báo trước 3 ngày, kết quả cũng tương tự: cách thường có WAPE 41,6%, tốt nhất trong các mô hình là trung bình cùng thứ với 44,4%.

### Kết luận trung thực
- Trên dữ liệu mô phỏng, **các mô hình thống kê chưa thắng cách tính thông thường**. Lý do: các mô hình đoán *tổng* lượng bán rồi trừ đi đơn đã có, còn cách thường đo thẳng *phần đặt thêm*.
- Vì vậy nhóm **không** dùng câu "AI dự báo chính xác hơn". Khi chạy thử với khách thật, nhóm sẽ tiếp tục ghi cả hai cách. Nếu sau đủ số buổi mô hình vẫn không thắng, nhóm chuyển kế hoạch sang dùng cách thường (chỉ cần đổi một dòng config).

---

## 4. Kết quả từng buổi giao

Trang **Đối chiếu buổi giao** ghi cho mỗi buổi giao và mỗi loại rau:

| Cột | Lấy từ đâu |
|---|---|
| Khách yêu cầu (kg) | Tổng các đơn không bị hủy |
| Giao được (kg) | Số gói thực giao × kg mỗi gói. Hiện "chưa ghi" nếu chưa nhập số giao |
| Dư/hỏng (kg) | Trang Rau dư cuối ngày |
| **Hết hàng (kg)** (mới) | Operator ghi tay phần khách hỏi mua mà không bán được (khách hỏi qua Zalo hoặc điện thoại). Đơn trên hệ thống không tự thấy phần này |
| Số lần đối chiếu | Đếm theo từng loại rau |

Ví dụ về cách đếm: 100 đơn tập trung trong 8 buổi giao, mỗi buổi dự báo 1 lần cho rau muống, thì rau muống chỉ có **8 lần đối chiếu**. Trang báo cáo luôn ghi số lần này cạnh mỗi con số sai số.

---

## 5. Lợi ích sau khi trừ chi phí

### Đã có trong hệ thống (theo từng buổi)
- **Tiền rau dư** = kg dư × giá thu mua mỗi kg
- **Lãi bỏ lỡ** = kg hết hàng × (giá bán mỗi kg − giá thu mua mỗi kg)

### Công thức nhóm sẽ dùng trong hồ sơ
```
Lợi ích ròng của dự báo = (Tiền rau dư giảm được + Lãi từ lượng bán thêm)
                          − (Chi phí máy chủ + Công nhập và kiểm tra dữ liệu + Công operator xem dự báo)
```
- Phần "giảm được" và "bán thêm" phải so với **cách tính thông thường** trong cùng giai đoạn, không so với lúc chưa có hệ thống.
- Chi phí điền theo thực tế khi chạy thử:

| Khoản | Cách tính | Số tiền/tháng |
|---|---|---|
| Máy chủ (Railway) | Theo hóa đơn | … đ |
| Nhập, kiểm tra đơn và rau dư | số phút/ngày × 30 × đơn giá công | … đ |
| Operator xem và sửa dự báo | số phút/ngày × 30 × đơn giá công | … đ |

Ví dụ giả định (theo thầy): dự báo giúp bán thêm 1 kg, còn lại 10.000đ sau chi phí trực tiếp, nhưng chạy hệ thống tốn thêm 15.000đ, thì chênh lệch là **−5.000đ**. Dự báo gần hơn trong trường hợp này vẫn chưa có lợi về tiền.

---

## 6. (Góp ý cô Hằng, Việc 1) AI đọc tin nhắn Zalo báo sản lượng

### Đã làm
- Trang **Sản lượng** có ô **Dán tin nhắn Zalo của HTX**. Ví dụ tin "mai có 30 ký cải ngọt, 20 ký rau muống" được đọc thành 2 dòng: loại rau, số kg, ngày có hàng. Người vận hành xem lại rồi bấm **Xác nhận lưu**. Dữ liệu lưu thành sản lượng với nguồn "Zalo".
- Dòng nào thiếu hoặc lạ thì có ⚠ và **không** được tích sẵn: không rõ loại rau, không rõ số kg, không rõ ngày, quá 500 kg, ngày đã qua, AI không chắc, hoặc HTX chưa đăng ký loại rau đó.
- AI dùng Gemini. Nếu không có khóa API hoặc Gemini lỗi, hệ thống tự dùng **cách dò từ khóa** (cách đơn giản). HTX không phải cài ứng dụng gì.

### Kết quả đo (lệnh `php artisan supply:eval-messages`, 24/11/2026 20:36)
Bộ 60 tin mẫu viết giống cách đại diện HTX nhắn: viết tắt, không dấu, "yến/tạ", "t7/cn", số viết bằng chữ, tin thiếu và tin mâu thuẫn. Chia làm hai phần:
- 20 tin để chỉnh câu lệnh cho AI.
- **40 tin để chấm.** Phần này không chỉnh theo kết quả.

| Cách đọc | Xử lý đúng | Tự đi tiếp nhưng sai | Chuyển cho người |
|---|---|---|---|
| Dò từ khóa (cách đơn giản) | 33/40 (82,5%) | 5 (12,5%) | 8 (20%) |
| **AI (Gemini)** | **37/40 (92,5%)** | **0 (0%)** | 10 (25%) |

- "Xử lý đúng" gồm hai trường hợp: tin đủ thông tin được đọc đúng hết, và tin thiếu hoặc mâu thuẫn được chuyển cho người.
- "Tự đi tiếp nhưng sai" là lỗi nguy hiểm nhất, vì có thể lưu sai số kg. AI không mắc lỗi nào loại này. Cách dò từ khóa mắc 5 lỗi, ví dụ:
  - "1 tạ rưỡi" đọc thành 100 kg.
  - "2 chục ký" đọc thành 2 kg.
  - "20 hay 25 gì đó" vẫn tự lưu 20 kg.
- 3 tin AI xử lý không đúng đều được chuyển cho người, nên không gây hại:
  - 2 tin do máy chủ Google báo quá tải (lỗi 503).
  - 1 tin không nhận ra "cải bẹ xanh".

**Ghi chú trung thực:**
- Bộ tin do nhóm tự soạn, nên con số có thể cao hơn khi dùng thật. Trước khi đưa vào hồ sơ cần bổ sung tin do người ngoài nhóm viết, hoặc tin Zalo thật của HTX nếu được đồng ý, rồi chạy lại lệnh.
- Cách dò từ khóa được sửa 1 lỗi (dấu phẩy thập phân "22,5kg") sau lần chấm đầu. Trước khi sửa, cách này đạt 80%.

### Ba con số nên nêu khi thuyết trình
1. AI xử lý đúng **92,5%** tin nhắn mẫu, cách dò từ khóa được 82,5%.
2. AI **không tự lưu sai** tin nào, cách dò từ khóa tự lưu sai 12,5% số tin.
3. **25%** tin được chuyển cho người xử lý (thiếu thông tin, mâu thuẫn hoặc AI không chắc).

Con số về dự báo dùng mục 3: trên dữ liệu mô phỏng, dự báo **chưa** hơn cách tính đơn giản.

---

## Đề nghị ghi trong hồ sơ

> "Nhóm sẽ thử dự báo lượng khách đặt thêm, so với cách tính thông thường qua nhiều buổi giao và tính lợi ích sau khi trừ chi phí. Hiệu quả AI là nội dung cần kiểm chứng; nhóm chỉ công bố kết quả khi có dữ liệu đối chiếu."

Có thể thêm: "Trên dữ liệu mô phỏng, cách tính thông thường đang cho sai số thấp hơn các mô hình thống kê (WAPE 47% so với 62–78%). Vì vậy hệ thống ghi song song cả hai cách để chọn theo dữ liệu thật."

---

## Phụ lục: các thay đổi trong code

| File | Thay đổi |
|---|---|
| `app/Services/HarvestPlanService.php` | Qua giờ chốt thì không cộng phần dự báo thêm |
| `app/Services/ForecastService.php` | Thêm cách tính thông thường (`extra_average`). Chấm sai số trên phần đặt thêm, trước 2 ngày. Thêm hàm `sessionComparison()` đối chiếu từng buổi |
| `app/Services/KpiService.php` | Thêm hàm `sessions()`: yêu cầu, giao được, dư, hết hàng, tiền |
| `app/Http/Controllers/Admin/SessionReportController.php`, `resources/views/admin/sessions/index.blade.php` | Trang mới **Đối chiếu buổi giao** (`/admin/sessions`), có ô ghi lượng hết hàng |
| `config/farm2home.php` | `evaluation_lead_days = 2`, `baseline_sessions = 4` |
| Migration mới | Thêm `extra_average` vào cột `forecasts.method`; bảng `unmet_demands` (lượng hết hàng) |
| Test | Gợi ý trước và sau giờ chốt, cách tính thông thường, chấm phần đặt thêm, đối chiếu từng buổi, trang báo cáo và ghi lượng hết hàng. Toàn bộ 390 test đều qua |

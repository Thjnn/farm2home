# Phase 9 — Hoàn thiện chức năng "Dự báo hôm nay" trên dữ liệu ~2.000 đơn

**Mục tiêu:** Bấm **Chạy dự báo hôm nay** cho ra con số kg từng loại rau cho 7 ngày giao tới mà operator tin được và so sánh được. Muốn vậy cần ba thứ mà bản MVP còn thiếu:

1. **Đủ dữ liệu để thấy dự báo hoạt động.** Dữ liệu mẫu chỉ có 163 đơn trong 4 tuần, mỗi loại rau vài kg mỗi ngày, sai số dao động mạnh. Phase này thêm một lệnh sinh **~2.000 đơn mô phỏng** trong 12 tuần, có nhịp cuối tuần, xu hướng tăng dần và nhiễu ngẫu nhiên.
2. **Một phương pháp biết nhịp trong tuần.** Trung bình trượt 7 ngày trộn Thứ Bảy (đông) với ngày thường (vắng) nên dự báo ngày thường quá cao. Thêm **Trung bình cùng thứ (4 tuần)**, đây chính là Task 7.5 đã đề xuất ở Phase 7.
3. **Lịch sử dự báo để đo sai số.** Bảng độ chính xác chỉ có số khi đã có dự báo cũ. Lệnh mô phỏng **chạy lại dự báo cho từng ngày trong quá khứ**, chỉ dùng những đơn đã có tại thời điểm đó, rồi điền số thực tế. Nhờ vậy bảng MAE/MAPE/WAPE có ngay ~80 ngày số liệu.

Trang Dự báo được bổ sung: chọn phương pháp để xem, đánh dấu phương pháp có WAPE thấp nhất, và biểu đồ "28 ngày qua + 7 ngày tới".

> **Dữ liệu mô phỏng không phải bằng chứng.** Nó chỉ để demo và kiểm thử. Quyết định đổi phương pháp mặc định phải dựa trên WAPE của **đơn thật** trong pilot (xem mục 7).

**Tech:** Laravel 13 · PHP 8.3 · MySQL 8.4 · Blade + Tailwind + Chart.js (đã có). Không thêm package.

**Nền:** [`docs/CHUC-NANG-AI.md`](../CHUC-NANG-AI.md) (dự báo hiện tại), [`phase-4-du-bao-thu-hoach.md`](phase-4-du-bao-thu-hoach.md), Task 7.5 trong [`phase-7-p1-deploy.md`](phase-7-p1-deploy.md).

---

## 1. "Dự báo hôm nay" làm gì, từng bước

Ngày chạy `D` (mặc định hôm nay, chạy tự động 18:15 sau giờ chốt đơn 18:00):

```
Với mỗi loại rau đang bán:
  1. Lấy lịch sử nhu cầu 28 ngày tới hết ngày D
     (Σ order_items.weight_kg của đơn chưa hủy, theo delivery_date; ngày không đơn = 0;
      bỏ các ngày trước đơn đầu tiên trong hệ thống)
  2. Tính mô hình:
       moving_average   = trung bình 7 ngày cuối ± độ lệch chuẩn
       exp_smoothing    = mức làm mượt α=0,3 ± RMSE dự báo 1 bước
       weekday_average  = trung bình 4 ngày CÙNG THỨ gần nhất ± độ lệch chuẩn   ← MỚI, tính riêng cho từng ngày đích
  3. Với mỗi ngày giao D+1 … D+7:
       đã_đặt          = Σ kg đơn chưa hủy của ngày đó, chỉ tính đơn đã tạo tới thời điểm chạy (knownAt)
       tổng_dự_báo     = max(mô hình, đã_đặt)          (D6: không bao giờ thấp hơn đơn đã có)
       dự_báo_thêm     = tổng_dự_báo − đã_đặt
       khoảng          = [max(lower, đã_đặt), max(upper, tổng)]
  4. Upsert vào forecasts theo khóa (product, target_date, generated_on, method)
```

Mỗi lần chạy ghi `7 loại rau × 7 ngày × 3 phương pháp = 147` dòng. Kế hoạch thu hoạch vẫn chỉ dùng `config('farm2home.forecast.default_method')` (hiện là `moving_average`).

### Ví dụ tính tay `weekday_average`

Ngày đích là Thứ Bảy 03/10. Bốn Thứ Bảy trước bán Rau muống `20, 28, 24, 26` kg.

- Dự báo = 98 / 4 = **24,5 kg**
- Độ lệch chuẩn = √((4,5² + 3,5² + 0,5² + 1,5²)/4) = √8,75 ≈ 2,96 → khoảng **21,54 – 27,46 kg**

Với cùng lịch sử, trung bình trượt 7 ngày trộn cả các ngày thường 5–8 kg nên chỉ ra khoảng 9–10 kg cho cả Thứ Bảy (thiếu) lẫn Thứ Ba (thừa).

### `knownAt`: vì sao cần

Đơn được đặt trước 1–6 ngày. Khi chạy dự báo **cho một ngày trong quá khứ** (replay), nếu đếm mọi đơn đang có trong DB thì "đã đặt" sẽ bằng đúng số thực tế, dự báo trông hoàn hảo một cách giả tạo. `run($D, knownAt: D 18:15)` chỉ đếm đơn có `created_at <= knownAt`. Khi chạy thật (hôm nay), `knownAt` bỏ trống và mọi đơn đều được tính như trước.

---

## 2. Dữ liệu mô phỏng: lệnh `demo:orders`

```
php artisan demo:orders                     # 2000 đơn, 84 ngày qua + 6 ngày tới, seed 2026
php artisan demo:orders --count=500 --days=28 --seed=7
```

| Tham số | Mặc định | Ý nghĩa |
|---|---|---|
| `--count` | 2000 | Tổng số đơn sinh ra (đúng bằng số này) |
| `--days` | 84 | Số ngày lịch sử, tính tới hết hôm nay |
| `--customers` | 150 | Số khách mô phỏng |
| `--seed` | 2026 | Cùng seed → cùng dữ liệu |
| `--force` | | Cho phép chạy ở production (mặc định bị chặn) |

### 2.1. Nhận diện và chạy lại

- Khách mô phỏng có tên `Khách mô phỏng 001…`, số điện thoại `0999000001…` (hợp lệ `^0\d{9}$`, không trùng số thật), `note = '[mô phỏng]'`.
- Mỗi lần chạy **xóa toàn bộ dữ liệu mô phỏng cũ trước** (đơn, dòng đơn, liên kết lô, phản hồi, bao bì, điểm, giỏ định kỳ của khách mô phỏng), rồi sinh lại. Chạy bao nhiêu lần cũng ra cùng một bộ dữ liệu. Dữ liệu mẫu 163 đơn **không bị đụng tới**.
- Cả bước xóa và sinh nằm trong một transaction.

### 2.2. Cách sinh đơn

Mỗi đơn được gán vào một ngày giao theo trọng số:

```
trọng_số(ngày) = hệ_số_thứ × xu_hướng × hệ_số_đặt_trước
  hệ_số_thứ        T2 0,8 · T3 0,7 · T4 0,8 · T5 0,9 · T6 1,1 · T7 1,8 · CN 1,4
  xu_hướng         tăng tuyến tính 0,7 → 1,2 trong suốt khoảng lịch sử (pilot đang có thêm khách)
  hệ_số_đặt_trước  ngày đã qua và hôm nay = 1; ngày tới D+1…D+6 = 0,85 · 0,5 · 0,35 · 0,25 · 0,2 · 0,15
                   (ngày càng xa càng ít khách đã đặt)
```

| Thuộc tính | Phân phối |
|---|---|
| Loại đơn | 40% giỏ (Mini 30% · Tiết Kiệm 45% · Gia Đình 25%), 60% đơn lẻ |
| Đơn lẻ | 1–4 loại rau khác nhau, chọn theo độ phổ biến (RM 25 · CN 18 · CX 12 · MT 12 · RD 10 · XL 13 · HL 10), mỗi loại 1–3 gói |
| Kênh | zalo 50% · form 25% · website 15% · phone 10% |
| Cụm giao | theo cụm của khách; cụm có điểm nhận → `pickup`, phí 0 |
| Thanh toán | COD 70%, chuyển khoản 30% |
| Thời điểm đặt | trước ngày giao 1 ngày (55%), 2 ngày (25%), 3 ngày (10%), 4–6 ngày (10%); giờ 07:00–17:59, nên luôn trước giờ chốt 18:00 hôm trước |
| Trạng thái | ngày đã qua: 97% `delivered` (đã thanh toán, `fulfilled_quantity` = số gói, 4% dòng thiếu 1 gói), 3% `cancelled`; hôm nay: `delivering`; ngày tới: 60% `pending`, 40% `confirmed` |

Tiền và khối lượng theo đúng quy ước của hệ thống:
- `weight_kg = quantity × pack_weight_kg`
- Đơn giỏ: dòng có `basket_id`, `unit_price = 0`; `subtotal` = giá giỏ
- Đơn lẻ: `line_total = quantity × price`; `subtotal = Σ line_total`
- `shipping_fee` qua `ShippingCalculator::forZone()`; `total = subtotal + shipping_fee`
- Mã đơn `DH{yymmdd}-{seq}`, `seq` nối tiếp số lớn nhất đang có của ngày đó

### 2.3. Phát lại dự báo (replay)

Sau khi sinh đơn, lệnh:

1. Với từng ngày `d` từ ngày đầu + 7 tới hôm nay: `ForecastService::run(d, knownAt: d 18:15)`.
2. Xóa `actual_kg` của các dự báo trong khoảng, rồi `backfillActuals(hôm qua)` để điền lại số thực tế.

Kết quả: bảng độ chính xác trên trang Dự báo và Dashboard có ngay số liệu so sánh 3 phương pháp.

### 2.4. Những gì lệnh cố ý không làm

- Không tạo lô hàng, kế hoạch thu hoạch, rau dư cho đơn mô phỏng. KPI "% rau dư trên hàng nhận" vì vậy chỉ phản ánh dữ liệu mẫu.
- Không cộng điểm thành viên cho đơn mô phỏng đã giao.
- Không sinh từ giỏ định kỳ; đơn giỏ mô phỏng là đơn giỏ lẻ.

---

## 3. Trang Dự báo (`/admin/forecasts`)

| Phần | Thay đổi |
|---|---|
| Nút | Giữ **Chạy dự báo hôm nay** và **Cập nhật số thực tế** |
| Chọn phương pháp | Ba nút chuyển `?method=` (mặc định là phương pháp trong config, có chữ "đang dùng cho kế hoạch") |
| Bảng độ chính xác | Thêm phương pháp mới; dòng có WAPE thấp nhất có nhãn **Sai số thấp nhất** |
| Biểu đồ | "Nhu cầu 28 ngày qua và dự báo 7 ngày tới": đường thực tế, đường dự báo (nét đứt), vùng khoảng thấp–cao. Chọn `?product=` một loại rau hoặc **Tất cả** (cộng các loại) |
| Bảng theo ngày giao | Như cũ, theo phương pháp đang chọn |

Biểu đồ dùng chung `resources/js/dashboard.js` (Chart.js đã có, cùng bảng màu đã kiểm định). Dữ liệu truyền qua `<script type="application/json">` như Dashboard.

---

## 4. File thay đổi

| File | Việc |
|---|---|
| `database/migrations/2026_10_06_000000_add_weekday_average_to_forecast_method.php` | Thêm `weekday_average` vào ENUM `forecasts.method` |
| `app/Enums/ForecastMethod.php` | `case WeekdayAverage`, nhãn "Trung bình cùng thứ (4 tuần)" |
| `app/Support/ForecastCalculator.php` | `weekdayAverage(array $historyByDate, CarbonInterface $target, int $weeks = 4)` |
| `app/Services/ForecastService.php` | Phương pháp thứ 3; tham số `?knownAt` cho `run()` và `confirmedKg()`; `chartSeries()` cho biểu đồ |
| `app/Console/Commands/DemoOrders.php` | Lệnh `demo:orders` (mới) |
| `app/Http/Controllers/Admin/ForecastController.php` | Đọc `?method=`, `?product=`, truyền dữ liệu biểu đồ |
| `resources/views/admin/forecasts/index.blade.php` | Nút chọn phương pháp, nhãn sai số thấp nhất, biểu đồ |
| `resources/js/dashboard.js` | Vẽ biểu đồ dự báo |
| `docs/CHUC-NANG-AI.md` | Cập nhật phương pháp 3, lệnh mô phỏng, giới hạn |

Test:

| Test | Kiểm tra |
|---|---|
| `tests/Unit/Support/ForecastCalculatorTest.php` | `weekdayAverage` chỉ dùng cùng thứ; không có ngày khớp thì 0 |
| `tests/Feature/Forecast/ForecastServiceTest.php` | Số dòng 42 → 63; `knownAt` bỏ qua đơn tạo sau thời điểm chạy |
| `tests/Feature/Forecast/ForecastScreenTest.php` | 98 → 147; chọn phương pháp; biểu đồ có dữ liệu |
| `tests/Unit/Enums/EnumLabelsTest.php` | Thêm `weekday_average` |
| `tests/Feature/DemoOrdersTest.php` | Đúng số đơn; tiền/khối lượng nhất quán; mã đơn đúng dạng và không trùng; chạy lại không nhân đôi; có dự báo replay kèm số thực tế; chặn ở production |

---

## 5. Các task

### Task 9.1 — Phương pháp "Trung bình cùng thứ" (Task 7.5)
- [ ] Test `weekdayAverage` (fail) → code calculator → pass
- [ ] Enum + migration; `php artisan migrate` trên DB dev (chỉ một migration mới, dữ liệu giữ nguyên)
- [ ] `run()` tính thêm phương pháp cho từng ngày đích; sửa các con số 42/98/6 trong test cũ
- [ ] `php artisan test` xanh, commit

### Task 9.2 — `knownAt` cho replay
- [ ] Test: đơn tạo sau `knownAt` không nằm trong `confirmed_kg` của lần chạy đó
- [ ] Thêm tham số tùy chọn cho `run()` và `confirmedKg()`; không đổi hành vi khi bỏ trống
- [ ] Commit

### Task 9.3 — Lệnh `demo:orders`
- [ ] `DemoOrdersTest` (fail) → code lệnh → pass
- [ ] Chạy trên DB dev: `php artisan demo:orders`, kiểm tra 2.000 đơn và bảng độ chính xác
- [ ] Commit

### Task 9.4 — Trang Dự báo
- [ ] Test màn hình: `?method=weekday_average` hiện nhãn đúng; có dữ liệu biểu đồ; nhãn "Sai số thấp nhất"
- [ ] Controller + view + JS; `npm run build`
- [ ] Kiểm tra trực quan ở 1280px và 360px
- [ ] Commit

### Task 9.5 — Tài liệu
- [ ] Cập nhật `docs/CHUC-NANG-AI.md` (mục 2, 8, 9, 10)
- [ ] Commit

---

## 6. Kiểm thử chấp nhận

1. `php artisan demo:orders` → in ra số đơn mô phỏng = 2.000, số dự báo replay, số dòng có thực tế.
2. Chạy lại lần nữa → tổng số đơn trong DB không đổi.
3. `/admin/forecasts`: bảng độ chính xác có 3 phương pháp, khoảng 14 điểm × 7 loại rau mỗi phương pháp; một dòng có nhãn **Sai số thấp nhất**.
4. Bấm **Chạy dự báo hôm nay** → thông báo "Đã chạy dự báo: 147 dòng."
5. Biểu đồ: đường thực tế có đỉnh vào Thứ Bảy; đường `weekday_average` cũng có đỉnh Thứ Bảy, còn `moving_average` là đường phẳng.
6. `/admin/harvest-plans` → **Tạo gợi ý** cho ngày mai vẫn chạy (dùng phương pháp mặc định).
7. `php artisan test` xanh toàn bộ.

---

## 7. Đổi phương pháp mặc định (việc của team, không làm trong phase này)

`config('farm2home.forecast.default_method')` vẫn là `moving_average`. Sau **ít nhất 2 tuần pilot với đơn thật**, mở Dự báo → bảng độ chính xác. Chỉ đổi sang `weekday_average` nếu WAPE của nó thấp hơn. Dữ liệu mô phỏng được sinh ra với nhịp tuần nên đương nhiên ưu ái `weekday_average`; không dùng nó làm căn cứ.

## 8. Giới hạn còn lại

- Dự báo 1 ngày trước, sau giờ chốt, gần như bằng đơn đã đặt. Sai số đo ở lead 1 chủ yếu là phần **dự báo thừa** (mô hình > đơn đã đặt). Dự báo xa hơn (lead 3–7) mới thật sự kiểm tra mô hình; bảng hiện chỉ đo lead 1 vì đó là con số dùng để thu hoạch.
- Chưa có lễ tết, thời tiết, khuyến mãi.
- Ngày có ít hơn 4 ngày cùng thứ trong lịch sử thì `weekday_average` trung bình trên số ngày đang có; chưa có ngày nào thì ra 0 (khi đó vẫn có `đã_đặt` chặn dưới).

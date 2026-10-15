# Phase 8 — Tích điểm thành viên (bản thiết kế)

> Ngày: 2026-10-02 · Trạng thái: chờ duyệt · Kế hoạch code sẽ nằm ở `docs/plans/phase-8-tich-diem.md` sau khi bản này được duyệt.

## 1. Mục đích

Thưởng cho hai hành vi mà pilot đang đo:

- **Mua lại** → chỉ số "tỷ lệ mua lại" (KPI khách hàng).
- **Trả bao bì** → chỉ số "tỷ lệ thu hồi bao bì" (KPI môi trường).

Thành công nghĩa là: khách xem được số điểm của mình bằng số điện thoại, đổi được quà ngay trong đơn kế tiếp mà không cần tài khoản, và dashboard cho thấy bao nhiêu điểm đã cộng, đã đổi.

Ngoài phạm vi bản này: chỉnh điểm thủ công, hạng thành viên, điểm thưởng riêng cho gói định kỳ, điểm hết hạn, thông báo Zalo tự động.

## 2. Ràng buộc kế thừa từ spec gốc

- Khách **không có tài khoản**; định danh bằng số điện thoại (`customers.phone`).
- **Con người xác nhận:** hệ thống không tự trừ điểm; người điều phối duyệt từng lần đổi quà.
- Tiền là số nguyên VND. Thao tác tiền và điểm bọc trong DB transaction.
- Không `migrate:fresh` trên DB `farm2home`; chỉ thêm migration mới.
- Giao diện tiếng Việt, mobile-first (360px, nút cao tối thiểu 44px). Không thêm package.
- Điểm không có giá trị quy đổi ra tiền mặt; giao diện chỉ dùng chữ "đổi quà".

## 3. Quy tắc nghiệp vụ

### 3.1 Cộng điểm

| Sự kiện | Điểm | Điều kiện |
|---|---|---|
| Đơn chuyển sang `delivered` | +5 | Một lần cho mỗi đơn. Chỉ khi `orders.subtotal >= 40.000đ`. |
| Ghi nhận trả bao bì có mã đơn | +5 | Một lần cho mỗi đơn, không phụ thuộc số bao và giá trị đơn. |
| Ghi nhận trả bao bì không có mã đơn | +5 | Tối đa một lần cho mỗi khách trong một `return_date`. |

Một đơn vì vậy mang lại tối đa 10 điểm (giao xong + trả bao bì của đơn đó). Điểm trả bao bì cộng cho khách ghi trên bản ghi `packaging_returns` (số điện thoại nhập vào, nếu không có thì khách của đơn). Bản ghi không xác định được khách (không có số điện thoại, không có mã đơn) thì không cộng điểm.

### 3.2 Đổi quà

| Mã quà | Tên hiển thị | Điểm | Tác động lên đơn |
|---|---|---|---|
| `small_bunch` | Tặng bó rau nhỏ | 20 | Thêm 1 gói rau giá 0đ |
| `free_shipping` | Miễn phí 1 lần giao hàng | 30 | `shipping_fee = 0` |
| `discount_20k` | Giảm 20.000đ cho đơn | 50 | `discount = min(20.000, subtotal)` |
| `small_combo` | Tặng combo rau nhỏ | 100 | Thêm 3 gói rau giá 0đ |

- Mỗi đơn đổi tối đa **một** quà.
- Chỉ áp dụng khi đơn đang `pending` hoặc `confirmed` (trước khi đóng gói và phân lô).
- Số dư phải đủ tại thời điểm duyệt; số dư không bao giờ âm.
- `free_shipping` bị từ chối khi đơn vốn đã có `shipping_fee = 0` (nhận tại điểm hoặc đã freeship), để khách không mất điểm vô ích.
- Sau khi áp dụng: `total = subtotal + shipping_fee − discount`.
- Hủy đơn đã đổi quà → hoàn lại đúng số điểm đã trừ.
- Điểm không hết hạn trong giai đoạn thí điểm.

### 3.3 Quà là rau

Người điều phối chọn loại rau lúc duyệt (1 gói cho `small_bunch`, đúng 3 gói cho `small_combo`, có thể trùng loại), nên có thể ưu tiên rau đang dư. Gói tặng là một dòng `order_items` bình thường với `unit_price = 0`, `line_total = 0`, `is_gift = 1`; nó được tính vào nhu cầu thu hoạch, phân lô và tỷ lệ giao đủ như mọi dòng khác.

Nếu kế hoạch thu hoạch của ngày giao đã `confirmed` trở đi, màn hình duyệt hiện cảnh báo rằng rau tặng sẽ lấy từ phần biên an toàn hoặc hàng dư; hệ thống không chặn.

## 4. Quyết định thiết kế

| # | Vấn đề | Quyết định |
|---|---|---|
| D19 | Lưu điểm thế nào | Sổ giao dịch `point_transactions` chỉ thêm dòng, không sửa. Số dư = `SUM(points)` theo khách; không có cột số dư lưu sẵn (pilot 20–30 hộ). |
| D20 | Chống cộng trùng | Khóa duy nhất `(order_id, reason)`: mỗi đơn có tối đa một dòng cho mỗi lý do. Trường hợp trả bao bì không mã đơn kiểm tra trong service, dưới khóa dòng khách. |
| D21 | Cấu hình | Điểm, ngưỡng 40.000đ và danh mục quà nằm ở `config('farm2home.loyalty')`. Không có CRUD quà trong admin. |
| D22 | Miễn phí giao ghi vào đâu | Đặt `shipping_fee = 0` chứ không ghi vào `discount`, để "phí giao thu được / đơn" (D17) và biên gộp (D16) vẫn đúng nghĩa. |
| D23 | Phân biệt gói tặng | Cột mới `order_items.is_gift`, vì dòng của giỏ cũng có `unit_price = 0`. |
| D24 | Yêu cầu của khách | Cột mới `orders.requested_reward` (mã quà, nullable). Lúc đặt hàng không kiểm tra số dư và không trừ điểm; đơn không bao giờ bị từ chối vì thiếu điểm. |
| D25 | Tra cứu bằng SĐT | Trang `/tra-cuu` hiện thêm **số dư** và bảng mốc quà; không hiện lịch sử giao dịch (giữ tinh thần D11). |
| D26 ⚠ | Combo rau nhỏ | Hiểu là 3 gói rau do người điều phối chọn. Cần nhóm xác nhận. |
| D27 | Ngưỡng 50.000đ | Chặn việc tách nhiều đơn lẻ để gom điểm. Nhóm đã chốt 50.000đ (15/10/2026, trước đó 40.000đ). |

## 5. Dữ liệu

Một migration mới, ba thay đổi:

**Bảng `point_transactions`**

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `customer_id` | BIGINT UNSIGNED, FK `customers` | |
| `points` | INT (có dấu) | dương = cộng, âm = trừ |
| `reason` | ENUM(`order_delivered`, `packaging_returned`, `redeemed`, `refunded`) | |
| `order_id` | BIGINT UNSIGNED NULL, FK `orders` | |
| `packaging_return_id` | BIGINT UNSIGNED NULL, FK `packaging_returns` | |
| `reward` | VARCHAR(40) NULL | mã quà, với `redeemed` và `refunded` |
| `created_by` | BIGINT UNSIGNED NULL, FK `users` | người duyệt đổi quà |
| `created_at`, `updated_at` | TIMESTAMP NULL | |

Khóa: `UNIQUE (order_id, reason)`, `INDEX (customer_id)`.

**`orders.requested_reward`** VARCHAR(40) NULL · **`order_items.is_gift`** TINYINT(1) NOT NULL DEFAULT 0.

## 6. Thành phần

| Thành phần | Trách nhiệm |
|---|---|
| `App\Enums\PointReason` | 4 lý do, có `label()` tiếng Việt. |
| `App\Models\PointTransaction` + factory | Quan hệ `customer`, `order`, `packagingReturn`, `creator`. `Customer::pointTransactions()`. |
| `App\Support\Reward` | Đọc danh mục quà từ config: `all()`, `find(key)`, các thuộc tính `key/label/points/type/packs/amount`. Thuần, test không cần DB. |
| `App\Services\LoyaltyService` | Toàn bộ nghiệp vụ điểm (mục 7). Không nơi nào khác ghi vào `point_transactions`. |
| `OrderService` | Gọi `LoyaltyService` khi đơn sang `delivered` và khi hủy; lưu `requested_reward` khi tạo đơn. |
| `Admin\PackagingReturnController` | Sau khi tạo bản ghi trả bao bì, gọi `LoyaltyService`. Thông báo ghi rõ có cộng điểm hay không. |
| `Admin\OrderController@redeem` | `POST admin/orders/{order}/reward` — duyệt đổi quà. |
| `Admin\LoyaltyController` | `GET admin/loyalty` (danh sách khách + số dư), `GET admin/loyalty/{customer}` (lịch sử). Cả admin và operator. |
| `KpiService::loyalty()` | `points_earned`, `points_redeemed`, `redemptions` trong khoảng ngày; dashboard hiện trong nhóm Khách hàng. |
| Lệnh `loyalty:backfill` | Cộng điểm cho đơn đã giao và lượt trả bao bì chưa có dòng trong sổ. Chạy lại không cộng trùng. Dùng cho dữ liệu mẫu. |

## 7. `LoyaltyService`

```php
balance(Customer $customer): int
awardForDeliveredOrder(Order $order): ?PointTransaction      // null khi không đủ điều kiện hoặc đã cộng
awardForPackagingReturn(PackagingReturn $return): ?PointTransaction
redeem(Order $order, Reward $reward, array $productIds, User $by): PointTransaction   // DomainException khi bị từ chối
refundForCancelledOrder(Order $order): ?PointTransaction
```

`redeem` chạy trong một transaction và khóa dòng `customers` (`lockForUpdate`) trước khi đọc số dư, để hai lần duyệt cùng lúc không tiêu quá số điểm. Nó từ chối bằng `DomainException` (thông báo tiếng Việt, controller hiện qua `withErrors(['action' => …])` như `transition`) khi: đơn không ở `pending`/`confirmed`, đơn đã đổi quà, số dư không đủ, `free_shipping` trên đơn không có phí giao, quà rau mà số gói chọn không đúng hoặc sản phẩm không còn bán.

## 8. Giao diện

**Form đặt hàng `/dat-hang`** — thêm một `<select>` không bắt buộc "Đổi điểm lấy quà (nếu có)", liệt kê 4 quà kèm số điểm, và một dòng chú thích: "Chúng tôi kiểm tra điểm khi xác nhận đơn. Xem điểm ở trang Tra cứu đơn."

**Trang cảm ơn** — nếu khách có chọn quà: "Yêu cầu đổi quà sẽ được xác nhận cùng đơn hàng."

**Trang `/tra-cuu`** — phía trên danh sách đơn: "Bạn đang có N điểm", bảng 4 mốc quà (mốc nào đủ điểm thì đánh dấu), và cách tích điểm trong một câu.

**Trang đơn trong admin** — khối "Điểm thành viên": số dư của khách, quà khách yêu cầu (nếu có), form duyệt gồm chọn quà, chọn rau khi quà là rau, nút "Áp dụng". Quà chưa đủ điểm hiện mờ kèm số điểm còn thiếu. Khi đơn đã đổi quà: hiện quà đã áp dụng thay cho form. Dòng tặng trong bảng rau có nhãn "Quà đổi điểm".

**Trang trả bao bì** — không đổi form; thông báo sau khi lưu thêm "+5 điểm cho {tên khách}" khi có cộng.

**Admin → Điểm thành viên** — bảng khách có giao dịch điểm: tên, số điện thoại, đã cộng, đã đổi, số dư; bấm vào xem lịch sử. Thêm mục trong menu admin.

**Dashboard** — nhóm Khách hàng thêm: điểm đã cộng, điểm đã đổi, số lượt đổi quà.

## 9. Lỗi và trường hợp biên

- Đơn dưới 40.000đ giao xong: không cộng, không báo lỗi.
- Bấm "đã giao" hai lần hoặc chạy `loyalty:backfill` nhiều lần: khóa duy nhất chặn dòng thứ hai; service trả `null`.
- Khách chọn quà nhưng chưa đủ điểm: đơn vẫn được tạo; người điều phối thấy "Chưa đủ điểm" và không áp dụng.
- Hủy đơn chưa đổi quà: không có gì để hoàn.
- Giảm 20.000đ trên đơn có `subtotal` nhỏ hơn 20.000đ: giảm bằng `subtotal`, vẫn trừ đủ 50 điểm.
- Đơn do gói định kỳ sinh ra: không có yêu cầu của khách, nhưng người điều phối vẫn áp dụng được quà trên trang đơn.

## 10. Kiểm thử

- **Unit:** `Reward` đọc đúng danh mục; mã lạ trả `null`.
- **`LoyaltyService` (feature, MySQL test):** cộng một lần cho đơn giao xong; không cộng dưới ngưỡng; trả bao bì theo đơn và theo ngày; từng loại quà thay đổi đơn đúng như bảng 3.2 và `total` đúng; từ chối khi thiếu điểm, sai trạng thái, đổi lần hai, freeship trên đơn không phí; hủy đơn hoàn điểm đúng một lần.
- **Màn hình:** form đặt hàng lưu `requested_reward` và từ chối mã lạ; `/tra-cuu` hiện số dư; trang đơn admin duyệt được quà và hiện lỗi; trang Điểm thành viên chặn khách chưa đăng nhập; dashboard hiện 3 số mới.
- **Dữ liệu mẫu:** `loyalty:backfill` chạy hai lần cho cùng kết quả; `SmokeTest` phủ các trang mới.
- Toàn bộ 238 test hiện có vẫn xanh.

# Phase 10 — Đối tác cung ứng là Hợp tác xã (HTX)

> Góp ý của người hướng dẫn: đổi đối tác cung ứng từ "nông hộ" sang "hợp tác xã".
> Trạng thái: **đã code lại (2026-11-11) theo câu trả lời của nhóm ở mục 0.** Phần bên dưới mục 0 là bản plan cũ (tầng HTX trên nông hộ), đã bỏ.

## 0. Đã chốt và đã làm

Câu trả lời của nhóm:
1. Không nhận hộ sản xuất độc lập; pilot chỉ liên kết với HTX.
2. HTX là đơn vị báo sản lượng chính thức.
3. F2H thanh toán cho HTX theo khối lượng nghiệm thu.
4. F2H chỉ phân bổ tới HTX; HTX tự chia cho thành viên.
5. Chưa có HTX nào ký; dùng tên HTX Long Bình (Gò Công) làm mẫu, chỉ một HTX.

Cách làm: **mỗi dòng `farmers` là một HTX** (giữ tên bảng/model `farmers`, `farmer_id`). Báo sản lượng, phân bổ kg, lô, QR, chứng nhận, tài khoản ngân hàng, tin Zalo chạy y như cũ nhưng theo HTX.
- Migration `2026_11_11_202101_suppliers_are_cooperatives`: thêm `farmers.representative_name`, `farmers.member_households`, `batches.grower_name`; chuyển dữ liệu mẫu (`DemoSeeder::convertSampleFarmersToCooperative`): NH01 → HTX01 "HTX Long Bình", NH02/NH03 → ngừng hợp tác (giữ lịch sử).
- Link cổng: `/htx/{token}`; link cũ `/nong-ho/{token}` chuyển hướng 301.
- Nhận hàng: ô tùy chọn "Hộ trồng (nếu HTX khai)"; trang QR hiện "Hộ trồng (theo HTX khai)" khi có.
- Trang QR: "Hợp tác xã", "Vùng trồng của …", chứng nhận ghi "Cấp cho {HTX}".
- Giao diện đổi chữ "nông hộ" → "HTX". Tên trong code (`Farmer`, `farmer_id`, route `farmer.*`) giữ nguyên.
- Không làm: danh sách hộ thành viên, chia kg cho thành viên, đối soát tiền HTX–thành viên.

---

## (Bản cũ, đã bỏ) 1. Ý tưởng chính

Thêm một tầng **HTX ở trên** nông hộ, không xoá nông hộ.

```
HTX (đối tác, người FARM2HOME làm việc cùng)
 ├─ Nông hộ thành viên NH01  ─► báo sản lượng, lô hàng, QR
 ├─ Nông hộ thành viên NH02
 └─ ...
Nông hộ độc lập (không thuộc HTX)  ─► vẫn chạy như hiện tại
```

- **Điều phối và liên lạc theo HTX:** chủ nhiệm/cán bộ HTX nhận 1 link, báo sản lượng giùm thành viên, nhận 1 tin Zalo cho cả HTX.
- **Truy xuất vẫn theo nông hộ:** lô hàng, mã lô `F2H-yymmdd-RM-NH01`, trang QR vẫn ghi đúng hộ trồng, thêm dòng "Thành viên HTX …". Minh bạch hơn, không mất dữ liệu cũ.
- **Phân bổ thu hoạch giữ nguyên thuật toán** (`HarvestMath::allocate` theo từng hộ). Chỉ gom nhóm theo HTX khi hiển thị và khi gửi tin.

Lợi ích để thuyết trình (inclusive): hộ lớn tuổi, không rành điện thoại vẫn tham gia được nhờ HTX báo giùm; chứng nhận VietGAP thường cấp cho cả HTX (chứng nhận nhóm), nên hộ nhỏ cũng được hưởng.

## 2. Thay đổi CSDL (migration mới, không sửa `farm2home.sql`)

**Bảng mới `cooperatives`**
| Cột | Ghi chú |
|---|---|
| `code` unique | `HTX01`… |
| `name` | VD: HTX Rau an toàn Tân Mỹ Chánh |
| `representative_name`, `phone`, `zalo_phone` | Người đại diện nhận tin |
| `address`, `growing_area` | Hiện trên QR |
| `certificate_name`, `certificate_file`, `certificate_expires_at` | Chứng nhận cấp cho HTX |
| `bank_name`, `bank_account_number` (encrypted), `bank_account_holder` | Nếu thanh toán qua HTX |
| `access_token` unique | Link `/htx/{token}` |
| `status` | Dùng lại enum `FarmerStatus` (active/paused/inactive) |
| `note`, timestamps | |

**Bảng `farmers`:** thêm `cooperative_id` nullable FK → `cooperatives` (`ON DELETE SET NULL`). Null = hộ độc lập.

Các bảng `supply_reports`, `harvest_plan_items`, `batches` **không đổi** (vẫn trỏ `farmer_id`).

## 3. Công việc theo bước

1. **Schema + model**: migration, model `Cooperative` (quan hệ `members()`, `hasValidCertificate()`, `zaloLink()` giống `Farmer`), `Farmer::cooperative()`, factory, thêm vào `BuildsCatalog`.
2. **Admin CRUD HTX** (`/admin/cooperatives`, chỉ admin): danh sách, thêm/sửa, tạo lại token, xem thành viên. Form nông hộ thêm ô chọn HTX; danh sách nông hộ lọc theo HTX.
3. **Cổng HTX** `/htx/{cooperative:access_token}` (không cần đăng nhập, throttle như cổng nông hộ):
   - Bảng báo sản lượng: thành viên × rau × ngày, lưu qua `SupplyReportService` với `source` = form, ghi chú "HTX báo giùm".
   - Xem danh sách thu hoạch của cả HTX, xác nhận giùm từng hộ (gọi lại `HarvestPlanService::farmerConfirm`).
   - Link riêng của từng nông hộ **vẫn giữ** (hộ nào tự báo được thì tự báo).
4. **Kế hoạch thu hoạch** (`admin/harvest-plans/show`): gom dòng theo HTX; tin Zalo mới `zaloMessage(plan, cooperative)` liệt kê "hộ — rau — kg" gửi người đại diện HTX. Hộ độc lập vẫn nhận tin riêng như cũ.
5. **Trang QR** (`public/batches/show`): thêm dòng "Hợp tác xã: …". Chứng nhận: hiện của hộ nếu hộ có và còn hạn, không thì của HTX nếu còn hạn, ghi rõ "cấp cho HTX …". Vẫn giữ nguyên tắc QR ≠ chứng nhận.
6. **Dashboard / KPI**: thẻ "Đối tác cung ứng" = số HTX đang hợp tác + số hộ thành viên; % sản lượng báo trước tính theo HTX. Cảnh báo chứng nhận sắp hết hạn gồm cả HTX.
7. **Đổi chữ trên giao diện**: menu "Nông hộ" → "HTX & nông hộ"; những chỗ chung chung dùng "nguồn cung"/"HTX". Không đổi tên code (`Farmer`, `farmer_id`), không đổi URL `/nong-ho`.
8. **Dữ liệu mẫu**: tạo 1 HTX mẫu, gán NH01–NH03 vào (DemoSeeder cho DB rỗng + lệnh/bước một lần cho DB dev đang có dữ liệu). Ghi rõ là dữ liệu giả.
9. **Tài liệu**: cập nhật `docs/CLAUDE.md` (mục 1, 2, 4, 6), README, kịch bản thuyết trình.
10. **Test**: CRUD HTX, cổng HTX báo sản lượng/xác nhận giùm, tin Zalo theo HTX, QR hiện tên HTX + chứng nhận HTX, hộ độc lập vẫn chạy như cũ.

Mỗi bước 1–2, 3, 4, 5, 6–7 là một commit riêng.

## 4. Không làm (chưa cần)

- HTX tự chia lại kg cho thành viên trong hệ thống (operator đã chỉnh `final_kg` + đổi hộ được rồi).
- Tài khoản đăng nhập cho HTX (token link là đủ, đúng nguyên tắc không bắt tạo tài khoản).
- Đối soát công nợ / chia tiền giữa HTX và thành viên.

## 5. Rủi ro

- Phân bổ theo hộ nhưng gửi tin theo HTX: tin phải ghi rõ từng hộ để HTX chia đúng.
- Chứng nhận HTX không tự động áp cho hộ ngoài HTX: chỉ lấy theo `cooperative_id` của hộ trồng lô đó.

## 6. Câu hỏi cần chốt với nhóm / người hướng dẫn

1. Hộ **độc lập** (không thuộc HTX) còn được phép không? Plan đang giả định là có.
2. Ai báo sản lượng: chỉ HTX báo giùm, hay cả HTX và từng hộ? Plan đang giả định là cả hai.
3. Thanh toán trả cho HTX hay cho từng hộ? Plan đang lưu tài khoản ở cả hai, ưu tiên HTX.
4. Có muốn hệ thống **phân bổ cho HTX** (HTX tự chia cho thành viên ngoài hệ thống) thay vì phân bổ tới từng hộ không? Cách này đơn giản hơn nhưng QR sẽ mất tên hộ trồng.
5. Tên HTX thật (nếu đã có HTX nào ở Châu Thành / Chợ Gạo / Cai Lậy nhận lời) để làm dữ liệu mẫu.

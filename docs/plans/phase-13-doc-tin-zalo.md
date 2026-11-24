# Phase 13 — AI đọc tin nhắn Zalo báo sản lượng của HTX

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Trạng thái (24/11/2026):** đã làm xong trong phiên này, chưa commit. Toàn bộ test qua.

**Goal:** Làm "Việc 1" theo góp ý của cô Hằng: người vận hành dán tin Zalo của HTX ("mai có 30 ký cải ngọt"), AI đọc thành dòng sản lượng nháp, người vận hành bấm xác nhận. Đo bằng số: AI đọc đúng bao nhiêu %, hơn cách dò từ khóa bao nhiêu, bao nhiêu tin phải chuyển cho người.

**Architecture:** `SupplyMessageRules` (Support, thuần PHP) là cách đơn giản và là phương án dự phòng. `SupplyMessageService` gọi Gemini với JSON schema, lỗi thì dùng cách đơn giản, rồi gắn lý do cần người xử lý cho từng dòng. `GeminiClient` gom phần gọi Gemini dùng chung với chatbot. Lệnh `supply:eval-messages` chấm hai cách trên bộ tin mẫu có đáp án.

**Tech Stack:** Laravel 13, PHP 8.3, Gemini (`gemini-flash-lite-latest`), Blade + Tailwind, PHPUnit.

**Spec:** Góp ý cô Hằng (cuộc trò chuyện 2026-11-24). Kết quả: `docs/BAO-CAO-GOP-Y-AI.md`, phần "Việc 1".

## Global Constraints

- AI chỉ đọc và đề xuất, không tự lưu. Mọi dòng phải được người vận hành bấm xác nhận (human-in-the-loop).
- Không có key hoặc Gemini lỗi ⇒ vẫn chạy bằng cách dò từ khóa; test luôn tắt key trừ khi giả lập `Http::fake`.
- Chỉ gửi nội dung tin nhắn và danh sách rau cho Gemini; không gửi tên, số điện thoại HTX hay dữ liệu khách.
- Bộ tin `test` không dùng để chỉnh câu lệnh hay luật dò; chỉnh trên phần `dev`.
- Chỉ commit khi người dùng bảo; chạy pint trước commit.

## Review Focus

1. Dấu phẩy thập phân ("22,5kg") không bị tách thành hai dòng.
2. "den" không dấu trùng "đến", "mot" trùng "một": không dùng làm từ khóa.
3. Dòng nào thiếu rau/kg/ngày, kg > 500, ngày đã qua hoặc xa hơn 7 ngày, AI không chắc, hay HTX chưa đăng ký loại rau ⇒ có lý do và không được tích sẵn "Lưu".
4. Nhập sai khi xác nhận ⇒ bản nháp vẫn còn (lưu bằng `session()->put`, xóa khi lưu xong).
5. Gemini trả 429/503 ⇒ thử lại 1 lần, vẫn lỗi thì dùng cách dò từ khóa.

---

### Task 1: Cách dò từ khóa và cách chấm điểm

**Files:**
- Create: `app/Support/SupplyMessageRules.php`, `app/Support/SupplyMessageScore.php`
- Test: `tests/Unit/Support/SupplyMessageRulesTest.php`

**Quyết định:**
- Tách dòng theo `, ; + xuống dòng`, " và/với/còn"; mỗi đoạn tìm tên rau (tên trong bảng `products` + tên gọi khác, so khi đã bỏ dấu, tên dài trước), số đầu tiên kèm đơn vị (kg/ký/kí/k; yến ×10; tạ ×100), ngày ("mai", "mốt" có dấu, "ngày kia", "nay", t2–t7/thứ hai…/cn, dd/mm).
- Chấm 3 số: **xử lý đúng** (tin đủ thông tin đọc đúng và tự đi tiếp, hoặc tin thiếu được chuyển người), **tự đi tiếp nhưng sai** (lỗi nguy hiểm), **chuyển người**; thêm "đọc đúng nội dung" trên tin đủ thông tin.

- [x] Test đọc 1 dòng, nhiều dòng không dấu, thứ/ngày, số thập phân, thiếu thông tin; test cách chấm.

### Task 2: Gọi Gemini dùng chung

**Files:**
- Create: `app/Services/GeminiClient.php`
- Modify: `app/Services/ChatbotService.php` (bỏ `askGemini()`, dùng `GeminiClient::generate()`)

- [x] Hành vi chatbot giữ nguyên (`tests/Feature/Orders/ChatbotTest.php` qua).
- [x] Thêm thử lại 1 lần khi 429/503.

### Task 3: Đọc tin bằng AI

**Files:**
- Create: `app/Services/SupplyMessageService.php`

**Interfaces:**
- `read(string $message, CarbonImmutable $now, bool $useAi = true): array{method: 'ai'|'rules', items: list<{product: ?code, kg: ?float, date: ?Y-m-d, unsure: bool}>}`
- `readWithRules()`, `readWithAi()` (null = AI lỗi), `reviewReason(array $item, CarbonImmutable $now, ?Farmer $farmer): ?string`, `MAX_KG = 500`.

**Quyết định:** `responseMimeType = application/json` + `responseSchema`, `temperature = 0`. Câu lệnh nêu ngày hôm nay, mã rau, quy tắc đơn vị/ngày, "thiếu thì để null, không đoán", "tầm/khoảng" là bình thường, mâu thuẫn hoặc "để coi lại" ⇒ `unsure`. Mã rau lạ ⇒ null.

### Task 4: Màn hình dán tin

**Files:**
- Modify: `app/Http/Controllers/Admin/SupplyController.php` (`read()`, `confirmMessage()`), `routes/web.php`, `resources/views/admin/supply/index.blade.php`
- Test: `tests/Feature/Farmer/SupplyMessageTest.php`

**Interfaces:** `POST /admin/supply/read-message` (farmer_id HTX đang hoạt động, message ≤ 1000 ký tự) ⇒ bản nháp trong session `parsed`; `POST /admin/supply/confirm-message` (rows[i][keep, product_id, kg, date]) ⇒ `SupplyReportService::save(..., SupplySource::Zalo)`.

- [x] Test không key ⇒ dò từ khóa; có AI (giả lập) ⇒ gắn lý do đúng; AI 503 ⇒ dự phòng; chỉ lưu dòng được tích; dòng tích phải đủ thông tin.

### Task 5: Bộ tin mẫu và lệnh chấm

**Files:**
- Create: `database/data/tin-nhan-zalo-mau.json` (60 tin: 20 `dev`, 40 `test`, có `handoff` cho tin thiếu/mâu thuẫn)
- Create: `app/Console/Commands/EvalSupplyMessages.php` (`supply:eval-messages --split=test|dev|all --details --delay=4`)

- [x] Kết quả phần `test` (24/11/2026 20:36): AI 37/40 xử lý đúng (92,5%), 0 tin tự đi sai, chuyển người 25%; dò từ khóa 33/40 (82,5%), 5 tin tự đi sai (12,5%), chuyển người 20%. AI có 2 tin lỗi 503 từ Google (tính là chuyển người).
- [x] Ghi chú trung thực: luật dò được sửa 1 lỗi (dấu phẩy thập phân) sau khi chạy phần `test` lần đầu (trước sửa: 80%).

## Việc còn mở

- [ ] Thay/bổ sung bộ tin bằng tin do người ngoài nhóm viết hoặc tin Zalo thật của HTX (có đồng ý), rồi chạy lại `supply:eval-messages` trước khi đưa số vào hồ sơ.
- [ ] Đo trên dùng thật: ghi số lần người vận hành sửa dòng AI đọc trước khi xác nhận (chưa có bảng lưu).

# FARM2HOME AI — Project context for Claude Code

> Điều phối nông sản theo nhu cầu, giảm dư thừa.
> Student startup project for **Business Challenge 2026 — "AI for Inclusive Growth and Sustainable Communities"** (field: sustainable & smart agriculture). Team of 4 from Trường Đại học Tiền Giang; I (Thịnh) am the only coder.

## 1. What this product is (read first)

FARM2HOME AI is **NOT** an online vegetable shop. It is a **demand → harvest coordination tool** for a short local supply chain of clean vegetables in **Mỹ Tho and surrounding areas (Đồng Tháp province, formerly Tiền Giang)**.

Core problem: buyers' demand and small farmers' harvest decisions are out of sync, so farmers over-harvest and vegetables get wasted. The system turns order data into an operational decision: **how many kg of each vegetable to harvest/purchase in the next 3–7 days**.

Closed data loop:
```
Pre-orders (Zalo / form / web) ─┐
Farmer supply reports ──────────┼─► Forecast (3–7 days) ─► Harvest suggestion ─► Operator confirms
Subscription baskets ───────────┘                                                   │
        ▲                                                                           ▼
   Feedback per batch ◄── QR traceability per batch ◄── Pack & deliver ◄── Farmer harvests
```

### Pitch principles the code MUST respect
- **Human-in-the-loop**: AI only *suggests*; an operator always reviews and confirms before anything is sent to farmers. Always store both `suggested_kg` and `final_kg`.
- **Inclusive design**: farmers and customers never need to install an app or create an account. Farmers use a tokenized link (`farmers.access_token`) shared via Zalo; customers are identified by phone number.
- **QR ≠ certification**: the QR page shows batch facts (farmer, growing area, harvest/process/pack times, storage guide, feedback form). Certification is shown **only** when `farmers.certificate_file` exists and is not expired. Never write marketing claims like "100% sạch" on the QR page.
- **"Just enough" tech**: simple, low-cost, works on mobile. No over-engineering.
- **No health/medical advice** in basket recommendations, only shopping suggestions.
- **Measurable**: every feature should feed the pilot KPIs (section 7).

## 2. Pilot scope (MVP target)
- 4 weeks in Mỹ Tho area · 2–3 farmer households · 20–30 customer households · 5–7 vegetable types
- Supply sources: prioritise farmers in Châu Thành, Cai Lậy, Chợ Gạo and nearby areas (sample data still uses Thân Cửu Nghĩa / Gò Công)
- Revenue: subscription baskets + single orders + delivery fee. Target is 500k–700k VND/day (an assumption to validate, not a fact)

## 3. Tech stack & environment
- **Laravel 13** (PHP ≥ 8.3) · **MySQL** · **Blade** + **Tailwind** (Vite) · **Chart.js** for dashboard
- QR: `endroid/qr-code` (or `simplesoftwareio/simple-qrcode`)
- Dev: **Windows + Laragon**, project at `E:\laragon\www\farm2home`, URL `http://farm2home.test`. Use the Laragon terminal (composer/php/node are on its PATH)
- Deploy target: Railway
- Timezone `Asia/Ho_Chi_Minh`, locale `vi`

## 4. Database

The schema and sample data come from **`database/farm2home.sql`**, which was imported directly (MySQL 8 / MariaDB). It has 24 tables: Laravel core (`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`) plus 19 domain tables.

⚠️ **Do NOT run `php artisan migrate:fresh`** or the default Laravel migrations: the tables already exist. If migrations are needed later, first delete the default `0001_01_01_*` migration files, then add **new** migration files only for schema changes. Set `QUEUE_CONNECTION=sync` (there is no `jobs` table).

Sample login: `admin@farm2home.vn` / `password` (role admin) · `operator@farm2home.vn` / `password` (role operator).
Sample data covers 2026-09-10 → 2026-10-03. Orders up to 10/01 are `delivered`, orders for 10/02–10/03 are `pending`/`confirmed`, and the harvest plan for 10/03 is `draft`.

### Tables by domain
| Domain | Tables |
|---|---|
| Master | `delivery_zones`, `farmers`, `products`, `farmer_products` (pivot + `typical_weekly_kg`), `baskets`, `basket_items` |
| Demand | `customers` (unique `phone`, `preferences` JSON), `subscriptions`, `orders`, `order_items` |
| Supply | `supply_reports` (unique farmer+product+available_date) |
| Coordination | `forecasts`, `harvest_plans` (unique `delivery_date`), `harvest_plan_items` |
| Traceability | `batches` (unique `code`), `order_item_batches` (pivot, composite PK) |
| Feedback / impact | `feedbacks`, `waste_logs`, `packaging_returns` |

### Key enums
- `users.role`: admin, operator
- `orders.status`: pending → confirmed → packing → delivering → delivered | cancelled
- `orders.order_type`: basket, single · `channel`: zalo, form, website, phone · `delivery_method`: home, pickup
- `orders.payment_method`: cod, bank_transfer · `payment_status`: unpaid, paid, refunded
- `subscriptions.frequency`: weekly, biweekly, monthly · `delivery_weekday` 1=Mon…7=Sun · `status`: active, paused, cancelled
- `harvest_plans.status`: draft → confirmed → sent → completed
- `forecasts.method`: moving_average, exp_smoothing, tree_regression, manual
- `batches.quality_status`: pending, passed, rejected
- `feedbacks.status`: new, processing, resolved
- `waste_logs.reason`: surplus, damaged, expired, discounted
- `farmers.status`: active, paused, inactive

### Data conventions
- Money: **integer VND** (no decimals). Display as `12.000đ`
- Weight: `DECIMAL` kg. `order_items.weight_kg = quantity × products.pack_weight_kg`
- `order_items.quantity` = number of packs. For basket orders, items have `basket_id` set with `unit_price = 0`, and the basket price goes into `orders.subtotal`
- `order_items.fulfilled_quantity` = packs actually delivered (used for the fill-rate KPI)
- Codes: order `DH{yymmdd}-{seq:03d}` (e.g. `DH261002-001`, by delivery date) · batch `F2H-{yymmdd}-{product.code}-{farmer.code}` (e.g. `F2H-260930-RM-NH01`). The batch code is the QR payload
- Product codes: RM rau muống, CN cải ngọt, CX cải bẹ xanh, MT mồng tơi, RD rau dền, XL xà lách, HL hành lá. Farmer codes: NH01…
- Shipping fee: from `delivery_zones.shipping_fee`, free when `subtotal >= free_ship_from`, and 0 for pickup zones

## 5. Core business logic

### Forecast (per product, per delivery date)
- Demand history = sum of `order_items.weight_kg` for non-cancelled orders, grouped by `orders.delivery_date` and `product_id`
- Baseline methods (pure PHP, no ML dependency yet):
  - **Moving average 7 days** plus a ± std-dev band → `lower_kg` / `upper_kg`
  - **Exponential smoothing**, α = 0.3
- Store each run in `forecasts`. After the delivery date, backfill `actual_kg` so we can compute **MAE / MAPE** and compare methods. A model is "better" only if it beats the baseline on real error.
- Tree regression comes later, as a separate Python service, only once enough data exists.

### Harvest suggestion
```
suggested_kg = ceil_to_0.5( max(0, confirmed_order_kg + forecast_extra_kg − available_stock_kg) × (1 + safety_margin_pct/100) )
```
- `confirmed_order_kg` = confirmed orders for that delivery date. `forecast_extra_kg` = max(0, predicted_total − confirmed). Default margin is 10%
- Allocate to farmers based on `supply_reports` for that date (the farmer with the most available supply first; split across farmers if one can't cover it)
- Operator edits `final_kg` and fills `adjust_reason` if it differs (weather, quality…), then confirms. After that, generate a short Zalo message per farmer (copy button / `https://zalo.me/{phone}` deep link). There is **no Zalo OA API** in the MVP
- On receiving: fill `received_kg`, create the `batches` row, link order items through `order_item_batches`
- At end of day: `received_kg − delivered demand` goes to `waste_logs` (surplus/discounted)

## 6. Actors & routes (planned)
| Actor | Auth | Main screens |
|---|---|---|
| Customer | none (phone) | Landing · Đặt hàng (basket/single, date, time slot, zone) · Tra cứu đơn by phone · `/lo/{code}` QR page + feedback form |
| Farmer | `access_token` link, e.g. `/nong-ho/{token}` | Báo sản lượng for the next days · View/confirm harvest list |
| Operator | login | Dashboard · Đơn hàng · Dự báo & Kế hoạch thu hoạch · Lô hàng & in tem QR · Feedback · Rau dư / bao bì |
| Admin | login | Everything above + CRUD products, baskets, farmers, zones, users |

Admin area under `/admin`, protected by `auth` + a role middleware.

### Priorities
- **P0 (pilot must-have):** order form → order management → farmer supply link → forecast + harvest plan → batches + QR page → feedback
- **P1:** KPI dashboard, subscriptions auto-generating orders, packaging returns
- **P2:** basket personalization, Zalo OA automation, ML model, PWA

## 7. Pilot KPIs the dashboard must show
- **Forecast:** MAE / MAPE per week & per product, number of operator adjustments (`final_kg ≠ suggested_kg`)
- **Customers:** fill rate (Σfulfilled / Σquantity), repeat-purchase rate, feedback count and resolution, avg rating
- **Economics:** revenue, avg order value, gross margin (price vs `cost_price_per_kg`), delivery cost per order
- **Farmers:** number of active farmers, % of volume confirmed in advance
- **Environment:** kg of surplus/waste per week (should trend down after forecasting starts on 09/17), packaging return & reuse rate
- Do not claim CO₂ reductions; we don't measure transport emissions.

## 8. Coding conventions
- **UI language: Vietnamese.** Code, identifiers, and comments in English (Vietnamese comments are OK for business rules)
- Mobile-first: customers and farmers use phones. Large tap targets, minimal typing
- Eloquent models with explicit relationships, `$casts` for dates/JSON/decimals, enums as PHP backed enums in `app/Enums`
- Business logic in service classes (`app/Services/ForecastService.php`, `HarvestPlanService.php`, `BatchService.php`), not in controllers
- Form Requests for validation. Validate Vietnamese phone numbers (`^0\d{9}$`)
- Wrap money/stock operations in DB transactions
- Collect minimal customer data; respect `customers.data_consent`
- **When editing existing files, make targeted diff-style edits; don't rewrite whole files.**
- Keep the stack simple: no SPA framework, Blade + a little Alpine.js/vanilla JS is enough

## 9. Open questions (confirm with team before hardcoding)
- Final list of 5–7 vegetables, pack sizes and prices (sample data is placeholder)
- Basket contents and prices (sample: Giỏ Mini 50k, Giỏ Xanh Tiết Kiệm 70k, Giỏ Gia Đình 115k)
- Real delivery zones / pickup point and fees
- Real farmer info and certificates (NH02's VietGAP in the sample data is fake, for UI testing only)

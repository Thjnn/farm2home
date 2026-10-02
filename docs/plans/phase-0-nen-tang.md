# Phase 0 — Nền tảng: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Đưa bộ khung Laravel về đúng cấu hình của spec, có git, có schema dựng lại được trên DB trống, có DB test và seeder dữ liệu mẫu.

**Architecture:** Schema không viết lại bằng Schema builder mà đọc thẳng phần DDL của `database/farm2home.sql` qua class `App\Support\SqlFile`. Migration baseline tự bỏ qua khi DB đã import sẵn.

**Tech Stack:** Laravel 13 · MySQL 8.4 · Vite 8 · Tailwind 4 · Alpine.js.

**Spec:** [`docs/CLAUDE.md`](../CLAUDE.md) mục 3, 4 · Kế hoạch tổng: [`docs/PLAN.md`](../PLAN.md) (đọc "Global Constraints" và quyết định D1, D2 trước).

## Global Constraints

Xem `docs/PLAN.md`. Riêng phase này: **không chạy `migrate:fresh` trên DB `farm2home`**; mọi lệnh chạy trong Laragon Terminal, Laragon đang Start.

---

### Task 0.1: Git, cấu hình môi trường, frontend

**Files:**
- Modify: `.env`, `.env.example`, `config/app.php` (dòng `'timezone'`), `config/database.php` (connection `mysql`), `resources/js/app.js`, `resources/css/app.css`, `vite.config.js`, `CLAUDE.md` (gốc)
- Create: `config/farm2home.php`
- Test: `tests/Feature/ConfigTest.php`

**Interfaces:**
- Produces: `config('farm2home.safety_margin_pct')` (int 10), `config('farm2home.forecast.{default_method,window_days,alpha,horizon_days,history_days}')`, `config('farm2home.order.{cutoff_hour,max_days_ahead,time_slots}')`, `config('farm2home.batch.harvest_time')`.

- [ ] **Step 1: Khởi tạo git và commit hiện trạng**

```bash
git init
git add -A
git commit -m "chore: laravel skeleton, project brief and sample database"
```

Kiểm tra `git status` không liệt kê `.env`, `vendor/`, `node_modules/`.

- [ ] **Step 2: Viết test cấu hình (sẽ fail)**

`tests/Feature/ConfigTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_app_uses_vietnam_timezone_and_locale(): void
    {
        $this->assertSame('Asia/Ho_Chi_Minh', config('app.timezone'));
        $this->assertSame('vi', config('app.locale'));
        $this->assertSame('+07:00', config('database.connections.mysql.timezone'));
    }

    public function test_business_defaults_match_the_spec(): void
    {
        $this->assertSame(10, config('farm2home.safety_margin_pct'));
        $this->assertSame('moving_average', config('farm2home.forecast.default_method'));
        $this->assertSame(7, config('farm2home.forecast.window_days'));
        $this->assertSame(0.3, config('farm2home.forecast.alpha'));
        $this->assertSame(7, config('farm2home.forecast.horizon_days'));
        $this->assertSame(18, config('farm2home.order.cutoff_hour'));
        $this->assertSame(['06:00-08:00', '10:00-12:00', '17:00-19:00'], config('farm2home.order.time_slots'));
    }
}
```

- [ ] **Step 3: Chạy test, xác nhận fail**

Run: `php artisan test --filter=ConfigTest`
Expected: FAIL (`'UTC'` khác `'Asia/Ho_Chi_Minh'`, `config('farm2home…')` trả `null`).

- [ ] **Step 4: Sửa `.env` và `.env.example`**

Đổi đúng các dòng sau trong cả hai file (các dòng khác giữ nguyên):

```dotenv
APP_URL=http://farm2home.test
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_LOCALE=vi
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=vi_VN
QUEUE_CONNECTION=sync
```

Trong `.env.example` đặt thêm `DB_CONNECTION=mysql`, `DB_DATABASE=farm2home`, `DB_USERNAME=root`, `DB_PASSWORD=` (bỏ comment nếu đang bị comment).

- [ ] **Step 5: Sửa `config/app.php` và `config/database.php`**

`config/app.php`, thay dòng `'timezone' => 'UTC',` bằng:

```php
    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
```

`config/database.php`, trong mảng `'mysql' => [ ... ]`, thêm ngay sau dòng `'collation' => ...`:

```php
            'timezone' => '+07:00',
```

- [ ] **Step 6: Tạo `config/farm2home.php`**

```php
<?php

return [
    // Biên an toàn mặc định (%) khi gợi ý thu hoạch.
    'safety_margin_pct' => 10,

    'forecast' => [
        'default_method' => 'moving_average',
        'window_days' => 7,
        'alpha' => 0.3,
        'horizon_days' => 7,
        'history_days' => 28,
    ],

    'order' => [
        // Đơn cho ngày mai đóng lúc 18:00 hôm nay.
        'cutoff_hour' => 18,
        'max_days_ahead' => 7,
        'time_slots' => ['06:00-08:00', '10:00-12:00', '17:00-19:00'],
    ],

    'batch' => [
        'harvest_time' => '05:30',
    ],
];
```

- [ ] **Step 7: Chạy test, xác nhận pass**

Run: `php artisan config:clear && php artisan test --filter=ConfigTest`
Expected: PASS (2 tests).

- [ ] **Step 8: Cài frontend**

```bash
npm install
npm install alpinejs chart.js
```

Thay toàn bộ `resources/js/app.js` (hiện chỉ có `//`) bằng:

```js
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
```

Trong `vite.config.js`, đổi font sang loại có đủ dấu tiếng Việt:

```js
                bunny('Be Vietnam Pro', {
                    weights: [400, 500, 600, 700],
                }),
```

Trong `resources/css/app.css`, đổi `'Instrument Sans'` thành `'Be Vietnam Pro'` trong `--font-sans`, và thêm dòng `@source '../views/**/*.blade.php';` ngay dưới hai dòng `@source` hiện có.

Run: `npm run build`
Expected: build thành công, tạo `public/build/manifest.json`.

- [ ] **Step 9: Cài Laravel Boost và nối bản mô tả dự án**

```bash
composer require laravel/boost --dev
php artisan boost:install
```

Sau đó mở `CLAUDE.md` ở gốc, thêm dòng sau ở **đầu file, ngoài thẻ `<laravel-boost-guidelines>`**:

```markdown
@docs/CLAUDE.md
```

- [ ] **Step 10: Kiểm tra và commit**

Run: `php artisan about`
Expected: mục Environment hiện `Timezone .... Asia/Ho_Chi_Minh`, `Locale .... vi`, `URL .... farm2home.test`.

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "chore: align env, timezone, locale and frontend tooling with project brief"
```

---

### Task 0.2: Schema baseline, DB test, seeder dữ liệu mẫu

**Files:**
- Delete: `database/migrations/0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`
- Create: `app/Support/SqlFile.php`, `database/migrations/2026_10_02_000000_create_farm2home_schema.php`, `database/seeders/DemoSeeder.php`
- Modify: `phpunit.xml` (2 dòng `DB_*`), `database/seeders/DatabaseSeeder.php`
- Test: `tests/Unit/Support/SqlFileTest.php`, `tests/Feature/SchemaTest.php`

**Interfaces:**
- Produces:
  - `App\Support\SqlFile::schemaStatements(string $path): array` — danh sách câu DDL (49 câu với file hiện tại).
  - `App\Support\SqlFile::sampleStatements(string $path): array` — danh sách câu nạp dữ liệu mẫu (26 câu).
  - `Database\Seeders\DemoSeeder` — nạp toàn bộ dữ liệu mẫu vào DB trống.
  - Mọi feature test từ đây dùng `Illuminate\Foundation\Testing\RefreshDatabase` trên DB `farm2home_test`.

- [ ] **Step 1: Tạo DB test**

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS farm2home_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

- [ ] **Step 2: Trỏ PHPUnit sang MySQL test**

Trong `phpunit.xml`, thay hai dòng:

```xml
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
```

bằng:

```xml
        <env name="DB_CONNECTION" value="mysql"/>
        <env name="DB_DATABASE" value="farm2home_test"/>
```

- [ ] **Step 3: Viết unit test cho `SqlFile` (sẽ fail)**

`tests/Unit/Support/SqlFileTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\SqlFile;
use PHPUnit\Framework\TestCase;

class SqlFileTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = dirname(__DIR__, 3).'/database/farm2home.sql';
    }

    public function test_schema_section_contains_only_ddl_for_24_tables(): void
    {
        $statements = SqlFile::schemaStatements($this->path);

        $this->assertCount(24, array_filter($statements, fn ($s) => str_starts_with($s, 'CREATE TABLE')));
        $this->assertCount(24, array_filter($statements, fn ($s) => str_starts_with($s, 'DROP TABLE IF EXISTS')));
        $this->assertSame([], array_filter($statements, fn ($s) => str_starts_with($s, 'INSERT')));
        $this->assertSame([], array_filter($statements, fn ($s) => str_contains($s, 'CREATE DATABASE') || str_starts_with($s, 'USE ')));
    }

    public function test_sample_section_contains_24_inserts(): void
    {
        $statements = SqlFile::sampleStatements($this->path);

        $this->assertCount(24, array_filter($statements, fn ($s) => str_starts_with($s, 'INSERT INTO')));
        $this->assertSame('SET FOREIGN_KEY_CHECKS = 0', $statements[0]);
        $this->assertSame('SET FOREIGN_KEY_CHECKS = 1', end($statements));
    }

    public function test_statements_have_no_comment_lines_or_trailing_semicolons(): void
    {
        foreach ([...SqlFile::schemaStatements($this->path), ...SqlFile::sampleStatements($this->path)] as $statement) {
            $this->assertStringStartsNotWith('--', $statement);
            $this->assertStringEndsNotWith(';', $statement);
        }
    }
}
```

- [ ] **Step 4: Chạy test, xác nhận fail**

Run: `php artisan test --filter=SqlFileTest`
Expected: FAIL với `Class "App\Support\SqlFile" not found`.

- [ ] **Step 5: Viết `app/Support/SqlFile.php`**

```php
<?php

namespace App\Support;

/**
 * Splits database/farm2home.sql into executable statements.
 * The file has two sections separated by marker comments.
 */
final class SqlFile
{
    private const SCHEMA_START = '-- 0. Laravel core tables';

    private const SAMPLE_START = '-- SAMPLE DATA';

    /** @return list<string> */
    public static function schemaStatements(string $path): array
    {
        $sql = self::read($path);
        $start = strpos($sql, self::SCHEMA_START);
        $end = strpos($sql, self::SAMPLE_START);

        return self::split(substr($sql, $start, $end - $start));
    }

    /** @return list<string> */
    public static function sampleStatements(string $path): array
    {
        $sql = self::read($path);

        return self::split(substr($sql, strpos($sql, self::SAMPLE_START)));
    }

    private static function read(string $path): string
    {
        // Git on Windows may convert line endings to CRLF.
        return str_replace("\r\n", "\n", file_get_contents($path));
    }

    /** @return list<string> */
    private static function split(string $sql): array
    {
        $sql = preg_replace('/^--.*$/m', '', $sql);

        return array_values(array_filter(array_map('trim', explode(";\n", $sql."\n"))));
    }
}
```

- [ ] **Step 6: Chạy test, xác nhận pass**

Run: `php artisan test --filter=SqlFileTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Viết feature test cho schema và seeder (sẽ fail)**

`tests/Feature/SchemaTest.php`:

```php
<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tests_never_run_against_the_dev_database(): void
    {
        $this->assertSame('farm2home_test', DB::connection()->getDatabaseName());
    }

    public function test_baseline_migration_creates_all_24_tables(): void
    {
        foreach ([
            'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks',
            'delivery_zones', 'farmers', 'products', 'farmer_products', 'baskets', 'basket_items',
            'customers', 'subscriptions', 'orders', 'order_items', 'supply_reports',
            'forecasts', 'harvest_plans', 'harvest_plan_items', 'batches', 'order_item_batches',
            'feedbacks', 'waste_logs', 'packaging_returns',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }
    }

    public function test_demo_seeder_loads_the_sample_data(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(155, DB::table('orders')->count());
        $this->assertSame(466, DB::table('order_items')->count());
        $this->assertSame(146, DB::table('batches')->count());
        $this->assertSame(45, DB::table('feedbacks')->count());
        $this->assertSame('Rau muống', DB::table('products')->where('code', 'RM')->value('name'));
    }
}
```

- [ ] **Step 8: Chạy test, xác nhận fail**

Run: `php artisan test --filter=SchemaTest`
Expected: FAIL (`Class "Database\Seeders\DemoSeeder" not found`; bảng nghiệp vụ chưa có vì migration mặc định chỉ tạo `users`, `cache`, `jobs`).

- [ ] **Step 9: Xóa 3 migration mặc định, tạo migration baseline**

```bash
git rm database/migrations/0001_01_01_000000_create_users_table.php database/migrations/0001_01_01_000001_create_cache_table.php database/migrations/0001_01_01_000002_create_jobs_table.php
```

`database/migrations/2026_10_02_000000_create_farm2home_schema.php`:

```php
<?php

use App\Support\SqlFile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DB dev đã import farm2home.sql: chỉ ghi nhận migration, không đụng bảng.
        if (Schema::hasTable('orders')) {
            return;
        }

        DB::unprepared('SET FOREIGN_KEY_CHECKS = 0');

        foreach (SqlFile::schemaStatements(database_path('farm2home.sql')) as $statement) {
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        // Baseline schema: intentionally not reversible.
    }
};
```

- [ ] **Step 10: Viết `database/seeders/DemoSeeder.php` và nối vào `DatabaseSeeder`**

```php
<?php

namespace Database\Seeders;

use App\Support\SqlFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('orders')->exists()) {
            throw new RuntimeException('DemoSeeder chỉ chạy trên DB trống (bảng orders đã có dữ liệu).');
        }

        foreach (SqlFile::sampleStatements(database_path('farm2home.sql')) as $statement) {
            DB::unprepared($statement);
        }
    }
}
```

Trong `database/seeders/DatabaseSeeder.php`, thay toàn bộ thân hàm `run()` bằng:

```php
        $this->call(DemoSeeder::class);
```

và xóa dòng `use App\Models\User;` không còn dùng.

- [ ] **Step 11: Chạy test, xác nhận pass**

Run: `php artisan test --filter=SchemaTest`
Expected: PASS (3 tests).

- [ ] **Step 12: Ghi nhận baseline trên DB dev mà không đụng dữ liệu**

Run: `php artisan migrate`
Expected: tạo bảng `migrations`, chạy `2026_10_02_000000_create_farm2home_schema` ... DONE.

Run: `php artisan tinker --execute="echo DB::table('orders')->count();"`
Expected: `155` (dữ liệu import vẫn nguyên).

- [ ] **Step 13: Chạy toàn bộ test và commit**

Run: `php artisan test`
Expected: PASS toàn bộ (gồm `ExampleTest` có sẵn).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: baseline schema migration from farm2home.sql, mysql test database, demo seeder"
```

---

## Kết quả Phase 0

- [ ] `php artisan test` xanh.
- [ ] `php artisan migrate` trên DB trống dựng đủ 24 bảng; trên DB dev không thay đổi gì.
- [ ] `php artisan db:seed` trên DB trống nạp đủ dữ liệu mẫu.
- [ ] `npm run build` thành công.

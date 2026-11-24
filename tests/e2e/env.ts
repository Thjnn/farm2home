import { execFileSync } from 'node:child_process';

// Laragon không đưa php lên PATH: đặt PHP_BIN nếu máy khác đường dẫn.
export const PHP = process.env.PHP_BIN ?? 'E:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

// Biến môi trường thật ưu tiên hơn .env, nên server và lệnh artisan đều trỏ vào DB *_e2e.
export const e2eEnv: Record<string, string> = {
    ...(process.env as Record<string, string>),
    APP_URL: 'http://127.0.0.1:8010',
    DB_DATABASE: 'farm2home_e2e',
    GEMINI_API_KEY: '',
};

/** Dựng lại DB e2e với đúng số món / giỏ đang bán (lệnh tự từ chối nếu không phải DB *_e2e). */
export function resetCatalog(products = 30, baskets = 10): void {
    execFileSync(PHP, ['artisan', 'e2e:reset', `--products=${products}`, `--baskets=${baskets}`], { env: e2eEnv, stdio: 'pipe' });
}

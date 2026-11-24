import { defineConfig, devices } from '@playwright/test';
import { e2eEnv, PHP } from './tests/e2e/env';

// Test chạy trên server PHP riêng + DB farm2home_e2e (không đụng DB dev, không gửi đơn thật).
// Cần `npm run build` trước (dùng public/build).
// ponytail: mọi project chạy Chromium (giả lập kích thước + UA của máy); thêm webkit khi gặp lỗi riêng iOS.
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    reporter: [['list']],
    timeout: 60_000,
    use: {
        baseURL: e2eEnv.APP_URL,
        trace: 'retain-on-failure',
    },
    webServer: {
        // server.php của Laravel tìm index.php theo thư mục hiện tại, nên chạy từ public/.
        command: `"${PHP}" -S 127.0.0.1:8010 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: 'public',
        url: `${e2eEnv.APP_URL}/up`,
        env: e2eEnv,
        reuseExistingServer: true,
        timeout: 60_000,
    },
    projects: [
        { name: 'iphone-13', use: { ...devices['iPhone 13'], browserName: 'chromium' } },
        { name: 'pixel-7', use: { ...devices['Pixel 7'] } },
        { name: 'galaxy-s', use: { ...devices['Galaxy S9+'], viewport: { width: 360, height: 800 }, browserName: 'chromium' } },
        { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } } },
    ],
});

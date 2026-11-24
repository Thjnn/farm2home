import { test } from '@playwright/test';

// Ảnh trước/sau chụp trên site dev (dữ liệu thật) để so cùng dữ liệu: SHOTS=before|after.
const folder = process.env.SHOTS ?? 'after';
const site = process.env.SHOTS_BASE_URL ?? 'http://farm2home-ui.test';

for (const [name, path] of [['home', '/'], ['rau', '/rau'], ['dat-hang', '/dat-hang']] as const) {
    test(`screenshot ${name}`, async ({ page }, info) => {
        test.skip(!process.env.SHOTS, 'Chỉ chạy khi đặt SHOTS=before|after');
        await page.goto(site + path);
        await page.screenshot({ path: `tests/screenshots/${folder}/${info.project.name}-${name}.png`, fullPage: true });
    });
}

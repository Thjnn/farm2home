import { expect, test, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { resetCatalog } from './env';

const productCards = (page: Page) => page.locator('section[aria-labelledby="products-heading"] [data-card]');
const basketCards = (page: Page) => page.locator('section[aria-labelledby="baskets-heading"] [data-card]');
const productPager = (page: Page) => page.getByRole('navigation', { name: 'Trang rau đang có' });
const basketPager = (page: Page) => page.getByRole('navigation', { name: 'Trang giỏ rau' });
const names = async (page: Page) => productCards(page).locator('p.truncate').allTextContents();

async function expectNoHorizontalOverflow(page: Page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

/** Mọi nút/link tương tác (trừ link nằm trong đoạn văn) phải ≥ 44×44px. */
async function expectTapTargets(page: Page) {
    const small = await page.evaluate(() =>
        [...document.querySelectorAll<HTMLElement>('a, button, select, input:not([type=hidden]), textarea, [role=button]')]
            .filter((el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden' && !el.closest('p'))
            .filter((el) => !el.matches('.sr-only') && !el.closest('label'))
            .map((el) => ({ el, rect: el.getBoundingClientRect() }))
            .filter(({ rect }) => rect.width < 44 || rect.height < 44)
            .map(({ el, rect }) => `${el.tagName} "${(el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 30)}" ${Math.round(rect.width)}x${Math.round(rect.height)}`),
    );
    expect(small).toEqual([]);
}

test.describe('trang chủ, 30 món và 10 giỏ', () => {
    test.beforeAll(() => resetCatalog(30, 10));

    test('không tràn ngang, nút đủ lớn, không lỗi axe critical', async ({ page }) => {
        await page.goto('/');
        await expect(productCards(page)).toHaveCount(6);
        await expectNoHorizontalOverflow(page);
        await expectTapTargets(page);
        const { violations } = await new AxeBuilder({ page }).analyze();
        expect(violations.filter((v) => v.impact === 'critical').map((v) => v.id)).toEqual([]);
    });

    test('rau: 6 món mỗi trang, sang trang 2, tải lại, Back', async ({ page }) => {
        await page.goto('/');
        await expect(productCards(page)).toHaveCount(6);
        await expect(productPager(page)).toBeVisible();
        const first = await names(page);

        await productPager(page).getByRole('button', { name: 'Trang 2' }).click();
        await expect(page).toHaveURL(/[?&]trang=2(&|$)/);
        await expect(productPager(page).getByRole('button', { name: 'Trang 2' })).toHaveAttribute('aria-current', 'page');
        await expect(productCards(page)).toHaveCount(6);
        const second = await names(page);
        expect(second.filter((name) => first.includes(name))).toEqual([]);

        await page.reload();
        await expect(productCards(page)).toHaveCount(6);
        expect(await names(page)).toEqual(second);

        await page.goBack();
        await expect(page).not.toHaveURL(/trang=/);
        await expect.poll(() => names(page)).toEqual(first);
    });

    test('giỏ: 4 giỏ mỗi trang, phân trang độc lập với rau', async ({ page }) => {
        await page.goto('/?trang=2');
        await expect(basketCards(page)).toHaveCount(4);
        const productsOnPage2 = await names(page);

        await basketPager(page).getByRole('button', { name: 'Trang 2' }).click();
        await expect(page).toHaveURL(/trang_gio=2/);
        await expect(page).toHaveURL(/[?&]trang=2(&|$)/);
        await expect(basketCards(page)).toHaveCount(4);
        expect(await names(page)).toEqual(productsOnPage2);

        await productPager(page).getByRole('button', { name: 'Trang 3' }).click();
        await expect(page).toHaveURL(/[?&]trang=3(&|$)/);
        await expect(page).toHaveURL(/trang_gio=2/);
        await expect(basketPager(page).getByRole('button', { name: 'Trang 2' })).toHaveAttribute('aria-current', 'page');
    });

    test('nút "Đặt giỏ này" mở trang đặt hàng với giỏ đó', async ({ page }) => {
        await page.goto('/');
        const link = basketCards(page).first().getByRole('link', { name: 'Đặt giỏ này' });
        await expect(link).toHaveAttribute('href', /\/dat-hang\?goi=\d+$/);
    });

    test('?trang=99 về trang cuối, không lỗi', async ({ page }) => {
        const response = await page.goto('/?trang=99');
        expect(response?.status()).toBe(200);
        await expect(productPager(page).getByRole('button', { name: 'Trang 5' })).toHaveAttribute('aria-current', 'page');
        await expect(page).toHaveURL(/[?&]trang=5(&|$)/);
        await expect(productCards(page)).toHaveCount(6);
        await expect(productPager(page).getByRole('button', { name: 'Trang sau' })).toBeDisabled();
    });
});

test.describe('trang chủ, chỉ 5 món', () => {
    test.beforeAll(() => resetCatalog(5, 4));

    test('không có thanh phân trang', async ({ page }) => {
        await page.goto('/');
        await expect(productCards(page)).toHaveCount(5);
        await expect(productPager(page)).toBeHidden();
        await expect(basketPager(page)).toBeHidden();
    });
});

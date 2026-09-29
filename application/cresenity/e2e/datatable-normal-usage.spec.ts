import { test, expect } from '@playwright/test';

test('ajax datatable normal: render, sort, search, pagination tidak error', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (msg) => { if (msg.type() === 'error') errors.push(msg.text()); });

    await page.goto('/demo/elements/table/action');
    await page.waitForSelector('table.dataTable tbody tr', { timeout: 15000 });

    const rowCountInitial = await page.locator('table.dataTable tbody tr').count();
    expect(rowCountInitial).toBeGreaterThan(0);

    // sort by clicking a sortable header
    const firstHeader = page.locator('table.dataTable thead th').first();
    await firstHeader.click();
    await page.waitForTimeout(1000);

    // search, kalau ada input pencarian datatable bawaan
    const searchInput = page.locator('.dataTables_filter input');
    if (await searchInput.count() > 0) {
        await searchInput.fill('a');
        await page.waitForTimeout(1000);
        await searchInput.fill('');
        await page.waitForTimeout(1000);
    }

    // pagination, kalau ada halaman berikutnya
    const nextBtn = page.locator('.paginate_button.next:not(.disabled)');
    if (await nextBtn.count() > 0) {
        await nextBtn.first().click();
        await page.waitForTimeout(1000);
    }

    expect(errors, 'errors: ' + JSON.stringify(errors)).toEqual([]);
});

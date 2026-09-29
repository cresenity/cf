import { test, expect } from '@playwright/test';

const PAGE = '/demo/elements/table/reloadbug';

test('reload ajax datatable saat data fetch pertama masih in-flight tidak melempar error DataTables', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (msg) => {
        if (msg.type() === 'error') errors.push(msg.text());
    });

    await page.goto(PAGE);
    // JANGAN tunggu tabel pertama selesai fetch data (sengaja dibuat lambat 2s
    // di server) - klik reload sesegera mungkin supaya request data lama masih
    // in-flight saat kontainernya diganti.
    await page.waitForSelector('text=Reload Table (repro)');
    await page.click('text=Reload Table (repro)');
    // beri jeda kecil lalu reload lagi, supaya reload KEDUA pun overlap dengan
    // proses render reload PERTAMA yang juga masih menunggu data lambat
    await page.waitForTimeout(300);
    await page.click('text=Reload Table (repro)');

    // tunggu cukup lama supaya seluruh request lambat (2s tiap tabel) selesai
    await page.waitForTimeout(6000);

    const relevant = errors.filter((e) => /oScroll|DataTable|Cannot read propert/i.test(e));
    expect(relevant, 'errors: ' + JSON.stringify(errors)).toEqual([]);
});

import { test, expect, Page } from '@playwright/test';

// Plugin x-autonumeric (media/js/cres/src/alpine/autonumeric.js) di halaman demo
// master-detail. Dua regresi yang pernah lolos ke produksi ohayomart 2026-09-18:
// - baris x-for dihapus di tick yang sama dengan perubahan nilainya -> autoNumeric('set')
//   pada elemen yang sudah di-destroy melempar dan mematikan reaktivitas halaman;
// - nilai yang diketik user ditimpa lagi oleh _x_bindings.value yang basi, sehingga
//   harga kembali ke nilai awal dan subtotal tidak berubah.
const PAGE = '/demo/cresjs/alpine/masterDetail';
const DATA = 'Alpine.$data(document.querySelector("#item-editor"))';

const seed = (n: number) => [...Array(n).keys()].map(i => ({
    id: i + 1, name: `item ${i + 1}`, price: (i + 1) * 1000, qty: i + 1, subtotal: (i + 1) * (i + 1) * 1000,
}));

async function open(page: Page, rows: number) {
    const errors: string[] = [];
    page.on('pageerror', e => errors.push('pageerror: ' + e.message));
    page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
    await page.goto(PAGE);
    // Alpine baru ada setelah cres.js selesai init (bukan sekadar setelah network idle)
    await page.waitForFunction(() => (window as any).Alpine && (window as any).Alpine.$data(document.querySelector('#item-editor')));
    await page.evaluate(([d, items]) => { eval(d as string).items = items; }, [DATA, seed(rows)] as const);
    await expect(page.locator('#item-editor tr.product-voa-item')).toHaveCount(rows);
    return errors;
}

test('harga yang diketik user masuk ke model dan subtotal ikut dihitung ulang', async ({ page }) => {
    const errors = await open(page, 1);
    await expect(page.locator('#price_0')).toHaveValue('1,000.00');
    await expect(page.locator('#subtotal_0')).toHaveValue('1,000.00');

    await page.fill('#price_0', '14918.91');
    await page.press('#price_0', 'Tab');

    await expect(page.locator('#price_0')).toHaveValue('14,918.91');
    await expect(page.locator('#subtotal_0')).toHaveValue('14,918.91');
    const model = await page.evaluate(d => eval(d).items[0], DATA);
    expect(String(model.price)).toBe('14918.91');
    expect(errors).toEqual([]);
});

test('menghapus baris sambil mengubah nilainya tidak melempar dan halaman tetap reaktif', async ({ page }) => {
    const errors = await open(page, 5);
    const rows = () => page.locator('#item-editor tr.product-voa-item');
    await expect(rows()).toHaveCount(5);

    // hapus baris tengah
    await page.evaluate(d => { eval(d).items.splice(1, 1); }, DATA);
    await expect(rows()).toHaveCount(4);

    // ubah nilai objek yang bergeser + hapus baris terakhir dalam satu tick (effect antre, elemen dibersihkan)
    await page.evaluate(d => { const x = eval(d); x.items[x.items.length - 1].price = 12345; x.items.splice(x.items.length - 1, 1); }, DATA);
    await expect(rows()).toHaveCount(3);

    // hapus lalu ubah objek yang tadinya terikat ke baris terhapus, tick berikutnya
    await page.evaluate(d => { eval(d).items.splice(0, 1); }, DATA);
    await page.evaluate(d => { eval(d).items.forEach((i: { price: number }) => { i.price = 777; }); }, DATA);
    await expect(rows()).toHaveCount(2);
    await expect(page.locator('#price_0')).toHaveValue('777.00');

    // kosongkan semua lalu isi ulang - reaktivitas harus masih hidup
    await page.evaluate(d => { const x = eval(d); const all = x.items.slice(); x.items = []; x.items = all; }, DATA);
    await expect(rows()).toHaveCount(2);

    expect(errors).toEqual([]);
});

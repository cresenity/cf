import { test, expect } from '@playwright/test';

// Dedup bundel tema (media/js/cres/src/CF.js, isAssetTagPresent/compiledBundleKey).
// Regresi yang lolos ke produksi dua kali (tribelio 2026-09-18 'TBCommentLight has already
// been declared', ohayomart 2026-09-22 "Can't create duplicate variable: 'Enterprise'"):
// dengan assets.*.compile menyala, deploy mengganti folder rilis bundel; tab yang masih
// terbuka lalu menerima daftar assets ajax berisi URL bundel baru, dan karena path-nya
// beda cres.js menyuntikkannya lagi - seluruh bundel tema dieksekusi dua kali.
// Halaman uji: ohayomart dev, satu-satunya dev vhost yang compile-nya menyala.
const PAGE = 'https://ohayomart.dev.cresenity.com/shop/products';

const BUNDLE = /\/compiled\/asset\/js\/([^/]+)\/([0-9a-f]{32}\.js)/;

test('bundel yang sama dari folder rilis lain dianggap sudah termuat, bukan disuntik ulang', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto(PAGE);
    await page.waitForFunction(() => (window as any).cresenity && (window as any).cresenity.cf);

    const current = await page.evaluate(() => {
        const tag = Array.from(document.querySelectorAll('script[src]')).find(s => /\/compiled\/asset\/js\//.test((s as HTMLScriptElement).src));
        return tag ? (tag as HTMLScriptElement).src : null;
    });
    expect(current, 'halaman harus menyajikan bundel tema (compile menyala)').toMatch(BUNDLE);

    const before = await page.evaluate(() => document.querySelectorAll('script[src]').length);

    // Rilis "baru": folder lain, md5 daftar berkas sama - persis yang dikirim respons ajax
    // sesudah deploy ke tab yang dibuka sebelum deploy.
    const sameBundleNewRelease = current!.replace(BUNDLE, '/compiled/asset/js/0000deadbeef/$2');
    await page.evaluate((url) => (window as any).cresenity.cf.requireJsAsync(url), sameBundleNewRelease);

    const after = await page.evaluate(() => document.querySelectorAll('script[src]').length);
    expect(after, 'tidak boleh ada tag <script> baru').toBe(before);
    expect(errors, 'tidak boleh ada error halaman').toEqual([]);
});

test('bundel dengan daftar berkas berbeda tetap dimuat (dedup tidak terlalu longgar)', async ({ page }) => {
    await page.goto(PAGE);
    await page.waitForFunction(() => (window as any).cresenity && (window as any).cresenity.cf);

    const current = await page.evaluate(() => {
        const tag = Array.from(document.querySelectorAll('script[src]')).find(s => /\/compiled\/asset\/js\//.test((s as HTMLScriptElement).src));
        return tag ? (tag as HTMLScriptElement).src : null;
    });
    expect(current).toMatch(BUNDLE);

    const before = await page.evaluate(() => document.querySelectorAll('script[src]').length);

    // md5 lain = himpunan berkas lain: harus dianggap belum ada. URL-nya tidak ada di server
    // (404), jadi hasil load-nya tidak ditunggu - yang diuji hanya keputusan menyuntik tag.
    const otherBundle = current!.replace(BUNDLE, '/compiled/asset/js/$1/ffffffffffffffffffffffffffffffff.js');
    await page.evaluate((url) => {
        (window as any).cresenity.cf.requireJsAsync(url);
    }, otherBundle);
    await page.waitForFunction((n) => document.querySelectorAll('script[src]').length > n, before, { timeout: 5_000 });

    const after = await page.evaluate(() => document.querySelectorAll('script[src]').length);
    expect(after).toBe(before + 1);
});

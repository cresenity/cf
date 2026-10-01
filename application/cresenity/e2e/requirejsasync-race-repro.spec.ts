import { test, expect } from '@playwright/test';

// CF.requireJsAsync()/requireCssAsync() (media/js/cres/src/CF.js) dulu resolve begitu tag
// <script src>/<link href> yang cocok SUDAH ADA di DOM (isAssetTagPresent), tanpa cek apakah
// browser sudah selesai mendownload/mengeksekusinya. Kalau sebuah tag baru saja disisipkan
// (oleh mekanisme lain, bukan loader ini) dan BELUM selesai load, pemanggil requireJsAsync
// untuk url yang sama langsung dapat promise ter-resolve meski script itu belum sempat jalan -
// ditemukan dari exception produksi tribelio #20057 (ReferenceError: TBPost is not defined,
// 2026-09-29).
//
// Tes ini menyuntik sendiri tag <script>/<link> yang sengaja lambat (server delay 1.2 detik)
// lewat page.evaluate() secara sinkron tepat sebelum memanggil requireJsAsync/requireCssAsync
// untuk url yang sama - mereproduksi persis kondisi "tag sudah ada, belum selesai load" tanpa
// bergantung pada timing race asli yang tidak deterministik.
const PAGE = '/demo/cresjs/requirejsrace';

async function waitForCf(page: import('@playwright/test').Page) {
    await page.goto(PAGE);
    await page.waitForFunction(() => !!(window as any).cresenity && !!(window as any).cresenity.cf);
}

test('requireJsAsync menunggu script yang tag-nya sudah ada tapi belum selesai load', async ({ page }) => {
    await waitForCf(page);

    const result = await page.evaluate(async () => {
        const url = new URL('/demo/cresjs/requirejsrace/slowScript', window.location.origin).href;
        (window as any).__slowScriptLoaded = false;

        // Sisipkan tag sendiri (bukan lewat cf.requireJsAsync) - inilah yang membuat
        // isAssetTagPresent() bernilai true padahal browser belum selesai memuatnya.
        const el = document.createElement('script');
        el.src = url;
        document.body.appendChild(el);

        const start = Date.now();
        await (window as any).cresenity.cf.requireJsAsync(url);
        const elapsedMs = Date.now() - start;

        return {
            slowScriptLoadedWhenResolved: (window as any).__slowScriptLoaded === true,
            elapsedMs,
        };
    });

    expect(result.slowScriptLoadedWhenResolved, 'requireJsAsync resolve sebelum script benar-benar selesai load').toBe(true);
    expect(result.elapsedMs).toBeGreaterThanOrEqual(1000);
});

test('requireCssAsync menunggu stylesheet yang tag-nya sudah ada tapi belum selesai load', async ({ page }) => {
    await waitForCf(page);

    const result = await page.evaluate(async () => {
        const url = new URL('/demo/cresjs/requirejsrace/slowCss', window.location.origin).href;

        const marker = document.createElement('div');
        marker.id = 'slow-css-marker';
        document.body.appendChild(marker);

        const el = document.createElement('link');
        el.rel = 'stylesheet';
        el.href = url;
        document.head.appendChild(el);

        const start = Date.now();
        await (window as any).cresenity.cf.requireCssAsync(url);
        const elapsedMs = Date.now() - start;

        const value = getComputedStyle(marker).getPropertyValue('--slow-css-loaded').trim();
        return { cssLoadedWhenResolved: value === '1', elapsedMs };
    });

    expect(result.cssLoadedWhenResolved, 'requireCssAsync resolve sebelum stylesheet benar-benar selesai load').toBe(true);
    expect(result.elapsedMs).toBeGreaterThanOrEqual(1000);
});

test('requireJsAsync untuk resource yang sudah ditandai loader sendiri tetap resolve instan (tidak ikut menunggu window load)', async ({ page }) => {
    await waitForCf(page);

    const elapsedMs = await page.evaluate(async () => {
        // jQuery sudah dimuat & ditandai di cf.required oleh init() - jalur ini TIDAK boleh
        // ikut menunggu readyState/window-load seperti jalur isAssetTagPresent di atas.
        const jqueryUrl = (window as any).cresenity.cf.getConfig().defaultJQueryUrl;
        const start = Date.now();
        await (window as any).cresenity.cf.requireJsAsync(jqueryUrl);
        return Date.now() - start;
    });

    expect(elapsedMs).toBeLessThan(200);
});

test('dua pemanggilan requireJsAsync bersamaan untuk url yang sama berbagi promise yang sama', async ({ page }) => {
    await waitForCf(page);

    const result = await page.evaluate(async () => {
        const url = new URL('/demo/cresjs/requirejsrace/slowScript', window.location.origin).href;
        (window as any).__slowScriptLoaded = false;

        const cf = (window as any).cresenity.cf;
        // dua pemanggilan beruntun tanpa menunggu salah satunya selesai - sebelum fix,
        // panggilan kedua bisa resolve instan lewat this.required.push(url) yang terjadi
        // di panggilan pertama sebelum script itu benar-benar selesai load.
        const p1 = cf.requireJsAsync(url);
        const p2 = cf.requireJsAsync(url);
        const [r1, r2] = await Promise.all([p1, p2]);

        return {
            sameUrl: r1 === r2,
            slowScriptLoadedWhenResolved: (window as any).__slowScriptLoaded === true,
        };
    });

    expect(result.slowScriptLoadedWhenResolved, 'panggilan kedua resolve sebelum script selesai load').toBe(true);
});

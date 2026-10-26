/**
 * MobX bawaan Redoc melempar error 35 bila halaman sudah punya MobX lain
 * (mis. dari extension browser). Sembunyikan global MobX itu selama Redoc
 * dimuat; redoc-mobx-after.js mengembalikannya.
 */
(function (g) {
    if (g.__mobxGlobals === undefined && g.__mobxInstanceCount === undefined) {
        return;
    }
    g.__cfRedocMobxStash = {
        globals: g.__mobxGlobals,
        instanceCount: g.__mobxInstanceCount
    };
    try {
        delete g.__mobxGlobals;
        delete g.__mobxInstanceCount;
    } catch (e) {
        g.__mobxGlobals = undefined;
        g.__mobxInstanceCount = undefined;
    }
})(typeof globalThis !== 'undefined' ? globalThis : window);

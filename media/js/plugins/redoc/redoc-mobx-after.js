/**
 * Pasangan redoc-mobx-before.js: kembalikan global MobX milik pihak lain.
 * Redoc tetap memegang state MobX-nya sendiri.
 */
(function (g) {
    var stash = g.__cfRedocMobxStash;
    if (!stash) {
        return;
    }
    g.__mobxGlobals = stash.globals;
    g.__mobxInstanceCount = stash.instanceCount;
    try {
        delete g.__cfRedocMobxStash;
    } catch (e) {
        g.__cfRedocMobxStash = undefined;
    }
})(typeof globalThis !== 'undefined' ? globalThis : window);

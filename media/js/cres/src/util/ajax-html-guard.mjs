/**
 * Respons HTML (mis. halaman login saat sesi habis) untuk ajax dataType 'script':
 * converter jQuery 'text script' meng-eval-nya lewat <script>, SyntaxError-nya lolos ke
 * window.onerror (tidak bisa di-try/catch). Cek sebelum eval, gagalkan request dengan rapi (#-20600).
 */
export const looksLikeHtmlPage = (text) => {
    return typeof text === 'string' && /^\s*(<!doctype\s+html|<html[\s>]|<head[\s>]|<body[\s>])/i.test(text);
};

/**
 * Objek JSON ({"key": ...}) selalu SyntaxError bila di-eval sebagai script; CF membalas XHR
 * dengan halaman ber-format JSON, jadi ini varian yang paling sering di app CF.
 */
export const looksLikeJsonObject = (text) => {
    return typeof text === 'string' && /^\s*\{\s*"/.test(text);
};

export const isNotAScript = (text) => looksLikeHtmlPage(text) || looksLikeJsonObject(text);

/**
 * @param {Function} originalConverter converter 'text script' bawaan jQuery
 * @param {Function} onHtmlPage dipanggil sekali per respons HTML
 * @returns {Function}
 */
export const guardScriptConverter = (originalConverter, onHtmlPage) => {
    const guarded = function (text) {
        if (isNotAScript(text)) {
            onHtmlPage(text);
            //jQuery menangkap throw dari converter dan menjadikannya status 'parsererror'
            throw new Error('Expected a script response but received ' + (looksLikeHtmlPage(text) ? 'an HTML page' : 'JSON') + ' (session expired?)');
        }

        return originalConverter.apply(this, arguments);
    };
    guarded.cresHtmlGuard = true;

    return guarded;
};

/**
 * Pasang guard di jQuery.ajaxSettings; aman dipanggil berulang.
 *
 * @param {object} jQuery
 * @param {Function} onHtmlPage
 * @returns {boolean} true bila terpasang
 */
export const installAjaxHtmlGuard = (jQuery, onHtmlPage) => {
    if (!jQuery || !jQuery.ajaxSettings || !jQuery.ajaxSettings.converters) {
        return false;
    }
    const original = jQuery.ajaxSettings.converters['text script'];
    if (typeof original !== 'function' || original.cresHtmlGuard) {
        return false;
    }
    jQuery.ajaxSettings.converters['text script'] = guardScriptConverter(original, onHtmlPage);

    return true;
};

// Browser-side counterpart of CDebug_Collector_JsException (system/libraries/CDebug/Collector/
// JsException.php). Reports uncaught JS errors and unhandled promise rejections to the same-origin
// endpoint `/cresenity/jsError`, which writes them into devcloud's existing Exception Collector -
// no separate dashboard, no third-party service.
//
// Attaches at module-load time (not on cres.init()/DOMContentLoaded) so it catches errors as early
// as possible - but cres.js itself loads via <script defer>, so an error thrown by a NON-deferred
// inline <script> earlier on the page (before cres.js runs) is out of reach here regardless.
//
// window.__CF_JS_COLLECTOR_ENABLED__ is injected by CApp's RendererTrait, set from the app's own
// `collector.exception` config - apps that never opted into exception collection get zero
// reporting overhead, not just a server-side drop.

const ENDPOINT = '/cresenity/jsError';
const MAX_REPORTS_PER_LOAD = 20;
let sentCount = 0;

function isEnabled() {
    return typeof window !== 'undefined' && window.__CF_JS_COLLECTOR_ENABLED__ === true;
}

function send(payload) {
    if (sentCount >= MAX_REPORTS_PER_LOAD) {
        return;
    }
    sentCount += 1;

    try {
        const body = JSON.stringify(payload);
        if (navigator.sendBeacon) {
            const blob = new Blob([body], { type: 'application/json' });
            navigator.sendBeacon(ENDPOINT, blob);
            return;
        }
        if (typeof fetch === 'function') {
            fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body,
                keepalive: true
            }).catch(function () {
                // never let a failed report itself surface as another error
            });
        }
    } catch (e) {
        // same - a reporting failure must stay silent
    }
}

function onError(event) {
    // a plain resource load failure (img/script/link) also fires window 'error' but carries
    // neither `.error` nor a real `.message` - not a JS exception, ignore it
    if (!event || (!event.error && !event.message)) {
        return;
    }

    const jsError = event.error;
    send({
        message: String((jsError && jsError.message) || event.message || 'Unknown error'),
        stack: jsError && jsError.stack ? String(jsError.stack) : null,
        name: jsError && jsError.name ? jsError.name : null,
        filename: event.filename || null,
        lineno: event.lineno || null,
        colno: event.colno || null,
        url: window.location.href,
        type: 'error'
    });
}

function onUnhandledRejection(event) {
    const reason = event && event.reason;
    const message = reason && reason.message ? reason.message : String(reason);
    send({
        message: String(message),
        stack: reason && reason.stack ? String(reason.stack) : null,
        name: reason && reason.name ? reason.name : 'UnhandledRejection',
        filename: null,
        lineno: null,
        colno: null,
        url: window.location.href,
        type: 'unhandledrejection'
    });
}

export function initJsErrorCollector() {
    if (!isEnabled() || typeof window === 'undefined') {
        return;
    }

    window.addEventListener('error', onError);
    window.addEventListener('unhandledrejection', onUnhandledRejection);
}

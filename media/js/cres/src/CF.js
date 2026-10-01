import {
    dispatch as dispatchWindowEvent
} from './util';
import { mergeOptions } from './util/config';
import pipeline from './util/pipeline';
class CF {
    constructor() {
        this.required = typeof this.required === 'undefined' ? [] : this.required;
        this.cssRequired = typeof this.cssRequired === 'undefined' ? [] : this.cssRequired;
        this.jsLoadingPromises = new Map();
        this.cssLoadingPromises = new Map();

        this.window = window;
        this.document = window.document;
        this.head = this.document.getElementsByTagName('head')[0];
        this.beforeInitCallback = [];
        this.afterInitCallback = [];
        let cappConfig = window.capp;
        if(typeof cappConfig == 'undefined') {
            cappConfig = {};
        }
        let defaultConfig = {
            baseUrl: '/',
            domain: 'localhost',
            defaultJQueryUrl: 'https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js',
            haveScrollToTop: false,
            vscode: {
                liveReload: {
                    enable: false,
                    protocol: 'ws',
                    host: 'localhost',
                    port: 3717
                }
            },
            requireJs: false,
            environment: 'production',
            CFVersion: '1.5',
            isProduction: false,
            debug: false,
            format: {
                decimalSeparator: '.',
                thousandSeparator: ',',
                decimalDigit: 0,
                date: 'Y-m-d',
                datetime: 'Y-m-d H:i:s',
                currencyDecimalDigit: null,
                currencyPrefix: '',
                currencySuffix: '',
                currencyStripZeroDecimal: false
            },
            haveClock: false,
            react: {
                enable: false
            },
            waves: {
                selector: '.cres-waves-effect'
            },
            timezoneString: '+07:00'

        };
        this.config = mergeOptions(defaultConfig, cappConfig);

        if (this.config.cssUrl) {
            this.config.cssUrl.forEach((item) => {
                this.required.push(item);
            });
        }
        if (this.config.jsUrl) {
            this.config.jsUrl.forEach((item) => {
                this.required.push(item);
            });
        }
    }

    debug(msg) {
        if (this.getConfig().debug) {
            window.console.log(msg);
        }
    }

    onBeforeInit(callback) {
        this.beforeInitCallback.push(callback);
        return this;
    }
    onAfterInit(callback) {
        this.afterInitCallback.push(callback);
        return this;
    }


    getConfig() {
        return this.config;
    }


    isUseRequireJs() {
        return this.getConfig().requireJs;
    }
    CFVersion() {
        return this.getConfig().CFVersion;
    }
    isAssetTagPresent(tagName, attr, url) {
        return this.findAssetTags(tagName, attr, url).length > 0;
    }
    findAssetTags(tagName, attr, url) {
        // Compares by resolved absolute path only -- a script/link already on
        // the page under a different cache-busting query string (e.g. `?v=...`)
        // or written as a relative path must still count as loaded, or callers
        // reload+re-execute the whole file. `elements[i][attr]` (the DOM
        // property, not getAttribute) is what resolves a relative src/href to
        // an absolute URL for us.
        const target = new URL(url, this.document.baseURI).href.split('?')[0];
        const targetBundle = this.compiledBundleKey(target);
        const elements = this.document.querySelectorAll(tagName + '[' + attr + ']');
        const matches = [];
        for (let i = 0; i < elements.length; i++) {
            const present = elements[i][attr].split('?')[0];
            if (present === target || (targetBundle !== null && this.compiledBundleKey(present) === targetBundle)) {
                // el.src/el.href on the match is the browser-resolved absolute url --
                // waitForExistingAssetTag checks/listens on this pair directly.
                matches.push({ el: elements[i], url: elements[i][attr] });
            }
        }
        return matches;
    }
    compiledBundleKey(absoluteUrl) {
        // `compiled/asset/<type>/<release>/<md5-of-file-list>.<ext>` (assets.*.compile): the md5
        // names the SET of files, the release folder only changes per deploy. A tab opened before
        // a deploy has already executed that same set, so a newer release of it must count as
        // loaded - re-injecting it re-runs jQuery, every plugin and every top-level `class` on a
        // live page ("Identifier ... has already been declared").
        const match = absoluteUrl.match(/\/compiled\/asset\/(css|js)\/[^/]+\/([0-9a-f]{32}\.(?:css|js))$/);
        return match ? match[1] + '/' + match[2] : null;
    }
    resolveAssetKey(url) {
        // Same normalization as isAssetTagPresent's comparison, exposed so callers can
        // share one in-flight promise per effective resource instead of per raw url string.
        const target = new URL(url, this.document.baseURI).href.split('?')[0];
        const bundleKey = this.compiledBundleKey(target);
        return bundleKey !== null ? bundleKey : target;
    }
    isResourceFetchComplete(absoluteUrl) {
        // ResourceTiming proves the browser already finished fetching this exact url,
        // independent of when in the page's life that happened -- unlike `document.readyState`,
        // which only ever describes the INITIAL document load and tells us nothing about a tag
        // some other code inserted afterwards (the actual shape of this race: it always
        // surfaces well after the page is already 'complete'). Cross-origin entries without a
        // `Timing-Allow-Origin` response header report zeroed timings, so this can false-negative
        // for third-party urls -- callers fall back to a live listener in that case, see below.
        if (typeof this.window.performance === 'undefined' || typeof this.window.performance.getEntriesByName !== 'function') {
            return false;
        }
        const entries = this.window.performance.getEntriesByName(absoluteUrl);
        return entries.length > 0 && entries[entries.length - 1].responseEnd > 0;
    }
    waitForExistingAssetTag(resolve, taggedElements) {
        // Check each matched element's own resolved url, not the originally-requested one --
        // a compiled-bundle match (compiledBundleKey) can have a different release-hash path
        // than what was asked for, so only the element's actual src/href is meaningful here.
        if (taggedElements.some((t) => this.isResourceFetchComplete(t.url))) {
            resolve();
            return;
        }
        // Not provably complete yet -- listen on the actual element(s) isAssetTagPresent
        // matched, and resolve as soon as any one of them finishes (success or error; a load
        // failure elsewhere isn't this caller's problem to retry). A listener attached here can
        // only miss an already-fired event in the narrow cross-origin-without-timing-header gap
        // above; the timeout below bounds that residual risk instead of hanging forever.
        let settled = false;
        const settle = () => {
            if (!settled) {
                settled = true;
                resolve();
            }
        };
        taggedElements.forEach((t) => {
            t.el.addEventListener('load', settle, { once: true });
            t.el.addEventListener('error', settle, { once: true });
        });
        this.window.setTimeout(settle, 15000);
    }
    requireCssAsync(url) {
        const assetKey = this.resolveAssetKey(url);
        if (this.cssLoadingPromises.has(assetKey)) {
            return this.cssLoadingPromises.get(assetKey);
        }
        const promise = new Promise((resolve, reject)=> {
            // Already tracked by this loader (including the config-provided jsUrl/cssUrl list,
            // which the server only ever lists for tags it rendered synchronously before this
            // module ran) -- that's a real guarantee, so resolve immediately as before.
            if (~this.cssRequired.indexOf(url)) {
                resolve(url);
                return;
            }
            // A tag matching this url already in the DOM is NOT the same guarantee -- it only
            // proves presence, not that the browser finished downloading/executing it. Wait for
            // real completion instead of resolving just because the tag exists.
            const existingTags = this.findAssetTags('link', 'href', url);
            if (existingTags.length > 0) {
                this.waitForExistingAssetTag(() => resolve(url), existingTags);
                return;
            }
            this.cssRequired.push(url);

            let string = '<link rel=\'stylesheet\' type=\'text/css\' href=\'' + url + '\' />';

            // if(this.config.debug) {
            //     console.log('Css Require:' + url + ', readyState:' + document.readyState);
            // }
            if ((document.readyState === 'loading' /* || mwd.readyState === 'interactive'*/) && !!window.CanvasRenderingContext2D && self === parent) {
                document.write(string);
                resolve(url);
            } else {
                let el;
                el = this.document.createElement('link');
                el.rel = 'stylesheet';
                el.type = 'text/css';
                el.href = url;
                // IE 6 & 7
                el.addEventListener('load', ()=> {
                    dispatchWindowEvent('cresenity:css:loaded', {
                        url: url
                    });
                    // if(this.config.debug) {
                    //     console.log('Css Loaded:' + url + '');
                    // }
                    resolve(url);
                });


                this.head.appendChild(el);
            }
        });
        this.cssLoadingPromises.set(assetKey, promise);
        return promise;
    }
    requireCss(url, callback) {
        this.requireCssAsync(url).then(callback);
    }
    requireJsAsync(url) {
        const assetKey = this.resolveAssetKey(url);
        if (this.jsLoadingPromises.has(assetKey)) {
            return this.jsLoadingPromises.get(assetKey);
        }
        const promise = new Promise((resolve, reject)=> {
            // Already tracked by this loader (including the config-provided jsUrl/cssUrl list,
            // which the server only ever lists for tags it rendered synchronously before this
            // module ran) -- that's a real guarantee, so resolve immediately as before.
            if (~this.required.indexOf(url)) {
                resolve(url);
                return;
            }
            // A tag matching this url already in the DOM is NOT the same guarantee -- it only
            // proves presence, not that the browser finished downloading/executing it. Wait for
            // real completion instead of resolving just because the tag exists.
            const existingTags = this.findAssetTags('link', 'href', url).concat(this.findAssetTags('script', 'src', url));
            if (existingTags.length > 0) {
                this.waitForExistingAssetTag(() => resolve(url), existingTags);
                return;
            }
            this.required.push(url);
            let string = '<script type=\'text/javascript\'  src=\'' + url + '\'></script>';
            // if(this.config.debug) {
            //     console.log('JS Require:' + url + ', readyState:' + document.readyState);
            // }

            if ((document.readyState === 'loading' /* || mwd.readyState === 'interactive'*/) && !!window.CanvasRenderingContext2D && self === parent) {
                document.write(string);
                if(this.config.debug) {
                    console.log('JS Loaded:' + url + '');
                }
                resolve(url);
            } else {
                let el;
                el = this.document.createElement('script');
                el.src = url;
                el.setAttribute('type', 'text/javascript');
                // IE 6 & 7

                el.addEventListener('load', ()=> {
                    dispatchWindowEvent('cresenity:js:loaded', {
                        url: url
                    });
                    // if(this.config.debug) {
                    //     console.log('JS Loaded:' + url + '');
                    // }
                    resolve(url);
                });

                this.document.body.appendChild(el);
            }
        });
        this.jsLoadingPromises.set(assetKey, promise);
        return promise;
    }
    requireJs(url, callback) {
        this.requireJsAsync(url).then(callback);
    }
    require(url, callback) {
        if (typeof url != 'string') {
            url = url[0];
        }

        if (!url) {
            return;
        }

        let toPush = url.trim();
        let t = 'js';

        let urlObject = new URL(toPush, document.baseURI);
        if (urlObject) {
            t = urlObject.pathname.split('.').pop();
        }

        if (t == 'js') {
            this.requireJs(toPush, callback);
        }else if (t == 'css') {
            this.requireCss(toPush, callback);
        } else {
            callback();
        }
    }
    isProduction() {
        return this.config.environment == 'production';
    }
    loadReact(callback) {
        let afterReactLoaded = () => {
            dispatchWindowEvent('cresenity:react:loaded');
            callback();
        };
        const reactDevelopmentUrl = 'https://unpkg.com/react@17/umd/react.development.js';
        const reactDevelopmentDomUrl = 'https://unpkg.com/react-dom@17/umd/react-dom.development.js';

        const reactProductionUrl = 'https://unpkg.com/react@17/umd/react.production.min.js';
        const reactProductionDomUrl = 'https://unpkg.com/react-dom@17/umd/react-dom.production.min.js';

        let reactUrl = this.getConfig().isProduction ? reactProductionUrl : reactDevelopmentUrl;
        let reactDomUrl = this.getConfig().isProduction ? reactProductionDomUrl : reactDevelopmentDomUrl;
        let loadReactDom = () => {
            let fileref = this.document.createElement('script');
            fileref.setAttribute('type', 'text/javascript');
            fileref.setAttribute('src', reactDomUrl);
            // IE 6 & 7
            if (typeof (callback) === 'function') {
                fileref.onload = ()=>{
                    afterReactLoaded();
                };
            }
            this.head.appendChild(fileref);
        };
        let loadReactBase = () => {
            let fileref = this.document.createElement('script');
            fileref.setAttribute('type', 'text/javascript');
            fileref.setAttribute('src', reactUrl);
            // IE 6 & 7
            if (typeof (callback) === 'function') {
                fileref.onload = ()=>{
                    loadReactDom();
                };
            }
            this.head.appendChild(fileref);
        };
        if (typeof React == 'undefined') {
            loadReactBase();
        } else {
            afterReactLoaded();
        }
    }
    loadJQuery(callback) {
        const jqueryUrl = this.getConfig().defaultJQueryUrl;
        let afterJQueryLoaded = () => {
            this.required.push(jqueryUrl);
            dispatchWindowEvent('cresenity:jquery:loaded');
            callback();
        };
        if (typeof jQuery == 'undefined') {
            let fileref = this.document.createElement('script');
            fileref.setAttribute('type', 'text/javascript');

            fileref.setAttribute('src', jqueryUrl);
            // IE 6 & 7
            if (typeof (callback) === 'function') {
                fileref.onload = ()=>{
                    afterJQueryLoaded();
                };
                // fileref.onreadystatechange = () => {
                //     if (fileref.readyState == 'complete') {
                //         afterJQueryLoaded();
                //     }
                // };
            }
            this.head.appendChild(fileref);
        } else {
            afterJQueryLoaded();
        }
    }

    init() {
        this.beforeInitCallback.forEach((item) => {
            item();
        });

        //push all item already loaded by html in capp
        let arrayJsUrl = this.getConfig().jsUrl;
        let arrayCssUrl = this.getConfig().cssUrl;
        if (typeof arrayJsUrl !== 'undefined') {
            arrayJsUrl.forEach((item) => {
                this.required.push(item);
            });
        }
        if (typeof arrayCssUrl !== 'undefined') {
            arrayCssUrl.forEach((item) => {
                this.cssRequired.push(item);
            });
        }


        let resolver = this.getConfig().react.enable
            ? (callback) => {
                this.loadJQuery(()=>{
                    this.loadReact(callback);
                });
            }
            : (callback) => {
                this.loadJQuery(callback);
            };
        resolver(() => {
            this.afterInitCallback.forEach((item) => {
                item();
            });
        });
    }
}

let cf = new CF();

export default cf;

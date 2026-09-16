import {
    dispatch as dispatchWindowEvent
} from './util';
import { mergeOptions } from './util/config';
import pipeline from './util/pipeline';
class CF {
    constructor() {
        this.required = typeof this.required === 'undefined' ? [] : this.required;
        this.cssRequired = typeof this.cssRequired === 'undefined' ? [] : this.cssRequired;

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
        // Compares by resolved absolute path only -- a script/link already on
        // the page under a different cache-busting query string (e.g. `?v=...`)
        // or written as a relative path must still count as loaded, or callers
        // reload+re-execute the whole file. `elements[i][attr]` (the DOM
        // property, not getAttribute) is what resolves a relative src/href to
        // an absolute URL for us.
        const target = new URL(url, this.document.baseURI).href.split('?')[0];
        const elements = this.document.querySelectorAll(tagName + '[' + attr + ']');
        for (let i = 0; i < elements.length; i++) {
            if (elements[i][attr].split('?')[0] === target) {
                return true;
            }
        }
        return false;
    }
    requireCssAsync(url) {
        return new Promise((resolve, reject)=> {
            let loaded = ~this.cssRequired.indexOf(url);
            if(!loaded) {
                loaded = this.isAssetTagPresent('link', 'href', url);
            }
            if (!loaded) {
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
            } else {
                resolve(url);
            }
        });
    }
    requireCss(url, callback) {
        this.requireCssAsync(url).then(callback);
    }
    requireJsAsync(url) {
        return new Promise((resolve, reject)=> {
            let loaded = ~this.required.indexOf(url);
            if(!loaded) {
                loaded = this.isAssetTagPresent('link', 'href', url) || this.isAssetTagPresent('script', 'src', url);
            }
            if (!loaded) {
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
            } else {
                resolve(url)
            }


        });
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

import './polyfills/index';

// eslint-disable-next-line no-extend-native
String.prototype.contains = function (a) {
    return !!~this.indexOf(a);
};

// eslint-disable-next-line no-extend-native
String.prototype.toNumber = function () {
    let n = parseFloat(this);
    if (!isNaN(n)) {
        return n;
    }
    return 0;
};

import './index.scss';
import Cresenity from './Cresenity';
import { initJsErrorCollector } from './module/jsErrorCollector';

initJsErrorCollector();

window.Cresenity = Cresenity;
if (!window.cresenity) {
    window.cresenity = new Cresenity();
}
if (!window.cres) {
    window.cres = window.cresenity;
}

// init cresenity after page load
if (window.document.readyState === 'complete') {
    window.cres.init();
} else {
    window.document.addEventListener(
        'DOMContentLoaded',
        function onContentLoaded() {
            window.cres.init();
            window.document.removeEventListener(
                'DOMContentLoaded',
                onContentLoaded
            );
        }
    );
}

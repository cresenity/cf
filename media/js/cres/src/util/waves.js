

// Find exact position of element
function isWindow(obj) {
    return obj !== null && obj === obj.window;
}

function getWindow(elem) {
    return isWindow(elem) ? elem : elem.nodeType === 9 && elem.defaultView;
}

function offset(elem) {
    let docElem, win,
        box = {top: 0, left: 0},
        doc = elem && elem.ownerDocument;

    docElem = doc.documentElement;

    if (typeof elem.getBoundingClientRect !== typeof undefined) {
        box = elem.getBoundingClientRect();
    }
    win = getWindow(doc);
    return {
        top: box.top + win.pageYOffset - docElem.clientTop,
        left: box.left + win.pageXOffset - docElem.clientLeft
    };
}
function convertStyle(obj) {
    let style = '';

    for (let a in obj) {
        if (obj.hasOwnProperty(a)) {
            style += (a + ':' + obj[a] + ';');
        }
    }

    return style;
}


const Effect = {

    // Effect delay
    duration: 750,

    show: function (e, element) {
        // Disable right click
        if (e.button === 2) {
            return false;
        }

        let el = element || this;

        // Create ripple
        let ripple = document.createElement('div');
        ripple.className = 'cres-waves-ripple';
        el.appendChild(ripple);

        // Get click coordinate and element witdh
        let pos = offset(el);
        let relativeY = (e.pageY - pos.top);
        let relativeX = (e.pageX - pos.left);
        let scale = 'scale('+((el.clientWidth / 100) * 10)+')';

        // Support for touch devices
        if ('touches' in e) {
            relativeY = (e.touches[0].pageY - pos.top);
            relativeX = (e.touches[0].pageX - pos.left);
        }

        // Attach data to element
        ripple.setAttribute('data-hold', Date.now());
        ripple.setAttribute('data-scale', scale);
        ripple.setAttribute('data-x', relativeX);
        ripple.setAttribute('data-y', relativeY);

        // Set ripple position
        let rippleStyle = {
            top: relativeY+'px',
            left: relativeX+'px'
        };

        ripple.className = ripple.className + ' waves-notransition';
        ripple.setAttribute('style', convertStyle(rippleStyle));
        ripple.className = ripple.className.replace('waves-notransition', '');

        // Scale the ripple
        rippleStyle['-webkit-transform'] = scale;
        rippleStyle['-moz-transform'] = scale;
        rippleStyle['-ms-transform'] = scale;
        rippleStyle['-o-transform'] = scale;
        rippleStyle.transform = scale;
        rippleStyle.opacity = '1';

        rippleStyle['-webkit-transition-duration'] = Effect.duration + 'ms';
        rippleStyle['-moz-transition-duration'] = Effect.duration + 'ms';
        rippleStyle['-o-transition-duration'] = Effect.duration + 'ms';
        rippleStyle['transition-duration'] = Effect.duration + 'ms';

        rippleStyle['-webkit-transition-timing-function'] = 'cubic-bezier(0.250, 0.460, 0.450, 0.940)';
        rippleStyle['-moz-transition-timing-function'] = 'cubic-bezier(0.250, 0.460, 0.450, 0.940)';
        rippleStyle['-o-transition-timing-function'] = 'cubic-bezier(0.250, 0.460, 0.450, 0.940)';
        rippleStyle['transition-timing-function'] = 'cubic-bezier(0.250, 0.460, 0.450, 0.940)';

        ripple.setAttribute('style', convertStyle(rippleStyle));
    },

    hide: function (e) {
        TouchHandler.touchup(e);

        let el = this;
        //var width = el.clientWidth * 1.4;

        // Get first ripple
        let ripple = null;
        let ripples = el.getElementsByClassName('cres-waves-ripple');
        if (ripples.length > 0) {
            ripple = ripples[ripples.length - 1];
        } else {
            return false;
        }

        let relativeX = ripple.getAttribute('data-x');
        let relativeY = ripple.getAttribute('data-y');
        let scale = ripple.getAttribute('data-scale');

        // Get delay beetween mousedown and mouse leave
        let diff = Date.now() - Number(ripple.getAttribute('data-hold'));
        let delay = 350 - diff;

        if (delay < 0) {
            delay = 0;
        }

        // Fade out ripple after delay
        setTimeout(function () {
            let style = {
                top: relativeY+'px',
                left: relativeX+'px',
                opacity: '0',

                // Duration
                '-webkit-transition-duration': Effect.duration + 'ms',
                '-moz-transition-duration': Effect.duration + 'ms',
                '-o-transition-duration': Effect.duration + 'ms',
                'transition-duration': Effect.duration + 'ms',
                '-webkit-transform': scale,
                '-moz-transform': scale,
                '-ms-transform': scale,
                '-o-transform': scale,
                transform: scale
            };

            ripple.setAttribute('style', convertStyle(style));

            setTimeout(function () {
                try {
                    el.removeChild(ripple);
                } catch(eee) {
                    return false;
                }
            }, Effect.duration);
        }, delay);
    },

    // Little hack to make <input> can perform waves effect
    wrapInput: function (elements) {
        for (let a = 0; a < elements.length; a++) {
            let el = elements[a];

            if (el.tagName.toLowerCase() === 'input') {
                let parent = el.parentNode;

                // If input already have parent just pass through
                if (parent.tagName.toLowerCase() === 'i' && parent.className.indexOf('waves-effect') !== -1) {
                    continue;
                }

                // Put element class and style to the specified parent
                let wrapper = document.createElement('i');
                wrapper.className = el.className + ' waves-input-wrapper';

                let elementStyle = el.getAttribute('style');

                if (!elementStyle) {
                    elementStyle = '';
                }

                wrapper.setAttribute('style', elementStyle);

                el.className = 'waves-button-input';
                el.removeAttribute('style');

                // Put element as child
                parent.replaceChild(wrapper, el);
                wrapper.appendChild(el);
            }
        }
    }
};


/**
 * Disable mousedown event for 500ms during and after touch
 */
const TouchHandler = {
    /* uses an integer rather than bool so there's no issues with
     * needing to clear timeouts if another touch event occurred
     * within the 500ms. Cannot mouseup between touchstart and
     * touchend, nor in the 500ms after touchend.
     */
    touches: 0,
    allowEvent: function (e) {
        let allow = true;

        if (e.type === 'touchstart') {
            TouchHandler.touches += 1; //push
        } else if (e.type === 'touchend' || e.type === 'touchcancel') {
            setTimeout(function () {
                if (TouchHandler.touches > 0) {
                    TouchHandler.touches -= 1; //pop after 500ms
                }
            }, 500);
        } else if (e.type === 'mousedown' && TouchHandler.touches > 0) {
            allow = false;
        }

        return allow;
    },
    touchup: function (e) {
        TouchHandler.allowEvent(e);
    }
};


/**
 * Delegated click handler for .waves-effect element.
 * returns null when .waves-effect element not in "click tree"
 */
function getWavesEffectElement(e) {
    if (TouchHandler.allowEvent(e) === false) {
        return null;
    }

    let element = null;
    let target = e.target || e.srcElement;

    while (target.parentNode !== null) {
        if (!(target instanceof SVGElement) && target.className.indexOf('waves-effect') !== -1) {
            element = target;
            break;
        }
        target = target.parentNode;
    }
    return element;
}

/**
 * Bubble the click and show effect if .waves-effect elem was found
 */
function showEffect(e) {
    let element = getWavesEffectElement(e);

    if (element !== null) {
        Effect.show(e, element);

        if ('ontouchstart' in window) {
            element.addEventListener('touchend', Effect.hide, false);
            element.addEventListener('touchcancel', Effect.hide, false);
        }

        element.addEventListener('mouseup', Effect.hide, false);
        element.addEventListener('mouseleave', Effect.hide, false);
        element.addEventListener('dragend', Effect.hide, false);
    }
}


/**
 * Attach Waves to an input element (or any element which doesn't
 * bubble mouseup/mousedown events).
 *   Intended to be used with dynamically loaded forms/inputs, or
 * where the user doesn't want a delegated click handler.
 */
export const attachWaves = function (element) {
    //FUTURE: automatically add waves classes and allow users
    // to specify them with an options param? Eg. light/classic/button
    if (element.tagName.toLowerCase() === 'input') {
        Effect.wrapInput([element]);
        element = element.parentNode;
    }

    if ('ontouchstart' in window) {
        element.addEventListener('touchstart', showEffect, false);
    }

    element.addEventListener('mousedown', showEffect, false);
};

import Url from './module/Url';
import cf from './CF';
import ScrollToTop from './module/ScrollToTop';
import { mergeOptions } from './util/config';
import {
    dispatch as dispatchWindowEvent,
    showHtmlModal,
    showUrlModal,
    toggleFullscreen
} from './util';
import {encode as base64encode, decode as base64decode} from './util/base64';
import php from './php';
import { domReady, elementReady, elementRendered } from './util/dom-observer';
import { debounce } from './util/debounce';
import { confirmFromElement, defaultConfirmHandler } from './module/confirm-handler';
import initValidation from './module/validation';
import initPlugin from './plugin';
import {element, initElement} from './element';
import ucfirst from 'locutus/php/strings/ucfirst';
import { resolveToastrMethod } from './util/toastr.mjs';
import Alpine from 'alpinejs';
import cresReact from './react';
import CSocket from './csocket/CSocket';
import removePreloader from './module/preloader';
import initProgressive from './module/progressive';
import cresToast from './module/toast';
import CresAlpine from './module/CresAlpine';
import SSE from './cresenity/SSE';
import { attachWaves } from './util/waves';
import formatter from './formatter';
import { initCssDomVar } from './module/css-dom-var';
import extend from './core/extend';
import {hasClass, addClass, removeClass} from './dom/classes';
import scrollTo from './animation/scrollTo';
import setHeight from './animation/setHeight';
import Theme from './cresenity/Theme';
import { initThemeMode } from './module/theme';
import { initMenu } from './module/menu';
import { formatCurrency, unformatCurrency } from './formatter/currency';
import { cresQuery } from './module/CresQuery';
import { isJson } from './util/helper';
import * as dateFns from 'date-fns';
import * as dateFnsTz from 'date-fns-tz';
import CresenityHistory from './history';
import clsx from './module/clsx';
import stylex from './module/stylex';
import collect from 'collect.js';
import emitter from './cresenity/emitter';
import bootstrapHelper from './module/BootstrapHelper';
import cresStyle from './module/CresStyle';
import DownloadProgress from './module/DownloadProgress';

export default class Cresenity {
    constructor() {
        this.cf = cf;
        this.base64 = {
            encode: base64encode,
            decode: base64decode
        };
        this.element = element;
        this.windowEventList = [
            'cresenity:confirm',
            'cresenity:jquery:loaded',
            'cresenity:loaded',
            'cresenity:js:loaded',
            'cresenity:ui:start',
            'cresenity:notification:message',
            'cresenity:history:statechange'
        ];
        this.modalElements = [];
        this.cresenityEventList = [

        ];
        this.url = new Url();
        this.scrollToTop = new ScrollToTop();
        this.callback = {};
        this.filesAdded = [];
        // Sisa dari cresenity.ui lama (port Livewire yang sudah dihapus): cuma utilitas waves.
        this.ui = {
            waves: {
                attach: attachWaves
            }
        };
        this.php = php;
        this.react = cresReact;
        this.observer = {
            elementRendered,
            elementReady
        };
        this.confirmHandler = defaultConfirmHandler;
        this.dispatchWindowEvent = dispatchWindowEvent;
        this.websocket = null;
        this.debounce = debounce;
        this.sse = new SSE();
        this.formatter = formatter;
        this.extend = extend;
        this.dom = {
            hasClass,
            addClass,
            removeClass
        };

        this.animation = {
            scrollTo,
            setHeight
        };
        this.theme = new Theme();
        this.$ = cresQuery;
        this.version = '1.4.1';
        this.checkAuthenticationInterval = null;
        this.dateFns = dateFns;
        this.dateFnsTz = dateFnsTz;
        this.clsx = clsx;
        this.stylex = stylex;
        this.collect = collect;
        this.emitter = emitter;
        this.download = new DownloadProgress(this);
    }
    loadJs(filename, callback) {
        let fileref = document.createElement('script');
        fileref.setAttribute('type', 'text/javascript');
        fileref.setAttribute('src', filename);
        // IE 6 & 7
        if (typeof (callback) === 'function') {
            fileref.onload = callback;
            fileref.onreadystatechange = () => {
                if (this.readyState === 'complete') {
                    callback();
                }
            };
        }
        document.getElementsByTagName('head')[0].appendChild(fileref);
    }
    createWebSocket(options) {
        return new CSocket(options);
    }
    haveCallback(name) {
        return typeof this.callback[name] === 'function';
    }
    doCallback(name, ...args) {
        if (this.haveCallback(name)) {
            this.callback[name](...args);
        }
    }
    setConfirmHandler(cb) {
        this.confirmHandler = cb;
        return this;
    }
    setCallback(name, cb) {
        this.callback[name] = cb;
        return this;
    }

    isUsingRequireJs() {
        return (typeof this.cf.getConfig().requireJs !== 'undefined') ? this.cf.getConfig().requireJs : true;
    }


    normalizeRequireJs() {
        if (!this.isUsingRequireJs()) {
            if (typeof define === 'function') {
                window.define = undefined;
            }
        }
    }
    isJson(text) {
        if (typeof text === 'string') {
            return (/^[\],:{}\s]*$/.test(text.replace(/\\["\\\/bfnrtu]/g, '@').replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g, ']').replace(/(?:^|:|,)(?:\s*\[)+/g, '')));
        }
        return false;
    }
    dispatch(eventName, params = {}) {
        dispatchWindowEvent('cresenity:' + eventName, params);
    }
    on(eventName, cb) {
        window.addEventListener('cresenity:' + eventName, cb);
    }
    async handleResponseAsync(data) {
        if(data.assets) {
            if(data.assets.css) {
                for (let i = 0; i < data.assets.css.length; i++) {
                    await this.cf.requireCssAsync(data.assets.css[i]);
                }
            }
            if(data.assets.js) {
                for (let i = 0; i < data.assets.js.length; i++) {
                    await this.cf.requireJsAsync(data.assets.js[i]);
                }
            }
        }
        return data;
    }
    handleResponse(data, callback) {
        this.handleResponseAsync(data).then(callback);
    }
    htmlModal(html) {
        showHtmlModal(html);
    }
    urlModal(url) {
        showUrlModal(url);
    }
    handleAjaxError(xhr, status, error) {
        if (error !== 'abort') {
            if(xhr.responseText && isJson(xhr.responseText)) {
                let data = JSON.parse(xhr.responseText);
                if(data && typeof data.errCode !== 'undefined') {
                    this.message('error', data.errMessage);
                }
            } else {
                this.message('error', 'Something went wrong... ' + (error ? ('(' + error + ')') : ''));
            }
            if(xhr.status!=200) {
                if(this.isDebug()) {
                    this.htmlModal(xhr.responseText);
                }
            }
        }
    }

    reload(options) {
        let targetOptions = {};
        if (options && options.selector) {
            let target = $(options.selector);
            if (target.attr('data-url')) {
                targetOptions.url = target.attr('data-url');
            }
            if (target.attr('data-method')) {
                targetOptions.method = target.attr('data-method');
            }
            if (target.attr('data-block-html')) {
                targetOptions.blockHtml = target.attr('data-block-html');
            }
            if (target.attr('data-block-type')) {
                targetOptions.blockType = target.attr('data-block-type');
            }
            if (target.attr('data-data-addition')) {
                targetOptions.dataAddition = JSON.parse(target.attr('data-data-addition'));
            }
        }

        let settings = extend({
            // These are the defaults.
            method: 'get',
            dataAddition: {},
            url: '/',
            reloadType: 'reload',
            onComplete: false,
            onSuccess: false,
            onBlock: false,
            blockHtml: false,
            blockType: 'default',
            onUnblock: false
        }, targetOptions, options);


        let method = settings.method;
        let selector = settings.selector;
        let blockOptions = {
            blockType: settings.blockType
        };
        if (settings.blockHtml) {
            blockOptions.innerMessage = settings.blockHtml;
        }
        let xhr = jQuery(selector).data('xhr');
        if (xhr) {
            xhr.abort();
        }
        let dataAddition = settings.dataAddition;
        let url = settings.url;
        if (url) {
            url = this.url.replaceParam(url);
        }
        if (typeof dataAddition === 'undefined') {
            dataAddition = {};
        }


        $(selector).each((index, element) => {
            let idTarget = $(element).attr('id');
            //url = this.url.addQueryString(url, 'capp_current_container_id', idTarget);


            if (typeof settings.onBlock === 'function') {
                settings.onBlock($(element));
            } else {
                this.blockElement($(element));
            }
            this.dispatch('reload:before');
            $(element).data('xhr', $.ajax({
                type: method,
                url: url,
                dataType: 'json',
                data: dataAddition,
                headers: {
                    Accept: 'application/json; charset=utf-8',
                    'X-Cres-Version': this.version
                },
                success: (data) => {
                    let isError = false;
                    let errMessage = null;
                    if(typeof data.errCode !== 'undefined') {
                        isError = data.errCode != 0;
                        if(isError) {
                            this.message('error', data.errMessage);
                            if(typeof data.data !== 'undefined' && data.data && this.isDebug()) {
                                this.htmlModal(data);
                            }
                        }
                    } else {
                        if(typeof data.html === 'undefined') {
                            if(this.isDebug()) {
                                this.htmlModal(data);
                            }
                            isError = true;
                        }
                    }
                    if(!isError) {
                        this.doCallback('onReloadSuccess', data);
                        this.dispatch('reload:success', data);
                        this.handleResponse(data, () => {
                            switch (settings.reloadType) {
                                case 'after':
                                    $(element).after(data.html);
                                    break;
                                case 'before':
                                    $(element).before(data.html);
                                    break;
                                case 'append':
                                    $(element).append(data.html);
                                    break;
                                case 'prepend':
                                    $(element).prepend(data.html);
                                    break;
                                default:
                                    $(element).html(data.html);
                                    break;
                            }
                            if (data.js && data.js.length > 0) {
                                let script = this.base64.decode(data.js);
                                eval(script);
                                this.applyDeferXData();
                            }


                            if ($(element).find('.prettyprint').length > 0) {
                                if (window.prettyPrint) {
                                    window.prettyPrint();
                                }
                            }
                            if (typeof settings.onSuccess === 'function') {
                                settings.onSuccess(data);
                            }
                        });
                    }
                },
                error: (errorXhr, ajaxOptions, thrownError) => {
                    this.dispatch('reload:error', {
                        xhr: errorXhr, ajaxOptions, error: thrownError
                    });
                    this.handleAjaxError(errorXhr, ajaxOptions, thrownError);
                },
                complete: () => {
                    this.dispatch('reload:complete');
                    $(element).data('xhr', false);
                    if (typeof settings.onUnblock === 'function') {
                        settings.onUnblock($(element));
                    } else {
                        this.unblockElement($(element));
                    }

                    if (typeof settings.onComplete === 'function') {
                        settings.onComplete();
                    }
                }
            }));
        });
    }

    append(options) {
        options.reloadType = 'append';
        this.reload(options);
    }
    prepend(options) {
        options.reloadType = 'prepend';
        this.reload(options);
    }
    after(options) {
        options.reloadType = 'after';
        this.reload(options);
    }
    before(options) {
        options.reloadType = 'before';
        this.reload(options);
    }
    isDebug() {
        if(this.cf.getConfig().environment == 'production') {
            return false;
        }
        return this.cf.getConfig().debug ?? false;
    }
    confirm(options) {
        if(typeof options == 'function') {
            options = {
                confirmCallback: options
            };
        }
        let settings = extend({
            // These are the defaults.
            method: 'get',
            dataAddition: {},
            message: window.capp?.labels?.confirm?.areYouSure ?? 'Are you sure?',
            onConfirmed: false,
            confirmCallback: false,
            owner: null
        }, options);
        const confirmCallback = settings.confirmCallback ? settings.confirmCallback : settings.onConfirmed;
        if(this.confirmHandler) {
            return this.confirmHandler(settings.owner, settings, confirmCallback);
        }
        if (window.bootbox) {
            return window.bootbox.confirm(settings.message, confirmCallback);
        }
    }
    modal(options) {
        let settings = extend({
            // These are the defaults.
            haveHeader: false,
            haveFooter: false,
            headerText: '',
            backdrop: 'static',
            modalClass: false,
            onClose: false,
            appendTo: false,
            footerAction: {}
        }, options);

        if (settings.title) {
            settings.haveHeader = true;
            settings.headerText = settings.title;
        }

        let modalContainer = jQuery('<div>').addClass('modal cres-modal').attr('role', 'dialog');

        if (settings.modalClass) {
            modalContainer.addClass(settings.modalClass);
        }

        if (settings.isSidebar) {
            modalContainer.addClass('sidebar');
            modalContainer.addClass(settings.sidebarMode);
        }
        if (settings.isFull) {
            modalContainer.addClass('sidebar full');
        }
        let modalDialog = jQuery('<div>').addClass('modal-dialog modal-xl').attr('role', 'document');
        let modalContent = jQuery('<div>').addClass('modal-content');

        let modalHeader = jQuery('<div>').addClass('modal-header');
        let modalTitle = jQuery('<div>').addClass('modal-title');
        let modalButtonClose = jQuery('<button type="button" class="close" aria-label="Close"><span aria-hidden="true">&times;</span></button>');
        modalButtonClose.click(() => {
            modalButtonClose.closest('.modal').modal('hide');
        });
        let modalBody = jQuery('<div>').addClass('modal-body');
        let modalFooter = jQuery('<div>').addClass('modal-footer');
        modalDialog.append(modalContent);
        modalContainer.append(modalDialog);
        if (settings.haveHeader) {
            modalTitle.html(settings.headerText);
            modalHeader.append(modalTitle).append(modalButtonClose);
            modalContent.append(modalHeader);
            if(typeof modalTitle == 'string') {
                modalContainer.attr('aria-labelledby', modalTitle);
            }
        }
        modalDialog.append(modalContent);
        if (settings.haveFooter) {
            modalContent.append(modalFooter);
        }
        modalContent.append(modalBody);

        let appendTo = settings.appendTo;
        if (typeof appendTo === 'undefined' || !appendTo) {
            appendTo = $('body');
        }

        modalContainer.appendTo(appendTo);
        modalContainer.addClass('capp-modal');
        modalContainer.on('hidden.bs.modal', (e) => {
            if (this.modalElements.length > 0) {
                let lastModal = this.modalElements[this.modalElements.length - 1];
                if (lastModal && lastModal.get(0) === $(e.target).get(0)) {
                    let Next = function () {
                        this.isRunning = false;
                        this.callback = (delay) => {
                            let delayMs = delay;
                            if (typeof delayMs === 'undefined') {
                                delayMs = 0;
                            }
                            if (isNaN(parseInt(delayMs, 10))) {
                                delayMs = 0;
                            }

                            setTimeout(() => {
                                $(lastModal).remove();
                                window.cresenity.modalElements.pop();


                                let modalExists = $('.modal:visible').length > 0;
                                if (!modalExists) {
                                    $('body').removeClass('modal-open');
                                } else if (!$('body').hasClass('modal-open')) {
                                    $('body').addClass('modal-open');
                                }
                            }, delayMs);
                            this.isRunning = true;
                        };
                    };
                    let next = new Next();
                    if (typeof settings.onClose === 'function') {
                        settings.onClose(e, next.callback);
                    }
                    if (!next.isRunning) {
                        next.callback();
                    }
                }
            }
        });

        modalContainer.on('shown.bs.modal', () => {
            this.modalElements.push(modalContainer);
        });

        if (settings.message) {
            modalBody.append(settings.message);
        }
        if (settings.reload) {
            let reloadOptions = settings.reload;
            reloadOptions.selector = modalBody;
            this.reload(reloadOptions);
        }
        if(bootstrapHelper.isBootstrap5()) {
            let modal = new window.bootstrap.Modal(modalContainer[0], {
                backdrop: settings.backdrop
            });
            modal.show();
        } else {
            modalContainer.modal({
                backdrop: settings.backdrop
            });
        }

        return modalContainer;
    }

    closeLastModal() {
        if (this.modalElements.length > 0) {
            let lastModal = this.modalElements[this.modalElements.length - 1];

            lastModal.modal('hide');
        }
    }
    closeAllDialog() {
        if (this.modalElements.length > 0) {
            for(let i = this.modalElements.length-1; i>=0; i--) {
                const modal = this.modalElements[i];
                modal.modal('hide');
            }
        }
    }
    closeDialog() {
        this.closeLastModal();
    }
    ajax(options) {
        let settings = extend({
            block: true,
            url: window.location.href,
            method: 'post'
        }, options);
        let dataAddition = settings.dataAddition;
        let requestData = settings.data;
        if (typeof requestData === 'undefined') {
            requestData = {};
        }
        if(dataAddition) {
            requestData = extend(requestData, dataAddition);
        }
        let url = settings.url;
        url = this.url.replaceParam(url);
        if (settings.block) {
            this.blockPage();
        }

        let validationIsValid = true;
        let ajaxOptions = {
            url: url,
            dataType: 'json',
            data: requestData,
            type: settings.method,

            success: (response) => {
                let onSuccess = () => {};
                let onError = (errMessage) => {
                    this.showError(errMessage);
                };
                if (typeof settings.onSuccess === 'function' && validationIsValid) {
                    onSuccess = settings.onSuccess;
                }
                if (typeof settings.onError === 'function' && validationIsValid) {
                    onError = settings.onError;
                }

                if (validationIsValid) {
                    if (settings.handleJsonResponse === true) {
                        this.handleJsonResponse(response, onSuccess, onError);
                    } else {
                        onSuccess(response);
                    }
                }
            },
            error: (xhr, errorAjaxOptions, thrownError) => {
                if(xhr.readyState == 4) {
                    if(xhr.status==404) {
                        this.showError('Not Found');
                    }
                }
                if (thrownError !== 'abort') {
                    this.showError('Something went wrong' + (thrownError ? ' (' + thrownError + ')' : ''));
                }
            },

            complete: () => {
                if (settings.block) {
                    this.unblockPage();
                }

                if (typeof settings.onComplete === 'function' && validationIsValid) {
                    settings.onComplete();
                }
            }
        };

        return $.ajax(ajaxOptions);
    }
    ajaxSubmit(options) {
        let settings = extend({}, options);
        let selector = settings.selector;
        $(selector).each((index, element) => {
            //don't do it again if still loading

            let formAjaxUrl = $(element).attr('action') || '';
            let formMethod = $(element).attr('method') || 'get';
            this.blockElement($(element));
            let validationIsValid = true;
            let ajaxOptions = {
                url: formAjaxUrl,
                dataType: 'json',
                type: formMethod,
                beforeSubmit: () => {
                    if (typeof $(element).validate === 'function') {
                        validationIsValid = $(element).validate().form();
                        return validationIsValid;
                    }
                    return true;
                },
                success: (response) => {
                    let onSuccess = () => {};
                    let onError = (errMessage) => {
                        this.showError(errMessage);
                    };

                    let haveOnSuccess = false;
                    if (typeof settings.onSuccess === 'function' && validationIsValid) {
                        onSuccess = settings.onSuccess;
                        haveOnSuccess = true;
                    }
                    if (typeof settings.onError === 'function' && validationIsValid) {
                        onError = settings.onError;
                    }

                    if (validationIsValid) {
                        if (settings.handleJsonResponse === true && haveOnSuccess) {
                            this.handleJsonResponse(response, onSuccess, onError);
                        } else {
                            onSuccess(response);
                        }
                    }
                },

                complete: () => {
                    this.unblockElement($(element));

                    if (typeof settings.onComplete === 'function' && validationIsValid) {
                        settings.onComplete();
                    }
                }
            };
            $(element).ajaxSubmit(ajaxOptions);
        });
        //always return false to prevent submit
        return false;
    }

    debug(message) {
        if (this.isDebug()) {
            window.console.log(message);
        }
    }
    toast(type, message, options) {
        let settings = extend({
            title: ucfirst(type),
            position: 'top-right'
        }, options);

        if(window.toastr) {
            // Tipe tak dikenal dipetakan ke method valid; tanpa ini window.toastr[type] undefined dan meng-crash halaman (TB-14233).
            let method = resolveToastrMethod(type);
            if (typeof window.toastr[method] !== 'function') {
                method = null;
            }
            if (method) {
                return window.toastr[method](message, settings.title, {
                    positionClass: 'toast-'+settings.position,
                    closeButton: true,
                    progressBar: true,
                    preventDuplicates: false,
                    newestOnTop: false
                });
            }
        }
        return cresToast.toast(message);
    }
    message(type, message, alertType, callback) {
        alertType = typeof alertType !== 'undefined' ? alertType : 'notify';
        let container = $('#container');
        if (container.length === 0) {
            container = $('body');
        }
        if (alertType === 'bootbox' && window.bootbox) {
            if (typeof callback === 'undefined') {
                return window.bootbox.alert(message);
            }
            return window.bootbox.alert(message, callback);
        }

        if (alertType === 'notify') {
            let obj = $('<div>');
            container.prepend(obj);
            obj.addClass('notifications');
            obj.addClass('top-right');
            if (typeof obj.notify !== 'undefined') {
                return obj.notify({
                    message: {
                        text: message
                    },
                    type: type
                }).show();
            }
        }

        return this.toast(type, message);
    }


    scrollTo(element, container) {
        if (typeof container === 'undefined') {
            container = document.body;
        }
        $(container).animate({
            scrollTop: $(element).offset().top - ($(container).offset().top + $(container).scrollTop())
        });
    }

    replaceAll(string, find, replace) {
        let escapedFind = find.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, '\\$1');
        return string.replace(new RegExp(escapedFind, 'g'), replace);
    }

    formatCurrency(rp) {
        return formatCurrency(rp);
    }

    unformatCurrency(rp) {
        return unformatCurrency(rp);
    }

    getStyles(selector, only, except) {
        // the map to return with requested styles and values as KVP
        let product = {};

        // the style object from the DOM element we need to iterate through
        let style;

        // recycle the name of the style attribute
        let name;

        let element = $(selector);

        // if it's a limited list, no need to run through the entire style object
        if (only && only instanceof Array) {
            for (let i = 0, l = only.length; i < l; i++) {
                // since we have the name already, just return via built-in .css method
                name = only[i];
                product[name] = element.css(name);
            }
        } else if (element.length) {
            // otherwise, we need to get everything
            let dom = element.get(0);

            // standards
            if (window.getComputedStyle) {
                // convenience methods to turn css case ('background-image') to camel ('backgroundImage')
                let pattern = /\-([a-z])/g;
                let uc = (a, b) => {
                    return b.toUpperCase();
                };
                let camelize = (string) => {
                    return string.replace(pattern, uc);
                };

                // make sure we're getting a good reference
                if (style = window.getComputedStyle(dom, null)) {
                    let camel;
                    let value;
                    // opera doesn't give back style.length - use truthy since a 0 length may as well be skipped anyways
                    if (style.length) {
                        for (let i = 0, l = style.length; i < l; i++) {
                            name = style[i];
                            camel = camelize(name);
                            value = style.getPropertyValue(name);
                            product[camel] = value;
                        }
                    } else {
                        // opera
                        for (name in style) {
                            camel = camelize(name);
                            value = style.getPropertyValue(name) || style[name];
                            product[camel] = value;
                        }
                    }
                }
            } else if (style = dom.currentStyle) {
                // IE - first try currentStyle, then normal style object - don't bother with runtimeStyle
                for (name in style) {
                    product[name] = style[name];
                }
            } else if (style = dom.style) {
                for (name in style) {
                    if (typeof style[name] !== 'function') {
                        product[name] = style[name];
                    }
                }
            }
        }


        // remove any styles specified...
        // be careful on blacklist - sometimes vendor-specific values aren't obvious but will be visible...  e.g., excepting 'color' will still let '-webkit-text-fill-color' through, which will in fact color the text
        if (except && except instanceof Array) {
            for (let i = 0, l = except.length; i < l; i++) {
                name = except[i];
                delete product[name];
            }
        }

        // one way out so we can process blacklist in one spot
        return product;
    }

    createPlaceholderElement(selector, root, depth) {
        depth = parseInt(depth, 10);
        if (!Number.isInteger(depth)) {
            depth = 0;
        }
        let element = $(selector);
        if (element.length === 0) {
            return null;
        }

        root = root || element;
        let newElement = element.clone().empty();
        newElement.removeAttr('id');
        newElement.removeAttr('data-block-html');
        newElement.removeClass();


        if (!(element.is(':visible'))) {
            return null;
        }


        let styles = this.getStyles(element);


        if (depth > 0) {
            //newElement.addClass('remove-after');
            //newElement.addClass('remove-before');
            if (element.children(':visible:not(:empty)').length === 0) {
                let relativeY = element.offset().top - root.offset().top;
                let relativeX = element.offset().left - root.offset().left;
                styles.width = '' + element.outerWidth() + 'px';
                styles.height = '' + (element.outerHeight() - 8) + 'px';
                styles.position = 'absolute';
                styles.top = '' + (relativeY + 4) + 'px';
                styles.left = '' + relativeX + 'px';
                styles.backgroundColor = '#ced4da';
            }
        }

        styles.border = '0';
        styles.borderRadius = '0';
        styles.overflow = 'hidden';

        switch (element.prop('tagName').toLowerCase()) {
            case 'ul':
                styles.padding = '0px';
                break;
            case 'li':
                styles.listStyle = 'none';
                break;
            default:
                break;
        }
        if (depth === 0) {
            styles.position = 'relative';
        }
        newElement.css(styles);
        if (depth === 0) {
            newElement.addClass('capp-ph-item');
            newElement.attr('style', (i, s) => {
                return (s || '') + 'margin: 0 !important;';
            });
        }
        element.children().each((idx, item) => {
            let newChild = this.createPlaceholderElement(item, root, depth + 1);
            if (newChild) {
                newElement.append(newChild);
            }
        });
        return newElement;
    }
    blockPage(options) {
        let settings = extend({
            innerMessage: '<div class="sk-folding-cube sk-primary"><div class="sk-cube1 sk-cube"></div><div class="sk-cube2 sk-cube"></div><div class="sk-cube4 sk-cube"></div><div class="sk-cube3 sk-cube"></div></div><h5 style="color: #444">LOADING...</h5>'
        }, options);
        $.blockUI({
            message: settings.innerMessage,
            css: {
                backgroundColor: 'transparent',
                border: '0',
                zIndex: 9999999
            },
            overlayCSS: {
                backgroundColor: '#fff',
                opacity: 0.8,
                zIndex: 9999990
            }
        });
    }
    unblockPage() {
        $.unblockUI();
    }
    blockElement(selector, options) {
        const blockHtml = window?.capp?.block?.html ?? '<div class="sk-wave sk-primary"><div class="sk-rect sk-rect1"></div> <div class="sk-rect sk-rect2"></div> <div class="sk-rect sk-rect3"></div> <div class="sk-rect sk-rect4"></div> <div class="sk-rect sk-rect5"></div></div>';
        let settings = extend({
            innerMessage: blockHtml
        }, options);

        $(selector).block({
            message: settings.innerMessage,
            css: {
                backgroundColor: 'transparent',
                border: '0'
            },
            overlayCSS: {
                backgroundColor: '#fff',
                opacity: 0.8
            }
        });
    }
    unblockElement(selector) {
        $(selector).unblock();
    }
    arrayValue(elms) {
        elms = $(elms);
        if (elms.length === 0) {
            return [];
        }
        return elms.map((index, elm) => {
            return this.value(elm);
        }).get();
    }
    value(elm) {
        elm = $(elm);
        if (elm.length === 0) {
            return null;
        }
        if (elm.attr('type') === 'checkbox') {
            if (!elm.is(':checked')) {
                return null;
            }
        }
        if (elm.attr('type') === 'radio') {
            if (!elm.is(':checked')) {
                return null;
            }
        }
        if (typeof elm.val() !== 'undefined') {
            return elm.val();
        }
        if (typeof elm.attr('value') !== 'undefined') {
            return elm.attr('value');
        }
        return elm.html();
    }
    isUsingBootstrap() {
        return bootstrapHelper.isBootstrap();
    }
    initDependency() {
        bootstrapHelper.check();
    }
    initConfirm() {
        elementRendered('a.confirm, button.confirm, input[type=submit].confirm', (el)=>{
            $(el).click((e)=>{
                e.preventDefault();
                e.stopPropagation();
                confirmFromElement(el, this.confirmHandler);
                return false;
            });
        });

        jQuery(document).ready(() => {
            jQuery('#toggle-subnavbar').click(() =>{
                let cmd = jQuery('#toggle-subnavbar span').html();
                if (cmd === 'Hide') {
                    jQuery('#subnavbar').slideUp('slow');
                    jQuery('#toggle-subnavbar span').html('Show');
                } else {
                    jQuery('#subnavbar').slideDown('slow');
                    jQuery('#toggle-subnavbar span').html('Hide');
                }
            });
            jQuery('#toggle-fullscreen').click(() => {
                toggleFullscreen(document.documentElement);
            });
        });
    }
    initReload() {
        let reloadInitialized = $('body').attr('data-reload-initialized');
        if (!reloadInitialized) {
            $('.capp-reload').each((idx, item) => {
                if(!$(item).hasClass('capp-reloaded')) {
                    let reloadOptions = {};
                    reloadOptions.selector = $(item);
                    this.reload(reloadOptions);
                    $(item).addClass('capp-reloaded');
                }
            });
            $('body').attr('data-reload-initialized', '1');
        }
    }
    initValidation() {
        if($ && $.validator) {
            initValidation();
        }
    }
    initPlugin() {
        initPlugin();
    }
    initElement() {
        initElement();
    }

    initWaves() {
        if($) {
            const selector = window.capp?.waves?.selector ?? '.cres-waves-effect';
            $(selector).each((index, item) => {
                if(!$(item).hasClass('cres-waves-effect')) {
                    $(item).addClass('cres-waves-effect');
                }
                attachWaves(item);
            });
        }
    }
    applyDeferXData() {
        const comp = document.querySelector('[defer-x-data]');
        if(comp) {
            comp.setAttribute('x-data', comp.getAttribute('defer-x-data'));
            //window.Alpine.start();
        }
    }
    initCssDomVar() {
        initCssDomVar();
    }
    initHistory() {
        this.history = CresenityHistory;
        if (!this.history.options || !this.history.options.delayInit) {
            this.history.init();
            this.history.Adapter.bind(window, 'statechange', function () { // Note: We are using statechange instead of popstate
                dispatchWindowEvent('cresenity:history:statechange');
            });
        }
    }
    initAlpineAndUi() {
        window.Alpine = Alpine;
        this.alpine = new CresAlpine(window.Alpine);
        this.cresStyle = cresStyle;
        window.Alpine.start();
    }

    initLiveReload() {
        if(!this.cf.isProduction() && this.cf.config.vscode.liveReload.enable) {
            new Promise((resolve, reject) => {
                const rsocket = new WebSocket(this.cf.config.vscode.liveReload.protocol + '://' +this.cf.config.vscode.liveReload.host+ ':'+this.cf.config.vscode.liveReload.port+'/', 'reload-protocol');
                rsocket.onmessage = function (msg) {
                    if (msg.data == 'RELOAD') {
                        location.reload();
                    }
                };

                rsocket.onerror = function () {
                    reject('couldn\'t connect');
                };
            }).catch(function (err) {
                //do nothing
                //console.log("Catch Live Reload handler sees: ", err)
            });
        }
    }
    init() {
        this.cf.onBeforeInit(() => {
            this.normalizeRequireJs();
        });
        this.cf.onAfterInit(() => {
            if (this.cf.getConfig().haveScrollToTop) {
                if (!document.getElementById('cres-topcontrol')) {
                    this.scrollToTop.init();
                }
            }
            this.initDependency();
            this.initElement();
            this.initConfirm();
            this.initReload();
            this.initValidation();
            this.initPlugin();
            this.initWaves();
            this.initAlpineAndUi();
            this.initCssDomVar();
            this.initHistory();

            this.initLiveReload();
            initProgressive();
            let root = document.getElementsByTagName('html')[0]; // '0' to assign the first (and only `HTML` tag)

            root.classList.add('cresenity-loaded');
            root.classList.remove('no-js');
            domReady(() => {
                removePreloader(()=>{
                    initThemeMode(this.theme.localStorageKey);
                    initMenu();
                });
                dispatchWindowEvent('cresenity:loaded');
            });
            this.applyDeferXData();
        });


        this.cf.init();
    }
    downloadProgress(options) {
        this.download.start(options);
    }
    reactive(data, cb) {
        const reactiveData =Alpine.reactive(data);
        if(typeof cb == 'function') {
            Alpine.effect(() => {
                cb(reactiveData);
            });
        }

        return reactiveData;
    }
    getAlpineData(node) {
        if(typeof node == 'string') {
            node = document.querySelector(node);
        }
        return this.alpine.getAlpineDataInstance(node);
    }
    handleJsonResponse(response, onSuccess, onError) {
        let errMessage = 'Unexpected error happened, please re-login or refresh this page';
        if (typeof onError == 'string') {
            errMessage = onError;
        }

        if (response.errCode == 0) {
            if (typeof onSuccess == 'function') {
                onSuccess(response.data);
            }
        } else {
            if (typeof response.errMessage != 'undefined') {
                errMessage = response.errMessage;
            }
            if (typeof onError == 'function') {
                onError(errMessage);
            } else {
                this.showError(errMessage);
            }
        }
    }

    showError(errMessage) {
        this.toast('error', errMessage);
    }

    inViewPort(el) {
        // Special bonus for those using jQuery
        if (typeof jQuery === 'function' && el instanceof jQuery) {
            el = el[0];
        }

        const rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && /* or $(window).height() */
            rect.right <= (window.innerWidth || document.documentElement.clientWidth) /* or $(window).width() */
        );
    }
    tzDate(date = null) {
        // Get local time as ISO string with offset at the end
        let now = new Date();
        if(date!==null) {
            now = date;
        }
        const tzo = -now.getTimezoneOffset();
        const dif = tzo >= 0 ? '+' : '-';
        const pad = function (num, ms) {
            let norm = Math.floor(Math.abs(num));
            if (ms) {return (norm < 10 ? '00' : norm < 100 ? '0' : '') + norm;}
            return (norm < 10 ? '0' : '') + norm;
        };
        return now.getFullYear()
            + '-' + pad(now.getMonth()+1)
            + '-' + pad(now.getDate())
            + 'T' + pad(now.getHours())
            + ':' + pad(now.getMinutes())
            + ':' + pad(now.getSeconds())
            + '.' + pad(now.getMilliseconds(), true)
            + dif + pad(tzo / 60)
            + ':' + pad(tzo % 60);
    }
    randomGUID() {
        let d = new Date().getTime();
        if (typeof performance !== 'undefined' && typeof performance.now === 'function') {
            d += performance.now(); //use high-precision timer if available
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            let r = (d + Math.random() * 16) % 16 | 0;
            d = Math.floor(d / 16);
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }
    getScrollParent(element, includeHidden = false) {
        let style = getComputedStyle(element);
        let excludeStaticParent = style.position === 'absolute';
        let overflowRegex = includeHidden ? /(auto|scroll|hidden)/ : /(auto|scroll)/;

        if (style.position === 'fixed') {return document.body;}
        for (let parent = element; (parent = parent.parentElement);) {
            style = getComputedStyle(parent);
            if (excludeStaticParent && style.position === 'static') {
                continue;
            }
            if (overflowRegex.test(style.overflow + style.overflowY + style.overflowX)) {return parent;}
        }

        return document.body;
    }

    startCheckAuthenticationInterval(intervalTime = 10000, callback) {
        if(this.checkAuthenticationInterval) {
            return;
        }
        this.checkAuthenticationInterval = setInterval(() => {
            const baseUrl = window?.capp?.baseUrl ?? '/';
            const sessionName = window?.capp?.sessionName ?? null;
            let sid = null;
            if(sessionName) {
                if (typeof document !== 'undefined') {
                    const match = document.cookie.match(new RegExp('(^|;\\s*)('+sessionName+')=([^;]*)'));
                    sid = match ? decodeURIComponent(match[3]) : null;
                }
            }
            let url = baseUrl + 'cresenity/auth/ping';
            $.ajax({
                type: 'get',
                url: url,
                dataType: 'json',
                success: (responseAuth) => {
                    this.handleJsonResponse(responseAuth, (dataAuth) => {
                        callback(dataAuth);
                    });
                }
            });
        }, intervalTime);
    }

    stopCheckAuthenticationInterval() {
        if(this.checkAuthenticationInterval) {
            clearInterval(this.checkAuthenticationInterval);
        }
    }
    onAuthExpired(callback, intervalTime = 1000) {
        this.stopCheckAuthenticationInterval();
        this.startCheckAuthenticationInterval(intervalTime, (data)=>{
            if(data && !data.isLogin) {
                callback(data);
            }
        });
    }
}

import Url from './module/Url';
import cf from './CF';
import ScrollToTop from './module/ScrollToTop';
import UI from './ui';
import {
    dispatch as dispatchWindowEvent,
    showHtmlModal,
    showUrlModal,
    toggleFullscreen
} from './util';
import {encode as base64encode, decode as base64decode} from './util/base64';
import php from './php';
import { elementReady, elementRendered } from './util/dom-observer';
import { debounce } from './util/debounce';
import { confirmFromElement, defaultConfirmHandler } from './module/confirm-handler';
import initValidation from './module/validation';
import ucfirst from 'locutus/php/strings/ucfirst';
import Alpine from 'alpinejs';
import cresReact from './react';
import CSocket from './csocket/CSocket';
import removePreloader from './module/preloader';
import initProgressive from './module/progressive';
import cresToast from './module/toast';
import CresAlpine from './module/CresAlpine';
import SSE from './module/SSE';

export default class Cresenity {
    constructor() {
        this.cf = cf;
        this.base64 = {
            encode: base64encode,
            decode: base64decode
        };
        this.windowEventList = [
            'cresenity:confirm',
            'cresenity:jquery:loaded',
            'cresenity:loaded',
            'cresenity:ui:start'
        ];
        this.modalElements = [];
        this.cresenityEventList = [

        ];
        this.url = new Url();
        this.scrollToTop = new ScrollToTop();
        this.callback = {};
        this.filesAdded = [];
        this.ui = new UI();
        this.php = php;
        this.react = cresReact;
        this.observer = {
            elementRendered: elementRendered,
            elementReady: elementReady
        };
        this.confirmHandler = defaultConfirmHandler;
        this.dispatchWindowEvent = dispatchWindowEvent;
        this.websocket = null;
        this.debounce = debounce;
        this.sse = new SSE();
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

    on(eventName, cb) {

    }


    handleResponse(data, callback) {
        if (data.cssRequire && data.cssRequire.length > 0) {
            for (let i = 0; i < data.cssRequire.length; i++) {
                this.cf.require(data.cssRequire[i], 'css');
            }
        }

        if (data.css_require && data.css_require.length > 0) {
            for (let i = 0; i < data.css_require.length; i++) {
                this.cf.require(data.css_require[i], 'css');
            }
        }

        callback();
    }
    htmlModal(html) {
        showHtmlModal(html);
    }
    urlModal(url) {
        showUrlModal(url);
    }
    handleAjaxError(xhr, status, error) {
        if (error !== 'abort') {
            this.message('error', 'Error, please call administrator... (' + error + ')');
            if(xhr.status!=200) {
                if(window.capp && window.capp.environment && window.capp.environment!=='production') {
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

        let settings = $.extend({
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
            url = this.url.addQueryString(url, 'capp_current_container_id', idTarget);


            if (typeof settings.onBlock === 'function') {
                settings.onBlock($(element));
            } else {
                this.blockElement($(element));
            }

            $(element).data('xhr', $.ajax({
                type: method,
                url: url,
                dataType: 'json',
                data: dataAddition,
                success: (data) => {
                    let isError = false;
                    if(typeof data.html === 'undefined') {
                        //error
                        this.htmlModal(data);
                        isError = true;
                    }
                    if(!isError) {
                        this.doCallback('onReloadSuccess', data);

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
                    this.handleAjaxError(errorXhr, ajaxOptions, thrownError);
                },
                complete: () => {
                    $(element).data('xhr', false);
                    if (typeof settings.onBlock === 'function') {
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
    confirm(options) {
        let settings = $.extend({
            // These are the defaults.
            method: 'get',
            dataAddition: {},
            message: 'Are you sure?',
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
        let settings = $.extend({
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

        let modalContainer = jQuery('<div>').addClass('modal');

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
        let modalDialog = jQuery('<div>').addClass('modal-dialog modal-xl');
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

        modalContainer.modal({
            backdrop: settings.backdrop
        });

        return modalContainer;
    }

    closeLastModal() {
        if (this.modalElements.length > 0) {
            let lastModal = this.modalElements[this.modalElements.length - 1];

            lastModal.modal('hide');
        }
    }
    closeDialog() {
        this.closeLastModal();
    }
    ajax(options) {
        let settings = $.extend({
            block: true,
            url: window.location.href,
            method: 'post'
        }, options);
        let dataAddition = settings.dataAddition;
        let url = settings.url;
        url = this.url.replaceParam(url);
        if (typeof dataAddition === 'undefined') {
            dataAddition = {};
        }
        if (settings.block) {
            this.blockPage();
        }

        let validationIsValid = true;
        let ajaxOptions = {
            url: url,
            dataType: 'json',
            data: dataAddition,
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
                if (thrownError !== 'abort') {
                    this.showError(thrownError);
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
        let settings = $.extend({}, options);
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
        if (this.cf.getConfig().debug) {
            window.console.log(message);
        }
    }
    toast(type, message, options) {
        let settings = $.extend({
            title: ucfirst(type),
            position: 'top-right'
        }, options);

        if(window.toastr) {
            return window.toastr[type](message, settings.title, {
                positionClass: 'toast-'+settings.position,
                closeButton: true,
                progressBar: true,
                preventDuplicates: false,
                newestOnTop: false
            });
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
        rp = '' + rp;
        let rupiah = '';
        let vfloat = '';
        let ds = window.capp.decimal_separator;
        let ts = window.capp.thousand_separator;
        let dd = window.capp.decimal_digit;
        dd = parseInt(dd, 10);
        let minusStr = '';
        if (rp.indexOf('-') >= 0) {
            minusStr = rp.substring(rp.indexOf('-'), 1);
            rp = rp.substring(rp.indexOf('-') + 1);
        }

        if (rp.indexOf('.') >= 0) {
            vfloat = rp.substring(rp.indexOf('.'));
            rp = rp.substring(0, rp.indexOf('.'));
        }
        let p = rp.length;
        while (p > 3) {
            rupiah = ts + rp.substring(p - 3) + rupiah;
            let l = rp.length - 3;
            rp = rp.substring(0, l);
            p = rp.length;
        }
        rupiah = rp + rupiah;
        vfloat = vfloat.replace('.', ds);
        if (vfloat.length > dd) {
            vfloat = vfloat.substring(0, dd + 1);
        }
        return minusStr + rupiah + vfloat;
    }

    unformatCurrency(rp) {
        if (typeof rp == 'undefined') {
            rp = '';
        }
        let ds = window.capp.decimal_separator;
        let ts = window.capp.thousand_separator;
        let last3 = rp.substr(rp.length - 3);
        let char_last3 = last3.charAt(0);
        if (char_last3 != ts) {
            rp = this.replaceAll(rp, ts, '');
        }

        rp = rp.replace(ds, '.');
        return rp;
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
        let settings = $.extend({
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
        let settings = $.extend({
            innerMessage: '<div class="sk-wave sk-primary"><div class="sk-rect sk-rect1"></div> <div class="sk-rect sk-rect2"></div> <div class="sk-rect sk-rect3"></div> <div class="sk-rect sk-rect4"></div> <div class="sk-rect sk-rect5"></div></div>'
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


    initAlpineAndUi() {
        window.Alpine = Alpine;
        this.ui.start();
        window.Alpine.start();
        this.alpine = new CresAlpine(window.Alpine);
    }

    initLiveReload() {
        if(!this.cf.isProduction() && this.cf.config.vscode.liveReload.enable) {
            try {
                const rsocket = new WebSocket(this.cf.config.vscode.liveReload.protocol + '://' +this.cf.config.vscode.liveReload.host+ ':'+this.cf.config.vscode.liveReload.port+'/', 'reload-protocol');
                rsocket.onmessage = function (msg) {
                    if (msg.data == 'RELOAD') {
                        location.reload();
                    }
                };
            }catch(e) {
                //do nothing
            }
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
            this.initConfirm();
            this.initReload();
            this.initValidation();
            this.initAlpineAndUi();
            this.initLiveReload();
            initProgressive();
            let root = document.getElementsByTagName('html')[0]; // '0' to assign the first (and only `HTML` tag)

            root.classList.add('cresenity-loaded');
            root.classList.remove('no-js');
            dispatchWindowEvent('cresenity:loaded');
        });


        this.cf.init();
    }
    downloadProgress(options) {
        let settings = $.extend({
            // These are the defaults.
            method: 'get',
            dataAddition: {},
            url: '/',
            onComplete: false,
            onSuccess: false,
            onBlock: false,
            onUnblock: false
        }, options);


        let method = settings.method;

        let xhr = jQuery(window).data('cappXhrProgress');
        if (xhr) {
            xhr.abort();
        }

        let dataAddition = settings.dataAddition;
        let url = settings.url;
        url = this.url.replaceParam(url);
        if (typeof dataAddition === 'undefined') {
            dataAddition = {};
        }


        if (typeof settings.onBlock === 'function') {
            settings.onBlock();
        } else {
            this.blockPage();
        }

        $.ajax({
            type: method,
            url: url,
            dataType: 'json',
            data: dataAddition,
            success: (response) => {
                this.handleJsonResponse(response, (data) => {
                    let progressUrl = data.progressUrl;
                    let progressContainer = $('<div>').addClass('cres-download-progress');

                    const interval = setInterval(() => {
                        $.ajax({
                            type: method,
                            url: progressUrl,
                            dataType: 'json',
                            success: (responseProgress) => {
                                this.handleJsonResponse(responseProgress, (dataProgress) => {
                                    let progressContainerStatus = progressContainer.find('.cres-download-progress-status');
                                    if (dataProgress.state === 'DONE') {
                                        progressContainerStatus.empty();
                                        let innerStatus = $('<div>');

                                        let innerStatusLabel = $('<label>', {
                                            class: 'mb-3 d-block'
                                        }).append('Your file is ready');
                                        let linkDownload = $('<a>', {
                                            target: '_blank',
                                            href: dataProgress.fileUrl,
                                            class: 'btn btn-primary'
                                        }).append('Download');
                                        let linkClose = $('<a>', {
                                            href: 'javascript:;',
                                            class: 'btn btn-primary ml-3'
                                        }).append('Close');

                                        innerStatus.append(innerStatusLabel);
                                        innerStatus.append(linkDownload);
                                        innerStatus.append(linkClose);

                                        progressContainerStatus.append(innerStatus);
                                        linkClose.click(() => {
                                            this.closeLastModal();
                                        });
                                        clearInterval(interval);
                                    } else {
                                        if(dataProgress.state === 'PENDING') {
                                            let progressValue = parseFloat(dataProgress.progressValue);
                                            if(progressValue>0) {
                                                let progressStatusBar = progressContainer.find('.cres-download-progress-status-bar');
                                                if(progressStatusBar.length==0) {
                                                    //create the status bar
                                                    let progressAnimation = progressContainer.find('.cres-download-progress-animation');
                                                    progressAnimation.empty();
                                                    let progressStatusBar = $('<div class="cres-download-progress-status-bar my-4">');
                                                    let progress = $('<div class="progress">');
                                                    let progressBar = $('<div class="progress-bar progress-bar-striped progress-bar-animated">');
                                                    progressAnimation.append(
                                                        progressStatusBar.append(progress.append(progressBar))
                                                    );
                                                }

                                                let progressMax = parseFloat(dataProgress.progressMax);
                                                if(isNaN(progressMax) || progressMax==0) {
                                                    progressMax = 100;
                                                }

                                                let progressBar = progressStatusBar.find('.progress-bar');
                                                let progressPercent = Math.round(progressMax>0 ? progressValue * 100 / progressMax : 0);

                                                progressBar.css('width', progressPercent + '%');
                                                progressBar.html(progressPercent + '%');
                                            }
                                        }
                                    }
                                });
                            }
                        });
                    }, 3000);

                    let innerStatus = $('<div>');
                    let innerStatusLabel = $('<label>', {
                        class: 'mb-4'
                    }).append('Please Wait...');
                    let innerStatusAnimation = $('<div class="cres-download-progress-animation">').append('<div class="sk-fading-circle sk-primary"><div class="sk-circle1 sk-circle"></div><div class="sk-circle2 sk-circle"></div><div class="sk-circle3 sk-circle"></div><div class="sk-circle4 sk-circle"></div><div class="sk-circle5 sk-circle"></div><div class="sk-circle6 sk-circle"></div><div class="sk-circle7 sk-circle"></div><div class="sk-circle8 sk-circle"></div><div class="sk-circle9 sk-circle"></div><div class="sk-circle10 sk-circle"></div><div class="sk-circle11 sk-circle"></div><div class="sk-circle12 sk-circle"></div></div>');
                    let innerStatusAction = $('<div>', {
                        class: 'text-center my-3'
                    });
                    let innerStatusCancelButton = $('<button>', {
                        class: 'btn btn-primary'
                    }).append('Cancel');
                    innerStatusAction.append(innerStatusCancelButton);
                    innerStatus.append(innerStatusLabel);
                    innerStatus.append(innerStatusAnimation);
                    innerStatus.append(innerStatusAction);
                    progressContainer.append($('<div class="text-center">').addClass('cres-download-progress-status')
                        .append(innerStatus));

                    innerStatusCancelButton.click(() => {
                        clearInterval(interval);
                        this.closeLastModal();
                    });


                    this.modal({
                        message: progressContainer,
                        modalClass: 'cres-modal-download-progress'
                    });
                });
            },
            error: (xhrError, ajaxOptions, thrownError) => {
                if (thrownError !== 'abort') {
                    this.message('error', 'Error, please call administrator... (' + thrownError + ')');
                }
            },
            complete: () => {
                if (typeof settings.onBlock === 'function') {
                    settings.onUnblock();
                } else {
                    this.unblockPage();
                }

                if (typeof settings.onComplete === 'function') {
                    settings.onComplete();
                }
            }
        });
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
        let errMessage = 'Unexpected error happen, please relogin ro refresh this page';
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
}

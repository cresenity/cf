import extend from '../core/extend';

const POLL_INTERVAL = 3000;

const SPINNER_HTML = '<div class="sk-fading-circle sk-primary">'
    + '<div class="sk-circle1 sk-circle"></div>'
    + '<div class="sk-circle2 sk-circle"></div>'
    + '<div class="sk-circle3 sk-circle"></div>'
    + '<div class="sk-circle4 sk-circle"></div>'
    + '<div class="sk-circle5 sk-circle"></div>'
    + '<div class="sk-circle6 sk-circle"></div>'
    + '<div class="sk-circle7 sk-circle"></div>'
    + '<div class="sk-circle8 sk-circle"></div>'
    + '<div class="sk-circle9 sk-circle"></div>'
    + '<div class="sk-circle10 sk-circle"></div>'
    + '<div class="sk-circle11 sk-circle"></div>'
    + '<div class="sk-circle12 sk-circle"></div>'
    + '</div>';

/**
 * The small modal a download-progress action opens: a spinner that polls
 * progressUrl every POLL_INTERVAL, then swaps to a download link once the
 * server reports state 'DONE'.
 */
class DownloadProgressModal {
    constructor(cresenity) {
        this.cresenity = cresenity;
        this.interval = null;
        this.statusEl = null;
    }

    open(progressUrl, method) {
        const container = $('<div>').addClass('cres-download-progress');
        this.statusEl = $('<div class="text-center">').addClass('cres-download-progress-status');
        container.append(this.statusEl.append(this.buildInitialStatus()));

        this.cresenity.modal({
            message: container,
            modalClass: 'cres-modal-download-progress'
        });

        this.interval = setInterval(() => this.poll(progressUrl, method), POLL_INTERVAL);
    }

    buildInitialStatus() {
        const wrapper = $('<div>');
        const label = $('<label>', {class: 'mb-4'}).append('Please Wait...');
        const animation = $('<div class="cres-download-progress-animation">').append(SPINNER_HTML);
        const actionContainer = $('<div>', {class: 'text-center my-3'});
        const cancelButton = $('<button>', {class: 'btn btn-primary'}).append('Cancel');

        cancelButton.click(() => this.cancel());

        actionContainer.append(cancelButton);
        wrapper.append(label).append(animation).append(actionContainer);

        return wrapper;
    }

    poll(progressUrl, method) {
        $.ajax({
            type: method,
            url: progressUrl,
            dataType: 'json',
            success: (response) => {
                this.cresenity.handleJsonResponse(response, (data) => {
                    if (data.state === 'DONE') {
                        this.renderDone(data);
                    } else if (data.state === 'PENDING') {
                        this.renderPending(data);
                    }
                });
            }
        });
    }

    renderDone(data) {
        clearInterval(this.interval);

        this.statusEl.empty();

        const label = $('<label>', {class: 'mb-3 d-block'}).append('Your file is ready');
        const downloadLink = $('<a>', {
            target: '_blank',
            href: data.fileUrl,
            class: 'btn btn-primary'
        }).append('Download');
        const closeLink = $('<a>', {
            href: 'javascript:;',
            class: 'btn btn-primary ml-3'
        }).append('Close');

        closeLink.click(() => this.cresenity.closeLastModal());

        this.statusEl.append($('<div>').append(label).append(downloadLink).append(closeLink));
    }

    renderPending(data) {
        const progressValue = parseFloat(data.progressValue);
        if (!(progressValue > 0)) {
            return;
        }

        let statusBar = this.statusEl.find('.cres-download-progress-status-bar');
        if (statusBar.length === 0) {
            const animationEl = this.statusEl.find('.cres-download-progress-animation');
            animationEl.empty();

            statusBar = $('<div class="cres-download-progress-status-bar my-4 d-flex align-items-center">');
            const progress = $('<div class="progress flex-grow-1">');
            const progressBar = $('<div class="progress-bar progress-bar-striped progress-bar-animated">');
            const percentLabel = $('<span class="cres-download-progress-percent ms-2">');
            animationEl.append(statusBar.append(progress.append(progressBar)).append(percentLabel));
        }

        let progressMax = parseFloat(data.progressMax);
        if (isNaN(progressMax) || progressMax === 0) {
            progressMax = 100;
        }

        const progressBar = statusBar.find('.progress-bar');
        const percent = Math.round(progressMax > 0 ? progressValue * 100 / progressMax : 0);
        progressBar.css('width', percent + '%');
        statusBar.find('.cres-download-progress-percent').text(percent + '%');
    }

    cancel() {
        clearInterval(this.interval);
        this.cresenity.closeLastModal();
    }
}

export default class DownloadProgress {
    constructor(cresenity) {
        this.cresenity = cresenity;
    }

    start(options) {
        let cresenity = this.cresenity;
        let settings = extend({
            method: 'get',
            dataAddition: {},
            url: '/',
            onComplete: false,
            onSuccess: false,
            onBlock: false,
            onUnblock: false
        }, options);

        let url = cresenity.url.replaceParam(settings.url);
        let dataAddition = settings.dataAddition || {};

        if (typeof settings.onBlock === 'function') {
            settings.onBlock();
        } else {
            cresenity.blockPage();
        }

        let xhr = jQuery(window).data('cappXhrProgress');
        if (xhr) {
            xhr.abort();
        }

        $.ajax({
            type: settings.method,
            url: url,
            dataType: 'json',
            data: dataAddition,
            success: function (response) {
                cresenity.handleJsonResponse(response, function (data) {
                    new DownloadProgressModal(cresenity).open(data.progressUrl, settings.method);
                });
            },
            error: function (xhrError, ajaxOptions, thrownError) {
                if (thrownError !== 'abort') {
                    cresenity.message('error', 'Error, please call administrator... (' + thrownError + ')');
                }
            },
            complete: function () {
                if (typeof settings.onUnblock === 'function') {
                    settings.onUnblock();
                } else {
                    cresenity.unblockPage();
                }

                if (typeof settings.onComplete === 'function') {
                    settings.onComplete();
                }
            }
        });
    }
}

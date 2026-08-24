/* eslint-disable camelcase */

if(window.Dropzone) {
    window.Dropzone.autoDiscover = false;
}


// eslint-disable-next-line no-unused-vars
var CFileManager = function (options) {
    window.cfm = this;
    this.settings = $.extend({
        selector: '.capp-fm',
        connectorUrl: '/cresenity/connector/fm',
        sortType: 'alphabetic',
        lang: {
            'nav-upload': 'Upload'
        }
    }, options);

    this.dropzoneInitilized = false;
    this.multiSelectionEnabled = false;
    var fab = function (menu, options) {
        menu.addClass('fab-wrapper');
        var toggler = $('<a>')
            .addClass('fab-button fab-toggle')
            .append($('<i>').addClass('fas fa-plus'))
            .click(function () {
                menu.toggleClass('fab-expand');
            });
        menu.append(toggler);
        options.buttons.forEach(function (button) {
            toggler.before(
                $('<a>').addClass('fab-button fab-action')
                    .attr('data-label', button.label)
                    .attr('id', button.attrs.id)
                    .append($('<i>').addClass(button.icon))
                    .click(function () {
                        menu.removeClass('fab-expand');
                    })
            );
        });
    };

    this.selected = [];
    this.items = [];

    this.showList = 'grid';
    this.callback = {};

    this.controllerMethod = {};


    this.haveCallback = (name) => {
        return typeof this.callback[name] == 'function';
    };

    this.doCallback = (name, ...args) => {
        if (this.haveCallback(name)) {
            this.callback[name](...args);
        }
    };

    this.setCallback = (name, cb) => {
        this.callback[name] = cb;
    };

    // ==================================
    // ==     Base Function            ==
    // ==================================

    this.getUrlParam = (paramName) => {
        var reParam = new RegExp('(?:[\?&]|&)' + paramName + '=([^&]+)', 'i');
        var match = window.location.search.match(reParam);
        return (match && match.length > 1) ? match[1] : null;
    };


    // ==================================
    // ==     WYSIWYG Editors Check    ==
    // ==================================

    this.usingTinymce3 = () => {
        return !!window.tinyMCEPopup;
    };

    this.usingTinymce4AndColorbox = () => {
        return !!this.getUrlParam('field_name');
    };

    this.usingCkeditor3 = () => {
        return !!this.getUrlParam('CKEditor') || !!this.getUrlParam('CKEditorCleanUpFuncNum');
    };

    this.usingFckeditor2 = () => {
        return window.opener && typeof data != 'undefined' && window.data.Properties.Width != '';
    };

    this.usingWysiwygEditor = () => {
        return this.usingTinymce3() || this.usingTinymce4AndColorbox() || this.usingCkeditor3() || this.usingFckeditor2();
    };
    // ====================
    // ==  Ajax actions  ==
    // ====================

    this.performFmRequest = (url, parameter, type) => {
        var data = this.defaultParameters();
        if (parameter != null) {
            $.each(parameter, function (key, value) {
                data[key] = value;
            });
        }

        return $.ajax({
            type: 'GET',
            beforeSend: (request) => {
                var token = this.getUrlParam('token');
                if (token !== null) {
                    request.setRequestHeader('Authorization', 'Bearer ' + token);
                }
            },
            dataType: type || 'text',
            url: this.settings.connectorUrl + '/' + url,
            data: data,
            cache: false
        }).fail((jqXHR, textStatus, errorThrown) => {
            this.displayErrorResponse(jqXHR, textStatus, errorThrown);
        });
    };

    this.displayErrorResponse = (jqXHR) => {
        //console.log('Display Error Response');
        //try to get json from this response
        let data = null;
        let message = jqXHR.responseText;
        try {
            data = JSON.parse(message);
        } catch(e) {
            //do nothing
        }
        if (data !== null && typeof data == 'object' && data.message) {
            message = data.message;
        }


        this.notify('<div style="max-height:50vh;overflow: scroll;">' + message + '</div>');
    };

    this.notify = (body, callback) => {
        $('#notify').find('.btn-primary').toggle(callback !== undefined);
        $('#notify').find('.btn-primary').unbind().click(()=>{
            $('#notify').modal('hide');
            callback();
        });

        if (window.cresenity.isJson(body)) {
            let json = JSON.parse(body);
            let message = json.html;
            if(json.exception && json.message) {
                message = json.message;
            }
            $('#notify').find('.modal-body').html(message);
            if(json.js) {
                eval(window.cresenity.base64.decode(json.js));
            }
            $('#notify').modal('show');
        } else {
            $('#notify').modal('show').find('.modal-body').html(body);
        }
    };

    this.notImp = () => {
        this.notify('error', 'Not yet implemented!');
    };

    this.defaultParameters = () => {
        return {
            working_dir: $('#working_dir').val(),
            type: $('#type').val()
        };
    };

    this.dialog = (title, value, callback) => {
        $('#dialog').find('input').val(value);
        $('#dialog').on('shown.bs.modal', function () {
            $('#dialog').find('input').focus();
        });
        // eslint-disable-next-line no-unused-vars
        $('#dialog').find('.btn-primary').unbind().click(function (e) {
            $('#dialog').modal('hide');
            callback($('#dialog').find('input').val());
        });
        $('#dialog').find('.modal-title').text(title);
        $('#dialog').modal();
    };

    this.refreshFoldersAndItems = (data) => {
        this.loadFolders();
        if (data != 'OK') {
            data = Array.isArray(data) ? data.join('<br/>') : data;
            this.notify(data);
        }
    };

    this.loadFolders = () => {
        var reloadOptions = {};
        reloadOptions.selector = '#tree';
        reloadOptions.url = this.settings.connectorUrl + '/folder';
        // eslint-disable-next-line no-unused-vars
        reloadOptions.onSuccess = (data) => {
            this.loadItems();
        };
        window.cresenity.reload(reloadOptions);
    };


    // ======================
    // ==  Folder actions  ==
    // ======================

    this.goTo = (new_dir) => {
        $('#working_dir').val(new_dir);
        this.loadItems();
    };

    this.getPreviousDir = () => {
        var working_dir = $('#working_dir').val();
        if (working_dir) {
            return working_dir.substring(0, working_dir.lastIndexOf('/'));
        }
        return null;
    };

    this.setOpenFolders = () => {
        $('#tree [data-path]').each(function (index, folder) {
            // close folders that are not parent
            var should_open = ($('#working_dir').val() + '/').startsWith($(folder).data('path') + '/');
            $(folder).children('i')
                .toggleClass('fa-folder-open', should_open)
                .toggleClass('fa-folder', !should_open);
        });
        $('#tree .nav-item').removeClass('active');
        $('#tree [data-path="' + $('#working_dir').val() + '"]').parent('.nav-item').addClass('active');
    };


    this.controllerMethod.move = (items) => {
        if(items.length==0) {
            return this.displayErrorResponse('No items selected, please select item');
        }
        this.performFmRequest('move', {items: items.map(function (item) {
            return item.name;
        })}).done(this.refreshFoldersAndItems);
    };

    this.controllerMethod.open = (item) => {
        this.goTo(item.url);
    };

    this.controllerMethod.preview = (items) => {
        var carousel = $('#carouselTemplate').clone().attr('id', 'previewCarousel').removeClass('d-none');
        var imageTemplate = carousel.find('.carousel-item').clone().removeClass('active');
        var indicatorTemplate = carousel.find('.carousel-indicators > li').clone().removeClass('active');
        carousel.children('.carousel-inner').html('');
        carousel.children('.carousel-indicators').html('');
        carousel.children('.carousel-indicators,.carousel-control-prev,.carousel-control-next').toggle(items.length > 1);
        items.forEach(function (item, index) {
            var carouselItem = imageTemplate.clone()
                .addClass(index === 0 ? 'active' : '');
            if (item.thumb_url) {
                carouselItem.find('.carousel-image').css('background-image', 'url(\'' + item.url + '?timestamp=' + item.time + '\')');
            } else {
                carouselItem.find('.carousel-image').css('width', '50vh').append($('<div>').addClass('mime-icon ico ico-' + item.icon));
            }

            carouselItem.find('.carousel-label').attr('target', '_blank').attr('href', item.url)
                .append(item.name)
                .append($('<i class="fas fa-external-link-alt ml-2"></i>'));
            carousel.children('.carousel-inner').append(carouselItem);
            var carouselIndicator = indicatorTemplate.clone()
                .addClass(index === 0 ? 'active' : '')
                .attr('data-slide-to', index);
            carousel.children('.carousel-indicators').append(carouselIndicator);
        });
        // carousel swipe control
        var touchStartX = null;
        carousel.on('touchstart', function (event) {
            var e = event.originalEvent;
            if (e.touches.length == 1) {
                var touch = e.touches[0];
                touchStartX = touch.pageX;
            }
        }).on('touchmove', function (event) {
            var e = event.originalEvent;
            if (touchStartX != null) {
                var touchCurrentX = e.changedTouches[0].pageX;
                if ((touchCurrentX - touchStartX) > 60) {
                    touchStartX = null;
                    carousel.carousel('prev');
                } else if ((touchStartX - touchCurrentX) > 60) {
                    touchStartX = null;
                    carousel.carousel('next');
                }
            }
        }).on('touchend', function () {
            touchStartX = null;
        });
        // end carousel swipe control

        this.notify(carousel);
    };


    // ==========================
    // ==  Multiple Selection  ==
    // ==========================

    this.toggleSelected = (e) => {
        e.stopPropagation();
        if (!this.multiSelectionEnabled) {
            this.selected = [];
        }


        var sequence = $(e.target).closest('a').data('id');
        var elementIndex = this.selected.indexOf(sequence);

        if (elementIndex === -1) {
            this.selected.push(sequence);
        } else {
            this.selected.splice(elementIndex, 1);
        }

        this.updateSelectedStyle();
    };

    this.clearSelected = () => {
        this.selected = [];
        this.multiSelectionEnabled = false;
        this.updateSelectedStyle();
    };

    this.updateSelectedStyle = () => {
        this.items.forEach((item, index) => {
            $('[data-id=' + index + ']')
                .find('.square')
                .toggleClass('selected', this.selected.indexOf(index) > -1);
        });
        this.toggleActions();
    };

    this.getOneSelectedElement = (orderOfItem) => {
        var index = orderOfItem !== undefined ? orderOfItem : this.selected[0];
        return this.items[index];
    };

    this.getSelectedItems = () => {
        return this.selected.reduce((arrObjects, id) => {
            arrObjects.push(this.getOneSelectedElement(id));
            return arrObjects;
        }, []);
    };

    this.toggleActions = () => {
        var oneSelected = this.selected.length === 1;
        var manySelected = this.selected.length >= 1;
        var onlyImage = this.getSelectedItems()
            .filter(function (item) {
                return !item.is_image;
            })
            .length === 0;
        var onlyFile = this.getSelectedItems()
            .filter(function (item) {
                return !item.is_file;
            })
            .length === 0;
        $('[data-action=use]').toggleClass('d-none', !(manySelected && onlyFile));
        $('[data-action=rename]').toggleClass('d-none', !oneSelected);
        $('[data-action=preview]').toggleClass('d-none', !(manySelected && onlyFile));
        $('[data-action=move]').toggleClass('d-none', !manySelected);
        $('[data-action=download]').toggleClass('d-none', !(manySelected && onlyFile));
        $('[data-action=resize]').toggleClass('d-none', !(oneSelected && onlyImage));
        $('[data-action=crop]').toggleClass('d-none', !(oneSelected && onlyImage));
        $('[data-action=trash]').toggleClass('d-none', !manySelected);
        $('[data-action=open]').toggleClass('d-none', !oneSelected || onlyFile);
        $('#multi_selection_toggle').toggleClass('d-none', this.usingWysiwygEditor() || !manySelected);
        $('#actions').toggleClass('d-none', this.selected.length === 0);
        $('#fab').toggleClass('d-none', this.selected.length !== 0);
    };


    this.controllerMethod.rename = (item) => {
        this.dialog(this.settings.lang['message-rename'], item.name, (new_name) => {
            this.performFmRequest('rename', {
                file: item.name,
                new_name: new_name
            }).done(this.refreshFoldersAndItems);
        });
    };

    this.controllerMethod.trash = (items) => {
        this.notify(this.settings.lang['message-delete'], () => {
            this.performFmRequest('delete', {
                items: items.map(function (item) {
                    return item.name;
                })
            }).done(this.refreshFoldersAndItems);
        });
    };

    this.controllerMethod.crop = (item) => {
        this.performFmRequest('crop', {img: item.name})
            .done(this.hideNavAndShowEditor);
    };

    this.controllerMethod.resize = (item) => {
        this.performFmRequest('resize', {img: item.name})
            .done(this.hideNavAndShowEditor);
    };

    this.controllerMethod.download = (items) => {
        items.forEach((item, index) => {
            var data = this.defaultParameters();
            data.file = item.name;
            var token = this.getUrlParam('token');
            if (token) {
                data.token = token;
            }

            setTimeout(() => {
                window.location.href = this.settings.connectorUrl + '/download?' + $.param(data);
            }, index * 100);
        });
    };
    this.controllerMethod.use = (items) => {
        let useTinymce3 = (url) => {
            if (!this.usingTinymce3()) {
                return;
            }

            var win = window.tinyMCEPopup.getWindowArg('window');
            win.document.getElementById(window.tinyMCEPopup.getWindowArg('input')).value = url;
            if (typeof (win.ImageDialog) != 'undefined') {
                // Update image dimensions
                if (win.ImageDialog.getImageData) {
                    win.ImageDialog.getImageData();
                }

                // Preview if necessary
                if (win.ImageDialog.showPreviewImage) {
                    win.ImageDialog.showPreviewImage(url);
                }
            }
            window.tinyMCEPopup.close();
        };

        let useTinymce4AndColorbox = (url) => {
            if (!window.cfm.usingTinymce4AndColorbox()) {
                return;
            }

            parent.document.getElementById(window.cfm.getUrlParam('field_name')).value = url;
            if (typeof parent.tinyMCE !== 'undefined') {
                parent.tinyMCE.activeEditor.windowManager.close();
            }
            if (typeof parent.$.fn.colorbox !== 'undefined') {
                parent.$.fn.colorbox.close();
            }
        };

        let useCkeditor3 = (url) => {
            if (!this.usingCkeditor3()) {
                return;
            }

            if (window.opener) {
                // Popup
                window.opener.CKEDITOR.tools.callFunction(window.cfm.getUrlParam('CKEditorFuncNum'), url);
            } else {
                // Modal (in iframe)
                parent.CKEDITOR.tools.callFunction(window.cfm.getUrlParam('CKEditorFuncNum'), url);
                parent.CKEDITOR.tools.callFunction(window.cfm.getUrlParam('CKEditorCleanUpFuncNum'));
            }
        };

        let useFckeditor2 = (url) => {
            if (!this.usingFckeditor2()) {
                return;
            }

            var p = url;
            var w = window.data.Properties.Width;
            var h = window.data.Properties.Height;
            window.opener.SetUrl(p, w, h);
        };
        let url;
        if(Array.isArray(items)) {
            url = items[0].url;
        } else {
            url = items.url;
        }


        if (typeof window.cfm !== 'undefined') {
            if (window.cfm.haveCallback('use')) {
                return window.cfm.doCallback('use', url);
            }
        }

        var callback = window.cfm.getUrlParam('callback');
        var useFileSucceeded = true;
        if (window.cfm.usingWysiwygEditor()) {
            useTinymce3(url);
            useTinymce4AndColorbox(url);
            useCkeditor3(url);
            useFckeditor2(url);
        } else if (callback && window[callback]) {
            window[callback](window.cfm.getSelectedItems());
        } else if (callback && parent[callback]) {
            parent[callback](window.cfm.getSelecteditems());
        } else if (window.opener) { // standalone button or other situations
            window.opener.SetUrl(window.cfm.getSelectedItems());
        } else {
            useFileSucceeded = false;
        }

        if (useFileSucceeded) {
            if (window.opener) {
                window.close();
            }
        } else {
            //console.log('window.opener not found');
            // No editor found, open/download file using browser's default method
            window.open(url);
        }
    };

    this.loadItems = () => {
        this.loading(true);
        this.performFmRequest('item', {showList: this.showList, sortType: this.sortType}, 'html')
            .done((data) => {
                this.selected = [];
                var response = JSON.parse(data);
                var working_dir = response.working_dir;
                this.items = response.items;
                var hasItems = window.cfm.items.length !== 0;
                $('#empty').toggleClass('d-none', hasItems);
                $('#content').html('').removeAttr('class');
                if (hasItems) {
                    $('#content').addClass(response.display).addClass('preserve_actions_space');
                    this.items.forEach((item, index) => {
                        var template = $('#item-template').clone()
                            .removeAttr('id class')
                            .attr('data-id', index)
                            .click(window.cfm.toggleSelected)
                            // eslint-disable-next-line no-unused-vars
                            .dblclick(function (e) {
                                if (item.is_file) {
                                    window.cfm.controllerMethod.use(window.cfm.getSelectedItems());
                                } else {
                                    window.cfm.goTo(item.url);
                                }
                            });
                        let image;
                        if (item.thumb_url) {
                            image = $('<div>').css('background-image', 'url("' + item.thumb_url + '?timestamp=' + item.time + '")');
                        } else {
                            let icon = $('<div>').addClass('ico');
                            image = $('<div>').addClass('mime-icon ico-' + item.icon).append(icon);
                        }


                        template.find('.square').append(image);
                        template.find('.item_name').text(item.name);
                        template.find('time').text((new Date(item.time * 1000)).toLocaleString());
                        if (!item.is_file) {
                            template.find('time').addClass('d-none');
                        } else {
                            template.find('time').removeClass('d-none');
                        }
                        $('#content').append(template);
                    });
                }

                $('#nav-buttons > ul').removeClass('d-none');
                $('#working_dir').val(working_dir);

                var breadcrumbs = [];
                var validSegments = working_dir.split('/').filter(function (e) {
                    return e;
                });
                validSegments.forEach((segment, index) => {
                    if (index === 0) {
                        // set root folder name as the first breadcrumb
                        breadcrumbs.push($('[data-path=\'/' + segment + '\']').text());
                    } else {
                        breadcrumbs.push(segment);
                    }
                });
                $('#current_folder').text(breadcrumbs[breadcrumbs.length - 1]);
                $('#breadcrumbs > ol').html('');
                breadcrumbs.forEach((breadcrumb, index) => {
                    var li = $('<li>').addClass('breadcrumb-item').text(breadcrumb);
                    if (index === breadcrumbs.length - 1) {
                        li.addClass('active').attr('aria-current', 'page');
                    } else {
                        li.click(() => {
                            // go to corresponding path
                            this.goTo('/' + validSegments.slice(0, 1 + index).join('/'));
                        });
                    }

                    $('#breadcrumbs > ol').append(li);
                });
                var atRootFolder = this.getPreviousDir() == '';
                $('#to-previous').toggleClass('d-none invisible-lg', atRootFolder);
                $('#show_tree').toggleClass('d-none', !atRootFolder).toggleClass('d-block', atRootFolder);
                this.setOpenFolders();
                this.loading(false);
                this.toggleActions();
            });
    };

    this.loading = (showLoading) => {
        $('#loading').toggleClass('d-none', !showLoading);
    };

    this.createFolder = (folderName) => {
        this.performFmRequest('newFolder', {name: folderName})
            .done(this.refreshFoldersAndItems);
    };

    this.initializeUploadForm = () => {
        if (!this.dropzoneInitilized) {
            this.dropzoneInitilized = true;

            // eslint-disable-next-line no-unused-vars
            let dropzone = new window.Dropzone('#uploadForm', {
                paramName: 'upload[]', // The name that will be used to transfer the file
                uploadMultiple: false,
                parallelUploads: 5,
                clickable: '#upload-button',
                dictDefaultMessage: this.settings.lang['message-drop'],
                init: function () {
                    this.on('success', function (file, response) {
                        if (response == 'OK') {
                            window.cfm.loadFolders();
                        } else if (window.cresenity.isJson(response)) {
                            let json = JSON.parse(response);
                            this.defaultOptions.error(file, json.join('\n'));
                        } else {
                            this.defaultOptions.error(file, response);
                        }
                    });
                },
                headers: {
                    Authorization: 'Bearer ' + this.getUrlParam('token')
                },
                acceptedFiles: this.settings.acceptedFiles,
                maxFilesize: (this.settings.maxFilesize / 1000)
            });
        }
    };


    // ======================
    // ==  Navbar actions  ==
    // ======================

    $('#multi_selection_toggle').click(() => {
        this.multiSelectionEnabled = !this.multiSelectionEnabled;
        $('#multi_selection_toggle i')
            .toggleClass('fa-times', this.multiSelectionEnabled)
            .toggleClass('fa-check-double', !this.multiSelectionEnabled);
        if (!this.multiSelectionEnabled) {
            this.clearSelected();
        }
    });
    $('#to-previous').click(() => {
        var previous_dir = this.getPreviousDir();
        if (previous_dir == '') {
            return;
        }
        this.goTo(previous_dir);
    });
    this.toggleMobileTree = (should_display) => {
        if (should_display === undefined) {
            should_display = !$('.capp-fm-tree').hasClass('in');
        }
        $('.capp-fm-tree').toggleClass('in', should_display);
    };

    // eslint-disable-next-line no-unused-vars
    $('#show_tree').click((e) => {
        this.toggleMobileTree();
    });
    // eslint-disable-next-line no-unused-vars
    $('#main').click((e) => {
        if ($('#tree').hasClass('in')) {
            this.toggleMobileTree(false);
        }
    });
    $(document).on('click', '#add-folder', () => {
        this.dialog(this.settings.lang['message-name'], '', this.createFolder);
    });
    $(document).on('click', '.capp-fm #upload', () => {
        $('#uploadModal').modal('show');
    });
    $(document).on('click', '.capp-fm [data-display]', (e) => {
        let target = e.currentTarget;
        this.showList = $(target).data('display');
        this.loadItems();
    });
    $(document).on('click', '.capp-fm [data-action]', (e) => {
        let target = e.currentTarget;
        this.controllerMethod[$(target).data('action')]($(target).data('multiple') ? this.getSelectedItems() : this.getOneSelectedElement());
    });

    $(document).on('click', '.capp-fm #tree a', (e) => {
        this.goTo($(e.target).closest('a').data('path'));
        this.toggleMobileTree(false);
    });

    // eslint-disable-next-line no-unused-vars
    $(document).on('click', '.capp-fm #content', (e) => {
        this.clearSelected();
    });


    fab($('#fab'), {
        buttons: [
            {
                icon: 'fas fa-upload',
                label: this.settings.lang['nav-upload'],
                attrs: {id: 'upload'}
            },
            {
                icon: 'fas fa-folder',
                label: this.settings.lang['nav-new'],
                attrs: {id: 'add-folder'}
            }
        ]
    });
    this.settings.actions.reverse().forEach(function (action) {
        $('#nav-buttons > ul').prepend(
            $('<li>').addClass('nav-item').append(
                $('<a>').addClass('nav-link d-none')
                    .attr('data-action', action.name)
                    .attr('data-multiple', action.multiple)
                    .append($('<i>').addClass('fas fa-fw fa-' + action.icon))
                    .append($('<span>').text(action.label))
            )
        );
    });
    this.settings.sortings.forEach((sort) => {
        $('#nav-buttons .dropdown-menu').append(
            $('<a>').addClass('dropdown-item').attr('data-sortby', sort.by)
                .append($('<i>').addClass('fas fa-fw fa-' + sort.icon))
                .append($('<span>').text(sort.label))
                .click(() => {
                    this.sortType = sort.by;
                    this.loadItems();
                })
        );
    });
    this.loadFolders();
    this.performFmRequest('error')
        .done((response) => {
            JSON.parse(response).forEach(function (message) {
                $('#alerts').append(
                    $('<div>').addClass('alert alert-warning')
                        .append($('<i>').addClass('fas fa-exclamation-circle'))
                        .append(' ' + message)
                );
            });
        });
    $(window).on('dragenter', () => {
        $('#uploadModal').modal('show');
    });
    if (this.usingWysiwygEditor()) {
        $('#multi_selection_toggle').hide();
    }

    this.initializeUploadForm();
};


window.CFileManager = CFileManager;

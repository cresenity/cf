<?php defined('SYSPATH') or die('No direct access allowed.'); ?>

<div id="dialog-select-<?php echo $id; ?>"
    class="dialog-select <?php echo ($value) ? 'dialog-select-exists' : 'dialog-select-new'; ?>">
    <div class="dialog-select-new" style="min-width: <?php echo $minWidth; ?>px; min-height: <?php echo $minHeight; ?>px;">
        <div class="dialog-select-image thumbnail">
            <div class="square">
                <div class="square-content">
                    <div class="image-absolute-wrapper">
                        <img src="<?php echo CApp_Base::noImageUrl(); ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="dialog-select-name">

        </div>
    </div>
    <div class="dialog-select-preview dialog-select-exists"
        style="min-width: <?php echo $minWidth; ?>px; min-height: <?php echo $minHeight; ?>px;">
        <div class="dialog-select-image thumbnail">
            <div class="square">
                <div class="square-content">
                    <div class="image-absolute-wrapper">
                        <img id="cimg-<?php echo $id; ?>" src="<?php echo $imgSrc; ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="dialog-select-name">
            <?php echo $itemName; ?>
        </div>
    </div>
    <div>
        <span class="btn btn-primary btn-dialog-select">
            <span class="dialog-select-new"><?php echo c::__($buttonLabel); ?></span>
            <span class="dialog-select-change dialog-select-exists">
                <?php echo c::__('Change'); ?>
            </span>
            <input type="hidden" id="<?php echo $id; ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>">
        </span>
        <!--<a href="javascript:;" class="btn btn-danger dialog-select-remove dialog-select-exists" data-dismiss="dialog-select"><?php echo clang::__('Remove'); ?></a>-->
    </div>
</div>

<div id="modal-dialog-select-<?php echo $id; ?>" class="modal" style="width: 100%">
    <div class="modal-dialog modal-dialog-select">
        <div class="modal-content animated bounceInRight">
            <div class="modal-header">
                <h3><?php echo $title; ?></h3>
                <a href="#" class="close">&times;</a><span class="loader"></span>
            </div>
            <div class="modal-body opened">
                <div class="dialog-select-search">
                    <input type="text" placeholder="<?php echo $placeholder; ?>">
                </div>
                <div class="dialog-select-item-list">

                </div>
                <div class="dialog-select-load-more">

                </div>
            </div>
            <div class="modal-footer">
                <div class="row" id="actions">
                    <div class="col-md-12 docs-buttons">
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary btn-choose">
                                <?php echo clang::__('Choose'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // $("#dialog-select-<?php echo $id; ?> img, #dialog-select-<?php echo $id; ?> .btn-dialog-select span").click(function() {
    // var modalDialog = $("#modal-dialog-select-<?php echo $id; ?>");

    // modalDialog.modal({backdrop: 'static', keyboard:false});
    // });

    $("#dialog-select-<?php echo $id; ?> .dialog-select-remove").click(function () {
        $("#dialog-select-<?php echo $id; ?> .dialog-select-preview .dialog-select-name").html('');
        $("#dialog-select-<?php echo $id; ?> input").val('');
        $("#dialog-select-<?php echo $id; ?> input").trigger('change');
        $("#dialog-select-<?php echo $id; ?>").removeClass('dialog-select-exists');
        $("#dialog-select-<?php echo $id; ?>").addClass('dialog-select-new');
    });

    $("#modal-dialog-select-<?php echo $id; ?>").find('.btn-choose').click(function () {
        var modalDialog = $("#modal-dialog-select-<?php echo $id; ?>");

        var selected = modalDialog.find('.dialog-select-item-list .selected');

        if (selected.length) {
            var input = $("#dialog-select-<?php echo $id; ?> input");
            var preview = $("#dialog-select-<?php echo $id; ?> .dialog-select-preview .dialog-select-name");
            var image = $("#dialog-select-<?php echo $id; ?> .dialog-select-preview img");

            input.val(selected.attr('data-id'));
            preview.html(selected.attr('data-name'));
            image.attr('src', selected.attr('data-image'));

            input.trigger('change');

            $("#dialog-select-<?php echo $id; ?>").removeClass('dialog-select-new');
            $("#dialog-select-<?php echo $id; ?>").addClass('dialog-select-exists');

            modalDialog.modal('hide');
        } else {
            alert('You have not choose an item');
        }
    });

    (function ($) {
        var modalDialog = $("#modal-dialog-select-<?php echo $id; ?>");
        var time = <?php echo $delay; ?>;

        delay = (function () {
            var timer;

            return function (callback, delay) {
                if (timer != undefined) {
                    clearTimeout(timer);
                }

                timer = setTimeout(callback, delay);
            };
        })();

        addLoading = (function (element, target) {
            element.find(target).addClass('loading spinner');
        });

        removeLoading = (function (element, target) {
            element.find(target).removeClass('loading spinner');
        })

        ajaxLoadItemList<?php echo $id; ?> = (function (modalDialog, keyword = '', page = 1) {
            return function () {
                $.ajax("<?php echo $ajaxUrl; ?>", {
                    dataType: 'json',
                    data: {
                        keyword: keyword,
                        page: page,
                    }
                }).done(function (data) {
                    var result = data.data.result;

                    var list = modalDialog.find('.dialog-select-item-list');
                    list.empty();
                    if (result) {
                        list.append(result);
                    } else {
                        list.append($('<div class="alert alert-info">No Data Found</div>'));
                    }
                }).always(function () {
                    removeLoading(modalDialog, '.dialog-select-item-list');

                    modalDialog.find('.dialog-select-item-list').children().off('click').click(
                        function (e) {
                            if ($(this).hasClass('selected')) {
                                $(this).removeClass('selected');
                            } else {
                                modalDialog.find('.dialog-select-item-list').children()
                                    .removeClass('selected');
                                $(this).addClass('selected');
                            }
                        });

                    loadMore<?php echo $id; ?> (modalDialog, keyword, ++page);
                });
            };
        });

        loadMore<?php echo $id; ?> = (function () {
            var loader;

            return function (modalDialog, keyword = '', page = 1) {
                if (loader != undefined) {
                    loader = undefined;
                }

                loader = (function (modalDialog) {
                    modalDialog.find('.dialog-select-item-list').off('scroll').scroll(function (
                        e) {
                        var scrollTop = $(this).scrollTop();
                        var clientHeight = this.clientHeight;
                        var scrollHeight = this.scrollHeight;

                        if (scrollTop + clientHeight >= scrollHeight) {
                            modalDialog.find('.dialog-select-load-more').addClass(
                                'processing');
                            addLoading(modalDialog, '.dialog-select-load-more');

                            $.ajax("<?php echo $ajaxUrl; ?>", {
                                dataType: 'json',
                                data: {
                                    keyword: keyword,
                                    page: page,
                                }
                            }).done(function (data) {
                                var result = data.data.result;

                                var list = modalDialog.find(
                                    '.dialog-select-item-list');
                                list.append(result);
                            }).always(function (data) {
                                var total = data.total;
                                var limit = data.data.limit;

                                modalDialog.find('.dialog-select-load-more')
                                    .removeClass('processing');
                                removeLoading(modalDialog,
                                    '.dialog-select-load-more');

                                modalDialog.find('.dialog-select-item-list')
                                    .children().off('click').click(function (
                                    e) {
                                        if ($(this).hasClass('selected')) {
                                            $(this).removeClass('selected');
                                        } else {
                                            modalDialog.find(
                                                    '.dialog-select-item-list'
                                                    ).children()
                                                .removeClass('selected');
                                            $(this).addClass('selected');
                                        }
                                    });

                                if (total >= limit) {
                                    loadMore<?php echo $id; ?> (modalDialog,
                                        keyword, ++page);
                                } else {
                                    modalDialog.find('.dialog-select-item-list')
                                        .off('scroll');
                                }
                            });
                        }
                    });
                });

                loader(modalDialog);
            };
        })();

        $("#dialog-select-<?php echo $id; ?> img, #dialog-select-<?php echo $id; ?> .btn-dialog-select span").click(function () {
            modalDialog.modal({
                backdrop: 'static',
                keyboard: false
            });

            if (!modalDialog.hasClass('dialog-select-initialized')) {
                addLoading(modalDialog, '.dialog-select-item-list');
                modalDialog.addClass('dialog-select-initialized');
                ajaxLoadItemList<?php echo $id; ?> (modalDialog)();
            }
        });

        modalDialog.find('.dialog-select-search input').keyup(function () {
            addLoading(modalDialog, '.dialog-select-item-list');
            delay(ajaxLoadItemList<?php echo $id; ?> (modalDialog, $(this).val().trim()), time);
        });

        modalDialog.find('.close').click(function (e) {
            e.preventDefault();
            e.stopPropagation();
            modalDialog.modal('hide');
        });
    })(jQuery);

</script>

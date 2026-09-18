<?php
/**
 * Description of Cropper.
 */
?>
<div id="modal-cropper-<?php echo $id; ?>" class="modal modal-cropper" >
    <div class="modal-dialog"><div class="modal-content animated bounceInRight">
            <div class="modal-header">

                <h3>@lang('element/cropper.cropper')</h3>

                <div class="btn-group btn-group-header-modal">
                    <button type="button" class="btn btn-primary btn-zoom-in" data-method="zoom" data-option="0.1" title="@lang('element/cropper.zoomIn')">
                        <span class="docs-tooltip" data-toggle="tooltip" title="">
                            <span class="fa fa-search-plus"></span>
                        </span>
                    </button>

                    <button type="button" class="btn btn-primary btn-zoom-out" data-method="zoom" data-option="-0.1" title="@lang('element/cropper.zoomOut')">
                        <span class="docs-tooltip" data-toggle="tooltip" title="">
                            <span class="fa fa-search-minus"></span>
                        </span>
                    </button>


                </div>
                <a href="#" class="close">&times;</a><span class="loader"></span>
            </div>
            <div class="modal-body opened">
                <div class="cropper-image-container">
                    <img class="img-responsive" src="/cresenity/noimage/800/500" height="auto" width="100%"/>
                </div>
            </div>
            <div class="modal-footer">


                <div class="row" id="actions">

                    <div class="col-md-12 docs-buttons">
                        <!--
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary" data-method="rotate" data-option="-45" title="Rotate Left">
                                <span class="docs-tooltip" data-toggle="tooltip" title="Rotate -45 Degree">
                                    <span class="fa fa-rotate-left"></span>
                                </span>
                            </button>
                            <button type="button" class="btn btn-primary" data-method="rotate" data-option="45" title="Rotate Right">
                                <span class="docs-tooltip" data-toggle="tooltip" title="Rotate 45 Degree">
                                    <span class="fa fa-rotate-right"></span>
                                </span>
                            </button>
                        </div>
                        -->

                        <div class="btn-group">

                            <button type="button" class="btn btn-primary btn-crop" data-method="crop" data-option="crop" title="@lang('element/cropper.crop')">
                                <span class="docs-tooltip" data-toggle="tooltip" title="">
                                    <span class="fa fa-crop"></span> @lang('element/cropper.ok')
                                </span>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function ($) {
        var Cropper = window.Cropper;
        var URL = window.URL || window.webkitURL;
        var modalCropper = $('#modal-cropper-<?php echo $id; ?>');
        var container = modalCropper.find('.cropper-image-container');
        var image = container.find('img');
        $('.btn-zoom-in').click(function (event) {
            image.cropper('zoom', 0.1);
        });
        $('.btn-zoom-out').click(function (event) {
            image.cropper('zoom', -0.1);
        });
        modalCropper.find('.close').click(function (e) {
            modalCropper.modal('hide');
        });
    })(jQuery);
</script>

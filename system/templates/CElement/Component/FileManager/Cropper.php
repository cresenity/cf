<?php
defined('SYSPATH') or die('No direct access allowed.');
?>
<div class="row no-gutters">
    <div class="col-xl-8">
        <div class="crop-container">
            <img src="<?php echo $img->url . '?timestamp=' . $img->time; ?>" class="img img-responsive">
        </div>
    </div>
    <div class="col-xl-4">
        <div class="text-center">
            <div class="img-preview center-block"></div>
            <br>
            <div class="btn-group clearfix">
                <label class="btn btn-info btn-aspectRatio active" data-aspect-width="16" data-aspect-height="9">
                    16:9
                </label>
                <label class="btn btn-info btn-aspectRatio" data-aspect-width="4" data-aspect-height="3">
                    4:3
                </label>
                <label class="btn btn-info btn-aspectRatio" data-aspect-width="1" data-aspect-height="1">
                    1:1
                </label>
                <label class="btn btn-info btn-aspectRatio" data-aspect-width="2" data-aspect-height="3">
                    2:3
                </label>
                <label class="btn btn-info btn-aspectRatio" onclick="changeAspectRatio(this, null)">
                    <?php echo clang::__('filemanager.btn-crop-free'); ?>
                </label>
            </div>
            <br>
            <br>
            <div class="btn-group clearfix">
                <button class="btn btn-secondary btn-cropper-load-items" ><?php echo clang::__('filemanager.btn-cancel'); ?></button>
                <button class="btn btn-warning btn-cropper-perform-crop-new" ><?php echo clang::__('filemanager.btn-copy-crop'); ?></button>
                <button class="btn btn-primary btn-cropper-perform-crop" ><?php echo clang::__('filemanager.btn-crop'); ?></button>
            </div>
            <form id='cropForm'>
                <input type="hidden" id="img" name="img" value="<?php echo $img->name; ?>">
                <input type="hidden" id="working_dir" name="working_dir" value="<?php echo $working_dir; ?>">
                <input type="hidden" id="dataX" name="dataX">
                <input type="hidden" id="dataY" name="dataY">
                <input type="hidden" id="dataWidth" name="dataWidth">
                <input type="hidden" id="dataHeight" name="dataHeight">
                <input type='hidden' name='_token' value='{{csrf_token()}}'>
            </form>
        </div>
    </div>
</div>

<script>
    var $image = null,
            options = {};
    $(document).ready(function () {
        var $dataX = $('#dataX'),
                $dataY = $('#dataY'),
                $dataHeight = $('#dataHeight'),
                $dataWidth = $('#dataWidth');
        $image = $('.crop-container > img');
        options = {
            aspectRatio: 16 / 9,
            preview: ".img-preview",
            strict: false,
            crop: function (data) {
                // Output the result data for cropping image.
                $dataX.val(Math.round(data.x));
                $dataY.val(Math.round(data.y));
                $dataHeight.val(Math.round(data.height));
                $dataWidth.val(Math.round(data.width));
            }
        };
        $image.cropper(options);
    });

    $('.btn-aspectRatio').click(function(e){
        var ratioWidth = parseFloat($(this).attr('data-aspect-width'));
        var ratioHeight = parseFloat($(this).attr('data-aspect-width'));
        var aspectRatio = ratioWidth/ratioHeight;

        options.aspectRatio = aspectRatio;
        $('.btn-aspectRatio.active').removeClass('active');
        $(this).addClass('active');
        $('.img-preview').removeAttr('style');
        $image.cropper('destroy').cropper(options);
        return false;
    });


    $('.btn-cropper-perform-crop').click(function (e) {
        window.cfm.performFmRequest('cropImage', {
            img: $("#img").val(),
            working_dir: $("#working_dir").val(),
            dataX: $("#dataX").val(),
            dataY: $("#dataY").val(),
            dataHeight: $("#dataHeight").val(),
            dataWidth: $("#dataWidth").val(),
            type: $('#type').val()
        }).done(window.cfm.loadItems);

    });
    $('.btn-cropper-perform-crop-new').click(function (e) {
        window.cfm.performFmRequest('cropNewImage', {
            img: $("#img").val(),
            working_dir: $("#working_dir").val(),
            dataX: $("#dataX").val(),
            dataY: $("#dataY").val(),
            dataHeight: $("#dataHeight").val(),
            dataWidth: $("#dataWidth").val(),
            type: $('#type').val()
        }).done(window.cfm.loadItems);
    });
    $('.btn-cropper-load-items').click(function(e){
        window.cfm.loadItems();
    });
</script>

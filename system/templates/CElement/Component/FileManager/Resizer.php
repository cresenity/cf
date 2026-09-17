<?php
defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Aug 12, 2019, 12:42:33 AM
 */
?>

<style>
    #work_space {
        padding: 30px;
        height: 100vw;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    @media screen and (min-width: 768px) {
        #work_space {
            width: unset;
            height: unset;
        }
    }
</style>

<div class="container">
    <div class="row">
        <div class="col-md-8 bg-light" id="work_space">
            <div id="containment" class="d-none d-md-inline">
                <img id="resize" src="<?php echo $img->url . '?timestamp=' . $img->time; ?>" height="<?php echo $height; ?>" width="<?php echo $width; ?>">
            </div>
            <div id="resize_mobile" style="background-image: url(<?php echo $img->url . '?timestamp=' . $img->time; ?>)" class="d-block d-md-none"></div>
        </div>
        <div class="col-md-4 pt-3">
            <table class="table table-compact table-striped">
                <thead></thead>
                <tbody>
                    <?php if ($scaled): ?>
                        <tr>
                            <td class="text-nowrap"><?php echo clang::__('filemanager.resize-ratio'); ?></td>
                            <td class="text-right"><?php echo number_format($ratio, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="text-nowrap"><?php echo clang::__('filemanager.resize-scaled'); ?></td>
                            <td class="text-right">
                                <?php echo clang::__('filemanager.resize-true'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-nowrap"><?php echo clang::__('filemanager.resize-old-height'); ?></td>
                        <td class="text-right"><?php echo $original_height; ?>px</td>
                    </tr>
                    <tr>
                        <td class="text-nowrap"><?php echo clang::__('filemanager.resize-old-width'); ?></td>
                        <td class="text-right"><?php echo $original_width; ?>px</td>
                    </tr>
                    <tr>
                        <td class="text-nowrap" style="vertical-align: middle"><?php echo clang::__('filemanager.resize-new-height'); ?></td>
                        <td class="text-right"><input type="text" id="height_display" class="form-control w-50 d-inline mr-2" value="<?php echo $height; ?>">px</td>
                    </tr>
                    <tr>
                        <td class="text-nowrap" style="vertical-align: middle"><?php echo clang::__('filemanager.resize-new-width'); ?></td>
                        <td class="text-right"><input type="text" id="width_display" class="form-control w-50 d-inline mr-2" value="<?php echo $width; ?>">px</td>
                    </tr>
                </tbody>
            </table>
            <div class="d-flex mb-3">
                <button class="btn btn-secondary w-50 mr-1 btn-resizer-load-items" onclick="loadItems()"><?php echo clang::__('filemanager.btn-cancel'); ?></button>
                <button class="btn btn-primary w-50 btn-resizer-perform-resize" ><?php echo clang::__('filemanager.btn-resize'); ?></button>
            </div>

            <input type="hidden" id="img" name="img" value="<?php echo $img->name; ?>">
            <input type="hidden" name="ratio" value="<?php echo $ratio; ?>">
            <input type="hidden" name="scaled" value="<?php echo $scaled; ?>">
            <input type="hidden" id="original_height" name="original_height" value="<?php echo $original_height; ?>">
            <input type="hidden" id="original_width" name="original_width" value="<?php echo $original_width; ?>">
            <input type="hidden" id="height" name="height" value="<?php echo $height; ?>">
            <input type="hidden" id="width" name="width" value="<?php echo $width; ?>">
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        renderResizedValues($("#width_display").val(), $("#height_display").val());
        let resizeAspectRatio = <?php echo $fm->config('resize_aspectRatio') ? 'true' : 'false'; ?>;
        let resizeOption = {
            aspectRatio: resizeAspectRatio,
            handles: "n, e, s, w, se, sw, ne, nw",
            resize: function (event, ui) {
                renderResizedValues(ui.size.width, ui.size.height);
            }
        };

        <?php if ($fm->config('resize_containment')): ?>
            resizeOption.containment= "#containment";
        <?php endif; ?>
        $("#resize").resizable(resizeOption);
    });
    $('#width_display, #height_display').change(function () {
        var newWidth = $("#width_display").val();
        var newHeight = $("#height_display").val();
        renderResizedValues(newWidth, newHeight);
        $("#containment > .ui-wrapper").width(newWidth).height(newHeight);
        $("#resize").width(newWidth).height(newHeight);
    });
    function renderResizedValues(newWidth, newHeight) {
        $("#width").val(newWidth);
        $("#height").val(newHeight);
        $("#width_display").val(newWidth);
        $("#height_display").val(newHeight);
        $('#resize_mobile').css('background-size', '100% 100%');
        if (newWidth < newHeight) {
            $('#resize_mobile').css('width', (newWidth / newHeight * 100) + '%').css('padding-bottom', '100%');
        } else if (newWidth > newHeight) {
            $('#resize_mobile').css('width', '100%').css('padding-bottom', (newHeight / newWidth * 100) + '%');
        } else { // newWidth === newHeight
            $('#resize_mobile').css('width', '100%').css('padding-bottom', '100%');
        }
    }

    $('.btn-resizer-perform-resize').click(function (e) {

        window.cfm.performFmRequest('resizeImage', {
            img: $("#img").val(),
            dataHeight: $("#height").val(),
            dataWidth: $("#width").val()
        }).done(loadItems);
    });
    $('.btn-resizer-load-items').click(function(e){
        window.cfm.loadItems();
    });
</script>

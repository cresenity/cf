<?php defined('SYSPATH') or die('No direct access allowed.'); ?>

<div class="ccard-item" data-id="<?php echo $id; ?>" data-name="<?php echo $name; ?>" data-image="<?php echo $imageUrl; ?>">
    <div class="square">
        <div class="square-content">
            <div class="image-absolute-wrapper">
                <img src="<?php echo $imageUrl; ?>">
            </div>
        </div>
    </div>
    <div class="ccard-item__name">
        <?php echo $name; ?>
    </div>
</div>

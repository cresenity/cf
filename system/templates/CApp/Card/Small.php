<?php
defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 13, 2018, 9:04:32 AM
 */
if (!isset($icon)) {
    $icon = 'lnr lnr-cart';
}
if (!isset($label)) {
    $label = '[EMPTY LABEL]';
}
if (!isset($amount)) {
    $amount = '0';
}
if (!isset($action_link)) {
    $action_link = '';
}

if (!isset($description)) {
    $description = '';
}
?>

<div class="card card-small mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <div class="<?php echo $icon; ?> "></div>
            <div class="ml-3">
                <div class="text-muted small"><?php echo $label; ?></div>
                <div class="text-large"><?php echo $amount; ?></div>
                <?php if (strlen($description) > 0): ?>
                    <p class="text-muted"><?php echo $description; ?></p>
                <?php endif; ?>
            </div>
            <?php if (strlen($action_link) > 0): ?>
                <a href="<?php echo $action_link; ?>" class="btn btn-primary ml-auto">
                    <i class="fas fa-cog"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

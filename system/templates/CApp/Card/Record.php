<?php
defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 13, 2018, 9:30:04 AM
 */
if (!isset($title)) {
    $title = '[EMPTY TITLE]';
}

if (!isset($content)) {
    $content = '[EMPTY CONTENT]';
}

if (!isset($edit_link)) {
    $edit_link = '';
}
if (!isset($delete_link)) {
    $delete_link = '';
}
if (!isset($info_link)) {
    $info_link = '';
}
if (!isset($second_link)) {
    $second_link = '';
}
if (!isset($info_text)) {
    $info_text = 'Manage';
}
if (!isset($second_text)) {
    $second_text = '';
}
if (!isset($description)) {
    $description = '';
}
if (!isset($dropdown_menu)) {
    $dropdown_menu = [];
}
?>

<div class="card card-record mb-3">
    <div class="card-body">
        <div class="card-title with-elements">
            <h5 class="m-0 mr-2"><?php echo $title; ?></h5>

            <div class="card-title-elements ml-md-auto">
                <?php if (strlen($edit_link) > 0): ?>
                    <a href="<?php echo $edit_link; ?>" class="btn btn-xs btn-outline-primary">
                        <span class="ion ion-md-create"></span>
                        EDIT
                    </a>
                <?php endif; ?>
                <?php if (strlen($delete_link) > 0): ?>
                    <a href="<?php echo $delete_link; ?>" class="btn icon-btn btn-xs btn-danger confirm">
                        <span class="ion ion-md-trash"></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <p class="card-text"><?php echo $content; ?></p>
        <?php if (strlen($description) > 0): ?>
            <p class="text-muted"><?php echo $description; ?></p>
        <?php endif; ?>

        <div class="pull-right">
            <?php if (strlen($info_link) > 0): ?>
                <a href="<?php echo $info_link ?>" class="btn btn-success card-link"><i class="fa fa-cog"></i> <?= $info_text ?></a>
            <?php endif; ?>
            <?php if (strlen($second_link) > 0): ?>
                <a href="<?php echo $second_link ?>" class="btn btn-success card-link ml-3"><i class="fa fa-info"></i> <?= $second_text ?></a>
            <?php endif; ?>

            <?php if (is_array($dropdown_menu) && count($dropdown_menu) > 0): ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-warning icon-btn rounded-pill dropdowinfon-toggle hide-arrow" data-toggle="dropdown" aria-expanded="false">
                        <i class="ion ion-ios-more"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" id="new-users-dropdown-menu" x-placement="bottom-end" >
                        <?php foreach ($dropdown_menu as $menu): ?>
                            <?php
                            $url = carr::get($menu, 'url');
                            $label = carr::get($menu, 'label');
                            ?>
                            <a class="dropdown-item" href="<?php echo $url; ?>"><?php echo $label; ?></a>

                        <?php endforeach; ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>

    </div>
</div>

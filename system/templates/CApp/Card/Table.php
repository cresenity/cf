<?php
defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 13, 2018, 10:31:24 AM
 */
if (!isset($title)) {
    $title = '[EMPTY TITLE]';
}
if (!isset($columns)) {
    $columns = [];
}
if (!isset($rows)) {
    $rows = [];
}
if (!isset($headers)) {
    $headers = '';
}
?>

<div class="card mb-4">
    <div class="card-header">
        <div class="card-title m-0 with-elements">
            <h5 class="m-0 mr-2"><?php echo $title; ?></h5>

            <div class="card-title-elements ml-md-auto">
                <?php echo $headers; ?>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table card-table">
            <thead>
                <tr>
                    <?php if (!empty($columns)): ?>
                        <?php foreach ($columns as $column): ?>
                            <th><?php echo $column; ?></th>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <?php
                            $value = $cell;
                            if (is_array($cell)) {
                                $value = carr::get($cell, 'value');
                            }
                            ?>
                            <td >
                                <?php echo $value; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

    </div>
</div>

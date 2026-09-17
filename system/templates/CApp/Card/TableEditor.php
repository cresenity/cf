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
$uniqid = uniqid(time());
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
                            <?php
                            $label = $column;
                            if (is_array($label)) {
                                $label = carr::get($label, 'label');
                            }
                            ?>
                            <th><?php echo $label; ?></th>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <th><div class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <?php foreach ($row as $colIndex => $cell) : ?>
                            <?php
                            $value = $cell;
                            if (is_array($cell)) {
                                $value = carr::get($cell, 'value');
                            }
                            $column = carr::get($columns, $colIndex);
                            $label = $column;
                            $type = 'text';

                            if (is_array($label)) {
                                $type = carr::get($label, 'type');
                                $list = carr::get($label, 'list');
                                $name = carr::get($label, 'name');
                                $label = carr::get($label, 'label');
                            }
                            $input = '<input name="' . $name . '" type="' . $type . '" value="' . $value . '" class="form-control" value="' . '' . '" />';
                            if ($type == 'select') {
                                $input = '';
                                $input .= '<select name="' . $name . '"class="form-control">';
                                foreach ($list as $key => $label) {
                                    $selected = $key == $value ? ' selected="selected"' : '';
                                    $input .= '<option value="' . $key . '"' . $selected . '>' . $label . '</option>';
                                }
                                $input .= '</select>';
                            }
                            ?>
                            <td >
                                <?php echo $input; ?>
                            </td>
                        <?php endforeach; ?>
                            <td class="td-action">
                            <div class="text-center">
                                <a href="javascript:;" class="btn btn-danger btn-remove-<?php echo $uniqid; ?>"><i class="fas fa-trash"></i> <span>Remove</span></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <?php if (!empty($columns)): ?>
                        <?php foreach ($columns as $column): ?>
                            <?php
                            $label = $column;
                            $type = 'text';
                            $value = '';
                            if (is_array($label)) {
                                $type = carr::get($label, 'type');
                                $list = carr::get($label, 'list');
                                $name = carr::get($label, 'name');
                                $label = carr::get($label, 'label');
                            }
                            $input = '<input name="' . $name . '" type="' . $type . '" value="' . $value . '" class="form-control" value="' . '' . '" />';
                            if ($type == 'select') {
                                $input = '';
                                $input .= '<select name="' . $name . '"class="form-control">';
                                foreach ($list as $key => $label) {
                                    $selected = $key == $value ? ' selected="selected"' : '';
                                    $input .= '<option value="' . $key . '"' . $selected . '>' . $label . '</option>';
                                }
                                $input .= '</select>';
                            }
                            ?>
                            <td><?php echo $input; ?></td>
                        <?php endforeach; ?>
                        <td class="td-action">
                            <div class="text-center">
                                <a id="btn-add-<?php echo $uniqid; ?>" href="javascript:;" class="btn btn-primary btn-add"><i class="fas fa-plus"></i> <span>Add</span></a>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            </tbody>
        </table>

    </div>
</div>
<script>
    $('.btn-remove-<?php echo $uniqid; ?>').click(function () {
        $(this).closest('tr').fadeOut(500, function () {
            $(this).remove();
        });
    });

    $('#btn-add-<?php echo $uniqid; ?>').click(function () {
        var table = $(this).closest('table');
        var tableBody = $(this).closest('tbody');
        var tr = $(this).closest('tr');

        var trCloned = tr.clone(true);
        var aCloned = trCloned.find('.td-action a');
        aCloned.removeClass('btn-primary').addClass('btn-danger');
        aCloned.find('i').removeClass('fa-plus').addClass('fa-trash');
        aCloned.find('span').html('Remove');

        var trSelects = $(tr).find("select");
        $(trSelects).each(function (i) {
            var select = this;
            trCloned.find("select").eq(i).val($(select).val());
        });

        tr.before(trCloned);
        aCloned.click(function () {
            $(this).closest('tr').fadeOut(500, function () {
                $(this).remove();
            });
        });

        tr.find('input').val('');
        tr.find('select').val('');
        tr.find('select').find('option').removeAttr('selected');

    });
</script>

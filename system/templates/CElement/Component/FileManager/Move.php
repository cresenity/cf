<?php
defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Aug 11, 2019, 10:03:55 PM
 */
?>
<ul class="nav nav-pills flex-column">
    <?php foreach ($rootFolders as $rootFolder): ?>
        <li class="nav-item">
            <a class="nav-link can-click" href="#" data-type="0" data-url="<?php echo $rootFolder->url; ?>">
                <i class="fa fa-folder fa-fw"></i> <?php echo $rootFolder->name; ?>

            </a>
        </li>
        <?php foreach ($rootFolder->children as $directory): ?>
            <li class="nav-item sub-item">
                <a class="nav-link can-click" href="#" data-type="0" data-url="<?php echo $directory->url; ?>">
                    <i class="fa fa-folder fa-fw"></i> <?php echo $directory->name; ?>

                </a>
            </li>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <div id="items">
        <?php foreach ($items as $i): ?>
            <input type="hidden" id="<?php echo $i; ?>" name="items[]" value="<?php echo $i; ?>">
        <?php endforeach; ?>
    </div>
</ul>

<script>

    $('.can-click').click(function () {
        var folder = $(this).attr('data-url');
        $("#notify").modal('hide');
        var items = [];
        $("#items").find("input").each(function () {
            items.push(this.id)
        });
        window.cfm.performFmRequest('doMove', {
            items: items,
            goToFolder: folder
        }).done(window.cfm.refreshFoldersAndItems);
    });

</script>

<?php
defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Aug 11, 2019, 2:40:00 AM
 */
?>
<div class="m-3 d-block d-lg-none">
    <h1 style="font-size: 1.5rem;">File Manager</h1>
    <small class="d-block">Ver 2.0</small>
    <div class="row mt-3">
        <div class="col-4">
            <img src="<?php echo curl::base() . 'modules/cresenity/media/img/filemanager/logo.png'; ?>" class="w-100">

        </div>

        <div class="col-8">
            <p>Current usage :</p>
            <p>20 GB (Max : 1 TB)</p>
        </div>
    </div>
    <div class="progress mt-3" style="height: .5rem;">
        <div class="progress-bar progress-bar-striped progress-bar-animated w-75 bg-main" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
</div>

<ul class="nav nav-pills flex-column">
    <?php foreach ($rootFolders as $rootFolder): ?>
        <li class="nav-item">
            <a class="nav-link" href="#" data-type="0" data-path="<?php echo $rootFolder->url; ?>">
                <i class="fa fa-folder fa-fw"></i> <?php echo $rootFolder->name; ?>
            </a>
        </li>
        <?php foreach ($rootFolder->children as $directory): ?>
            <li class="nav-item sub-item">
                <a class="nav-link" href="#" data-type="0" data-path="<?php echo $directory->url; ?>">
                    <i class="fa fa-folder fa-fw"></i> <?php echo $directory->name; ?>
                </a>
            </li>
        <?php endforeach; ?>
    <?php endforeach; ?>
</ul>

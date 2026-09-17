<?php
defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Aug 11, 2019, 12:53:38 AM
 */
?>
<div class="capp-fm">
    <nav class="navbar sticky-top navbar-expand-lg navbar-dark" id="nav">
        <a class="navbar-brand invisible-lg d-none d-lg-inline" id="to-previous">
            <i class="fas fa-arrow-left fa-fw"></i>
            <span class="d-none d-lg-inline"><?php echo clang::__('filemanager.nav-back'); ?></span>
        </a>
        <a class="navbar-brand d-block d-lg-none" id="show_tree">
            <i class="fas fa-bars fa-fw"></i>
        </a>
        <a class="navbar-brand d-block d-lg-none" id="current_folder"></a>
        <a id="loading" class="navbar-brand"><i class="fas fa-spinner fa-spin"></i></a>
        <div class="ml-auto px-2">
            <a class="navbar-link d-none" id="multi_selection_toggle">
                <i class="fa fa-check-double fa-fw"></i>
                <span class="d-none d-lg-inline"><?php echo clang::__('filemanager.menu-multiple'); ?></span>
            </a>
        </div>
        <a class="navbar-toggler collapsed border-0 px-1 py-2 m-0" data-toggle="collapse" data-target="#nav-buttons">
            <i class="fas fa-cog fa-fw"></i>
        </a>
        <div class="collapse navbar-collapse flex-grow-0" id="nav-buttons">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-display="grid">
                        <i class="fas fa-th-large fa-fw"></i>
                        <span><?php echo clang::__('filemanager.nav-thumbnails'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-display="list">
                        <i class="fas fa-list-ul fa-fw"></i>
                        <span><?php echo clang::__('filemanager.nav-list'); ?></span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-sort fa-fw"></i><?php echo clang::__('filemanager.nav-sort'); ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right border-0"></div>
                </li>
            </ul>
        </div>
    </nav>

    <nav class="bg-light fixed-bottom border-top d-none" id="actions">
        <a data-action="open" data-multiple="false"><i class="fas fa-folder-open"></i><?php echo clang::__('filemanager.btn-open'); ?></a>
        <?php if ($fm->config('action.preview')): ?>
            <a data-action="preview" data-multiple="true"><i class="fas fa-images"></i><?php echo clang::__('filemanager.menu-view'); ?></a>
        <?php endif; ?>
        <?php if ($fm->config('action.use')): ?>
            <a data-action="use" data-multiple="true"><i class="fas fa-check"></i><?php echo clang::__('filemanager.btn-confirm'); ?></a>
        <?php endif; ?>
    </nav>

    <div class="d-flex flex-row">
        <div id="tree"></div>

        <div id="main">
            <div id="alerts"></div>

            <nav aria-label="breadcrumb" class="d-none d-lg-block" id="breadcrumbs">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item invisible">Home</li>
                </ol>
            </nav>

            <div id="empty" class="d-none">
                <i class="far fa-folder-open"></i>
                <?php echo clang::__('filemanager.message-empty'); ?>
            </div>

            <div id="content"></div>

            <a id="item-template" class="d-none">
                <div class="square"></div>

                <div class="info">
                    <div class="item_name text-truncate"></div>
                    <time class="text-muted font-weight-light text-truncate"></time>
                </div>
            </a>
        </div>

        <div id="fab"></div>
    </div>

    <div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="myModalLabel"><?php echo clang::__('filemanager.title-upload'); ?></h4>
                    <button type="button" class="close" data-dismiss-modal="uploadModal" aria-label="Close"><span aia-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <form action="<?php echo rtrim($fm->connectorUrl(), '/') . '/upload'; ?>" role='form' id='uploadForm' name='uploadForm' method='post' enctype='multipart/form-data' class="dropzone">
                        <div class="form-group" id="attachment">
                            <div class="controls text-center">
                                <div class="input-group w-100">
                                    <a class="btn btn-primary w-100 text-white" id="upload-button"><?php echo clang::__('filemanager.message-choose'); ?></a>
                                </div>
                            </div>
                        </div>
                        <input type='hidden' name='working_dir' id='working_dir'>
                        <input type='hidden' name='type' id='type' value='<?php echo CHTTP::request()->input('type'); ?>'>
                        <input type='hidden' name='_token' value='{{csrf_token()}}'>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary w-100" data-dismiss-modal="uploadModal"><?php echo clang::__('filemanager.btn-close'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="notify" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary w-100" data-dismiss-modal="notify"><?php echo clang::__('filemanager.btn-close'); ?></button>
                    <button type="button" class="btn btn-primary w-100" data-dismiss-modal="notify"><?php echo clang::__('filemanager.btn-confirm'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="dialog" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"></h4>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary w-100" data-dismiss-modal="dialog"><?php echo clang::__('filemanager.btn-close'); ?></button>
                    <button type="button" class="btn btn-primary w-100" data-dismiss-modal="dialog"><?php echo clang::__('filemanager.btn-confirm'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div id="carouselTemplate" class="d-none carousel slide bg-light" data-ride="carousel">
        <ol class="carousel-indicators">
            <li data-target="#previewCarousel" data-slide-to="0" class="active"></li>
        </ol>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <a class="carousel-label"></a>
                <div class="carousel-image"></div>
            </div>
        </div>
        <a class="carousel-control-prev" href="#previewCarousel" role="button" data-slide="prev">
            <div class="carousel-control-background" aria-hidden="true">
                <i class="fas fa-chevron-left"></i>
            </div>
            <span class="sr-only">Previous</span>
        </a>
        <a class="carousel-control-next" href="#previewCarousel" role="button" data-slide="next">
            <div class="carousel-control-background" aria-hidden="true">
                <i class="fas fa-chevron-right"></i>
            </div>
            <span class="sr-only">Next</span>
        </a>
    </div>
</div>
<script>
    function checkModal() {
        var modalExists = $('.modal:visible').length > 0;
        if (!modalExists) {
            $('body').removeClass('modal-open');
            $('.modal-backdrop.show').remove();
        } else {
            if (!$('body').hasClass('modal-open')) {
                $('body').addClass('modal-open');
            }

        }
    }
    $(document).on('hidden.bs.modal', function() {
        checkModal();
    });
    $("[data-dismiss-modal=dialog]").click(function(){
        $('#dialog').modal('hide');



    });
    $("[data-dismiss-modal=notify]").click(function(){
        $('#notify').modal('hide');

    });
    $("[data-dismiss-modal=uploadModal]").click(function(){
        $('#uploadModal').modal('hide');

    });
    var lang = <?php echo json_encode($fm->getTranslation()); ?>;
    var config = {};
    config.action = <?php echo json_encode($fm->config('action')); ?>;
    var actions = [];
    if (config.action.use) {
        actions.push({
            name: 'use',
            icon: 'check',
            label: 'Confirm',
            multiple: true
        });
    }
    if (config.action.rename) {
        actions.push({
            name: 'rename',
            icon: 'edit',
            label: lang['menu-rename'],
            multiple: false
        });
    }
    if (config.action.download) {
        actions.push({
            name: 'download',
            icon: 'download',
            label: lang['menu-download'],
            multiple: true
        });
    }
    if (config.action.preview) {
        actions.push({
            name: 'preview',
            icon: 'image',
            label: lang['menu-view'],
            multiple: true
        });
    }
    if (config.action.move) {
        actions.push({
            name: 'move',
            icon: 'paste',
            label: lang['menu-move'],
            multiple: true
        });
    }
    if (config.action.resize) {
        actions.push({
            name: 'resize',
            icon: 'arrows-alt',
            label: lang['menu-resize'],
            multiple: false
        });
    }
    if (config.action.crop) {
        actions.push({
            name: 'crop',
            icon: 'crop',
            label: lang['menu-crop'],
            multiple: false
        });
    }
    if (config.action.delete) {
        actions.push({
            name: 'trash',
            icon: 'trash',
            label: lang['menu-delete'],
            multiple: true
        });
    }


    var sortings = [
        {
            by: 'alphabetic',
            icon: 'sort-alpha-down',
            label: lang['nav-sort-alphabetic']
        },
        {
            by: 'time',
            icon: 'sort-numeric-down',
            label: lang['nav-sort-time']
        }
    ];

    var fmRoute = '<?php echo rtrim($fm->connectorUrl(), '/'); ?>';


    var acceptedFiles = '<?php echo implode(',', $fm->availableMimeTypes()); ?>';
    var maxFilesize = '<?php echo $fm->maxUploadSize(); ?>';



    var fileManagerOptions = {
        config: config,
        lang: lang,
        actions: actions,
        sortings: sortings,
        connectorUrl: fmRoute,
        acceptedFiles: acceptedFiles,
        maxFilesize: maxFilesize,
    };
    cresenity.fileManager = new CFileManager(fileManagerOptions);




</script>

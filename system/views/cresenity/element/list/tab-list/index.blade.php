<?php
/**
 * @var string                        $id
 * @var string                        $classes
 * @var array                         $cresConfig
 * @var CElement_List_TabList_Tab[]   $tabs
 * @var null|CElement_List_TabList_Tab $activeTab
 * @var string                        $tabPosition 'left' or 'top'
 * @var bool                          $ajax
 * @var null|CElement_Element_Div     $header
 * @var bool                          $haveIcon
 * @var string                        $widgetWrapperClass
 * @var string                        $widgetBodyClass
 * @var string                        $widgetHeaderClass
 * @var string                        $widgetClasses
 */
$navUlClass = 'nav nav-tabs nav-stacked' . ($tabPosition == 'left' ? ' tabs-vertical' : '');
$menuClass = $tabPosition == 'top' ? 'row-tab-menu row-tab-menu-top' : 'span2 row-tab-menu row-tab-menu-left';
$navContainerClass = $tabPosition == 'top' ? 'top-nav-container d-flex align-items-center' : 'side-nav-container affix-top';
$contentColClass = $tabPosition == 'top' ? 'row-tab-content row-tab-content-top' : 'span10 row-tab-content row-tab-content-left';
?>
<div class="row-fluid tab-list cres:element:component:TabList <?php echo c::e($classes); ?>" cres-element="component:TabList" cres-config="<?php echo c::jsonAttr($cresConfig); ?>">
    <div class="span12">
        <div class="row-fluid">
            <div class="<?php echo $menuClass; ?>">
                <div class="<?php echo $navContainerClass; ?>">
                    <ul id="<?php echo $id; ?>-tab-nav" class="<?php echo $navUlClass; ?>">
                        <?php foreach ($tabs as $tab) {
                            $tabClasses = implode(' ', $tab->getClasses());
                            if ($tab->isNoPadding()) {
                                $tabClasses = trim($tabClasses . ' nopadding');
                            }
                            $activeClass = $tab->isActive() ? 'active' : '';
                            ?>
                        <li class="nav-item w-100 p-1 <?php echo $activeClass; ?>">
                            <a href="javascript:;"
                                <?php if (strlen($tabClasses) > 0) { ?>data-class="<?php echo c::e($tabClasses); ?>"<?php } ?>
                                <?php if (strlen($tab->getIcon()) > 0) { ?>data-icon="<?php echo c::e($tab->getIcon()); ?>"<?php } ?>
                                data-tab="<?php echo c::e($tab->id()); ?>"
                                <?php if (strlen($tab->getTarget()) > 0) { ?>data-target="<?php echo c::e($tab->getTarget()); ?>"<?php } ?>
                                tab-responsive="#<?php echo c::e($tab->id()); ?>"
                                <?php if (strlen($tab->getAjaxUrl()) > 0) { ?>data-url="<?php echo c::e($tab->getAjaxUrl()); ?>"<?php } ?>
                                class="nav-link <?php echo $activeClass; ?> tab-ajax-load">
                                <?php if (strlen($tab->getIcon()) > 0) { ?>
                                <span class="icon"><i class="<?php echo c::e($tab->getIcon()); ?>"></i></span>
                                <?php } ?>
                                <?php echo $tab->getLabel(); ?>
                            </a>
                        </li>
                        <?php } ?>
                    </ul>
                    <?php if ($header != null) {
                        echo $header->html();
                    } ?>
                </div>
            </div>
            <div class="<?php echo $contentColClass; ?>">
                <div id="<?php echo $id; ?>-tab-widget" class="<?php echo c::e($widgetWrapperClass); ?> nomargin widget-transaction-tab <?php echo c::e($widgetClasses); ?>">
                    <?php if ($tabPosition != 'top') { ?>
                    <div class="<?php echo c::e($widgetHeaderClass); ?> tab-widget-header">
                        <?php if ($haveIcon) { ?>
                        <span class="icon">
                            <i class="icon-<?php echo $activeTab ? c::e($activeTab->getIcon()) : ''; ?>"></i>
                        </span>
                        <?php } ?>
                        <h5><?php echo $activeTab ? $activeTab->getLabel() : ''; ?></h5>
                    </div>
                    <?php } ?>
                    <div class="<?php echo c::e($widgetBodyClass); ?> tab-widget-body">
                        <?php if ($ajax) { ?>
                        <div id="<?php echo $ajaxContentId; ?>" class="ajax-tab-content"></div>
                        <?php } else {
                            foreach ($tabs as $tab) {
                                echo $tab->html();
                            }
                        } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

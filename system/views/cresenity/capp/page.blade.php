<?php
defined('SYSPATH') or die('No direct access allowed.');

$session = CSession::instance();
$user = $session->get('user');
$role_id = '';
$username = '';
$user_id = '';
if ($user) {
    $role_id = $user->role_id;
    $user_id = $user->user_id;
    $username = $user->username;
}
$app = CApp::instance();
$org = $app->org();
?>
<!DOCTYPE html>
<html class="no-js material-style" lang="{{ str_replace('_', '-', CF::getLocale()) }}" >
    <head>
        <meta charset="utf-8">
        <title>@CAppPageTitle</title>

        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="">
        @CAppStyles
    </head>
    <body>

        <div id="wrapper" class="layout-wrapper layout-2">
            <div class="layout-inner">

                <nav id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-sidenav-theme navbar-default navbar-static-side layout-color-white" role="navigation">
                    <div class="brand">
                        <span class="brand-logo">
                            <img src="" />
                        </span>
                        <a href="<?php echo curl::base() . 'admin'; ?>"
                           class="brand-name sidenav-text font-weight-normal ml-2">
                            @CAppTitle
                        </a>
                    </div>
                    <div class="sidenav-divider mt-0"></div>
                    <div class="sidenav-inner py-1 ps">
                        @CAppNav
                    </div> <!-- /sidebar-collapse -->
                </nav> <!-- /nav -->


                <div id="page-wrapper" class="layout-container">

                    <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center container-p-x bg-navbar-theme font-color-white" id="layout-navbar">
                        <a href="<?php echo curl::base(); ?>" class="navbar-brand brand d-lg-none py-0">
                            <span class="brand-logo">
                                <img src="" />
                            </span>
                            <span class="brand-name font-weight-normal ml-2">@CAppTitle</span>
                        </a>
                        <div class="layout-sidenav-toggle navbar-nav align-items-lg-center mr-auto">
                            <a class="nav-item nav-link px-0 ml-2 ml-lg-0" href="javascript:void(0)">
                                <i class="ion ion-md-menu text-large align-middle"></i>
                            </a>
                        </div>

                        <button class="navbar-toggler collapsed" type="button" data-toggle="collapse" data-target="#layout-navbar-collapse" aria-expanded="false">
                            <span class="navbar-toggler-icon"></span>
                        </button>

                        <span class="ml-3">

                        </span>
                        <div class="navbar-nav align-items-lg-center ml-auto">

                            <div class="navbar-collapse collapse" id="layout-navbar-collapse" >

                                <a href="javascript:void(0)" class="layout-sidenav-toggle sidenav-link text-large"></a>
                                <!-- Divider -->
                                <hr class="d-lg-none w-100 my-2">
                                <div class="navbar-nav align-items-lg-center ml-auto">
                                    <span id="servertime"></span>


                                    <!--
                                <div class="navbar-messages nav-item  mr-lg-3">
                                    <a id="toggle-fullscreen" class="nav-link hide-arrow" href="#" >
                                        <i class="ion ion-ios-expand navbar-icon align-middle"></i>
                                        <span class="d-lg-none align-middle">&nbsp; Fullscreen</span>
                                    </a>
                                </div>
                                    -->


                                    <div class="navbar-user nav-item dropdown dropdown-pull-right">
                                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                                            <span class="d-inline-flex flex-lg-row-reverse align-items-center align-middle">
                                                <i class="ion ion-ios-person"></i>
                                                <span class="px-1 mr-lg-2 ml-2 ml-lg-0"><?php echo $username; ?></span>
                                            </span>
                                        </a>

                                        <div class="dropdown-menu pull-right">

                                            <li><a href="<?php echo curl::base(); ?>admin/account/password/change"><i class="fas fa-key"></i>&nbsp;&nbsp;<?php echo c::__('Change Password'); ?></a></li>

                                            <li><a href="<?php echo curl::base(); ?>admin/auth/logout"><i class="fas fa-sign-out-alt"></i>&nbsp;&nbsp;<?php echo c::__('Logout'); ?></a></li>
                                        </div>

                                    </div>
                                </div>
                            </div><!--/.nav-collapse -->
                        </div>


                    </nav> <!-- /navbar -->


                    <div class="main layout-content">
                        <div class="main-inner container-fluid flex-grow-1 container-p-y">
                            <div class="row page-heading">
                                <div class="col-lg-12">
                                    <?php if ($show_breadcrumb): ?>
                                        <?php
                                        if ($breadcrumb == '') {
                                            $breadcrumb = $title;
                                        }
                                        if (!is_array($breadcrumb)) {
                                            $breadcrumb = [];
                                        }
                                        if (CFRouter::$controller != 'home')
                                            :?>
                                            <div class="bg-lightest container-m--x container-m--y mb-3">
                                                <ol class="breadcrumb text-big container-p-x py-3 m-0">
                                                    <li class="breadcrumb-item">
                                                        <a href="<?php echo curl::base() . 'admin'; ?>" class="tip-bottom" data-original-title="Go to Home"><i class="ion ion-ios-home"></i></a>
                                                    </li>
                                                    <?php foreach ($breadcrumb as $k => $b) : ?>
                                                        <li class="breadcrumb-item">
                                                            <a href="<?php echo $b ?>" class=""><?php echo $k; ?></a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                    <li class="breadcrumb-item active">
                                                        <a href="javascript:;" class="active"><strong><?php echo $title; ?></strong></a>
                                                    </li>
                                                </ol>
                                                <hr class="m-0">
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($show_title): ?>
                                        <h4 class="font-weight-bold py-3 mb-4"><?php echo $title ?></h4>
                                    <?php endif; ?>

                                </div>
                            </div>

                            <?php
                            $msg = cmsg::flash_all();
                            if (strlen($msg) > 0) {
                                echo '<div class="row-fluid"><div class="span12">' . $msg . '</div></div>';
                            }
                            ?>
                            <div class="wrapper wrapper-content">
                                <div class="row">
                                    <div class="col-lg-12">

                                        <div class="container">

                                            @CAppContent

                                        </div>

                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="layout-footer footer bg-footer-theme">
                        <div class="container-fluid d-flex flex-wrap justify-content-between text-center container-p-x pb-3">
                            <div class="pt-3">
                                <span class="footer-text font-weight-bolder">
                                    &copy; <?php echo date('Y'); ?> <?php echo CF::codeName(); ?> V<?php echo CF::version(); ?>
                                </span>
                            </div> <!-- /span12 -->
                        </div>
                        <div></div> <!-- /row -->

                    </div> <!-- /footer -->
                </div><!-- /page-wrapper -->
            </div>
            <div class="layout-overlay layout-sidenav-toggle"></div>
        </div><!-- /wrapper -->
        @CAppScripts
    </body>
</html>

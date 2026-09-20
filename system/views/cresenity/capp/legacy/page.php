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
<html class="no-js capp-legacy" lang="<?php echo clang::getlang(); ?>" >
    <head>
        <meta charset="utf-8">
        <title><?php echo $title; ?></title>
        <?php echo $head_client_script; ?>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="<?php echo curl::base(); ?>media/img/favico.png">

        <?php echo $additional_head; ?>
    </head>
    <body>
        <?php echo $custom_header; ?>
        <?php echo $begin_client_script; ?>
        <div class="navbar navbar-fixed-top">

            <div class="navbar-inner">

                <div class="container">

                    <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </a>

                    <a class="brand" href="<?php echo curl::base(); ?>">
                        <?php
                        $web_title = CApp_Config::get('title');
                        //if($org!=null) $web_title = strtoupper($org->name);
                        echo $web_title;
                        ?>
                    </a>
                    <span id="servertime">
                    </span>
                    <div class="nav-collapse">
                        <ul class="nav pull-right">
                            <?php if (CApp_Config::get('top_menu_cashier')): ?>
                                <li >
                                    <a href="<?php echo curl::base(); ?>retail/sales" id="">
                                        <i class="icon-th"></i>
                                        <span>Cashier</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li >
                                <a href="javascript:void(0)" id="toggle-fullscreen">
                                    <i class="icon-fullscreen"></i>

                                </a>
                            </li>
                            <li >
                                <?php
                                $show_nav = CApp_Var::getVar('NAV_MENU_SHOW', $user_id);
                                if ($show_nav == null) {
                                    $show_nav = true;
                                }
                                if ($show_nav) :?>
                                    <a href="javascript:void(0)" id="toggle-subnavbar">
                                        <i class="icon-th"></i>
                                        <span>Hide</span>
                                    </a>
                                <?php else: ?>
                                    <a href="javascript:void(0)" id="toggle-subnavbar">
                                        <i class="icon-th"></i>
                                        <span>Show</span>
                                    </a>

                                <?php endif; ?>

                            </li>
                            <?php if (CApp_Config::get('multilang')): ?>
                                <li class="dropdown">

                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                        <img style="padding-right:5px;display:inline-block;margin-top:-3px;" src="<?php echo curl::base(); ?>media/img/flags/<?php echo clang::getlang(); ?>.gif" />
                                        <?php echo clang::current_lang_name(); ?>
                                        <b class="caret"></b>
                                    </a>

                                    <ul class="dropdown-menu">
                                        <?php
                                        $list = clang::get_lang_list();
                                        foreach ($list as $k => $v) {
                                            $active = '';
                                            if ($k == clang::getlang()) {
                                                $active = 'active';
                                            }
                                            $img = '<img style="padding-right:10px;display:inline-block;margin-top:-3px;" src="' . curl::base() . 'media/img/flags/' . $k . '.gif" />';
                                            echo '<li class="' . $active . '"><a href="' . curl::base() . 'cresenity/change_lang/' . $k . '" hreflang="' . $k . '">' . $img . ' ' . $v . '</a></li>';
                                        }
                                        ?>

                                    </ul>

                                </li>
                            <?php endif; ?>

                            <li class="dropdown">

                                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                    <i class="icon-user"></i>
                                    <?php echo $username; ?>
                                    <b class="caret"></b>
                                </a>

                                <ul class="dropdown-menu">
                                    <!--
                                    <li><a href="javascript:;">My Profile</a></li>
                                    <li><a href="javascript:;">My Groups</a></li>
                                    <li class="divider"></li>
                                    -->
                                    <li><a href="<?php echo curl::base(); ?>account/profile"><i class="icon icon-user"></i>&nbsp;&nbsp;<?php echo c::__('My Profile'); ?></a></li>
                                    <li><a href="<?php echo curl::base(); ?>account/settings"><i class="icon icon-wrench"></i>&nbsp;&nbsp;<?php echo c::__('My Settings'); ?></a></li>
                                    <li class="divider"></li>
                                    <li><a href="<?php echo curl::base(); ?>account/change_password"><i class="icon icon-key"></i>&nbsp;&nbsp;<?php echo c::__('Change Password'); ?></a></li>
                                    <li><a href="<?php echo curl::base(); ?>cresenity/logout"><i class="icon icon-signout"></i>&nbsp;&nbsp;<?php echo c::__('Logout'); ?></a></li>
                                </ul>

                            </li>
                        </ul>
                        <!--
                        <form class="navbar-search pull-right">
                                <input type="text" class="search-query" placeholder="Search">
                        </form>
                        -->
                    </div><!--/.nav-collapse -->

                </div> <!-- /container -->

            </div> <!-- /navbar-inner -->

        </div> <!-- /navbar -->

        <div class="subnavbar" id="subnavbar" <?php echo !$show_nav ? 'style="display:none"' : ''; ?>>

            <div class="subnavbar-inner">

                <div class="container">
                    <?php
                    //echo cmenu::populate_menu($role_id);
                    echo CNavigation::instance()->render();
                    ?>


                </div> <!-- /container -->

            </div> <!-- /subnavbar-inner -->

        </div> <!-- /subnavbar -->

        <div class="main">

            <div class="main-inner">

                <div id="container" class="container">


                    <!--
                    <div class="btn-group" style="width: auto; ">
                            <a class="btn btn-large tip-bottom" data-original-title="Manage Files"><i class="icon-file"></i></a>
                            <a class="btn btn-large tip-bottom" data-original-title="Manage Users"><i class="icon-user"></i></a>
                            <a class="btn btn-large tip-bottom" data-original-title="Manage Comments"><i class="icon-comment"></i><span class="label label-important">5</span></a>
                            <a class="btn btn-large tip-bottom" data-original-title="Manage Orders"><i class="icon-shopping-cart"></i></a>
                    </div>
                    -->
                    <!--
                    <div class="container-fluid">
                    -->
                    <?php if ($show_title): ?>
                        <h1><?php echo $title; ?></h1>
                    <?php endif; ?>
                    <?php if ($show_breadcrumb): ?>
                        <?php
                        if ($breadcrumb == '') {
                            $breadcrumb = $title;
                        }
                        if (!is_array($breadcrumb)) {
                            $breadcrumb = [];
                        }
                        if (CFRouter::$controller != 'home') :?>
                            <div id="breadcrumb">
                                <a href="<?php echo curl::base(); ?>" class="tip-left" data-original-title="Go to Home"><i class="icon-home"></i> Home</a>
                                <?php foreach ($breadcrumb as $k => $b) : ?>
                                    <a href="<?php echo $b; ?>" class=""><?php echo $k; ?></a>
                                <?php endforeach; ?>
                                <a href="javascript:;" class="current"><?php echo $title; ?></a>
                            </div>
                            <?php
                        endif;
                        ?>
                    <?php endif; ?>
                    <?php
                    $msg = cmsg::flash_all();
                    if (strlen($msg) > 0) {
                        echo '<div class="row-fluid"><div class="span12">' . $msg . '</div></div>';
                    }
                    ?>


                    <?php echo $content; ?>

                    <?php defined('SYSPATH') or die('No direct access allowed.'); ?>


                </div>
            </div>
        </div>
        <div class="footer">
            <div class="footer-inner">
                <div class="container">
                    <div class="row">
                        <div class="span12">
                            &copy; 2017 <a href="http://ittron.co.id">Ittron Global Technology</a>.
                        </div> <!-- /span12 -->
                    </div> <!-- /row -->
                </div> <!-- /container -->
            </div> <!-- /footer-inner -->
        </div> <!-- /footer -->

        <script src="<?php echo curl::base(); ?>media/js/require.js"></script>
        <!-- Load javascript here -->

        <?php echo $end_client_script; ?>

        <script language="javascript">

<?php
echo $js;
echo $ready_client_script;
?>

            if (window) {
                window.onload = function () {
<?php echo $load_client_script; ?>
                }
            }
<?php echo $custom_js; ?>
        </script>
        <?php echo $custom_footer; ?>
    </body>
</html>
<?php
if (CApp_Config::get('log_request')) {
    $user = CApp::instance()->user();
    if ($user != null) {
        CApp_Log_Request::populate();
    }
}
?>

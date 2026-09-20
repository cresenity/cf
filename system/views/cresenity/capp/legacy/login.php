<?php
defined('SYSPATH') OR die('No direct access allowed.');

$username = "";
$password = "";
if (isset($_GET["demo"])) {
    $username = "demo";
    $password = "demo";
}
?>
<!DOCTYPE html>
<html class="no-js" lang="en">
    <head>
        <meta charset="utf-8">
        <title>Login</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="<?php echo curl::base(); ?>media/css/cresenity.reset.css" rel="stylesheet">
        <link href="<?php echo curl::base(); ?>media/css/bootstrap.css" rel="stylesheet">
        <link href="<?php echo curl::base(); ?>media/css/bootstrap-responsive.css" rel="stylesheet">

        <link href="<?php echo curl::base(); ?>media/css/font-awesome.css" rel="stylesheet">

        <link href="<?php echo curl::base(); ?>media/css/cresenity.colors.css" rel="stylesheet">
        <link href="<?php echo curl::base(); ?>media/css/cresenity/cresenity.form.css" rel="stylesheet">
        <link href="<?php echo curl::base(); ?>media/css/cresenity/cresenity.message.css" rel="stylesheet">
        <link href="<?php echo curl::base(); ?>media/css/cresenity/cresenity.utilities.css" rel="stylesheet">
        <link href="<?php echo curl::base(); ?>media/css/cresenity/cresenity.generic-element.css" rel="stylesheet">
        <link href="<?php echo curl::base(); ?>media/css/cresenity/cresenity.block-arrow.css" rel="stylesheet">
        <link href="<?php echo curl::base(); ?>media/css/cresenity.main.css" rel="stylesheet">
        <link href="<?php echo curl::base(); ?>media/css/cresenity.login.css" rel="stylesheet">
        <!-- JavaScript at bottom except for Modernizr -->
        <script src="<?php echo curl::base(); ?>media/js/libs/modernizr.custom.js"></script>

    </head>
    <body>

        <div class="navbar navbar-fixed-top">

            <div class="navbar-inner">

                <div class="container">

                    <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </a>

                    <a class="brand" href="<?php echo curl::base(); ?>">
                        <?php echo CApp_Config::get('title'); ?>				
                    </a>		

                    <div class="nav-collapse">
                        <ul class="nav pull-right">
                            <?php if (CApp_Config::get("multilang")): ?>
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
                                            $active = "";
                                            if ($k == clang::getlang())
                                                $active = "active";
                                            $img = '<img style="padding-right:10px;display:inline-block;margin-top:-3px;" src="' . curl::base() . 'media/img/flags/' . $k . '.gif" />';
                                            echo '<li class="' . $active . '"><a href="' . curl::base() . 'cresenity/change_lang/' . $k . '" hreflang="' . $k . '">' . $img . ' ' . $v . '</a></li>';
                                        }
                                        ?>

                                    </ul>

                                </li>
                            <?php endif; ?>
                            <?php if (CApp_Config::get("signup")): ?>
                                <li class="">						
                                    <a href="<?php echo curl::base(); ?>cresenity/signup" class="">
                                        Create an Account
                                    </a>

                                </li>
                            <?php endif; ?>
                        </ul>

                    </div><!--/.nav-collapse -->	

                </div> <!-- /container -->

            </div> <!-- /navbar-inner -->

        </div> <!-- /navbar -->



        <div class="account-container">
            <div class="logo">
                <img src="<?php echo curl::base(); ?>media/img/cresenity-logo.png" />
            </div>
            <div class="content clearfix" id="container">

                <form method="post" action="" id="form-login">

                    <h1 class="align-center"><?php echo c::__('Sign In'); ?></h1>		

                    <div class="login-fields">
                        <h4 class="align-center"><?php echo CApp::instance()->name() ?></h4>
                        <p>&nbsp;</p>
                        <!-- <p><?php echo c::__('Sign in using your registered account'); ?>:</p> -->

                        <div class="field">
                            <label for="email"><?php echo c::__('ID'); ?>:</label>
                            <input type="text" id="email" name="email" value="<?php echo $username; ?>" placeholder="Email/Username" class="login username-field" autocomplete="off"/>
                        </div> <!-- /field -->

                        <div class="field">
                            <label for="password"><?php echo c::__('Password'); ?>:</label>
                            <input type="password" id="password" name="password" value="<?php echo $password; ?>" placeholder="Password" class="login password-field" autocomplete="off"/>
                        </div> <!-- /password -->

                    </div> <!-- /login-fields -->
                    <div class="login-actions">

                        <span class="login-checkbox">
                            <!--
                            <input id="Field" name="Field" type="checkbox" class="field login-checkbox" value="First Choice" tabindex="4" />
                            <label class="choice" for="Field"><?php echo c::__('Keep me signed in'); ?></label>
                            -->
                        </span>

                        <button class="button btn btn-warning btn-large"><?php echo c::__('Sign In'); ?></button>

                    </div> <!-- .actions -->



                </form>

            </div> <!-- /content -->

        </div> <!-- /account-container -->

        <?php if (CApp_Config::get("signup")): ?>

            <!-- Text Under Box -->
            <div class="login-extra">
                Don't have an account? <a href="<?php echo curl::base(); ?>cresenity/signup"><?php echo c::__('Sign Up'); ?></a><br/>
                <!--
                <?php echo c::__('Remind'); ?> <a href="<?php echo curl::base(); ?>/cresenity/forget_password"><?php echo c::__('Password'); ?></a>
                -->
            </div> <!-- /login-extra -->
        <?php endif; ?>
        <!--
        <div id="container">
        
                        <hgroup id="login-title" class="large-margin-bottom">
                                <h1 class="login-title-image">192.168.0.4</h1>
                                <h5>&copy; Cresenity</h5>
                        </hgroup>
        
                        <form method="post" action="" id="form-login">
                                <ul class="inputs black-input large">
                                        <li><span class="icon-user mid-margin-right"></span><input type="text" name="username" id="username" value="" class="input-unstyled" placeholder="Login" autocomplete="off"></li>
                                        <li><span class="icon-lock mid-margin-right"></span><input type="password" name="password" id="password" value="" class="input-unstyled" placeholder="Password" autocomplete="off"></li>
                                        <li><span class="mid-margin-right"><img id="img_captcha" src="<?php echo curl::base() . "index.php/cresenity/captcha/" ?>" alt=", type it in the box" width="50" height="24" align="absbottom"></span><input type="text" name="captcha" id="captcha" value="" class="captcha input-unstyled" placeholder="Verification code" autocomplete="off"></li>
                                        
                                </ul>
        
                                <button type="submit" class="btn full-width">Login</button>
                        </form>
        
                </div>
        -->
        <!-- Load javascript here -->
        <script src="<?php echo curl::base(); ?>media/js/libs/jquery.js"></script>
        <script src="<?php echo curl::base(); ?>media/js/libs/jquery.ui.custom.js"></script>


        <script src="<?php echo curl::base(); ?>media/js/libs/bootstrap.min.js"></script>


        <script src="<?php echo curl::base(); ?>media/js/cresenity.setup.js"></script>
        <script src="<?php echo curl::base(); ?>media/js/cresenity.message.js"></script>
        <script>

            /*
             * How do I hook my login script to this?
             * --------------------------------------
             *
             * This script is meant to be non-obtrusive: if the user has disabled javascript or if an error occurs, the login form
             * works fine without ajax.
             *
             * The only part you need to edit is the login script between the EDIT SECTION tags, which does inputs validation
             * and send data to server. For instance, you may keep the validation and add an AJAX call to the server with the
             * credentials, then redirect to the dashboard or display an error depending on server return.
             *
             * Or if you don't trust AJAX calls, just remove the event.preventDefault() part and let the form be submitted.
             */

            jQuery(document).ready(function ()
            {
                /*
                 * JS login effect
                 * This script will enable effects for the login page
                 */
                // Elements
                var doc = jQuery('html').addClass('js-login'),
                        container = jQuery('#container'),
                        formLogin = jQuery('#form-login'),
                        // If layout is centered
                        centered;



                /*
                 * AJAX login
                 * This function will handle the login process through AJAX
                 */
                formLogin.submit(function (event)
                {
                    // Values
                    var login = jQuery.trim(jQuery('#email').val()),
                            pass = jQuery.trim(jQuery('#password').val()),
                            captcha = jQuery.trim(jQuery('#captcha').val());

                    // Check inputs
                    if (login.length === 0) {
                        // Remove previous messages
                        formLogin.clearMessages();
                        // Display message
                        displayError('Please fill in your username');
                        return false;
                    } else if (pass.length === 0) {
                        // Remove previous messages
                        formLogin.clearMessages();
                        // Display message
                        displayError('Please fill in your password');
                        return false;
                    } else {
                        // Remove previous messages
                        formLogin.clearMessages();

                        // Show progress
                        displayLoading('Checking credentials...');

                        event.preventDefault();

                        // Stop normal behavior
                        event.preventDefault();

                        /*
                         * This is where you may do your AJAX call, for instance:
                         */
                        var url = '<?php echo curl::base(); ?>index.php/cresenity/login';

                        jQuery.ajax(url, {
                            dataType: 'json',
                            type: 'POST',
                            data: $("#form-login").serialize(),
                            success: function (data) {
                                var result = data.result;
                                var message = data.message;
                                if (result == "OK") {
                                    document.location.href = '<?php echo curl::base() . curl::current() . CFRouter::$query_string; ?>';
                                } else {
                                    formLogin.clearMessages();
                                    displayError(message);
                                }

                            },
                            error: function () {
                                formLogin.clearMessages();
                                displayError('Error while contacting server, please try again');
                            }
                        });


                    }
                });



                // Handle resizing (mostly for debugging)
                function handleLoginResize()
                {
                    // Detect mode
                    centered = (container.css('position') === 'absolute');

                    // Set min-height for mobile layout
                    if (!centered)
                    {
                        container.css('margin-top', '');
                    } else
                    {
                        if (parseInt(container.css('margin-top'), 10) === 0)
                        {
                            centerForm(false);
                        }
                    }
                }
                ;

                // Register and first call
                $(window).bind('normalized-resize', handleLoginResize);
                handleLoginResize();

                /*
                 * Center function
                 * @param boolean animate whether or not to animate the position change
                 * @param string|element|array any jQuery selector, DOM element or set of DOM elements which should be ignored
                 * @return void
                 */
                function centerForm(animate, ignore)
                {
                    // If layout is centered
                    if (centered)
                    {
                        var siblings = formLogin.siblings(),
                                finalSize = formLogin.outerHeight();

                        // Ignored elements
                        if (ignore)
                        {
                            siblings = siblings.not(ignore);
                        }

                        // Get other elements height
                        siblings.each(function (i)
                        {
                            finalSize += $(this).outerHeight(true);
                        });

                        // Setup
                        container[animate ? 'animate' : 'css']({marginTop: -Math.round(finalSize / 2) + 'px'});
                    }
                }
                ;

                // Initial vertical adjust
                centerForm(false);

                /**
                 * Function to display error messages
                 * @param string message the error to display
                 */
                function displayError(message)
                {
                    // Show message
                    var message = formLogin.message(message, {
                        append: false,
                        arrow: 'none',
                        classes: ['red-gradient'],
                        animate: false					// We'll do animation later, we need to know the message height first
                    });

                    // Vertical centering (where we need the message height)
                    centerForm(true, 'fast');

                    // Watch for closing and show with effect
                    message.bind('endfade', function (event)
                    {
                        // This will be called once the message has faded away and is removed
                        centerForm(true, message.get(0));

                    }).hide().slideDown('fast');
                }

                /**
                 * Function to display loading messages
                 * @param string message the message to display
                 */
                function displayLoading(message)
                {
                    // Show message
                    var message = formLogin.message('<strong>' + message + '</strong>', {
                        append: false,
                        arrow: 'none',
                        classes: ['blue-gradient', 'align-center'],
                        stripes: true,
                        darkStripes: false,
                        closable: false,
                        animate: false					// We'll do animation later, we need to know the message height first
                    });

                    // Vertical centering (where we need the message height)
                    centerForm(true, 'fast');

                    // Watch for closing and show with effect
                    message.bind('endfade', function (event)
                    {
                        // This will be called once the message has faded away and is removed
                        centerForm(true, message.get(0));

                    }).hide().slideDown('fast');
                }
            });

            // What about a notification?
            //notify('This is new demo of solusigps', 'Want to see another login page style? Try the <a href="login-box.html"><b>box version</b></a> or the <a href="login-full.html"><b>full version</b></a>.', {
            //	autoClose: false,
            //	delay: 2500,
            //	icon: 'img/demo/icon.png'
            //});

        </script>

    </body>
</html>
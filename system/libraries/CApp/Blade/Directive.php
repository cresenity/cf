<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @see CApp
 */
class CApp_Blade_Directive {
    /**
     * Blade directive handler for @styles.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function styles($expression) {
        return '{!! CApp::instance()->renderStyles() !!}';
    }

    /**
     * Blade directive handler for @message.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function message($expression) {
        return '{!! CApp_Message::flashAll() !!}';
    }

    /**
     * Blade directive handler for @scripts.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function scripts($expression) {
        return '
        {!! CApp::instance()->renderScripts() !!}
        ';
    }

    /**
     * Blade directive handler for @pageTitle.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function pageTitle($expression) {
        return '{!! CApp::instance()->renderPageTitle() !!}';
    }

    /**
     * Blade directive handler for @title.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function title($expression) {
        return '{!! CApp::instance()->renderTitle() !!}';
    }

    /**
     * Blade directive handler for @nav.
     *
     * @param string $expression the raw expression passed to the directive
     *
     * @return string
     */
    public static function nav($expression) {
        return '{!! CApp::instance()->renderNavigation(' . $expression . ') !!}';
    }

    /**
     * Blade directive handler for @seo.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function seo($expression) {
        return '{!! CApp::instance()->renderSeo() !!}';
    }

    /**
     * Blade directive handler for @content.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function content($expression) {
        return '{!! CApp::instance()->renderContent() !!}';
    }

    /**
     * Blade directive handler for @react.
     *
     * @param string $expression the raw expression passed to the directive
     *
     * @return string
     */
    public static function react($expression) {
        return '{!! CApp_React::render(' . $expression . ') !!}';
    }

    /**
     * Blade directive handler for @startReact.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function startReact($expression) {
        return '<?php \CApp::instance()->startPush(\'capp-react\') ?>';
    }

    /**
     * Blade directive handler for @endReact.
     *
     * @param string $expression the raw expression passed to the directive
     *
     * @return string
     */
    public static function endReact($expression) {
        return '<?php \CApp::instance()->stopPush(\'capp-react\') ?>' . '{!! CApp_React::render(' . $expression . ', CApp::instance()->yieldPushContent(\'capp-react\')) !!}';
    }

    /**
     * Blade directive handler for @pushScript.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function pushScript($expression) {
        return '<?php \CApp::instance()->startPush(\'capp-script\') ?>';
    }

    /**
     * Blade directive handler for @endPushScript.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function endPushScript($expression) {
        return '<?php \CApp::instance()->stopPush(\'capp-script\'); ?>';
    }

    /**
     * Blade directive handler for @prependScript.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function prependScript($expression) {
        return '<?php \CApp::instance()->startPrepend(\'capp-script\'); ?>';
    }

    /**
     * Blade directive handler for @endPrependScript.
     *
     * @param string $expression the raw expression passed to the directive (unused)
     *
     * @return string
     */
    public static function endPrependScript($expression) {
        return '<?php \CApp::instance()->stopPrepend(\'capp-script\'); ?>';
    }

    /**
     * Blade directive handler for @element.
     *
     * @param string $expression the raw expression passed to the directive
     *
     * @return string
     */
    public static function element($expression) {
        if (cstr::startsWith(trim($expression), 'function')) {
            return "<?php echo \CApp::instance()->yieldViewElement(isset(\$__CAppElementView) ? \$__CAppElementView : null, " . $expression . '); ?>';
        }
        $expression = str_replace(['(', ')'], '', $expression);
        $expression = str_replace(['"', '\''], '', $expression);
        $expression = str_replace(',', ' ', $expression);

        return "<?php echo \CApp::instance()->yieldViewElement(isset(\$__CAppElementView) ? \$__CAppElementView : null, '" . $expression . "'); ?>";
    }

    /**
     * Dispatch a directive by name, invoking the matching handler method.
     *
     * @param string $expression the directive name (e.g. 'styles', 'scripts', 'content', 'pageTitle', 'title')
     *
     * @throws InvalidArgumentException if the expression does not match a known directive
     *
     * @return string
     */
    public static function directive($expression) {
        $expression = str_replace(['(', ')'], '', $expression);
        $expression = str_replace(['"', '\''], '', $expression);
        $expression = str_replace(',', ' ', $expression);
        switch ($expression) {
            case 'styles':
                return static::styles($expression);
            case 'scripts':
                return static::scripts($expression);
            case 'content':
                return static::content($expression);
            case 'pageTitle':
                return static::pageTitle($expression);
            case 'title':
                return static::title($expression);
            default:
                throw new InvalidArgumentException('Argument ' . $expression . ' is invalid on CApp directive');
        }

        return $expression;
    }

    /**
     * Blade directive handler for @pwa.
     *
     * @param string $expression the raw expression passed to the directive
     *
     * @return string
     */
    public static function pwa($expression) {
        $expression = str_replace(['(', ')'], '', $expression);
        $expression = str_replace(['"', '\''], '', $expression);
        $expression = str_replace(',', ' ', $expression);

        return (new CApp_PWA_MetaService($expression))->render();
    }

    /**
     * Blade directive handler for @preloader. Renders the preloader HTML markup.
     *
     * @param string $expression the expression evaluating to the preloader image URL; defaults to the app logo when empty
     *
     * @return string
     */
    public static function preloader($expression) {
        if (strlen($expression) == 0) {
            $expression = c::url('media/img/logo.png');
        }

        return <<<HTML
<!-- Cres Preloader Start Here {$expression} -->
<div id="cres-preloader">
    <div class="preloader-container">
        <div class="preloader-loader">
        </div>
        <img src="<?php echo {$expression}; ?>" />
    </div>
</div>

<!-- Cres Preloader End Here -->
HTML;
    }
}

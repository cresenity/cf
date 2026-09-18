<?php

/**
 * Description of CManager_Theme.
 */
class CManager_Theme {
    /**
     * @var callable
     */
    protected static $themeCallback;

    protected static $themeData = [];

    protected static $theme = null;

    public static function setThemeCallback(callable $themeCallback) {
        self::$themeCallback = $themeCallback;
    }

    public static function getDefaultTheme() {
        $theme = static::$theme;
        if ($theme == null) {
            $theme = CF::config('app.theme');
        }

        return $theme;
    }

    public static function getCurrentTheme() {
        $theme = self::getDefaultTheme();
        if ($theme == null) {
            $theme = 'null';
        }

        if (self::$themeCallback != null && is_callable(self::$themeCallback)) {
            $theme = call_user_func(self::$themeCallback, $theme);
        }

        return $theme;
    }

    public static function setTheme($theme) {
        static::$theme = $theme;
    }

    public static function getThemeData($theme = null) {
        if ($theme == null) {
            $theme = self::getCurrentTheme();
        }
        if (!isset(self::$themeData[$theme])) {
            $themeFile = CF::getFile('themes', $theme);
            $themeAllData = null;
            if (CFile::exists($themeFile)) {
                $themeAllData = include $themeFile;
            }
            self::$themeData[$theme] = $themeAllData;
        }

        return self::$themeData[$theme];
    }

    public static function setThemeData($themeData, $theme = null) {
        if ($theme == null) {
            $theme = self::getCurrentTheme();
        }
        self::$themeData[$theme] = $themeData;

        return self::$themeData[$theme];
    }

    public static function getData($key, $default = null) {
        $themeAllData = self::getThemeData();
        $themeData = carr::get($themeAllData, 'data', $default);

        return carr::get($themeData, $key, $default);
    }

    /**
     * Get Theme Path.
     *
     * @return string
     *
     * @deprecated 1.1
     */
    public static function getThemePath() {
        $themePath = '';
        $theme = self::getCurrentTheme();
        $themeFile = CF::getFile('themes', $theme);
        if (file_exists($themeFile)) {
            $themeData = include $themeFile;
            $themePath = carr::get($themeData, 'theme_path');
            if ($themePath == null) {
                $themePath = '';
            } else {
                $themePath .= '/';
            }
        }

        return $themePath;
    }
}

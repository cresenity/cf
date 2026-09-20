<?php

class CManager_Lang_Data {
    /**
     * The language data.
     *
     * @var array
     */
    protected static $langData = [];

    /**
     * Get the first character of the message to use as a folder name.
     *
     * @param string $message
     *
     * @return string
     */
    protected static function getCharForFolder($message) {
        $char = '_';
        if (strlen($message) > 0) {
            $char = strtolower(substr($message, 0, 1));
            if (!preg_match('/^[a-zA-Z]$/', $char)) {
                $char = '_';
            }
        }
        return $char;
    }

    public static function getLangDir() {
        $dir = DOCROOT . 'application/' . CF::appCode() . '/default/lang/';
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        return $dir;
    }

    /**
     * Get the language file for the given message.
     *
     * @param string      $message
     * @param string|null $lang
     *
     * @return string
     */
    public static function getLangFile($message, $lang = null) {
        if ($lang == null) {
            $lang = CManager_Lang::getLang();
        }
        $dir = static::getLangDir();
        $dir .= $lang . '/';
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $char = static::getCharForFolder($message);
        $file = $dir . $char . '.php';

        return $file;
    }

    /**
     * Check if the language data exists for the given message.
     *
     * @param string      $lang
     * @param string      $char
     * @param string      $message
     *
     * @return bool
     */
    public static function langDataExists($lang, $char, $message) {
        if (static::langDataLoaded($lang, $char)) {
            return isset(static::$langData[$lang][$char][$message]);
        }

        return false;
    }

    /**
     * Check if the language data is loaded for the given character.
     *
     * @param string      $lang
     * @param string      $char
     *
     * @return bool
     */
    public static function langDataLoaded($lang, $char) {
        if (!is_array(static::$langData)) {
            return false;
        }
        if (!isset(static::$langData[$lang])) {
            return false;
        }
        if (!isset(static::$langData[$lang][$char])) {
            return false;
        }
        return true;
    }

    public static function addLangDataTranslation($lang, $message, $translation) {
        $char = static::getCharForFolder($message);

        if (!static::langDataExists($lang, $char, $message)) {
            static::load($char, $lang);
            //$message = addslashes($message);
            static::$langData[$lang][$char][$message] = $translation;
            return static::save($lang, $char);
        }
        return false;
    }

    public static function setLangDataTranslation($lang, $message, $translation) {
        $char = static::getCharForFolder($message);

        static::load($char, $lang);
        //$message = addslashes($message);
        static::$langData[$lang][$char][$message] = $translation;
        return static::save($lang, $char);
    }

    public static function getLangDataChar($lang, $message) {
        $char = static::getCharForFolder($message);
        if (!static::langDataLoaded($lang, $char)) {
            static::load($char, $lang);
        }

        return static::$langData[$lang][$char];
    }

    public static function getLangDataTranslation($lang, $message) {
        if (!CF::appCode()) {
            return $message;
        }
        $char = static::getCharForFolder($message);
        if (!static::langDataExists($lang, $char, $message)) {
            static::load($char, $lang);
        }
        $translation = null;
        if (isset(static::$langData[$lang][$char][$message])) {
            $translation = static::$langData[$lang][$char][$message];
        }
        return $translation;
    }

    public static function load($char, $lang = null) {
        $char = static::getCharForFolder($char);
        if (!isset(static::$langData)) {
            static::$langData = [];
        }
        if (!isset(static::$langData[$lang])) {
            static::$langData[$lang] = [];
        }
        if (!isset(static::$langData[$lang][$char])) {
            static::$langData[$lang][$char] = [];
        }
        $filename = static::getLangFile($char, $lang);
        if (file_exists($filename)) {
            static::$langData[$lang][$char] = include $filename;
        }

        return static::$langData;
    }

    public static function save($lang = null, $char = null) {
        foreach (static::$langData as $langKey => $subData) {
            if ($lang == null || $lang == $langKey) {
                foreach ($subData as $charKey => $subSubData) {
                    if ($char == null || $char == $charKey) {
                        $filename = static::getLangFile($charKey, $langKey);
                        CFile::putPhpValue($filename, $subSubData);
                    }
                }
            }
        }

        return true;
    }
}

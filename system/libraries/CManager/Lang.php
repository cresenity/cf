<?php

/**
 * Description of CManager_Lang
 */
class CManager_Lang implements CManager_LangInterface {
    const LANG_SESSION_NAME = '_CAPP_LANG';

    const DEFAULT_LANG = 'id';

    //@codingStandardsIgnoreStart
    public static function __($message, $params = [], $lang = null) {
        //@codingStandardsIgnoreEnd
        if ($lang == null) {
            $lang = static::getLang();
        }

        //get translation
        $translation = static::getTranslation($message, $params, $lang);

        return $translation;
    }

    public static function getLang() {
        $session = CSession::instance();
        $lang = $session->get(static::LANG_SESSION_NAME);
        if ($lang == null) {
            $lang = static::DEFAULT_LANG;
        }
        return $lang;
    }

    public static function setLang($langKey) {
        $session = CSession::instance();
        if (static::getLangName($langKey) !== null) {
            $lang = $session->set(static::LANG_SESSION_NAME, $langKey);
        }
        return $lang;
    }

    public static function getTranslation($message, $params = [], $lang = null) {
        if ($lang == null) {
            $lang = static::getLang();
        }

        $translation = CManager_Lang_Data::getLangDataTranslation($lang, $message);
        if ($translation === null) {
            //save to default language
            if (CF::config('lang.auto_add', false)) {
                CManager_Lang_Data::addLangDataTranslation(static::DEFAULT_LANG, $message, $message);
            }
            $translation = $message;
        }
        if (is_array($params)) {
            $translation = strtr($translation, $params);
        }
        return $translation;
    }

    public static function getLangAvailable() {
        $langAvailable = [
            'id' => 'Indonesian',
            'en' => 'English',
            'ms' => 'Malaysia',
            'zh' => 'Chinese',
        ];
        return $langAvailable;
    }

    public static function isLangAvailable($langKey) {
        return array_key_exists($langKey, static::getLangAvailable());
    }

    public static function getLangName($langKey = null) {
        if ($langKey == null) {
            $langKey = static::getLang();
        }
        $langAvailable = static::getLangAvailable();
        return carr::get($langAvailable, $langKey, null);
    }
}

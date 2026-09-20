<?php

//@codingStandardsIgnoreStart
/**
 * @deprecated 1.6, use c::__
 */
class clang {
    private static $lang = null;

    private static $langs = [
        'default' => 'Default',
        'en' => 'English',
        'id' => 'Indonesia',
    ];

    public static function __($message, $params = [], $lang = null) {
        CF::deprecated(__METHOD__, 'c::__', '1.6', null, true);
        if (strpos($message, '.') !== false) {
            $message = CTranslation::translator()->get($message, $params, $lang);
        }

        $langObject = CManager::lang();
        if ($lang == null) {
            $lang = $langObject->getLang();
        }

        //get translation
        $translation = $langObject->getTranslation($message, $params, $lang);

        return $translation;
    }

    /*
      public static function __($word, $params = array()) {
      if (!is_string($word))
      return $word;
      if (clang::$lang == null) {
      //get $lang variable
      $lang = clang::getlang();
      $files = CF::get_files('lang', $lang);

      $files = array_reverse($files);
      foreach ($files as $file) {
      $lang = include $file;
      // Merge in configuration
      if (!empty($lang) AND is_array($lang)) {
      foreach ($lang as $k => $v) {
      self::$lang[$k] = $v;
      }
      }
      }
      }

      if (isset(clang::$lang[$word])) {
      $word = clang::$lang[$word];
      }
      if (is_array($params)) {
      $word = strtr($word, $params);
      }
      return $word;
      }
     */

    public static function current_lang_name() {
        CF::deprecated(__METHOD__, 'CTranslation', '1.6', null, true);
        $code = clang::getlang();
        $name = clang::get_lang_name_by_code($code);

        return $name;
    }

    public static function get_lang_name_by_code($code) {
        CF::deprecated(__METHOD__, 'CTranslation', '1.6', null, true);
        foreach (self::$langs as $k => $v) {
            if ($k == $code) {
                return $v;
            }
        }

        return null;
    }

    public static function get_lang_list() {
        CF::deprecated(__METHOD__, 'CTranslation', '1.6', null, true);
        return self::$langs;
    }

    public static function defaultlang() {
        CF::deprecated(__METHOD__, 'CTranslation', '1.6', null, true);
        return ccfg::get('lang');
    }

    public static function getlang() {
        CF::deprecated(__METHOD__, 'CTranslation', '1.6', null, true);
        $session = CSession::instance();

        $lang = $session->get('lang');
        //die($lang);
        if ($lang == null) {
            $lang = clang::defaultlang();
        }

        return $lang;
    }

    public static function setlang($lang) {
        CF::deprecated(__METHOD__, 'CTranslation', '1.6', null, true);
        $session = CSession::instance();
        $session->set('lang', $lang);
    }

    public static function get_file($lang) {
        CF::deprecated(__METHOD__, 'CTranslation', '1.6', null, true);
        $file = CF::getFile('lang', $lang);
        if ($file != null) {
            return $file;
        }

        return null;
    }

    public static function get_dir($lang) {
        CF::deprecated(__METHOD__, 'CTranslation', '1.6', null, true);
        $file = CF::getDir('lang');
        if ($file != null) {
            return $file;
        }

        return null;
    }

    public static function langfiles($directory, $filename, $required = false, $ext = false) {
        CF::deprecated(__METHOD__, 'CTranslation', '1.6', null, true);
        // NOTE: This test MUST be not be a strict comparison (===), or empty
        // extensions will be allowed!
        if ($ext == '') {
            // Use the default extension
            $ext = EXT;
        } else {
            // Add a period before the extension
            $ext = '.' . $ext;
        }

        // Search path
        $search = $directory . '/' . $filename . $ext;

        // Load include paths
        //$paths = self::$include_paths;
        // Add APPPATH as the first path
        $paths = [APPPATH];

        foreach (CF::config('core.modules') as $path) {
            if ($path = str_replace('\\', '/', realpath($path))) {
                // Add a valid path
                $paths[] = $path . '/';
            }
        }

        // Add SYSPATH as the last path
        $paths[] = SYSPATH;

        // Nothing found, yet
        $found = null;

        if ($directory === 'config' or $directory === 'i18n' or $directory === 'lang') {
            // Search in reverse, for merging
            $paths = array_reverse($paths);

            foreach ($paths as $path) {
                if (is_file($path . $search)) {
                    // A matching file has been found
                    $found[] = $path . $search;
                }
            }
        } else {
            foreach ($paths as $path) {
                if (is_file($path . $search)) {
                    // A matching file has been found
                    $found = $path . $search;

                    // Stop searching
                    break;
                }
            }
        }

        if ($found === null) {
            if ($required === true) {
                // Directory i18n key
                $directory = 'core.' . $directory;

                // If the file is required, throw an exception
                throw new Exception(c::__('core.resource_not_found', ['filename' => c::__($directory)], $filename));
            } else {
                // Nothing was found, return FALSE
                $found = false;
            }
        }

        return $found;
    }
}

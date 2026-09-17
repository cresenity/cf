<?php

defined('SYSPATH') or die('No direct access allowed.');
/**
 * @deprecated sinze 1.6, use CManager or CManager::asset()->module()
 */
final class CClientModules {
    use CTrait_Compat_ClientModules;

    protected static $instance;

    public function allModules() {
        return CManager::asset()->module()->allModules();
    }

    public function requirements($module) {
        return CManager::asset()->module()->requirements($module);
    }

    public function isRegisteredModule($mod) {
        return CManager::asset()->module()->isRegisteredModule($mod);
    }

    public function getRegisteredModule() {
        return CManager::asset()->module()->getRegisteredModule();
    }

    public function registerModules($modules) {
        return CManager::asset()->module()->registerRunTimeModules($modules);
    }

    public function defineModule($name, $moduleData) {
        return CManager::asset()->module()->defineModule($name, $moduleData);
    }

    public function unregisterModule($module) {
        return CManager::asset()->module()->unregisterRunTimeModule($module);
    }

    public function registerModule($module, $parent = null) {
        return $this->registerRunTimeModule($module);
    }

    public function registerThemeModule($module, $parent = null) {
        return CManager::asset()->module()->registerThemeModule($module);
    }

    public function registerRunTimeModule($module, $parent = null) {
        return CManager::asset()->module()->registerRunTimeModule($module);
    }

    /**
     * @return CClientModules
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new CClientModules();
        }

        return self::$instance;
    }
}

<?php

use CApp_Navigation_Helper as Helper;

//@codingStandardsIgnoreStart
/**
 * @deprecated 1.8 use CApp_Navigation_Helper
 */
class cnav {
    public static function nav($nav = null, $controller = null, $method = null, $path = null) {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::nav($nav, $controller, $method, $path);
    }

    public static function have_access($nav = null, $role_id = null, $app_id = null, $domain = null) {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::haveAccess($nav, $role_id, $app_id, $domain);
    }

    public static function have_permission($action, $nav = null, $role_id = null, $app_id = null, $domain = null) {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::havePermission($action, $nav, $role_id, $app_id, $domain);
    }

    public static function app_user_rights_array($app_id, $role_id, $app_role_id = '', $domain = '') {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::appUserRightsArray($app_id, $role_id, $app_role_id, $domain);
    }

    public static function as_user_rights_array($app_id, $role_id, $navs = null, $app_role_id = '', $domain = '', $level = 0) {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::asUserRightsArray($app_id, $role_id, $navs, $app_role_id, $domain, $level);
    }

    public static function child_count($nav) {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::childCount($nav);
    }

    public static function have_child($nav) {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::haveChild($nav);
    }

    public static function is_leaf($nav) {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::isLeaf($nav);
    }

    public static function url($nav) {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::url($nav);
    }

    public static function access_available($nav = null, $appId = '', $domain = '', $appRoleId = '') {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::accessAvailable($nav, $appId, $domain, $appRoleId);
    }

    public static function permission_available($action, $nav = null, $appId = '', $domain = '', $appRoleId = '') {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::permissionAvailable($action, $nav, $appId, $domain, $appRoleId);
    }

    public static function render($navs = null, $level = 0, &$child = 0) {
        CF::deprecated(__METHOD__, 'CApp_Navigation_Helper', '1.8', null, true);
        return Helper::render($navs, $level, $child);
    }
}

//@codingStandardsIgnoreEnd

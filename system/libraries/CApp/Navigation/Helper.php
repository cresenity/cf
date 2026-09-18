<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.6 Use CNavigation_Manager instead
 */
class CApp_Navigation_Helper {
    /**
     * @var array<int|string, array<int|string, array>>
     */
    protected static $role_navs = [];

    /**
     * @param null|mixed $nav
     * @param null|mixed $controller
     * @param null|mixed $method
     * @param null|mixed $path
     *
     * @return array|bool
     */
    public static function nav($nav = null, $controller = null, $method = null, $path = null) {
        //Di luar konteks HTTP — CLI, tes, atau perintah konsol — tidak ada route
        //yang sedang berjalan, sehingga getCurrentRoute() bernilai null. Data
        //route hanya dipakai untuk menentukan menu mana yang aktif, jadi
        //ketiadaannya cukup membuat tidak ada yang ditandai aktif, bukan
        //menggagalkan seluruh perenderan navigasi.
        $currentRoute = c::router()->getCurrentRoute();
        $routeData = $currentRoute != null ? $currentRoute->getRouteData() : null;
        if ($routeData != null) {
            if ($controller == null) {
                $controller = $routeData->getController();
            }
            if ($method == null) {
                $method = $routeData->getMethod();
            }
            if ($path == null) {
                $path = $routeData->getControllerDir();
            }
        }
        if ($nav == null) {
            $navs = CApp_Navigation_Data::get();

            if ($navs == null) {
                return null;
            }

            foreach ($navs as $navItem) {
                $res = self::nav($navItem, $controller, $method, $path);
                if ($res !== false) {
                    return $res;
                }
            }
        } else {
            $navPath = carr::get($nav, 'path', '');
            $navMethod = carr::get($nav, 'method', '');
            $navController = carr::get($nav, 'controller', '');

            $navAlias = carr::wrap(carr::get($nav, 'alias', ''));
            $navAliases = carr::wrap(carr::get($nav, 'aliases', []));
            $navUri = carr::get($nav, 'uri', '');
            $routerUri = $path . $controller . '/' . $method;

            //$routerUri is rebuilt from route data, so it stops at the method and
            //drops any route parameter. A nav entry pointing at a parameterised
            //URL (eg. 'cloud/account/do/dashboard/4') could therefore never be
            //detected as active. Compare against the real request path as well.
            $requestUri = null;
            if (!CF::isCli()) {
                $requestUri = trim((string) c::request()->path(), '/');
            }

            //`uri` is the actual link target, so it is compared literally.
            //`aliases` are patterns and may use `*` wildcards, which lets one
            //entry cover a family of URLs — 'manager/project/*' instead of
            //listing every sub-page, or 'cloud/account/do/*/4' to stay active
            //across any action of one particular record.
            $uriMatches = function ($candidate, $allowWildcard = false) use ($routerUri, $requestUri) {
                $candidate = trim((string) $candidate, '/');
                if (strlen($candidate) == 0) {
                    return false;
                }
                $targets = [trim($routerUri, '/')];
                if ($requestUri !== null) {
                    $targets[] = $requestUri;
                }

                foreach ($targets as $target) {
                    if ($allowWildcard ? cstr::is($candidate, $target) : $candidate == $target) {
                        return true;
                    }
                }

                return false;
            };

            if ($navUri != null) {
                if ($uriMatches($navUri)) {
                    return $nav;
                }
            }

            if (is_array($navAliases)) {
                foreach ($navAliases as $navAliasItem) {
                    if ($uriMatches($navAliasItem, true)) {
                        return $nav;
                    }
                }
            }

            if ($navController != ''
                && $navMethod != ''
                && $path . $controller == $navPath . $navController
                && ($method == $navMethod || in_array($method, $navAlias))
            ) {
                return $nav;
            }

            if (isset($nav['action'])) {
                foreach ($nav['action'] as $act) {
                    $actPath = carr::get($act, 'path', $navPath);
                    $actMethod = carr::get($act, 'method', $navMethod);
                    $actController = carr::get($act, 'controller', $navController);
                    if ($actController != '' && $actMethod != '' && $path . $controller == $actPath . $actController && $method == $actMethod) {
                        return $nav;
                    }
                }
            }
            foreach (CNavigation_Data::resolveSubnav($nav) as $sn) {
                $res = self::nav($sn, $controller, $method, $path);
                if ($res !== false) {
                    return $res;
                }
            }
        }

        return false;
    }

    /**
     * @param null|mixed $nav
     * @param null|mixed $roleId
     * @param null|mixed $appId
     * @param null|mixed $domain
     *
     * @return bool
     */
    public static function haveAccess($nav = null, $roleId = null, $appId = null, $domain = null) {
        $canAccess = static::protectedhaveAccess($nav, $roleId, $appId, $domain);
        $accessCallback = CApp_Navigation::getAccessCallback($domain);
        if ($accessCallback != null && is_callable($accessCallback)) {
            $canAccess = call_user_func($accessCallback, $nav, $roleId, $appId, $domain, $canAccess);
        }

        return $canAccess;
    }

    /**
     * @param null|mixed $nav
     * @param null|mixed $roleId
     * @param null|mixed $appId
     * @param null|mixed $domain
     *
     * @return bool
     */
    protected static function protectedhaveAccess($nav = null, $roleId = null, $appId = null, $domain = null) {
        $app = c::app();
        $role = null;
        if ($roleId == null) {
            $role = $app->role();
            if ($role != null) {
                $roleId = $role->role_id;
            }
        }
        if ($appId == null) {
            $appId = CF::appId();
        }
        if ($nav == null) {
            $nav = self::nav();
        }

        if ($nav === false) {
            return false;
        }
        $app = c::app();
        if (!$app->isAuthEnabled()) {
            return true;
        }
        if ($roleId == 'PUBLIC') {
            $roleId = null;
        }
        if ($role == null) {
            $role = c::app()->getRole($roleId);
        }

        if ($role != null && $role->parent_id == null) {
            //is is superadmin
            return true;
        }

        if (!isset(self::$role_navs[$appId]) || !isset(self::$role_navs[$appId][$roleId])) {
            if (!isset(self::$role_navs[$appId])) {
                self::$role_navs[$appId] = [];
            }
            if (!isset(self::$role_navs[$appId][$roleId])) {
                $roleNavModel = CApp_Model_RoleNav::whereNull('role_id');
                if ($roleId != null) {
                    $roleNavModel = CApp_Model_RoleNav::where('role_id', '=', $roleId);
                }

                self::$role_navs[$appId][$roleId] = $roleNavModel->where('app_id', '=', $appId)->get()->pluck('nav')->toArray();
            }
        }

        return in_array($nav['name'], self::$role_navs[$appId][$roleId]);
    }

    /**
     * @param mixed      $action
     * @param null|mixed $nav
     * @param null|mixed $roleId
     * @param null|mixed $appId
     * @param null|mixed $domain
     *
     * @return bool
     */
    public static function havePermission($action, $nav = null, $roleId = null, $appId = null, $domain = null) {
        $app = c::app();
        $role = null;
        if ($roleId == null) {
            $role = $app->role();
            if ($role == null) {
                return false;
            }
            $roleId = $role->role_id;
        }
        if ($appId == null) {
            $appId = $app->appId();
        }

        if ($role == null) {
            $role = CApp_Auth_Role::getModel($roleId);
        }
        /** @var CApp_Model_Roles $role */
        if ($role == null) {
            return false;
        }
        if ($role != null && $role->parent_id == null) {
            return true;
        }

        return $role->rolePermission()->where('name', '=', $action)->where('app_id', '=', $appId)->count() > 0;
    }

    /**
     * @param mixed       $appId
     * @param mixed       $roleId
     * @param mixed       $appRoleId
     * @param null|string $domain
     *
     * @return array
     */
    public static function appUserRightsArray($appId, $roleId, $appRoleId = '', $domain = null) {
        $navs = CApp_Navigation_Data::get($domain);

        return self::asUserRightsArray($appId, $roleId, $navs, $appRoleId, $domain);
    }

    /**
     * @param mixed                        $appId
     * @param mixed                        $roleId
     * @param null|array|CNavigation_Nav   $navs
     * @param mixed                        $appRoleId
     * @param mixed                        $domain
     * @param int                          $level
     *
     * @return array
     */
    public static function asUserRightsArray($appId, $roleId, $navs = null, $appRoleId = '', $domain = '', $level = 0) {
        if ($navs == null) {
            $navs = CApp_Navigation::instance()->navs();
        }
        if ($navs instanceof CNavigation_Nav) {
            $navs = $navs->getData();
        }
        $result = [];

        foreach ($navs as $d) {
            if (!self::accessAvailable($d, $appId, $domain, $appRoleId)) {
                continue;
            }

            $res = $d;
            if (!is_array($res)) {
                throw new Exception('Error on nav structure on navs: ' . json_encode($navs));
            }

            $res['level'] = $level;
            $res['role_id'] = $roleId;
            $res['app_id'] = $appId;
            $res['domain'] = $domain;
            $subnav = [];
            $resolvedSubnav = CNavigation_Data::resolveSubnav($d);
            if (count($resolvedSubnav) > 0) {
                $subnav = self::asUserRightsArray($appId, $roleId, $resolvedSubnav, $appRoleId, $domain, $level + 1);
            }

            if (count($subnav) == 0 && ((!isset($d['controller']) || strlen($d['controller']) == 0))) {
                if (!isset($d['uri']) || strlen($d['uri']) == 0) {
                    continue;
                }
            }
            $result[] = $res;
            $result = array_merge($result, $subnav);
        }

        return $result;
    }

    /**
     * @param mixed $nav
     *
     * @return int
     */
    public static function childCount($nav) {
        return count(CNavigation_Data::resolveSubnav($nav));
    }

    /**
     * @param mixed $nav
     *
     * @return bool
     */
    public static function haveChild($nav) {
        return self::childCount($nav) > 0;
    }

    /**
     * @param mixed $nav
     *
     * @return bool
     */
    public static function isLeaf($nav) {
        return !CNavigation_Data::hasSubnav($nav);
    }

    /**
     * @param mixed $nav
     *
     * @return bool
     */
    public static function url($nav) {
        $controller = '';
        $method = '';
        $path = '';
        $link = '';

        if (isset($nav['path'])) {
            $path = $nav['path'];
        }
        if (isset($nav['controller'])) {
            $controller = $nav['controller'];
        }
        if (isset($nav['method'])) {
            $method = $nav['method'];
        }
        if (isset($nav['link'])) {
            $link = $nav['link'];
        }

        if (strlen($link) > 0) {
            $url = $link;
        } else {
            if (strlen($path) > 0) {
                $path .= '/';
            }
            if (strlen($controller) == 0) {
                return '';
            }
            if (strlen($method) == 0) {
                return '';
            }
            $url = curl::base() . $path . $controller . '/' . $method;
        }

        return $url;
    }

    /**
     * @param null|mixed $nav
     * @param mixed      $appId
     * @param mixed      $domain
     * @param mixed      $appRoleId
     *
     * @return bool
     */
    public static function accessAvailable($nav = null, $appId = '', $domain = '', $appRoleId = '') {
        if ($nav == null) {
            $nav = self::nav();
        }
        if ($nav === false) {
            return false;
        }
        $navname = carr::get($nav, 'name');
        $app = CApp::instance();
        if (!$app->isAuthEnabled()) {
            return true;
        }

        $appRole = null;
        if (strlen($appRoleId) == 0) {
            $appRole = $app->role();
            if ($appRole) {
                $appRoleId = c::get($appRole, 'role_id');
            }
        } else {
            $appRole = c::app()->getRole($appRoleId);
        }

        if (strlen($appRoleId) > 0) {
            if ($appRole != null && $appRole->parent_id == null) {
                return true;
            }
            if ($appRole != null && !CNavigation_Data::hasSubnav($nav)) {
                $parentRoleId = $appRole->parent_id;
                if ($parentRoleId != null) {
                    if (!self::haveAccess($nav, $appRoleId, $appId)) {
                        return false;
                    }
                }
            }
        }

        if (isset($nav['requirements'])) {
            $requirements = $nav['requirements'];
            foreach ($requirements as $k => $v) {
                $configValue = ccfg::get($k, $domain);
                if ($configValue != $v) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param string     $action
     * @param null|mixed $nav
     * @param mixed      $appId
     * @param mixed      $domain
     * @param mixed      $appRoleId
     *
     * @return bool
     */
    public static function permissionAvailable($action, $nav = null, $appId = '', $domain = '', $appRoleId = '') {
        if ($nav == null) {
            $nav = self::nav();
        }
        if ($nav === false) {
            return false;
        }

        if (!self::accessAvailable($nav, $appId, $domain, $appRoleId)) {
            return false;
        }

        $navname = $nav['name'];
        if (isset($nav['action'])) {
            $navactions = $nav['action'];
            foreach ($navactions as $act) {
                if ($act['name'] == $action && isset($act['requirements'])) {
                    $requirements = $act['requirements'];

                    foreach ($requirements as $k => $v) {
                        $config_value = ccfg::get($k, $domain);
                        if ($config_value != $v) {
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param null|array $navs
     * @param int        $level
     * @param int        $child
     *
     * @return bool|string
     */
    public static function render($navs = null, $level = 0, &$child = 0) {
        if ($navs == null) {
            $navs = CApp_Navigation::instance()->navs();
        }

        if ($navs == null) {
            return false;
        }
        $html = '';
        $child_count = 0;
        foreach ($navs as $d) {
            $child = 0;
            $pass = 0;
            $active_class = '';
            $controller = '';
            $method = '';
            $label = '';
            $icon = '';

            if (isset($d['controller'])) {
                $controller = $d['controller'];
            }
            if (isset($d['method'])) {
                $method = $d['method'];
            }
            if (isset($d['label'])) {
                $label = $d['label'];
            }
            if (isset($d['icon'])) {
                $icon = $d['icon'];
            }

            $child_html = '';

            $resolvedSubnav = CNavigation_Data::resolveSubnav($d);
            if (count($resolvedSubnav) > 0) {
                $child_html .= self::render($resolvedSubnav, $level + 1, $child);
            }

            $url = self::url($d);

            if (!isset($url) || $url == null) {
                $url = '';
            }

            if (strlen($child_html) > 0 || strlen($url) > 0) {
                if (!self::accessAvailable($d, CF::appId(), CF::domain())) {
                    continue;
                }
                if (isset($d['controller']) && $d['controller'] != '') {
                    if (CF::config('app.have_user_access')) {
                        if (!self::haveAccess($d)) {
                            continue;
                        }
                    }
                }

                $child_count++;

                $border = carr::get($d, 'border');

                $find_nav = self::nav($d);

                if ($find_nav !== false) {
                    $active_class = ' active';
                }

                $li_class = 'sidenav-item ';
                if ($child > 0) {
                    $li_class .= ' with-right-arrow';
                    if ($level == 0) {
                        $li_class .= ' dropdown';
                    } else {
                        $li_class .= ' dropdown-submenu ';
                    }
                }

                $addition_style = '';
                if ($border == 'top') {
                    $addition_style = ' style="border-top:1px solid #bbb"';
                }
                if ($border == 'bottom') {
                    $addition_style = ' style="border-bottom:1px solid #bbb"';
                }

                $html .= '<li class="' . $li_class . $active_class . '" ' . $addition_style . '>';

                $iconClass = carr::get($d, 'icon');
                if (strlen($iconClass) > 0 && strpos($iconClass, 'fa-') === false && strpos($iconClass, 'ion-') === false && strpos($iconClass, 'ti-') === false && strpos($iconClass, 'lnr') === false && strpos($iconClass, 'pe-') === false) {
                    $iconClass = 'icon-' . $iconClass;
                }
                $icon_html = '';
                if (strlen($iconClass) > 0) {
                    $icon_html = '<i class="' . $iconClass . '"></i>';
                }
                if ($url == '') {
                    $caret = '';
                    if ($level == 0) {
                        $caret = '<b class="caret">';
                    }

                    $elem = '<a class="' . $active_class . ' dropdown-toggle sidenav-link sidenav-toggle" href="javascript:;" data-toggle="dropdown">' . $icon_html . '<span>' . c::__($label) . '</span>' . $caret . '</b>';
                    if ($child > 0) {
                        //$elem .= '<span class="label">'.$child.'</span>';
                    }
                    $elem .= "</a>\r\n";
                } else {
                    $target = '';
                    $notif = '';
                    if (isset($d['target']) && strlen($d['target']) > 0) {
                        $target = ' target="' . $d['target'] . '"';
                    }
                    if (isset($d['notif_count'])) {
                        $callable = $d['notif_count'];

                        if (is_callable($callable)) {
                            $notif = call_user_func($callable);
                        }
                    }

                    $strNotif = '';
                    if ($notif != null && $notif > 0) {
                        $strNotif = ' <span class="label label-info nav-notif nav-notif-count">' . $notif . '</span>';
                    }
                    $elem = '<a class="' . $active_class . ' sidenav-link" href="' . $url . '"' . $target . '>' . $icon_html . '<span>' . c::__($label) . '</span>' . $strNotif . "</a>\r\n";
                }
                $html .= $elem;
                $html .= $child_html;
                $html .= '</li>';
            }
        }
        if (strlen($html) > 0) {
            if ($level == 0) {
                $html = "  <ul class=\"mainnav \">\r\n" . $html . "  </ul>\r\n";
            } else {
                $html = "  <ul class=\"dropdown-menu\">\r\n" . $html . "  </ul>\r\n";
            }
        }
        if ($child_count == 0) {
            $html = '';
        }
        $child = $child_count;

        return $html;
    }
}

<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 1, 2018, 12:11:34 PM
 */
use CApp_Navigation_Helper as Helper;

abstract class CApp_Navigation_Engine implements CApp_Navigation_EngineInterface {
    protected $roleNavs = [];

    protected $roleId = null;

    protected $appId = null;

    protected $navs = null;

    public function __construct($options = []) {
        $app = CApp::instance();

        $roleId = carr::get($options, 'role_id');
        $appId = carr::get($options, 'app_id');
        $navs = carr::get($options, 'navs');

        if ($roleId == null) {
            $role = $app->role();
            if ($role != null) {
                $roleId = $role->role_id;
            }
        }
        if ($appId == null) {
            $appId = CF::appId();
        }
        $this->roleId = $roleId;
        $this->appId = $appId;

        /* get nav */
        if ($navs == null) {
            $navs = CApp_Navigation_Data::get();
        }

        $this->navs = $navs;

        $this->roleNavs = [];

        if (!CApp::isAdministrator()) {
            if (CApp::instance()->isAuthEnabled()) {
                $db = CDatabase::instance();
                $q = 'select nav from role_nav where role_id=' . $db->escape($roleId) . ' and app_id=' . $db->escape($appId);
                if ($roleId == null) {
                    $q = 'select nav from role_nav where role_id is null and app_id=' . $db->escape($appId);
                }
                $this->roleNavs = cdbutils::get_array($q);
            }
        }
    }
}

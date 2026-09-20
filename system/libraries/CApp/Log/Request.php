<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @since Mar 10, 2019, 6:13:15 AM
 */
use CApp_Base as Base;

use CApp_Navigation_Helper as NavHelper;

class CApp_Log_Request {
    /**
     * @param array $data
     *
     * @return bool|void
     */
    public static function populate($data = []) {
        $db = c::db();
        $appId = Base::appId();
        $orgId = Base::orgId();
        $userId = Base::userId();
        $browser = new CBrowser();

        //no need to log ajax request
        if (c::request()->ajax()) {
            return false;
        }
        //no need to log on administrator page
        $router_uri = CFRouter::routedUri(CFRouter::$current_uri);
        $rsegment = explode('/', $router_uri);
        if (count($rsegment) > 0) {
            if ($rsegment[0] == 'admin') {
                return false;
            }
        }

        $nav = NavHelper::nav();

        $nav_name = '';
        $nav_label = '';
        $action_label = '';
        $action_name = '';
        $controller = c::router()->current()->getController();
        if ($controller == 'cresenity') {
            return false;
        }
        $method = c::router()->current()->getRouteData()->getMethod();
        if ($nav != null) {
            $nav_name = $nav['name'];
            $nav_label = $nav['label'];

            if (isset($nav['action'])) {
                foreach ($nav['action'] as $act) {
                    if (isset($act['controller'], $act['method']) && $act['controller'] == $controller && $act['method'] == $method) {
                        $action_name = $act['name'];
                        $action_label = $act['label'];
                    }
                }
            }
        }
        $ip_address = Base::remoteAddress();

        $platform_version = '';
        $description = CF::domain();

        $data = [
            'request_date' => date('Y-m-d H:i:s'),
            'app_id' => $appId,
            'org_id' => $orgId,
            'session_id' => CSession::instance()->id(),
            'user_agent' => $browser->getUserAgent(),
            'browser' => $browser->getBrowser(),
            'browser_version' => $browser->getVersion(),
            'platform' => $browser->getPlatform(),
            'platform_version' => $platform_version,
            'remote_addr' => $ip_address,
            'user_id' => $userId,
            'uri' => c::router()->current()->getRouteData()->getCompleteUri(),
            'routed_uri' => c::router()->current()->getRouteData()->getRoutedUri(),
            'controller' => c::router()->current()->getController(),
            'method' => c::router()->current()->getRouteData()->getMethod(),
            'query_string' => c::router()->current()->getRouteData()->getQueryString(),
            'nav' => $nav_name,
            'nav_label' => $nav_label,
            'action' => $action_name,
            'action_label' => $action_label,
            'description' => $description,
        ];
        $db->insert('log_request', $data);
    }
}

<?php

defined('SYSPATH') or die('No direct access allowed.');

use CApp_Base as Base;
use CApp_Navigation_Helper as NavHelper;

class CApp_Log_Activity {
    /**
     * Mencatat satu aktivitas.
     *
     * `$extra` disediakan supaya aplikasi dapat menambahkan kolomnya sendiri —
     * devcloud memakainya untuk `team_id`, yang menentukan siapa boleh membaca
     * catatan itu kembali. Modelnya dikembalikan agar pemanggil dapat
     * menambahkan sesuatu tanpa membangun ulang seluruh isinya.
     *
     * @param string $description
     * @param array  $data
     * @param array  $extra       kolom tambahan milik aplikasi
     *
     * @return CModel
     */
    public static function populate($description, $data, array $extra = []) {
        $modelName = CF::config('app.model.log_activity', CApp_Model_LogActivity::class);
        $model = new $modelName();
        /** @var CModel $model */
        $nav = NavHelper::nav();
        $navName = '';
        $navLabel = '';
        $actionName = '';
        $actionLabel = '';
        $controller = '';
        $method = '';
        $queryString = '';
        $routedUri = '';
        $completeUri = '';
        $request = c::request();
        $route = $request->route();
        if ($route) {
            /** @var CRouting_Route $route */
            $routeData = $route->getRouteData();
            if ($routeData) {
                $controller = $routeData->getControllerClass();
                $method = $routeData->getMethod();
                $queryString = $routeData->getQueryString();
                $routedUri = $routeData->getRoutedUri();
                $completeUri = $routeData->getCompleteUri();
            }
        }

        if ($nav) {
            $navName = $nav['name'];
            $navLabel = $nav['label'];
            if (isset($nav['action'])) {
                foreach ($nav['action'] as $act) {
                    if (isset($act['controller'], $act['method']) && $act['controller'] == $controller && $act['method'] == $method) {
                        $actionName = $act['name'];
                        $actionLabel = $act['label'];
                    }
                }
            }
        }
        $appId = Base::appId();
        $orgId = Base::orgId();
        $userId = Base::userId();
        $username = Base::username();
        $model->fill([
            'org_id' => $orgId,
            'app_id' => $appId,
            'session_id' => c::session()->getId(),
            'remote_addr' => CHTTP::request()->ip(),
            'user_agent' => static::truncate(CHTTP::request()->userAgent(), 255),
            'browser' => CApp::browserName(),
            'browser_version' => CApp::browserVersion(),
            'platform' => CApp::platformName(),
            'platform_version' => CApp::platformVersion(),
            'user_id' => $userId,
            'uri' => $completeUri,
            'routed_uri' => $routedUri,
            'controller' => $controller,
            'method' => $method,
            'query_string' => $queryString,
            'nav' => $navName,
            'nav_label' => $navLabel,
            'action' => $actionName,
            'action_label' => $actionLabel,
            'createdby' => $username,
        ] + $extra);
        $data = static::normalizeDataForJsonEncoding($data);
        $model->data = json_encode($data);

        $model->activity_date = c::now();
        $model->description = $description;
        $model->save();

        return $model;
    }

    /**
     * Potong string sepanjang kolom `user_agent varchar(255)` - beberapa bot
     * mengirim User-Agent yang lebih panjang (mis. header ganda/rusak), dan
     * tanpa ini insert-nya gagal dengan "Data too long for column".
     *
     * @param null|string $value
     * @param int         $length
     *
     * @return null|string
     */
    protected static function truncate($value, $length) {
        if ($value === null) {
            return null;
        }

        return cstr::limit((string) $value, $length, '');
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected static function normalizeDataForJsonEncoding($data) {
        foreach ($data as $dataIndex => $record) {
            //bentuk before/after hanya ada pada pencatatan perubahan baris;
            //peristiwa yang dicatat langsung berisi apa saja, dan dulu tiap
            //baris seperti itu memicu dua peringatan foreach atas null
            $beforeData = carr::get($record, 'before', []);
            $afterData = carr::get($record, 'after', []);
            if (!is_array($beforeData)) {
                $beforeData = [];
            }
            if (!is_array($afterData)) {
                $afterData = [];
            }
            foreach ($beforeData as $beforeIndex => $value) {
                if ($value instanceof CCarbon || $value instanceof CarbonLegacy\Carbon) {
                    $data[$dataIndex]['before'][$beforeIndex] = (string) $value;
                }
            }
            foreach ($afterData as $afterIndex => $value) {
                if ($value instanceof CCarbon || $value instanceof CarbonLegacy\Carbon) {
                    $data[$dataIndex]['after'][$afterIndex] = (string) $value;
                }
            }
        }

        return $data;
    }
}

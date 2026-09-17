<?php

defined('SYSPATH') or die('No direct access allowed.');

class CAjax {
    const TYPE_SELECT_SEARCH = 'SelectSearch';

    const TYPE_CALLBACK = 'Callback';

    const TYPE_DATA_TABLE = 'DataTable';

    const TYPE_FILE_MANAGER = 'FileManager';

    const TYPE_IMG_UPLOAD = 'ImgUpload';

    const TYPE_FILE_UPLOAD = 'FileUpload';

    const TYPE_RELOAD = 'Reload';

    const TYPE_VALIDATION = 'Validation';

    const TYPE_DEPENDS_ON = 'DependsOn';

    const TYPE_TREE_VIEW = 'TreeView';

    const TYPE_CALENDAR = 'Calendar';

    /**
     * @param null|array|string $options
     *
     * @return \CAjax_Method
     */
    public static function createMethod($options = null) {
        if (!is_array($options)) {
            if ($options != null) {
                return CAjax_Method::createFromJson($options);
            }
        }

        return new CAjax_Method($options);
    }

    /**
     * Folder temp method ajax untuk app yang sedang berjalan.
     *
     * @return string
     */
    public static function temporaryFolder() {
        $appCode = (string) CF::appCode();

        return 'ajax' . DIRECTORY_SEPARATOR . (strlen($appCode) > 0 ? $appCode : 'common');
    }

    /**
     * Path file temp sebuah method ajax. Berkas baru selalu per app (ajax/<appCode>/...); id yang
     * dibuat sebelum pemisahan masih ditemukan di lokasi lama bersama (ajax/...) selama berkasnya ada.
     *
     * @param string $ajaxMethodId
     *
     * @return string
     */
    public static function temporaryFile($ajaxMethodId) {
        $filename = $ajaxMethodId . '.tmp';
        $perApp = CTemporary::getPath(static::temporaryFolder(), $filename);
        $disk = CTemporary::disk();
        if ($disk->exists($perApp)) {
            return $perApp;
        }
        $legacy = CTemporary::getPath('ajax', $filename);
        if ($disk->exists($legacy)) {
            return $legacy;
        }

        return $perApp;
    }

    /**
     * @param string $file
     *
     * @throws Exception
     *
     * @return array
     */
    public static function getData($file) {
        $file = static::temporaryFile($file);

        $disk = CTemporary::disk();
        if (!$disk->exists($file)) {
            throw new Exception(c::__('failed to get temporary file :filename', [':filename' => $file]));
        }
        $json = $disk->get($file);

        $data = json_decode($json, true);

        return $data;
    }

    /**
     * @param string $file
     * @param array  $data
     *
     * @return array
     */
    public static function setData($file, $data) {
        $file = static::temporaryFile($file);

        $disk = CTemporary::disk();

        $disk->put($file, json_encode($data));

        return $data;
    }

    /**
     * @return int
     */
    public static function getDefaultExpiration() {
        return c::now()->addMinutes(CF::config('app.ajax.expiration', 60))->getTimestamp();
    }

    /**
     * @return CAjax_Info|CBase_ForwarderStaticClass
     */
    public static function info() {
        return new CBase_ForwarderStaticClass(CAjax_Info::class);
    }
}

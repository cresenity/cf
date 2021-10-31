<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 6, 2018, 11:32:00 AM
 */
class CAjax {
    /**
     * @param type $options
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

    public static function getData($file) {
        $filename = $file . '.tmp';

        $file = CTemporary::getPath('ajax', $filename);

        $disk = CTemporary::disk();
        if (!$disk->exists($file)) {
            throw new Exception(c::__('failed to get temporary file :filename', [':filename' => $file]));
        }
        $json = $disk->get($file);

        $data = json_decode($json, true);

        return $data;
    }

    public static function setData($file, $data) {
        $filename = $file . '.tmp';

        $file = CTemporary::getPath('ajax', $filename);

        $disk = CTemporary::disk();

        $json = $disk->put($file, json_encode($data));

        return $data;
    }
}

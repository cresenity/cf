<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 14, 2018, 4:40:47 AM
 */
class CApp_Api_Method_Server_GetDomainList extends CApp_Api_Method_Server {
    public function execute() {
        $errCode = 0;
        $errMessage = '';
        $domain = $this->domain;

        $allFiles = cfs::list_files(CFData::path() . 'domain');

        /*
        $fileHelper = CHelper::file();
        $allFiles = $fileHelper->files(CFData::path() . 'domain');
        $files = array();
        foreach ($allFiles as $fileObject) {
            $filename = $fileObject->getPathname();
            $domain = basename($filename);
            if (substr($domain, -4) == '.php') {
                $domain = substr($domain, 0, strlen($domain) - 4);
            }

            $file = array(
                'domain' => $domain,
                'created' => date('Y-m-d H:i:s', filemtime($filename)),
            );
            $files[] = $file;
        }
        */
        foreach ($allFiles as $filename) {
            $domain = basename($filename);
            if (substr($domain, -4) == '.php') {
                $domain = substr($domain, 0, strlen($domain) - 4);
            }

            $file = [
                'domain' => $domain,
                'created' => date('Y-m-d H:i:s', filemtime($filename)),
            ];
            $files[] = $file;
        }
        $data = [];
        $data['list'] = $files;
        $data['count'] = count($files);

        $this->errCode = $errCode;
        $this->errMessage = $errMessage;
        $this->data = $data;

        return $this;
    }
}

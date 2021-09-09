<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Aug 11, 2019, 2:36:12 AM
 */
use CManager_File_Connector_FileManager_FM as FM;

class CManager_File_Connector_FileManager_Controller_FolderController extends CManager_File_Connector_FileManager_AbstractController {
    /**
     * Get list of folders as json to populate treeview.
     *
     * @return mixed
     */
    public function execute() {
        $fm = $this->fm();

        $app = CApp::instance();
        $folder_types = array_filter(['root'], function ($type) use ($fm) {
            return $fm->allowFolderType($type);
        });
        $rootFolders = array_map(function ($type) use ($folder_types, $fm) {
            $path = $fm->path()->dir($fm->getRootFolder($type));
            return (object) [
                'name' => $type,
                'url' => $path->path('working_dir'),
                'children' => $path->folders(),
                'has_next' => !($type == end($folder_types)),
            ];
        }, $folder_types);

        $app->addTemplate()->setTemplate('CElement/Component/FileManager/Tree')->setVar('fm', $fm)->setVar('rootFolders', $rootFolders);
        echo $app->render();
    }
}

<?php

defined('SYSPATH') or die('No direct access allowed.');

class CManager_File_Connector_FileManager_Controller_RenameController extends CManager_File_Connector_FileManager_AbstractController {
    /**
     * Renames (moves) a file or folder within the current working directory.
     *
     * @return \CHTTP_JsonResponse
     */
    public function execute() {
        $fm = $this->fm();
        $old_name = $fm->input('file');
        $new_name = $fm->input('new_name');
        $old_file = $fm->path()->pretty($old_name);
        $is_directory = $old_file->isDirectory();

        try {
            if (empty($new_name)) {
                $fm->error($is_directory ? 'folder-name' : 'file-name');
            } elseif ($fm->config('alphanumeric_directory') && preg_match('/[^\w-]/i', $new_name)) {
                $fm->error('folder-alnum');
            } elseif ($fm->path()->setName($new_name)->exists()) {
                $fm->error('rename');
            } else {
                if (!$is_directory) {
                    $extension = $old_file->extension();
                    if ($extension) {
                        $new_name = str_replace('.' . $extension, '', $new_name) . '.' . $extension;
                    }
                }
                if (!$is_directory && CFile::isDangerousFilename($new_name)) {
                    throw new \Exception('File type not allowed');
                }
                $new_file = $fm->path()->setName($new_name)->path('absolute');
                if ($is_directory) {
                    $fm->dispatch(new CManager_File_Connector_FileManager_Event_FolderIsRenaming($old_file->path(), $new_file));
                } else {
                    $fm->dispatch(new CManager_File_Connector_FileManager_Event_FileIsRenaming($old_file->path(), $new_file));
                }
                if ($old_file->hasThumb()) {
                    $fm->path()->setName($old_name)->thumb()
                        ->move($fm->path()->setName($new_name)->thumb());
                }
                $fm->path()->setName($old_name)
                    ->move($fm->path()->setName($new_name));
                if ($is_directory) {
                    $fm->dispatch(new CManager_File_Connector_FileManager_Event_FolderWasRenamed($old_file->path(), $new_file));
                } else {
                    $fm->dispatch(new CManager_File_Connector_FileManager_Event_FileWasRenamed($old_file->path(), $new_file));
                }
            }
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }

        return $this->successResponse();
    }
}

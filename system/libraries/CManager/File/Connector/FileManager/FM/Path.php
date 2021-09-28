<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 *
 * @since Aug 11, 2019, 3:20:19 AM
 *
 * @license Ittron Global Teknologi <ittron.co.id>
 */
use Intervention\Image\Facades\Image;
// import the Intervention Image Manager Class
use Intervention\Image\ImageManager;

/**
 * @property-read CManager_File_Connector_FileManager_FM_StorageRepository $storage
 */
class CManager_File_Connector_FileManager_FM_Path {
    private $working_dir;

    private $item_name;

    private $is_thumb = false;

    private $helper;

    public function __construct(CManager_File_Connector_FileManager_FM $fm = null) {
        $this->helper = $fm;
    }

    public function __get($var_name) {
        if ($var_name == 'storage') {
            return $this->helper->getStorage($this->path('url'));
        }
    }

    public function __call($function_name, $arguments) {
        return $this->storage->$function_name(...$arguments);
    }

    public function dir($working_dir) {
        $this->working_dir = $working_dir;
        return $this;
    }

    public function thumb($is_thumb = true) {
        $this->is_thumb = $is_thumb;
        return $this;
    }

    public function setName($item_name) {
        $this->item_name = $item_name;
        return $this;
    }

    public function getName() {
        return $this->item_name;
    }

    public function path($type = 'storage') {
        if ($type == 'working_dir') {
            // working directory: /{user_slug}
            return $this->translateToFmPath($this->normalizeWorkingDir());
        } elseif ($type == 'url') {
            // storage: files/{user_slug}
            return $this->helper->getCategoryName() . $this->path('working_dir');
        } elseif ($type == 'storage') {
            // storage: files/{user_slug}
            // storage on windows: files\{user_slug}

            return $this->translateToOsPath($this->path('url'));
        } else {
            // absolute: /var/www/html/project/storage/app/files/{user_slug}
            // absolute on windows: C:\project\storage\app\files\{user_slug}
            return rtrim($this->storage->rootPath(), '/') . '/' . $this->path('storage');
        }
    }

    public function translateToFmPath($path) {
        return str_replace($this->helper->ds(), DS, $path);
    }

    public function translateToOsPath($path) {
        return str_replace(DS, $this->helper->ds(), $path);
    }

    public function url() {
        return $this->storage->url($this->path('url'));
    }

    public function folders() {
        $all_folders = array_map(function ($directory_path) {
            return $this->pretty($directory_path);
        }, $this->storage->directories());

        $folders = array_filter($all_folders, function ($directory) {
            return $directory->name !== $this->helper->getThumbFolderName();
        });
        return $this->sortByColumn($folders);
    }

    public function files() {
        $files = array_map(function ($file_path) {
            return $this->pretty($file_path);
        }, $this->storage->files());
        return $this->sortByColumn($files);
    }

    public function pretty($item_path) {
        $cloned = clone($this);

        $cloned->setName($this->helper->getNameFromPath($item_path));

        return new CManager_File_Connector_FileManager_FM_Item($cloned, $this->helper);
    }

    public function delete() {
        if ($this->isDirectory()) {
            return $this->storage->deleteDirectory();
        } else {
            return $this->storage->delete();
        }
    }

    /**
     * Create folder if not exist.
     *
     * @return bool
     */
    public function createFolder() {
        if ($this->storage->exists($this)) {
            return false;
        }
        $this->storage->makeDirectory(0777, true, true);
        $this->helper->dispatch(new CManager_File_Connector_FileManager_Event_FolderIsCreated($this->path()));
    }

    public function isDirectory() {
        $working_dir = $this->path('working_dir');

        $parent_dir = substr($working_dir, 0, strrpos($working_dir, '/'));
        if (strlen($parent_dir) == 0) {
            $parent_dir = '/';
        }

        $parent_directories = array_map(function ($directory_path) {
            return $this->createNewPathObject()->translateToFmPath($directory_path);
        }, $this->createNewPathObject()->dir($parent_dir)->directories());

        return in_array($this->path('url'), $parent_directories);
    }

    public function createNewPathObject() {
        return new static($this->helper);
    }

    /**
     * Check a folder and its subfolders is empty or not.
     *
     * @return bool
     */
    public function directoryIsEmpty() {
        return count($this->storage->allFiles()) == 0;
    }

    public function normalizeWorkingDir() {
        $path = $this->working_dir ?: $this->helper->input('working_dir') ?: $this->helper->getRootFolder();
        if ($this->is_thumb) {
            // Prevent if working dir is "/" normalizeWorkingDir will add double "//" that breaks S3 functionality
            $path = rtrim($path, DS) . DS . $this->helper->getThumbFolderName();
        }
        if ($this->getName()) {
            // Prevent if working dir is "/" normalizeWorkingDir will add double "//" that breaks S3 functionality
            $path = rtrim($path, DS) . DS . $this->getName();
        }
        return $path;
    }

    /**
     * Sort files and directories.
     *
     * @param mixed $arr_items array of files or folders or both
     *
     * @return array of object
     */
    public function sortByColumn($arr_items) {
        $sortBy = $this->helper->input('sortType');
        if (in_array($sortBy, ['name', 'time'])) {
            $keyToSort = $sortBy;
        } else {
            $keyToSort = 'name';
        }
        uasort($arr_items, function ($a, $b) use ($keyToSort) {
            return strcmp($a->{$keyToSort}, $b->{$keyToSort});
        });
        return $arr_items;
    }

    public function error($error_type, $variables = []) {
        return $this->helper->error($error_type, $variables);
    }

    /**
     * Upload File
     *
     * @param mixed $file
     *
     * @return string
     */
    public function upload($file) {
        $this->uploadValidator($file);
        $newFileName = $this->getNewName($file);
        $newFilePath = $this->setName($newFileName)->path('absolute');

        $this->helper->dispatch(new CManager_File_Connector_FileManager_Event_ImageIsUploading($newFilePath));
        try {
            $newFileName = $this->saveFile($file, $newFileName);
        } catch (\Exception $e) {
            // \Log::info($e);
            // return $this->error('invalid');
            return $this->error($e->getMessage());
        }
        // TODO should be "FileWasUploaded"
        $this->helper->dispatch(new CManager_File_Connector_FileManager_Event_ImageWasUploaded($newFilePath));
        return $newFileName;
    }

    private function uploadValidator($file) {
        if (empty($file)) {
            return $this->error('file-empty');
        } elseif (!$file instanceof CHTTP_UploadedFile) {
            return $this->error('instance');
        } elseif ($file->getError() == UPLOAD_ERR_INI_SIZE) {
            return $this->error('file-size', ['max' => ini_get('upload_max_filesize')]);
        } elseif ($file->getError() != UPLOAD_ERR_OK) {
            throw new \Exception('File failed to upload. Error code: ' . $file->getError());
        }
        $newFileName = $this->getNewName($file);
        if ($this->setName($newFileName)->exists() && !$this->helper->config('over_write_on_duplicate')) {
            return $this->error('file-exist');
        }
        if ($this->helper->config('should_validate_mime', false)) {
            $mimetype = $file->getMimeType();
            if (false === in_array($mimetype, $this->helper->availableMimeTypes())) {
                return $this->error('mime') . $mimetype;
            }
        }
        if ($this->helper->config('should_validate_size', false)) {
            // size to kb unit is needed
            $file_size = $file->getSize() / 1000;
            if ($file_size > $this->helper->maxUploadSize()) {
                return $this->error('size') . $file_size;
            }
        }
        return 'pass';
    }

    private function getNewName($file) {
        $newFileName = $this->helper
            ->translateFromUtf8(trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)));
        if ($this->helper->config('rename_file') === true) {
            $newFileName = uniqid();
        } elseif ($this->helper->config('alphanumeric_filename') === true) {
            $newFileName = preg_replace('/[^A-Za-z0-9\-\']/', '_', $newFileName);
        }
        $extension = $file->getClientOriginalExtension();
        if ($extension) {
            $newFileName .= '.' . $extension;
        }
        return $newFileName;
    }

    private function saveFile($file, $newFileName) {
        $this->setName($newFileName)->storage->save($file);
        $this->makeThumbnail($newFileName);
        return $newFileName;
    }

    public function makeThumbnail($fileName) {
        $original_image = $this->pretty($fileName);
        if (!$original_image->shouldCreateThumb()) {
            return;
        }
        // create folder for thumbnails
        $this->setName(null)->thumb(true)->createFolder();
        // generate cropped image content
        $this->setName($fileName)->thumb(true);

        $imageManager = new ImageManager();
        $image = $imageManager->make($original_image->get())
            ->fit($this->helper->config('thumb_img_width', 200), $this->helper->config('thumb_img_height', 200));
        $this->storage->put($image->stream()->detach());
    }
}

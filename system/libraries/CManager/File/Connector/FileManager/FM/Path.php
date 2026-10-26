<?php

defined('SYSPATH') or die('No direct access allowed.');

use Intervention\Image\ImageManager;
// import the Intervention Image Manager Class
use Intervention\Image\Facades\Image;

/**
 * @property-read CManager_File_Connector_FileManager_FM_StorageRepository $storage
 */
class CManager_File_Connector_FileManager_FM_Path {
    /**
     * Set via dir(), read by normalizeWorkingDir(); falls back to the fm helper's
     * own input('working_dir')/getRootFolder() when null.
     *
     * @var null|string
     */
    private $working_dir;

    /**
     * Set via setName(); the current file/folder name this path instance
     * represents, appended onto $working_dir by normalizeWorkingDir().
     *
     * @var null|string
     */
    private $item_name;

    /**
     * Set via thumb(); when true, path()/normalizeWorkingDir() resolve to the
     * item's thumbnail location instead of its real one.
     *
     * @var bool
     */
    private $is_thumb = false;

    /**
     * @var CManager_File_Connector_FileManager_FM
     */
    private $fm;

    /**
     * @param null|CManager_File_Connector_FileManager_FM $fm
     *
     * @return void
     */
    public function __construct(?CManager_File_Connector_FileManager_FM $fm = null) {
        $this->fm = $fm;
    }

    /**
     * @param string $var_name
     *
     * @return null|CManager_File_Connector_FileManager_FM_StorageRepository
     */
    public function __get($var_name) {
        if ($var_name == 'storage') {
            return $this->fm->getStorage($this->path('url'));
        }
    }

    /**
     * Proxies any undefined method call through to the underlying storage
     * repository for the current path.
     *
     * @param string $function_name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($function_name, $arguments) {
        return $this->storage->$function_name(...$arguments);
    }

    /**
     * @param CManager_File_Connector_FileManager_FM_Path $newPath
     *
     * @return void
     */
    public function move($newPath) {
        $this->storage->move($newPath);
    }

    /**
     * @return bool
     */
    public function exists() {
        return $this->storage->exists();
    }

    /**
     * @param string $working_dir
     *
     * @return $this
     */
    public function dir($working_dir) {
        $this->working_dir = $working_dir;

        return $this;
    }

    /**
     * @param bool $is_thumb
     *
     * @return $this
     */
    public function thumb($is_thumb = true) {
        $this->is_thumb = $is_thumb;

        return $this;
    }

    /**
     * @param null|string $item_name
     *
     * @return $this
     */
    public function setName($item_name) {
        if ($item_name !== null && $item_name !== '') {
            $this->assertNameIsSafe($item_name);
        }
        $this->item_name = $item_name;

        return $this;
    }

    /**
     * A file/folder name must be a single path segment -- rejects '/', '\\'
     * and '..'/'.' so a tampered `file`/`name`/`items[]`/etc. request input
     * can't smuggle in a nested or parent-traversing path (e.g.
     * "../../../etc/passwd") and escape the configured root_path.
     *
     * @param mixed $name
     *
     * @throws \Exception
     */
    private function assertNameIsSafe($name) {
        if (!is_string($name)
            || strpbrk($name, '/\\') !== false
            || $name === '.'
            || $name === '..'
        ) {
            $this->error('invalid-path');
        }
    }

    /**
     * Rejects any '..' path segment -- prevents a tampered `working_dir`/
     * `path`/`goToFolder` request input from traversing above the file
     * manager's configured root_path. Directory paths are otherwise allowed
     * to contain multiple segments (unlike item names, see assertNameIsSafe()).
     *
     * @param mixed $path
     *
     * @throws \Exception
     */
    private function assertWorkingDirIsSafe($path) {
        if (!is_string($path)) {
            $this->error('invalid-path');
        }
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '..') {
                $this->error('invalid-path');
            }
        }
    }

    /**
     * @return null|string
     */
    public function getName() {
        return $this->item_name;
    }

    /**
     * @param string $type one of 'working_dir', 'url', 'storage' (default), or
     *                     anything else for the absolute filesystem path
     *
     * @return string
     */
    public function path($type = 'storage') {
        if ($type == 'working_dir') {
            // working directory: /{user_slug}

            return $this->translateToFmPath($this->normalizeWorkingDir());
        } elseif ($type == 'url') {
            // storage: files/{user_slug}
            return $this->fm->getCategoryName() . $this->path('working_dir');
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

    /**
     * @param string $path
     *
     * @return string
     */
    public function translateToFmPath($path) {
        return str_replace($this->fm->ds(), DS, $path);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public function translateToOsPath($path) {
        return str_replace(DS, $this->fm->ds(), $path);
    }

    /**
     * @return string
     */
    public function url() {
        return $this->storage->url($this->path('url'));
    }

    /**
     * @return CManager_File_Connector_FileManager_FM_Item[]
     */
    public function folders() {
        $all_folders = array_map(function ($directory_path) {
            return $this->pretty($directory_path);
        }, $this->storage->directories());

        $folders = array_filter($all_folders, function ($directory) {
            return $directory->name !== $this->fm->getThumbFolderName();
        });

        return $this->sortByColumn($folders);
    }

    /**
     * @return CManager_File_Connector_FileManager_FM_Item[]
     */
    public function files() {
        $files = array_map(function ($file_path) {
            return $this->pretty($file_path);
        }, $this->storage->files());

        return $this->sortByColumn($files);
    }

    /**
     * Wraps a raw file/folder path (as returned by the storage adapter) into an
     * Item, cloning this Path so the original working_dir/thumb state is
     * unaffected.
     *
     * @param string $item_path
     *
     * @return CManager_File_Connector_FileManager_FM_Item
     */
    public function pretty($item_path) {
        $cloned = clone $this;

        $cloned->setName($this->fm->getNameFromPath($item_path));

        return new CManager_File_Connector_FileManager_FM_Item($cloned, $this->fm);
    }

    /**
     * @return mixed
     */
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
        if ($this->storage->exists()) {
            return false;
        }
        $this->storage->makeDirectory(0777, true, true);

        $this->fm->dispatch(new CManager_File_Connector_FileManager_Event_FolderIsCreated($this->path()));
    }

    /**
     * @return bool
     */
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

    /**
     * @return CManager_File_Connector_FileManager_FM_Path
     */
    public function createNewPathObject() {
        return new static($this->fm);
    }

    /**
     * Check a folder and its subfolders is empty or not.
     *
     * @return bool
     */
    public function directoryIsEmpty() {
        return count($this->storage->allFiles()) == 0;
    }

    /**
     * @return string
     */
    public function normalizeWorkingDir() {
        $path = $this->working_dir ?: $this->fm->input('working_dir') ?: $this->fm->getRootFolder();
        $this->assertWorkingDirIsSafe($path);
        if ($this->is_thumb) {
            // Prevent if working dir is "/" normalizeWorkingDir will add double "//" that breaks S3 functionality
            $path = rtrim($path, DS) . DS . $this->fm->getThumbFolderName();
        }
        if ($this->getName()) {
            // Prevent if working dir is "/" normalizeWorkingDir will add double "//" that breaks S3 functionality
            $path = rtrim($path, DS) . DS . $this->getName();
        }

        return $path;
    }

    /**
     * Sort files and directories. `sortType` input is '{field}_{asc|desc}'
     * (e.g. 'time_desc'), matching the list view's sortable column headers and
     * the grid view's sort dropdown -- see FileManager.js.
     *
     * @param CManager_File_Connector_FileManager_FM_Item[] $arr_items array of files or folders or both
     *
     * @return CManager_File_Connector_FileManager_FM_Item[]
     */
    public function sortByColumn($arr_items) {
        $sortBy = $this->fm->input('sortType') ?: 'name_asc';
        $direction = cstr::endsWith($sortBy, '_desc') ? 'desc' : 'asc';
        $field = preg_replace('/_(asc|desc)$/', '', $sortBy);
        if (!in_array($field, ['name', 'time', 'size'])) {
            $field = 'name';
        }
        // size() is a human-readable string ("12.34 MB") that sorts wrong as
        // text -- sizeBytes() is the raw byte count used for a numeric sort.
        $keyToSort = $field === 'size' ? 'size_bytes' : $field;

        uasort($arr_items, function ($a, $b) use ($keyToSort, $direction) {
            $aVal = $a->{$keyToSort};
            $bVal = $b->{$keyToSort};
            $cmp = is_numeric($aVal) && is_numeric($bVal) ? ($aVal <=> $bVal) : strcmp((string) $aVal, (string) $bVal);

            return $direction === 'desc' ? -$cmp : $cmp;
        });

        return $arr_items;
    }

    /**
     * @param string $error_type
     * @param array  $variables
     *
     * @throws \Exception
     */
    public function error($error_type, $variables = []) {
        return $this->fm->error($error_type, $variables);
    }

    /**
     * Upload File.
     *
     * @param mixed $file
     *
     * @return string
     */
    public function upload($file) {
        $this->uploadValidator($file);
        $newFileName = $this->getNewName($file);
        $newFilePath = $this->setName($newFileName)->path('absolute');

        $this->fm->dispatch(new CManager_File_Connector_FileManager_Event_FileIsUploading($newFilePath));

        try {
            $newFileName = $this->saveFile($file, $newFileName);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
        $this->fm->dispatch(new CManager_File_Connector_FileManager_Event_FileWasUploaded($newFilePath));

        return $newFileName;
    }

    /**
     * @param mixed $file
     *
     * @return string
     */
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
        if (CFile::isDangerousFilename($file->getClientOriginalName()) || CFile::isDangerousFilename($newFileName)) {
            throw new \Exception('File type not allowed');
        }
        $onDuplicate = $this->fm->input('on_duplicate');
        if ($this->setName($newFileName)->exists()
            && !$this->fm->config('over_write_on_duplicate')
            && !in_array($onDuplicate, ['replace', 'keep_both'], true)
        ) {
            // Google Drive-style conflict prompt: the client shows this error's
            // translated message verbatim to recognize it's a name conflict (not
            // some other failure), then re-submits with on_duplicate=replace/
            // keep_both -- see promptDuplicate()/uploadFile() in FileManager.js.
            return $this->error('file-exist');
        }
        if ($this->fm->config('should_validate_mime', false)) {
            $mimetype = $file->getMimeType();
            if (false === in_array($mimetype, $this->fm->availableMimeTypes())) {
                return $this->error('mime') . $mimetype;
            }
        }
        if ($this->fm->config('should_validate_size', false)) {
            // size to kb unit is needed
            $file_size = $file->getSize() / 1000;
            if ($file_size > $this->fm->maxUploadSize()) {
                return $this->error('size') . $file_size;
            }
        }

        return 'pass';
    }

    /**
     * @param mixed $file
     *
     * @return string
     */
    private function getNewName($file) {
        $newFileName = $this->fm
            ->translateFromUtf8(trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)));
        if ($this->fm->config('rename_file') === true) {
            $newFileName = uniqid();
        } elseif ($this->fm->config('alphanumeric_filename') === true) {
            $newFileName = preg_replace('/[^A-Za-z0-9\-\']/', '_', $newFileName);
        }
        $extension = $file->getClientOriginalExtension();
        if ($extension) {
            $newFileName .= '.' . $extension;
        }
        if ($this->fm->input('on_duplicate') === 'keep_both') {
            $newFileName = $this->uniqueNameFor($newFileName);
        }

        return $newFileName;
    }

    /**
     * Appends " (1)", " (2)", ... to $fileName until it no longer collides with
     * an existing file in this directory -- Google Drive's "Keep both files".
     *
     * @param string $fileName
     *
     * @return string
     */
    private function uniqueNameFor($fileName) {
        if (!$this->setName($fileName)->exists()) {
            return $fileName;
        }
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $base = $extension ? substr($fileName, 0, -(strlen($extension) + 1)) : $fileName;
        $i = 1;
        do {
            $candidate = $base . ' (' . $i . ')' . ($extension ? '.' . $extension : '');
            $i++;
        } while ($this->setName($candidate)->exists());

        return $candidate;
    }

    /**
     * @param mixed  $file
     * @param string $newFileName
     *
     * @return string
     */
    private function saveFile($file, $newFileName) {
        $this->setName($newFileName)->storage->save($file);
        $this->makeThumbnail($newFileName);

        return $newFileName;
    }

    /**
     * @param string $fileName
     *
     * @return void
     */
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
            ->fit($this->fm->config('thumb_img_width', 200), $this->fm->config('thumb_img_height', 200));
        $this->storage->put($image->stream()->detach());
    }
}

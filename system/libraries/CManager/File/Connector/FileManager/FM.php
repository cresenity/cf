<?php

defined('SYSPATH') or die('No direct access allowed.');

class CManager_File_Connector_FileManager_FM {
    const PACKAGE_NAME = 'capp-filemanager';

    const DS = '/';

    /**
     * Raw config array passed in from CElement_Component_FileManager::buildConfig(),
     * merged over the framework's filemanager.php config in config().
     *
     * @var array
     */
    protected $config = [];

    /**
     * Cached translation array, lazily loaded by getTranslation().
     *
     * @var null|array
     */
    protected $labels;

    /**
     * @param array $config
     *
     * @return void
     */
    public function __construct($config = []) {
        $this->config = $config;

        $this->dispatch(new CManager_File_Connector_FileManager_Event_ManagerInitialized($this));
    }

    /**
     * Dispatch an event and call the listeners. Takes variadic arguments instead of a
     * fixed signature: the first is the event instance/name, the rest are passed
     * through to the listeners as the event payload.
     *
     * @return null|array
     */
    public static function dispatch() {
        $args = func_get_args();
        $event = carr::get($args, 0);
        $payload = array_slice($args, 1);

        return CEvent::dispatcher()->dispatch($event, $payload);
    }

    /**
     * @return CManager_File_Connector_FileManager_FM_Path
     */
    public function path() {
        return new CManager_File_Connector_FileManager_FM_Path($this);
    }

    /**
     * Get Input.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function input($key) {
        return $this->translateFromUtf8(CHTTP::request()->input($key));
    }

    /**
     * Reads a config key (dot notation), preferring the value passed in via the
     * element's setConfig()/buildConfig(), falling back to the framework's own
     * filemanager.php config file.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function config($key, $default = null) {
        return carr::get($this->config, $key, CF::config('filemanager.' . $key, $default));
    }

    /**
     * @return array
     */
    public function configData() {
        return $this->config;
    }

    /**
     * Get current lfm type.
     *
     * @return string
     */
    public function currentFmType() {
        $fmType = 'file';
        $request_type = lcfirst(cstr::singular($this->input('type') ?: ''));
        $available_types = array_keys($this->config('folder_categories') ?: []);
        if (in_array($request_type, $available_types)) {
            $fmType = $request_type;
        }

        return $fmType;
    }

    /**
     * @return array
     */
    public function availableMimeTypes() {
        return $this->config('folder_categories.' . $this->currentFmType() . '.valid_mime');
    }

    /**
     * Translate file name to make it compatible on Windows.
     *
     * @param string $input any string
     *
     * @return string
     */
    public function translateFromUtf8($input) {
        if ($this->isRunningOnWindows()) {
            $input = iconv('UTF-8', mb_detect_encoding($input), $input);
        }

        return $input;
    }

    /**
     * Check current operating system is Windows or not.
     *
     * @return bool
     */
    public function isRunningOnWindows() {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Max upload size for the current fm type/category, in KB.
     *
     * @return int
     */
    public function maxUploadSize() {
        return $this->config('folder_categories.' . $this->currentFmType() . '.max_size');
    }

    /**
     * Lazily loads and caches the element/filemanager language file for the
     * current locale.
     *
     * @return array
     */
    public function getTranslation() {
        if ($this->labels == null) {
            $translator = CTranslation::translator();
            $this->labels = $translator->getLoader()->load($translator->getLocale(), 'element/filemanager');
        }

        return $this->labels;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getLabel($key, $default = null) {
        return carr::get($this->getTranslation(), $key, $default);
    }

    /**
     * @param string $type 'user' for private per-user folders, anything else for the shared folder
     *
     * @return bool
     */
    public function allowFolderType($type) {
        if ($type == 'user') {
            return $this->allowMultiUser();
        } else {
            return $this->allowShareFolder();
        }
    }

    /**
     * Check if users are allowed to use their private folders.
     *
     * @return bool
     */
    public function allowMultiUser() {
        return $this->config('allow_multi_user') === true;
    }

    /**
     * Check if users are allowed to use the shared folder.
     * This can be disabled only when allowMultiUser() is true.
     *
     * @return bool
     */
    public function allowShareFolder() {
        if (!$this->allowMultiUser()) {
            return true;
        }

        return $this->config('allow_share_folder') === true;
    }

    /**
     * 'grid' or 'list', from the showList request input, falling back to the
     * current fm type's configured startup_view.
     *
     * @return string
     */
    public function getDisplayMode() {
        $typeKey = $this->currentFmType();
        $startupView = $this->config('folder_categories.' . $typeKey . '.startup_view');
        $viewType = 'grid';
        $targetDisplayType = $this->input('showList') ?: $startupView;
        if (in_array($targetDisplayType, ['list', 'grid'])) {
            $viewType = $targetDisplayType;
        }

        return $viewType;
    }

    /**
     * @param string $storagePath
     *
     * @return CManager_File_Connector_FileManager_FM_StorageRepository
     */
    public function getStorage($storagePath) {
        return new CManager_File_Connector_FileManager_FM_StorageRepository($storagePath, $this);
    }

    /**
     * The current fm type's category subfolder (e.g. 'files'/'photos'), prefixed
     * with root_path -- see CElement_Component_FileManager's own docblock re:
     * root_path being a container directory rather than the browsable root itself.
     *
     * @return string
     */
    public function getCategoryName() {
        $type = $this->currentFmType();

        $categoryName = $this->config('folder_categories.' . $type . '.folder_name', 'files');
        $rootPath = ltrim($this->config('root_path'), '/');
        if (strlen($rootPath) > 0) {
            //rtrim tanpa daftar karakter hanya membuang spasi, bukan garis
            //miring, sehingga root_path berakhiran '/' menghasilkan '//' yang
            //merusak lintasan objek S3.
            $rootPath = rtrim($rootPath, '/') . '/' . rtrim($categoryName, '/');
        }

        return $rootPath;
    }

    /**
     * @param null|string $type currently unused; always returns the site root
     *
     * @return string
     */
    public function getRootFolder($type = null) {
        return '/';
    }

    /**
     * @return string
     */
    public function getUserSlug() {
        $config = $this->config('user_folder_name');
        if (is_callable($config)) {
            return call_user_func($config);
        }
        if (class_exists($config)) {
            // return app()->make($config)->userField();
        }
        $app = c::app();
        $user = $app->user();

        return $user ? $user->username : '';
    }

    /**
     * Get directory seperator of current operating system.
     *
     * @return string
     */
    public function ds() {
        $ds = DS;
        if ($this->isRunningOnWindows()) {
            $ds = '\\';
        }

        return $ds;
    }

    /**
     * Shorter function for throwing a localized error message (key looked up
     * under element/filemanager.error-{$errorType}) -- always throws, never
     * actually returns.
     *
     * @param string $errorType key of message in lang file
     * @param array  $variables variables the message needs
     *
     * @throws \Exception
     */
    public function error($errorType, array $variables = []) {
        throw new \Exception(c::__('element/filemanager.error-' . $errorType, $variables));
    }

    /**
     * Get only the file name.
     *
     * @param string $path real path of a file
     *
     * @return string
     */
    public function getNameFromPath($path) {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * @return string
     */
    public function getThumbFolderName() {
        return $this->config('thumb_folder_name');
    }

    /**
     * @param string $ext file extension, without the leading dot
     *
     * @return string
     */
    public function getFileIcon($ext) {
        return $this->config("file_icon_array.{$ext}", 'fa-file-o');
    }

    /**
     * @param string $ext file extension, without the leading dot
     *
     * @return string
     */
    public function getFileType($ext) {
        return $this->config("file_type_array.{$ext}", 'File');
    }

    /**
     * @return string
     */
    public function connectorUrl() {
        return $this->config('connector_url', curl::base() . 'cresenity/connector/fm');
    }
}

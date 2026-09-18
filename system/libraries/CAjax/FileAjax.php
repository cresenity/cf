<?php

class CAjax_FileAjax {
    const TYPE_FILE = 'file';

    const TYPE_IMAGE = 'image';

    protected $fileId;

    protected $type;

    protected $extension;

    protected $path;

    protected $resource;

    protected $url;

    protected $filename;

    public function __construct($fileId = null) {
        $this->fileId = $fileId;
        $filenameWithoutExtension = pathinfo($fileId, PATHINFO_FILENAME);
        $this->extension = pathinfo($fileId, PATHINFO_EXTENSION);
        $lastChar = substr($filenameWithoutExtension, -1);
        $this->type = $lastChar == 'i' ? self::TYPE_IMAGE : self::TYPE_FILE;
        $this->path = CTemporary::getPath($this->getTemporaryFolderName(), $fileId);
    }

    protected function getTemporaryFolderName() {
        return CF::config('temporary.upload.' . $this->getType(), $this->getType() == CAjax_FileAjax::TYPE_IMAGE ? 'imgupload' : 'fileupload');
    }

    protected function getInfoTemporaryFolderName() {
        return CF::config('temporary.upload.' . $this->getType() . '_info', $this->getType() == CAjax_FileAjax::TYPE_IMAGE ? 'imguploadinfo' : 'fileuploadinfo');
    }

    /**
     * Get the type of the file.
     *
     * @return string the type of the file, either 'file' or 'image'
     */
    public function getType() {
        return $this->type;
    }

    protected function getInfoPath() {
        return CTemporary::getPath($this->getInfoTemporaryFolderName(), $this->fileId);
    }

    public function haveInfo() {
        $infoPath = $this->getInfoPath();

        return $this->getDisk()->exists($infoPath);
    }

    public function getUrl() {
        if ($this->url) {
            return $this->url;
        }
        if ($this->fileId) {
            return CTemporary::getPublicUrl($this->getTemporaryFolderName(), $this->fileId);
        }

        if ($this->resource) {
            return $this->resource->getFullUrl();
        }

        return null;
    }

    public function getFileName() {
        if ($this->filename) {
            return $this->filename;
        }
        if ($this->fileId) {
            return basename($this->fileId);
        }
        if ($this->resource) {
            return basename($this->resource->getUrl());
        }

        return null;
    }

    public function setFilename($filename) {
        $this->filename = $filename;

        return $this;
    }

    /**
     * @return array the information about the file
     */
    public function getInfo() {
        $path = $this->getInfoPath();
        $info = $this->getDisk()->get($path);

        return json_decode($info, true);
    }

    /**
     * Get the disk used for storing temporary files.
     *
     * @return CStorage_Adapter the storage adapter for the public temporary disk
     */
    protected function getDisk() {
        return CTemporary::publicDisk();
    }

    public function setResource(CModel $resource) {
        $this->resource = $resource;
    }

    public static function fromResource(CModel $resource) {
        $file = new self(null);
        $file->setResource($resource);

        return $file;
    }

    public static function create($mixed) {
        if (is_array($mixed)) {
            return self::fromJson(json_encode($mixed));
        }
        if (cstr::startsWith($mixed, '{')) {
            return self::fromJson($mixed);
        }
        if (cstr::startsWith($mixed, '@')) {
            return self::fromIdentifier($mixed);
        }
        if (cstr::startsWith($mixed, 'http')) {
            return self::fromUrl($mixed);
        }
        if ($mixed instanceof CModel) {
            return self::fromResource($mixed);
        }

        return new self($mixed);
    }

    public function setUrl($url) {
        $this->url = $url;

        return $this;
    }

    public static function fromUrl($url) {
        $file = new self(null);
        $file->setUrl($url);

        return $file;
    }

    public static function fromJson($json) {
        if (is_string($json)) {
            $data = json_decode($json, true);
        }
        $fileId = carr::get($data, 'fileId');
        $url = carr::get($data, 'url');
        $filename = carr::get($data, 'fileName');

        $file = new self($fileId);
        $file->setFilename($filename);
        $file->setUrl($url);
        // $file->setUrl($url);

        return $file;
    }

    public static function fromIdentifier($identifier) {
        $data = self::parseIdentifier($identifier);
        $fileId = carr::get($data, 'fileId');
        $resourceId = carr::get($data, 'resourceId');
        $url = carr::get($data, 'url');

        if ($fileId) {
            $file = new self($fileId);

            return $file;
        }
        if ($resourceId) {
            $resourceClass = CF::config('resource.resource_model');
            $resource = $resourceClass::find($resourceId);
            $file = self::fromResource($resource);

            return $file;
        }

        $file = new self(null);
        $file->setUrl($url);

        return $file;
    }

    public static function parseIdentifier($identifier) {
        if (cstr::startsWith($identifier, '@')) {
            $identifier = substr($identifier, 1);
        }
        $identifier = explode('|', $identifier);

        return [
            'fileId' => carr::get($identifier, 0),
            'resourceId' => carr::get($identifier, 1),
            'url' => carr::get($identifier, 2),
        ];
    }

    public function saveToResource() {
    }

    public function getIdentifier() {
        $identifier = '@' . $this->fileId;
        $identifier .= '|' . c::optional($this->resource)->getKey();
        $identifier .= '|' . $this->url;

        return $identifier;
    }
}

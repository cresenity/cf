<?php

defined('SYSPATH') or die('No direct access allowed.');

use League\Flysystem\WhitespacePathNormalizer;

class CComponent_FileUploadConfiguration {
    public static function storage() {
        if (CF::isTesting()) {
            // We want to "fake" the first time in a test run, but not again because
            // ::fake() whipes the storage directory every time its called.
            c::rescue(function () {
                // If the storage disk is not found (meaning it's the first time),
                // this will throw an error and trip the second callback.
                return CStorage::instance()->disk(static::disk());
            }, function () {
                return CStorage::instance()->fake(static::disk());
            });
        }

        return CStorage::instance()->disk(static::disk());
    }

    public static function disk() {
        if (CF::isTesting()) {
            return 'tmp-for-tests';
        }

        return CF::config('storage.temp') ?: CF::config('storage.default');
    }

    public static function diskConfig() {
        return CF::config('storage.disks.' . static::disk());
    }

    public static function isUsingS3() {
        $diskBeforeTestFake = CF::config('storage.temp') ?: CF::config('storage.default');

        return CF::config('storage.disks.' . strtolower($diskBeforeTestFake) . '.driver') === 's3';
    }

    public static function normalizeRelativePath($path) {
        return (new WhitespacePathNormalizer())->normalizePath($path);
    }

    protected static function directory() {
        return static::normalizeRelativePath('component/upload');
    }

    protected static function s3Root() {
        return static::isUsingS3() && is_array(static::diskConfig()) && array_key_exists('root', static::diskConfig()) ? static::normalizeRelativePath(static::diskConfig()['root']) : '';
    }

    public static function path($path = '', $withS3Root = true) {
        $prefix = $withS3Root ? static::s3Root() : '';
        $directory = static::directory();
        $path = static::normalizeRelativePath($path);

        return $prefix . ($prefix ? '/' : '') . $directory . ($path ? '/' : '') . $path;
    }

    public static function middleware() {
        //return config('livewire.temporary_file_upload.middleware') ?: 'throttle:60,1';
        return 'throttle:60,1';
    }

    public static function rules() {
        //$rules = config('livewire.temporary_file_upload.rules');
        $rules = null;
        if (is_null($rules)) {
            return ['required', 'file', 'max:12288'];
        }

        if (is_array($rules)) {
            return $rules;
        }

        return explode('|', $rules);
    }
}

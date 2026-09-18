<?php


use Symfony\Component\Finder\Finder;

class CBackup_FileSelection {

    /** @var \CCollection */
    protected $includeFilesAndDirectories;

    /** @var \CCollection */
    protected $excludeFilesAndDirectories;

    /** @var bool */
    protected $shouldFollowLinks = false;

    /**
     * @param array|string $includeFilesAndDirectories
     *
     * @return \CBackup_FileSelection
     */
    public static function create($includeFilesAndDirectories = []) {
        return new static($includeFilesAndDirectories);
    }

    /**
     * @param array|string $includeFilesAndDirectories
     */
    public function __construct($includeFilesAndDirectories = []) {
        $this->includeFilesAndDirectories = c::collect($includeFilesAndDirectories);
        $this->excludeFilesAndDirectories = c::collect();
    }

    /**
     * Do not included the given files and directories.
     *
     * @param array|string $excludeFilesAndDirectories
     *
     * @return \CBackup_FileSelection
     */
    public function excludeFilesFrom($excludeFilesAndDirectories) {
        $this->excludeFilesAndDirectories = $this->excludeFilesAndDirectories->merge($this->sanitize($excludeFilesAndDirectories));
        return $this;
    }

    public function shouldFollowLinks($shouldFollowLinks) {
        $this->shouldFollowLinks = $shouldFollowLinks;
        return $this;
    }

    /**
     * @return \Generator|string[]
     */
    public function selectedFiles() {
        if ($this->includeFilesAndDirectories->isEmpty()) {
            return [];
        }
        $finder = (new Finder())
                ->ignoreDotFiles(false)
                ->ignoreVCS(false);
        if ($this->shouldFollowLinks) {
            $finder->followLinks();
        }
        foreach ($this->includedFiles() as $includedFile) {
            yield $includedFile;
        }
        if (!count($this->includedDirectories())) {
            return;
        }
        $finder->in($this->includedDirectories());
        foreach ($finder->getIterator() as $file) {
            if ($this->shouldExclude($file)) {
                continue;
            }
            yield $file->getPathname();
        }
    }

    protected function includedFiles() {
        return $this->includeFilesAndDirectories->filter(function ($path) {
                    return is_file($path);
                })->toArray();
    }

    protected function includedDirectories() {
        return $this->includeFilesAndDirectories->reject(function ($path) {
                    return is_file($path);
                })->toArray();
    }

    protected function shouldExclude($path) {
        foreach ($this->excludeFilesAndDirectories as $excludedPath) {
            if (cstr::startsWith(realpath($path), $excludedPath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string|array $paths
     *
     * @return \CCollection
     */
    protected function sanitize($paths) {
        return c::collect($paths)
                        ->reject(function ($path) {
                            return $path === '';
                        })
                        ->flatMap(function ($path) {
                            return glob($path);
                        })
                        ->map(function ($path) {
                            return realpath($path);
                        })
                        ->reject(function ($path) {
                            return $path === false;
                        });
    }

}

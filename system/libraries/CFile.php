<?php

defined('SYSPATH') or die('No direct access allowed.');

use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

class CFile {
    /**
     * Check if a file exists in a path or url.
     *
     * @param string $file → path or file url
     *
     * @return bool
     */
    public static function exists($file) {
        if (filter_var($file, FILTER_VALIDATE_URL)) {
            $stream = stream_context_create(['http' => ['method' => 'HEAD']]);
            if ($content = @fopen($file, 'r', false, $stream)) {
                $headers = stream_get_meta_data($content);
                fclose($content);
                $status = substr($headers['wrapper_data'][0], 9, 3);

                return $status >= 200 && $status < 400;
            }

            return false;
        }

        return file_exists($file) && is_file($file);
    }

    /**
     * Atomic filewriter.
     *
     * Safely writes new contents to a file using an atomic two-step process.
     * If the script is killed before the write is complete, only the temporary
     * trash file will be corrupted.
     *
     * @param string $path         filename to write the data to
     * @param string $contents     data to write to file
     * @param string $atomicSuffix lets you optionally provide a different
     *                             suffix for the temporary file
     *
     * @return mixed number of bytes written on success, otherwise FALSE
     */
    public static function putAtomic($path, $contents, $atomicSuffix = 'atomictmp') {
        // Perform an exclusive (locked) overwrite to a temporary file.
        $pathTemp = sprintf('%s.%s', $path, $atomicSuffix);
        $writeResult = @file_put_contents($pathTemp, $contents, LOCK_EX);
        if ($writeResult !== false) {
            // Now move the file to its real destination (replaced if exists).
            $moveResult = @rename($pathTemp, $path);
            if ($moveResult === true) {
                // Successful write and move. Return number of bytes written.
                return $writeResult;
            }
        }

        return false; // Failed.
    }

    /**
     * Give the diff of time modified file and given time parameters
     * Use current time if parameter $time not passed.
     *
     * @param string     $filename
     * @param string|int $time
     *
     * @return int diff in second
     */
    public static function lastModifiedDiff($filename, $time = null) {
        if ($time == null) {
            $time = time();
        }
        if (is_string($time)) {
            $time = strtotime($time);
        }

        return $time - self::lastModified($filename);
    }

    public static function phpValue($val, $level = 0) {
        $indentString = '    ';
        $str = '';
        $eol = PHP_EOL;
        $indent = str_repeat($indentString, $level);
        if (is_array($val)) {
            $str .= '[' . $eol;
            $indent2 = $indent . $indentString;
            foreach ($val as $k => $v) {
                $str .= $indent2 . "'" . addslashes($k) . "'=>";
                $str .= static::phpValue($v, $level + 1);
                $str .= ',' . $eol;
            }
            $str .= $indent . ']';
        } elseif (is_null($val)) {
            $str .= 'NULL';
        } elseif (is_bool($val)) {
            $str .= ($val === true ? 'TRUE' : 'FALSE');
        } else {
            $str .= "'" . addslashes($val) . "'";
        }

        return $str;
    }

    public static function putPhpValue($filename, $data, $lock = true) {
        $val = '<?php ' . PHP_EOL . 'return ' . static::phpValue($data) . ';';

        return static::put($filename, $val, $lock);
    }

    /**
     * Determine if a file or directory is missing.
     *
     * @param string $path
     *
     * @return bool
     */
    public static function missing($path) {
        return !static::exists($path);
    }

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @param bool   $lock
     *
     * @throws CFile_Exception_FileNotFoundException
     *
     * @return string
     */
    public static function get($path, $lock = false) {
        if (static::isFile($path)) {
            return $lock ? static::sharedGet($path) : file_get_contents($path);
        }

        throw new CStorage_Exception_FileNotFoundException("File does not exist at path {$path}");
    }

    /**
     * Get the contents of a file as decoded JSON.
     *
     * @param string $path
     * @param int    $flags
     * @param bool   $lock
     *
     * @throws \CStorage_Exception_FileNotFoundException
     *
     * @return array
     */
    public static function json($path, $flags = 0, $lock = false) {
        return json_decode(static::get($path, $lock), true, 512, $flags);
    }

    /**
     * Get contents of a file with shared access.
     *
     * @param string $path
     *
     * @return string
     */
    public static function sharedGet($path) {
        $contents = '';
        $handle = fopen($path, 'rb');
        if ($handle) {
            try {
                if (flock($handle, LOCK_SH)) {
                    clearstatcache(true, $path);
                    $contents = fread($handle, static::size($path) ?: 1);
                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }

        return $contents;
    }

    /**
     * Get the returned value of a file.
     *
     * @param string $path
     * @param array  $data
     *
     * @throws CStorage_Exception_FileNotFoundException
     *
     * @return mixed
     */
    public static function getRequire($path, array $data = []) {
        if (static::isFile($path)) {
            $__path = $path;
            $__data = $data;

            $function = static function () use ($__path, $__data) {
                extract($__data, EXTR_SKIP);

                return require $__path;
            };

            return $function();
        }

        throw new CStorage_Exception_FileNotFoundException("File does not exist at path {$path}");
    }

    /**
     * Require the given file once.
     *
     * @param string $path
     * @param array  $data
     *
     * @throws \CStorage_Exception_FileNotFoundException
     *
     * @return mixed
     */
    public static function requireOnce($path, array $data = []) {
        if (static::isFile($path)) {
            $__path = $path;
            $__data = $data;
            $function = static function () use ($__path, $__data) {
                extract($__data, EXTR_SKIP);

                return require_once $__path;
            };

            return $function();
        }

        throw new CStorage_Exception_FileNotFoundException("File does not exist at path {$path}.");
    }

    /**
     * Get the contents of a file one line at a time.
     *
     * @param string $path
     *
     * @throws \CStorage_Exception_FileNotFoundException
     *
     * @return \CCollection_LazyCollection
     */
    public static function lines($path) {
        if (!static::isFile($path)) {
            throw new CStorage_Exception_FileNotFoundException(
                "File does not exist at path {$path}."
            );
        }

        return CCollection_LazyCollection::make(function () use ($path) {
            $file = new SplFileObject($path);

            $file->setFlags(SplFileObject::DROP_NEW_LINE);

            while (!$file->eof()) {
                yield $file->fgets();
            }
        });
    }

    /**
     * Get the hash of the file at the given path.
     *
     * @param string $path
     * @param string $algorithm
     *
     * @return string
     */
    public static function hash($path, $algorithm = 'md5') {
        return hash_file($algorithm, $path);
    }

    /**
     * Write the contents of a file.
     *
     * @param string $path
     * @param string $contents
     * @param bool   $lock
     *
     * @return int|bool
     */
    public static function put($path, $contents, $lock = false) {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Write the contents of a file, replacing it atomically if it already exists.
     *
     * @param string $path
     * @param string $content
     *
     * @return void
     */
    public static function replace($path, $content) {
        // If the path already exists and is a symlink, get the real path...
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;

        $tempPath = tempnam(dirname($path), basename($path));

        // Fix permissions of tempPath because `tempnam()` creates it with permissions set to 0600...
        chmod($tempPath, 0777 - umask());

        file_put_contents($tempPath, $content);

        rename($tempPath, $path);
    }

    /**
     * Replace a given string within a given file.
     *
     * @param array|string $search
     * @param array|string $replace
     * @param string       $path
     *
     * @return void
     */
    public static function replaceInFile($search, $replace, $path) {
        file_put_contents($path, str_replace($search, $replace, file_get_contents($path)));
    }

    /**
     * Prepend to a file.
     *
     * @param string $path
     * @param string $data
     *
     * @return int
     */
    public static function prepend($path, $data) {
        if (static::exists($path)) {
            return static::put($path, $data . static::get($path));
        }

        return static::put($path, $data);
    }

    /**
     * Append to a file.
     *
     * @param string $path
     * @param string $data
     *
     * @return int
     */
    public static function append($path, $data) {
        return file_put_contents($path, $data, FILE_APPEND);
    }

    /**
     * Get or set UNIX mode of a file or directory.
     *
     * @param string   $path
     * @param null|int $mode
     *
     * @return mixed
     */
    public static function chmod($path, $mode = null) {
        if ($mode) {
            return chmod($path, $mode);
        }

        return substr(sprintf('%o', fileperms($path)), -4);
    }

    /**
     * Delete the file at a given path.
     *
     * @param string|array $paths
     *
     * @return bool
     */
    public static function delete($paths) {
        $paths = is_array($paths) ? $paths : func_get_args();
        $success = true;
        foreach ($paths as $path) {
            try {
                if (!@unlink($path)) {
                    $success = false;
                }
            } catch (ErrorException $e) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Move a file to a new location.
     *
     * @param string $path
     * @param string $target
     *
     * @return bool
     */
    public static function move($path, $target) {
        return rename($path, $target);
    }

    /**
     * Copy a file to a new location.
     *
     * @param string $path
     * @param string $target
     *
     * @return bool
     */
    public static function copy($path, $target) {
        return copy($path, $target);
    }

    /**
     * Create a symlink to the target file or directory. On Windows, a hard link is created if the target is a file.
     *
     * @param string $target
     * @param string $link
     *
     * @return void
     */
    public static function link($target, $link) {
        if (!CServer::isWindows()) {
            return symlink($target, $link);
        }

        $mode = static::isDirectory($target) ? 'J' : 'H';

        exec("mklink /{$mode} " . escapeshellarg($link) . ' ' . escapeshellarg($target));
    }

    /**
     * Create a relative symlink to the target file or directory.
     *
     * @param string $target
     * @param string $link
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public static function relativeLink($target, $link) {
        if (!class_exists(SymfonyFilesystem::class)) {
            throw new RuntimeException(
                'To enable support for relative links, please install the symfony/filesystem package.'
            );
        }

        $relativeTarget = (new SymfonyFilesystem())->makePathRelative($target, dirname($link));

        static::link($relativeTarget, $link);
    }

    /**
     * Extract the file name from a file path.
     *
     * @param string $path
     *
     * @return string
     */
    public static function name($path) {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Extract the trailing name component from a file path.
     *
     * @param string $path
     *
     * @return string
     */
    public static function basename($path) {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * Extract the parent directory from a file path.
     *
     * @param string $path
     *
     * @return string
     */
    public static function dirname($path) {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    /**
     * Extract the file extension from a file path.
     *
     * @param string $path
     *
     * @return string
     */
    public static function extension($path) {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Guess the file extension from the mime-type of a given file.
     *
     * @param string $path
     *
     * @throws \RuntimeException
     *
     * @return null|string
     */
    public static function guessExtension($path) {
        if (!class_exists(MimeTypes::class)) {
            throw new RuntimeException(
                'To enable support for guessing extensions, please install the symfony/mime package.'
            );
        }
        $mimeTypes = (new MimeTypes())->getExtensions(static::mimeType($path));

        return isset($mimeTypes[0]) ? $mimeTypes[0] : null;
    }

    /**
     * Get the file type of a given file.
     *
     * @param string $path
     *
     * @return string
     */
    public static function type($path) {
        return filetype($path);
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param string $path
     *
     * @return string|false
     */
    public static function mimeType($path) {
        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    }

    /**
     * Get the file size of a given file.
     *
     * @param string $path
     *
     * @return int
     */
    public static function size($path) {
        clearstatcache();
        $filesize = filesize($path);
        if ($filesize == 0 && static::exists($path)) {
            //try to get another method
            $fp = fopen($path, 'rb');
            fseek($fp, 0, SEEK_END);
            $filesize = ftell($fp);
            fclose($fp);
        }

        return $filesize;
    }

    /**
     * Get the file's last modification time.
     *
     * @param string $path
     *
     * @return int
     */
    public static function lastModified($path) {
        return filemtime($path);
    }

    /**
     * Determine if the given path is a directory.
     *
     * @param string $directory
     *
     * @return bool
     */
    public static function isDirectory($directory) {
        return is_dir($directory);
    }

    /**
     * Determine if the given path is a directory that does not contain any other files or directories.
     *
     * @param string $directory
     * @param bool   $ignoreDotFiles
     *
     * @return bool
     */
    public static function isEmptyDirectory($directory, $ignoreDotFiles = false) {
        return !Finder::create()->ignoreDotFiles($ignoreDotFiles)->in($directory)->depth(0)->hasResults();
    }

    /**
     * Determine if the given path is readable.
     *
     * @param string $path
     *
     * @return bool
     */
    public static function isReadable($path) {
        return is_readable($path);
    }

    /**
     * Determine if the given path is writable.
     *
     * @param string $path
     *
     * @return bool
     */
    public static function isWritable($path) {
        return is_writable($path);
    }

    /**
     * Determine if two files are the same by comparing their hashes.
     *
     * @param string $firstFile
     * @param string $secondFile
     *
     * @return bool
     */
    public static function hasSameHash($firstFile, $secondFile) {
        $hash = @md5_file($firstFile);

        return $hash && $hash === @md5_file($secondFile);
    }

    /**
     * Determine if the given path is a file.
     *
     * @param string $file
     *
     * @return bool
     */
    public static function isFile($file) {
        return is_file($file);
    }

    /**
     * Find path names matching a given pattern.
     *
     * @param string $pattern
     * @param int    $flags
     *
     * @return array
     */
    public static function glob($pattern, $flags = 0) {
        return glob($pattern, $flags);
    }

    /**
     * Get an array of all files in a directory.
     *
     * @param string $directory
     * @param bool   $hidden
     *
     * @return \Symfony\Component\Finder\SplFileInfo[]
     */
    public static function files($directory, $hidden = false) {
        return iterator_to_array(
            Finder::create()->files()->ignoreDotFiles(!$hidden)->in($directory)->depth(0)->sortByName(),
            false
        );
    }

    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param string $directory
     * @param bool   $hidden
     *
     * @return \Symfony\Component\Finder\SplFileInfo[]
     */
    public static function allFiles($directory, $hidden = false) {
        return iterator_to_array(
            Finder::create()->files()->ignoreDotFiles(!$hidden)->in($directory)->sortByName(),
            false
        );
    }

    /**
     * Get all of the directories within a given directory.
     *
     * @param string $directory
     *
     * @return array
     */
    public static function directories($directory) {
        $directories = [];
        foreach (Finder::create()->in($directory)->directories()->depth(0)->sortByName() as $dir) {
            $directories[] = $dir->getPathname();
        }

        return $directories;
    }

    /**
     * Ensure a directory exists.
     *
     * @param string $path
     * @param int    $mode
     * @param bool   $recursive
     *
     * @return void
     */
    public static function ensureDirectoryExists($path, $mode = 0755, $recursive = true) {
        if (!static::isDirectory($path)) {
            static::makeDirectory($path, $mode, $recursive);
        }
    }

    /**
     * Create a directory.
     *
     * @param string $path
     * @param int    $mode
     * @param bool   $recursive
     * @param bool   $force
     *
     * @return bool
     */
    public static function makeDirectory($path, $mode = 0755, $recursive = false, $force = false) {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * Move a directory.
     *
     * @param string $from
     * @param string $to
     * @param bool   $overwrite
     *
     * @return bool
     */
    public static function moveDirectory($from, $to, $overwrite = false) {
        if ($overwrite && static::isDirectory($to) && !static::deleteDirectory($to)) {
            return false;
        }

        return @rename($from, $to) === true;
    }

    /**
     * Copy a directory from one location to another.
     *
     * @param string   $directory
     * @param string   $destination
     * @param null|int $options
     *
     * @return bool
     */
    public static function copyDirectory($directory, $destination, $options = null) {
        if (!static::isDirectory($directory)) {
            return false;
        }

        $options = $options ?: FilesystemIterator::SKIP_DOTS;

        // If the destination directory does not actually exist, we will go ahead and
        // create it recursively, which just gets the destination prepared to copy
        // the files over. Once we make the directory we'll proceed the copying.
        static::ensureDirectoryExists($destination, 0777);

        $items = new FilesystemIterator($directory, $options);

        foreach ($items as $item) {
            // As we spin through items, we will check to see if the current file is actually
            // a directory or a file. When it is actually a directory we will need to call
            // back into this function recursively to keep copying these nested folders.
            $target = $destination . '/' . $item->getBasename();

            if ($item->isDir()) {
                $path = $item->getPathname();

                if (!static::copyDirectory($path, $target, $options)) {
                    return false;
                }
            } elseif (!static::copy($item->getPathname(), $target)) {
                // If the current items is just a regular file, we will just copy this to the new
                // location and keep looping. If for some reason the copy fails we'll bail out
                // and return false, so the developer is aware that the copy process failed.
                return false;
            }
        }

        return true;
    }

    /**
     * Recursively delete a directory.
     *
     * The directory itself may be optionally preserved.
     *
     * @param string $directory
     * @param bool   $preserve
     *
     * @return bool
     */
    public static function deleteDirectory($directory, $preserve = false) {
        if (!static::isDirectory($directory)) {
            return false;
        }
        $items = new FilesystemIterator($directory);
        foreach ($items as $item) {
            // If the item is a directory, we can just recurse into the function and
            // delete that sub-directory otherwise we'll just delete the file and
            // keep iterating through each file until the directory is cleaned.
            if ($item->isDir() && !$item->isLink()) {
                static::deleteDirectory($item->getPathname());
            } else {
                // If the item is just a file, we can go ahead and delete it since we're
                // just looping through and waxing all of the files in this directory
                // and calling directories recursively, so we delete the real path.
                static::delete($item->getPathname());
            }
        }
        if (!$preserve) {
            @rmdir($directory);
        }

        return true;
    }

    /**
     * Remove all of the directories within a given directory.
     *
     * @param string $directory
     *
     * @return bool
     */
    public static function deleteDirectories($directory) {
        $allDirectories = static::directories($directory);
        if (!empty($allDirectories)) {
            foreach ($allDirectories as $directoryName) {
                static::deleteDirectory($directoryName);
            }

            return true;
        }

        return false;
    }

    /**
     * Empty the specified directory of all files and folders.
     *
     * @param string $directory
     *
     * @return bool
     */
    public static function cleanDirectory($directory) {
        return static::deleteDirectory($directory, true);
    }
}

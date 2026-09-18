<?php

/**
 * SCSS server.
 */
class CManager_Asset_SCSS_Server {
    /**
     * Join path components.
     *
     * @param string $left  Path component, left of the directory separator
     * @param string $right Path component, right of the directory separator
     *
     * @return string
     */
    protected function join($left, $right) {
        return rtrim($left, '/\\') . DIRECTORY_SEPARATOR . ltrim($right, '/\\');
    }

    /**
     * Get name of requested .scss file.
     *
     * @return null|string
     */
    protected function inputName() {
        switch (true) {
            case isset($_GET['p']):
                return $_GET['p'];
            case isset($_SERVER['PATH_INFO']):
                return $_SERVER['PATH_INFO'];
            case isset($_SERVER['DOCUMENT_URI']):
                return substr($_SERVER['DOCUMENT_URI'], strlen($_SERVER['SCRIPT_NAME']));
        }
    }

    /**
     * Get path to requested .scss file.
     *
     * @return string
     */
    protected function findInput() {
        if (($input = $this->inputName())
            && strpos($input, '..') === false
            && substr($input, -5) === '.scss'
        ) {
            $name = $this->join($this->dir, $input);

            if (is_file($name) && is_readable($name)) {
                return $name;
            }
        }

        return false;
    }

    /**
     * Get path to cached .css file.
     *
     * @param mixed $fname
     *
     * @return string
     */
    protected function cacheName($fname) {
        return $this->join($this->cacheDir, md5($fname) . '.css');
    }

    /**
     * Get path to cached imports.
     *
     * @param mixed $out
     *
     * @return string
     */
    protected function importsCacheName($out) {
        return $out . '.imports';
    }

    /**
     * Determine whether .scss file needs to be re-compiled.
     *
     * @param string $in  Input path
     * @param string $out Output path
     *
     * @return bool true if compile required
     */
    protected function needsCompile($in, $out) {
        if (!is_file($out)) {
            return true;
        }

        $mtime = filemtime($out);
        if (filemtime($in) > $mtime) {
            return true;
        }

        // look for modified imports
        $icache = $this->importsCacheName($out);
        if (is_readable($icache)) {
            $imports = unserialize(file_get_contents($icache));
            foreach ($imports as $import) {
                if (filemtime($import) > $mtime) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get If-Modified-Since header from client request.
     *
     * @return string
     */
    protected function getModifiedSinceHeader() {
        $modifiedSince = '';

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $modifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'];

            if (false !== ($semicolonPos = strpos($modifiedSince, ';'))) {
                $modifiedSince = substr($modifiedSince, 0, $semicolonPos);
            }
        }

        return $modifiedSince;
    }

    /**
     * Compile .scss file.
     *
     * @param string $in  Input path (.scss)
     * @param string $out Output path (.css)
     *
     * @return string
     */
    protected function compile($in, $out) {
        $start = microtime(true);
        $css = $this->scss->compile(file_get_contents($in), $in);
        $elapsed = round((microtime(true) - $start), 4);

        $v = CManager_Asset_SCSS_Compiler::$VERSION;
        $t = @date('r');
        $css = "/* compiled by scssphp {$v} on {$t} ({$elapsed}s) */\n\n" . $css;

        file_put_contents($out, $css);
        file_put_contents(
            $this->importsCacheName($out),
            serialize($this->scss->getParsedFiles())
        );

        return $css;
    }

    /**
     * Compile requested scss and serve css.  Outputs HTTP response.
     *
     * @param string $salt Prefix a string to the filename for creating the cache name hash
     */
    public function serve($salt = '') {
        $protocol = isset($_SERVER['SERVER_PROTOCOL'])
            ? $_SERVER['SERVER_PROTOCOL']
            : 'HTTP/1.0';

        if ($input = $this->findInput()) {
            $output = $this->cacheName($salt . $input);

            if ($this->needsCompile($input, $output)) {
                try {
                    $css = $this->compile($input, $output);

                    $lastModified = gmdate('D, d M Y H:i:s', filemtime($output)) . ' GMT';

                    header('Last-Modified: ' . $lastModified);
                    header('Content-type: text/css');

                    echo $css;

                    return;
                } catch (Exception $e) {
                    header($protocol . ' 500 Internal Server Error');
                    header('Content-type: text/plain');

                    echo 'Parse error: ' . $e->getMessage() . "\n";
                }
            }

            header('X-SCSS-Cache: true');
            header('Content-type: text/css');

            $modifiedSince = $this->getModifiedSinceHeader();
            $mtime = filemtime($output);

            if (@strtotime($modifiedSince) === $mtime) {
                header($protocol . ' 304 Not Modified');

                return;
            }

            $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
            header('Last-Modified: ' . $lastModified);

            echo file_get_contents($output);

            return;
        }

        header($protocol . ' 404 Not Found');
        header('Content-type: text/plain');

        $v = CManager_Asset_SCSS_Compiler::$VERSION;
        echo "/* INPUT NOT FOUND scss {$v} */\n";
    }

    /**
     * Constructor.
     *
     * @param string      $dir      Root directory to .scss files
     * @param string      $cacheDir Cache directory
     * @param null|\scssc $scss     SCSS compiler instance
     */
    public function __construct($dir, $cacheDir = null, $scss = null) {
        $this->dir = $dir;

        if (!isset($cacheDir)) {
            $cacheDir = $this->join($dir, 'scss_cache');
        }

        $this->cacheDir = $cacheDir;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        if (!isset($scss)) {
            $scss = new CManager_Asset_SCSS_Compiler();
            $scss->setImportPaths($this->dir);
        }
        $this->scss = $scss;
    }

    /**
     * Helper method to serve compiled scss.
     *
     * @param string $path Root path
     */
    public static function serveFrom($path) {
        $server = new self($path);
        $server->serve();
    }
}

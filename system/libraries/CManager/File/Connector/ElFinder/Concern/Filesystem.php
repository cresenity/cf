<?php

trait CManager_File_Connector_ElFinder_Concern_Filesystem {
    /**
     * Create directory
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * */
    protected function mkdir($args) {
        $target = $args['target'];
        $name = $args['name'];
        $dirs = $args['dirs'];
        if ($name === '' && !$dirs) {
            return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_INV_PARAMS, 'mkdir')];
        }

        if (($volume = $this->volume($target)) == false) {
            return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_MKDIR, $name, CManager_File_Connector_ElFinder_Base::ERROR_TRGDIR_NOT_FOUND, '#' . $target)];
        }
        if ($dirs) {
            sort($dirs);
            $reset = null;
            $mkdirs = [];
            foreach ($dirs as $dir) {
                $tgt = &$mkdirs;
                $_names = explode('/', trim($dir, '/'));
                foreach ($_names as $_key => $_name) {
                    if (!isset($tgt[$_name])) {
                        $tgt[$_name] = [];
                    }
                    $tgt = &$tgt[$_name];
                }
                $tgt = &$reset;
            }
            $res = $this->ensureDirsRecursively($volume, $target, $mkdirs);
            $ret = [
                'added' => $res['stats'],
                'hashes' => $res['hashes']
            ];
            if ($res['error']) {
                $ret['warning'] = $this->error(CManager_File_Connector_ElFinder_Base::ERROR_MKDIR, $res['error'][0], $volume->error());
            }
            return $ret;
        } else {
            return ($dir = $volume->mkdir($target, $name)) == false ? ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_MKDIR, $name, $volume->error())] : ['added' => [$dir]];
        }
    }

    /**
     * Create empty file
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * */
    protected function mkfile($args) {
        $target = $args['target'];
        $name = $args['name'];

        if (($volume = $this->volume($target)) == false) {
            return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_MKFILE, $name, CManager_File_Connector_ElFinder_Base::ERROR_TRGDIR_NOT_FOUND, '#' . $target)];
        }

        return ($file = $volume->mkfile($target, $args['name'])) == false ? ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_MKFILE, $name, $volume->error())] : ['added' => [$file]];
    }

    /**
     * Rename file, Accept multiple items >= API 2.1031
     *
     * @param array $args
     *
     * @return array
     *
     * @throws CManager_File_Connector_ElFinder_Exception_AbortException
     */
    protected function rename($args) {
        $target = $args['target'];
        $name = $args['name'];
        $query = (strpos($args['q'], '*') !== false) ? $args['q'] : '';
        $targets = $args['targets'];
        $rms = [];
        $notfounds = [];
        $locked = [];
        $errs = [];
        $files = [];
        $removed = [];
        $res = [];
        $type = 'normal';

        if (!($volume = $this->volume($target))) {
            return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_RENAME, '#' . $target, CManager_File_Connector_ElFinder_Base::ERROR_FILE_NOT_FOUND)];
        }

        if ($targets) {
            array_unshift($targets, $target);
            foreach ($targets as $h) {
                if ($rm = $volume->file($h)) {
                    if ($this->itemLocked($h)) {
                        $locked[] = $rm['name'];
                    } else {
                        $rm['realpath'] = $volume->realpath($h);
                        $rms[] = $rm;
                    }
                } else {
                    $notfounds[] = '#' . $h;
                }
            }
            if (!$rms) {
                $res['error'] = [];
                if ($notfounds) {
                    $res['error'] = [CManager_File_Connector_ElFinder_Base::ERROR_RENAME, join(', ', $notfounds), CManager_File_Connector_ElFinder_Base::ERROR_FILE_NOT_FOUND];
                }
                if ($locked) {
                    array_push($res['error'], CManager_File_Connector_ElFinder_Base::ERROR_LOCKED, join(', ', $locked));
                }
                return $res;
            }

            $res['warning'] = [];
            if ($notfounds) {
                array_push($res['warning'], CManager_File_Connector_ElFinder_Base::ERROR_RENAME, join(', ', $notfounds), CManager_File_Connector_ElFinder_Base::ERROR_FILE_NOT_FOUND);
            }
            if ($locked) {
                array_push($res['warning'], CManager_File_Connector_ElFinder_Base::ERROR_LOCKED, join(', ', $locked));
            }

            if ($query) {
                // batch rename
                $splits = static::splitFileExtention($query);
                if ($splits[1] && $splits[0] === '*') {
                    $type = 'extention';
                    $name = $splits[1];
                } elseif (strlen($splits[0]) > 1) {
                    if (substr($splits[0], -1) === '*') {
                        $type = 'prefix';
                        $name = substr($splits[0], 0, strlen($splits[0]) - 1);
                    } elseif (substr($splits[0], 0, 1) === '*') {
                        $type = 'suffix';
                        $name = substr($splits[0], 1);
                    }
                }
                if ($type !== 'normal') {
                    if (!empty($this->listeners['rename.pre'])) {
                        $_args = ['name' => $name];
                        foreach ($this->listeners['rename.pre'] as $handler) {
                            $_res = call_user_func_array($handler, ['rename', &$_args, $this, $volume]);
                            if (!empty($_res['preventexec'])) {
                                break;
                            }
                        }
                        $name = $_args['name'];
                    }
                }
            }
            foreach ($rms as $rm) {
                if ($type === 'normal') {
                    $rname = $volume->uniqueName($volume->realpath($rm['phash']), $name, '', false);
                } else {
                    $rname = $name;
                    if ($type === 'extention') {
                        $splits = static::splitFileExtention($rm['name']);
                        $rname = $splits[0] . '.' . $name;
                    } elseif ($type === 'prefix') {
                        $rname = $name . $rm['name'];
                    } elseif ($type === 'suffix') {
                        $splits = static::splitFileExtention($rm['name']);
                        $rname = $splits[0] . $name . ($splits[1] ? ('.' . $splits[1]) : '');
                    }
                    $rname = $volume->uniqueName($volume->realpath($rm['phash']), $rname, '', true);
                }
                if ($file = $volume->rename($rm['hash'], $rname)) {
                    $files[] = $file;
                    $removed[] = $rm;
                } else {
                    $errs[] = $rm['name'];
                }
            }

            if (!$files) {
                $res['error'] = $this->error(CManager_File_Connector_ElFinder_Base::ERROR_RENAME, join(', ', $errs), $volume->error());
                if (!$res['warning']) {
                    unset($res['warning']);
                }
                return $res;
            }
            if ($errs) {
                array_push($res['warning'], CManager_File_Connector_ElFinder_Base::ERROR_RENAME, join(', ', $errs), $volume->error());
            }
            if (!$res['warning']) {
                unset($res['warning']);
            }
            $res['added'] = $files;
            $res['removed'] = $removed;
            return $res;
        } else {
            if (!($rm = $volume->file($target))) {
                return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_RENAME, '#' . $target, CManager_File_Connector_ElFinder_Base::ERROR_FILE_NOT_FOUND)];
            }
            if ($this->itemLocked($target)) {
                return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_LOCKED, $rm['name'])];
            }
            $rm['realpath'] = $volume->realpath($target);

            return ($file = $volume->rename($target, $name)) == false ? ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_RENAME, $rm['name'], $volume->error())] : ['added' => [$file], 'removed' => [$rm]];
        }
    }

    /**
     * Duplicate file - create copy with "copy %d" suffix
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * @throws CManager_File_Connector_ElFinder_Exception_AbortException
     */
    protected function duplicate($args) {
        $targets = is_array($args['targets']) ? $args['targets'] : [];
        $result = [];
        $suffix = empty($args['suffix']) ? 'copy' : $args['suffix'];

        $this->itemLock($targets);

        foreach ($targets as $target) {
            static::checkAborted();

            if (($volume = $this->volume($target)) == false || ($src = $volume->file($target)) == false) {
                $result['warning'] = $this->error(CManager_File_Connector_ElFinder_Base::ERROR_COPY, '#' . $target, CManager_File_Connector_ElFinder_Base::ERROR_FILE_NOT_FOUND);
                break;
            }

            if (($file = $volume->duplicate($target, $suffix)) == false) {
                $result['warning'] = $this->error($volume->error());
                break;
            }
        }

        return $result;
    }

    /**
     * Remove dirs/files
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * @throws CManager_File_Connector_ElFinder_Exception_AbortException
     */
    protected function rm($args) {
        $targets = is_array($args['targets']) ? $args['targets'] : [];
        $result = ['removed' => []];

        foreach ($targets as $target) {
            static::checkAborted();

            if (($volume = $this->volume($target)) == false) {
                $result['warning'] = $this->error(CManager_File_Connector_ElFinder_Base::ERROR_RM, '#' . $target, CManager_File_Connector_ElFinder_Base::ERROR_FILE_NOT_FOUND);
                break;
            }

            if ($this->itemLocked($target)) {
                $rm = $volume->file($target);
                $result['warning'] = $this->error(CManager_File_Connector_ElFinder_Base::ERROR_LOCKED, $rm['name']);
                break;
            }

            if (!$volume->rm($target)) {
                $result['warning'] = $this->error($volume->error());
                break;
            }
        }

        return $result;
    }

    /**
     * Return has subdirs
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * */
    protected function subdirs($args) {
        $result = ['subdirs' => []];
        $targets = $args['targets'];

        foreach ($targets as $target) {
            if (($volume = $this->volume($target)) !== false) {
                $result['subdirs'][$target] = $volume->subdirs($target) ? 1 : 0;
            }
        }
        return $result;
    }

    /**
     * Required to output file in browser when volume URL is not set
     * Return array contains opened file pointer, root itself and required headers
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * @throws elFinderAbortException
     */
    protected function file($args) {
        $target = $args['target'];
        $download = !empty($args['download']);
        $onetime = !empty($args['onetime']);
        //$h304     = 'HTTP/1.1 304 Not Modified';
        //$h403     = 'HTTP/1.0 403 Access Denied';
        $h404 = 'HTTP/1.0 404 Not Found';
        $a404 = ['error' => 'File not found', 'header' => $h404, 'raw' => true];

        if ($onetime) {
            $volume = null;
            $tmpdir = static::$commonTempPath;
            if (!$tmpdir || !is_file($tmpf = $tmpdir . DIRECTORY_SEPARATOR . 'ELF' . $target)) {
                return $a404;
            }
            $GLOBALS['elFinderTempFiles'][$tmpf] = true;
            if ($file = json_decode(file_get_contents($tmpf), true)) {
                $src = base64_decode($file['file']);
                if (!is_file($src) || !($fp = fopen($src, 'rb'))) {
                    return $a404;
                }
                if (strpos($src, $tmpdir) === 0) {
                    $GLOBALS['elFinderTempFiles'][$src] = true;
                }
                unset($file['file']);
                $file['read'] = true;
                $file['size'] = filesize($src);
            } else {
                return $a404;
            }
        } else {
            if (($volume = $this->volume($target)) == false) {
                return $a404;
            }

            if (($file = $volume->file($target)) == false) {
                return $a404;
            }

            if (!$file['read']) {
                return $a404;
            }

            if (($fp = $volume->open($target)) == false) {
                return $a404;
            }
        }

        // check aborted by user
        static::checkAborted();

        // allow change MIME type by 'file.pre' callback functions
        $mime = isset($args['mime']) ? $args['mime'] : $file['mime'];
        if ($download || $onetime) {
            $disp = 'attachment';
        } else {
            $dispInlineRegex = $volume->getOption('dispInlineRegex');
            $inlineRegex = false;
            if ($dispInlineRegex) {
                $inlineRegex = '#' . str_replace('#', '\\#', $dispInlineRegex) . '#';
                try {
                    preg_match($inlineRegex, '');
                } catch (Exception $e) {
                    $inlineRegex = false;
                }
            }
            if (!$inlineRegex) {
                $inlineRegex = '#^(?:(?:image|text)|application/x-shockwave-flash$)#';
            }
            $disp = preg_match($inlineRegex, $mime) ? 'inline' : 'attachment';
        }

        $filenameEncoded = rawurlencode($file['name']);
        if (strpos($filenameEncoded, '%') === false) { // ASCII only
            $filename = 'filename="' . $file['name'] . '"';
        } else {
            $ua = $_SERVER['HTTP_USER_AGENT'];
            if (preg_match('/MSIE [4-8]/', $ua)) { // IE < 9 do not support RFC 6266 (RFC 2231/RFC 5987)
                $filename = 'filename="' . $filenameEncoded . '"';
            } elseif (strpos($ua, 'Chrome') === false && strpos($ua, 'Safari') !== false && preg_match('#Version/[3-5]#', $ua)) { // Safari < 6
                $filename = 'filename="' . str_replace('"', '', $file['name']) . '"';
            } else { // RFC 6266 (RFC 2231/RFC 5987)
                $filename = 'filename*=UTF-8\'\'' . $filenameEncoded;
            }
        }

        if ($args['cpath'] && $args['reqid']) {
            setcookie('elfdl' . $args['reqid'], '1', 0, $args['cpath']);
        }

        $result = [
            'volume' => $volume,
            'pointer' => $fp,
            'info' => $file,
            'header' => [
                'Content-Type: ' . $mime,
                'Content-Disposition: ' . $disp . '; ' . $filename,
                'Content-Transfer-Encoding: binary',
                'Content-Length: ' . $file['size'],
                'Last-Modified: ' . gmdate('D, d M Y H:i:s T', $file['ts']),
                'Connection: close'
            ]
        ];

        if (!$onetime) {
            // add cache control headers
            if ($cacheHeaders = $volume->getOption('cacheHeaders')) {
                $result['header'] = array_merge($result['header'], $cacheHeaders);
            }

            // check 'xsendfile'
            $xsendfile = $volume->getOption('xsendfile');
            $path = null;
            if ($xsendfile) {
                $info = stream_get_meta_data($fp);
                if ($path = empty($info['uri']) ? null : $info['uri']) {
                    $basePath = rtrim($volume->getOption('xsendfilePath'), DIRECTORY_SEPARATOR);
                    if ($basePath) {
                        $root = rtrim($volume->getRootPath(), DIRECTORY_SEPARATOR);
                        if (strpos($path, $root) === 0) {
                            $path = $basePath . substr($path, strlen($root));
                        } else {
                            $path = null;
                        }
                    }
                }
            }
            if ($path) {
                $result['header'][] = $xsendfile . ': ' . $path;
                $result['info']['xsendfile'] = $xsendfile;
            }
        }

        // add "Content-Location" if file has url data
        if (isset($file['url']) && $file['url'] && $file['url'] != 1) {
            $result['header'][] = 'Content-Location: ' . $file['url'];
        }
        return $result;
    }

    /**
     * Count total files size
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * @throws elFinderAbortException
     */
    protected function size($args) {
        $size = 0;
        $files = 0;
        $dirs = 0;
        $itemCount = true;
        $sizes = [];

        foreach ($args['targets'] as $target) {
            static::checkAborted();
            if (($volume = $this->volume($target)) == false || ($file = $volume->file($target)) == false || !$file['read']) {
                return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_OPEN, '#' . $target)];
            }

            $volRes = $volume->size($target);
            if (is_array($volRes)) {
                $sizeInfo = ['size' => 0, 'fileCnt' => 0, 'dirCnt' => 0];
                if (!empty($volRes['size'])) {
                    $sizeInfo['size'] = $volRes['size'];
                    $size += $volRes['size'];
                }
                if (!empty($volRes['files'])) {
                    $sizeInfo['fileCnt'] = $volRes['files'];
                }
                if (!empty($volRes['dirs'])) {
                    $sizeInfo['dirCnt'] = $volRes['dirs'];
                }
                if ($itemCount) {
                    $files += $sizeInfo['fileCnt'];
                    $dirs += $sizeInfo['dirCnt'];
                }
                $sizes[$target] = $sizeInfo;
            } elseif (is_numeric($volRes)) {
                $size += $volRes;
                $files = $dirs = 'unknown';
                $itemCount = false;
            }
        }
        return ['size' => $size, 'fileCnt' => $files, 'dirCnt' => $dirs, 'sizes' => $sizes];
    }

    /**
     * "Open" directory
     * Return array with following elements
     *  - cwd          - opened dir info
     *  - files        - opened dir content [and dirs tree if $args[tree]]
     *  - api          - api version (if $args[init])
     *  - uplMaxSize   - if $args[init]
     *  - error        - on failed
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * @throws elFinderAbortException
     */
    protected function open($args) {
        $target = $args['target'];
        $init = !empty($args['init']);
        $tree = !empty($args['tree']);
        $volume = $this->volume($target);
        $cwd = $volume ? $volume->dir($target) : false;
        $hash = $init ? 'default folder' : '#' . $target;
        $compare = '';

        // on init request we can get invalid dir hash -
        // dir which can not be opened now, but remembered by client,
        // so open default dir
        if ((!$cwd || !$cwd['read']) && $init) {
            $volume = $this->default;
            $target = $volume->defaultPath();
            $cwd = $volume->dir($target);
        }

        if (!$cwd) {
            return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_OPEN, $hash, CManager_File_Connector_ElFinder_Base::ERROR_DIR_NOT_FOUND)];
        }
        if (!$cwd['read']) {
            return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_OPEN, $hash, CManager_File_Connector_ElFinder_Base::ERROR_PERM_DENIED)];
        }

        $files = [];

        // get current working directory files list
        if (($ls = $volume->scandir($cwd['hash'])) === false) {
            return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_OPEN, $cwd['name'], $volume->error())];
        }

        if (isset($cwd['dirs']) && $cwd['dirs'] != 1) {
            $cwd = $volume->dir($target);
        }

        // get other volume root
        if ($tree) {
            foreach ($this->volumes as $id => $v) {
                $files[] = $v->file($v->root());
            }
        }

        // long polling mode
        if ($args['compare']) {
            $sleep = max(1, (int) $volume->getOption('lsPlSleep'));
            $standby = (int) $volume->getOption('plStandby');
            if ($standby > 0 && $sleep > $standby) {
                $standby = $sleep;
            }
            $limit = max(0, floor($standby / $sleep)) + 1;
            do {
                static::extendTimeLimit(30 + $sleep);
                $_mtime = 0;
                foreach ($ls as $_f) {
                    $_mtime = max($_mtime, $_f['ts']);
                }
                $compare = strval(count($ls)) . ':' . strval($_mtime);
                if ($compare !== $args['compare']) {
                    break;
                }
                if (--$limit) {
                    sleep($sleep);
                    $volume->clearstatcache();
                    if (($ls = $volume->scandir($cwd['hash'])) === false) {
                        break;
                    }
                }
            } while ($limit);
            if ($ls === false) {
                return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_OPEN, $cwd['name'], $volume->error())];
            }
        }

        if ($ls) {
            if ($files) {
                $files = array_merge($files, $ls);
            } else {
                $files = $ls;
            }
        }

        $result = [
            'cwd' => $cwd,
            'options' => $volume->options($cwd['hash']),
            'files' => $files
        ];

        if ($compare) {
            $result['cwd']['compare'] = $compare;
        }

        if (!empty($args['init'])) {
            $result['api'] = sprintf('%.1F%03d', self::$ApiVersion, self::$ApiRevision);
            $result['uplMaxSize'] = ini_get('upload_max_filesize');
            $result['uplMaxFile'] = ini_get('max_file_uploads');
            $result['netDrivers'] = array_keys(self::$netDrivers);
            $result['maxTargets'] = $this->maxTargets;
            if ($volume) {
                $result['cwd']['root'] = $volume->root();
            }
            if (static::$textMimes) {
                $result['textMimes'] = static::$textMimes;
            }
        }

        return $result;
    }

    /**
     * Return dir files names list
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * */
    protected function ls($args) {
        $target = $args['target'];
        $intersect = isset($args['intersect']) ? $args['intersect'] : [];

        if (($volume = $this->volume($target)) == false || ($list = $volume->ls($target, $intersect)) === false) {
            return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_OPEN, '#' . $target)];
        }
        return ['list' => $list];
    }

    /**
     * Return subdirs for required directory
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * */
    protected function tree($args) {
        $target = $args['target'];

        if (($volume = $this->volume($target)) == false || ($tree = $volume->tree($target)) == false) {
            return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_OPEN, '#' . $target)];
        }

        return ['tree' => $tree];
    }

    /**
     * Return parents dir for required directory
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * @throws elFinderAbortException
     */
    protected function parents($args) {
        $target = $args['target'];
        $until = $args['until'];

        if (($volume = $this->volume($target)) == false || ($tree = $volume->parents($target, false, $until)) == false) {
            return ['error' => $this->error(CManager_File_Connector_ElFinder_Base::ERROR_OPEN, '#' . $target)];
        }

        return ['tree' => $tree];
    }

    /**
     * Return new created thumbnails list
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * @throws ImagickException
     * @throws elFinderAbortException
     */
    protected function tmb($args) {
        $result = ['images' => []];
        $targets = $args['targets'];

        foreach ($targets as $target) {
            static::checkAborted();

            if (($volume = $this->volume($target)) != false && (($tmb = $volume->tmb($target)) != false)) {
                $result['images'][$target] = $tmb;
            }
        }
        return $result;
    }

    /**
     * Download files/folders as an archive file
     * 1st: Return srrsy contains download archive file info
     * 2nd: Return array contains opened file pointer, root itself and required headers
     *
     * @param array $args command arguments
     *
     * @return array
     *
     * @throws Exception
     */
    protected function zipdl($args) {
        $targets = $args['targets'];
        $download = !empty($args['download']);
        $h404 = 'HTTP/1.x 404 Not Found';

        if (!$download) {
            //1st: Return array contains download archive file info
            $error = [CManager_File_Connector_ElFinder_Base::ERROR_ARCHIVE];
            if (($volume = $this->volume($targets[0])) !== false) {
                if ($dlres = $volume->zipdl($targets)) {
                    $path = $dlres['path'];
                    register_shutdown_function(['elFinder', 'rmFileInDisconnected'], $path);
                    if (count($targets) === 1) {
                        $name = basename($volume->path($targets[0]));
                    } else {
                        $name = $dlres['prefix'] . '_Files';
                    }
                    $name .= '.' . $dlres['ext'];
                    $uniqid = uniqid();
                    $this->session->set('zipdl' . $uniqid, basename($path));
                    $result = [
                        'zipdl' => [
                            'file' => $uniqid,
                            'name' => $name,
                            'mime' => $dlres['mime']
                        ]
                    ];
                    return $result;
                }
                $error = array_merge($error, $volume->error());
            }
            return ['error' => $error];
        } else {
            // 2nd: Return array contains opened file session key, root itself and required headers
            if (count($targets) !== 4 || ($volume = $this->volume($targets[0])) == false || !($file = $this->session->get('zipdl' . $targets[1]))) {
                return ['error' => 'File not found', 'header' => $h404, 'raw' => true];
            }
            $this->session->remove('zipdl' . $targets[1]);
            if ($volume->commandDisabled('zipdl')) {
                return ['error' => 'File not found', 'header' => $h404, 'raw' => true];
            }
            $path = $volume->getTempPath() . DIRECTORY_SEPARATOR . basename($file);
            if (!is_readable($path) || !is_writable($path)) {
                return ['error' => 'File not found', 'header' => $h404, 'raw' => true];
            }
            // register auto delete on shutdown
            $GLOBALS['elFinderTempFiles'][$path] = true;
            // for HTTP headers
            $name = $targets[2];
            $mime = $targets[3];

            $filenameEncoded = rawurlencode($name);
            if (strpos($filenameEncoded, '%') === false) { // ASCII only
                $filename = 'filename="' . $name . '"';
            } else {
                $ua = $_SERVER['HTTP_USER_AGENT'];
                if (preg_match('/MSIE [4-8]/', $ua)) { // IE < 9 do not support RFC 6266 (RFC 2231/RFC 5987)
                    $filename = 'filename="' . $filenameEncoded . '"';
                } elseif (strpos($ua, 'Chrome') === false && strpos($ua, 'Safari') !== false && preg_match('#Version/[3-5]#', $ua)) { // Safari < 6
                    $filename = 'filename="' . str_replace('"', '', $name) . '"';
                } else { // RFC 6266 (RFC 2231/RFC 5987)
                    $filename = 'filename*=UTF-8\'\'' . $filenameEncoded;
                }
            }

            $fp = fopen($path, 'rb');
            $file = fstat($fp);
            $result = [
                'pointer' => $fp,
                'header' => [
                    'Content-Type: ' . $mime,
                    'Content-Disposition: attachment; ' . $filename,
                    'Content-Transfer-Encoding: binary',
                    'Content-Length: ' . $file['size'],
                    'Accept-Ranges: none',
                    'Connection: close'
                ]
            ];
            return $result;
        }
    }
}

<?php
use Symfony\Component\Process\Process;

abstract class CException_ContextAbstract {
    /**
     * Konteks git yang dicatat bersama laporan exception. Hash dan remote dibaca langsung dari `.git/`,
     * sisanya (message, tag, isDirty) lewat biner git dengan timeout dan di-cache per `exception.git.cacheSeconds`;
     * `isDirty` (`git status`) hanya dihitung bila `exception.git.dirty` menyala (default: di luar production).
     *
     * @return array
     */
    public function getGit() {
        if (!CF::config('exception.git.enabled', true)) {
            return [];
        }
        $baseDir = $this->getGitBaseDirectory();

        if (!$baseDir) {
            return [];
        }

        try {
            $cached = $this->readGitCache($baseDir);
            if ($cached !== null) {
                return $cached;
            }
            $git = [
                'hash' => $this->getGitHash($baseDir),
                'message' => $this->getGitMessage($baseDir),
                'tag' => $this->getGitTag($baseDir),
                'remote' => $this->getGitRemote($baseDir),
                'isDirty' => $this->shouldCheckDirty() ? !$this->getGitIsClean($baseDir) : null,
            ];
            $this->writeGitCache($baseDir, $git);

            return $git;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return bool
     */
    protected function shouldCheckDirty() {
        $dirty = CF::config('exception.git.dirty');

        return $dirty === null ? !CF::isProduction() : (bool) $dirty;
    }

    /**
     * Get the latest git commit hash in the repository.
     *
     * @param string $baseDir The base directory of the git repository.
     *
     * @return string|null The latest git commit hash, or null if not found.
     */
    protected function getGitHash($baseDir) {
        $hash = CManager_Asset_Helper::gitRevision(rtrim($baseDir, DS) . DS);
        if ($hash !== null) {
            return $hash;
        }

        return $this->command("git log --pretty=format:'%H' -n 1", $baseDir);
    }

    /**
     * Get the latest git commit message in the repository.
     *
     * @param string $baseDir The base directory of the git repository.
     *
     * @return string|null The latest git commit message, or null if not found.
     */
    protected function getGitMessage($baseDir) {
        return $this->command("git log --pretty=format:'%s' -n 1", $baseDir);
    }

    /**
     * Get the latest git tag in the repository.
     *
     * @param string $baseDir The base directory of the git repository.
     *
     * @return string|null The latest git tag, or null if no tags are found.
     */
    protected function getGitTag($baseDir) {
        return $this->command('git describe --tags --abbrev=0', $baseDir);
    }

    /**
     * Get the remote URL of the git repository (dibaca dari .git/config, tanpa proses git).
     *
     * @param string $baseDir The base directory of the git repository.
     *
     * @return string|null The remote URL, or null if not found.
     */
    protected function getGitRemote($baseDir) {
        $config = @file_get_contents($this->getGitDir($baseDir) . 'config');
        if ($config !== false && preg_match('/\[remote "origin"\][^\[]*?\burl\s*=\s*(\S+)/s', $config, $match)) {
            return $match[1];
        }

        return $this->command('git config --get remote.origin.url', $baseDir);
    }

    /**
     * Check if the git repository is clean (no uncommitted changes).
     *
     * @param string $baseDir The base directory of the git repository.
     *
     * @return bool True if the repository is clean, false otherwise.
     */
    protected function getGitIsClean($baseDir) {
        return empty($this->command('git status -s', $baseDir));
    }

    /**
     * Akar repo git: naik dari DOCROOT (lalu cwd) sampai menemukan `.git`, tanpa memanggil biner git.
     *
     * @return null|string
     */
    protected function getGitBaseDirectory() {
        foreach (array_unique([rtrim(DOCROOT, DS), (string) getcwd()]) as $start) {
            $dir = $start;
            while ($dir !== '' && $dir !== DS && $dir !== dirname($dir)) {
                if (file_exists($dir . DS . '.git')) {
                    return $dir;
                }
                $dir = dirname($dir);
            }
        }

        return null;
    }

    /**
     * Folder `.git/` sebuah repo (mengikuti `gitdir:` pada worktree/submodule), selalu berakhiran DS.
     *
     * @param string $baseDir
     *
     * @return string
     */
    protected function getGitDir($baseDir) {
        $gitPath = rtrim($baseDir, DS) . DS . '.git';
        if (is_file($gitPath)) {
            $pointer = trim((string) @file_get_contents($gitPath));
            if (strpos($pointer, 'gitdir:') === 0) {
                $target = trim(substr($pointer, 7));
                $gitPath = $target[0] === DS ? $target : rtrim($baseDir, DS) . DS . $target;
            }
        }

        return rtrim($gitPath, DS) . DS;
    }

    /**
     * @param string $baseDir
     *
     * @return string
     */
    protected function getGitCachePath($baseDir) {
        return CTemporary::getDirectory() . 'exception-git-' . md5($baseDir) . '.json';
    }

    /**
     * Konteks git yang tersimpan, bila masih segar dan HEAD belum berubah.
     *
     * @param string $baseDir
     *
     * @return null|array
     */
    protected function readGitCache($baseDir) {
        $seconds = (int) CF::config('exception.git.cacheSeconds', 60);
        if ($seconds <= 0) {
            return null;
        }
        $raw = @file_get_contents($this->getGitCachePath($baseDir));
        $cache = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($cache) || !isset($cache['at'], $cache['head'], $cache['git'])) {
            return null;
        }
        if (time() - (int) $cache['at'] > $seconds || $cache['head'] !== $this->getGitHeadStamp($baseDir)) {
            return null;
        }

        return $cache['git'];
    }

    /**
     * @param string $baseDir
     * @param array  $git
     *
     * @return void
     */
    protected function writeGitCache($baseDir, array $git) {
        if ((int) CF::config('exception.git.cacheSeconds', 60) <= 0) {
            return;
        }
        @file_put_contents($this->getGitCachePath($baseDir), json_encode(['at' => time(), 'head' => $this->getGitHeadStamp($baseDir), 'git' => $git]), LOCK_EX);
    }

    /**
     * Penanda perubahan HEAD/index: mtime `.git/HEAD` dan `.git/index` (deploy atau edit lokal membatalkan cache).
     *
     * @param string $baseDir
     *
     * @return string
     */
    protected function getGitHeadStamp($baseDir) {
        $gitDir = $this->getGitDir($baseDir);

        return (string) @filemtime($gitDir . 'HEAD') . '-' . (string) @filemtime($gitDir . 'index');
    }

    /**
     * Execute a shell command in the given base directory and return the output.
     *
     * @param string $command The shell command to execute.
     * @param string $baseDir The base directory to execute the command in.
     *
     * @return string The output of the command (kosong bila gagal atau melewati `exception.git.timeout` detik).
     */
    protected function command($command, $baseDir) {
        try {
            $process = Process::fromShellCommandline($command, $baseDir);
            $process->setTimeout((float) CF::config('exception.git.timeout', 3));
            $process->run();

            return trim($process->getOutput());
        } catch (Throwable $e) {
            return '';
        }
    }

    protected function getAppData() {
        $daemonClass = null;
        $isDaemon = CDaemon::isDaemon();
        $daemonService = CDaemon::getRunningService();
        if ($daemonService != null && is_object($daemonService)) {
            $daemonClass = get_class($daemonService);
        }
        $queueRunner = CQueue::runner();
        $isQueue = false;
        $queueJobName = null;
        if ($queueRunner != null) {
            $isQueue = true;
            $queueJobName = $queueRunner->getCurrentJobName();
        }

        return [
            'isCli' => CF::isCli(),
            'isCFCli' => CF::isCFCli(),
            'sharedAppCode' => CF::getSharedApp(),
            'locale' => CF::getLocale(),
            'domain' => CF::domain(),
            'appCode' => CF::appCode(),
            'orgCode' => CF::orgCode(),
            'theme' => c::theme()->getCurrentTheme(),
            'nav' => c::app()->getNavName(),
            'isDaemon' => $isDaemon,
            'daemonClass' => $daemonClass,
            'isQueue' => $isQueue,
            'queueJobName' => $queueJobName,
        ];
    }

    protected function getDebugData() {
        $variables = CDebug::getVariables();
        //serialize all variables
        return c::collect($variables)->map(function ($item) {
            if ($item instanceof Closure) {
                $item = new CFunction_SerializableClosure($item);
            }

            return serialize($item);
        })->toArray();
    }
}

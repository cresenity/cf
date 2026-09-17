<?php

class CDebug_Collector_Exception extends CDebug_CollectorAbstract {
    /**
     * @param Throwable $exception
     *
     * @return bool
     */
    protected function shouldCollect($exception) {
        return $exception instanceof Throwable && (!$exception instanceof CDebug_Contract_ShouldNotCollectException);
    }

    /**
     * A failure gathering context (app/org/request state a CLI/daemon/queue-worker
     * process may not have) must never cost the original exception its record - a
     * degraded write beats a silently dropped one.
     *
     * @param Throwable $exception
     *
     * @return array
     */
    public function collect(Throwable $exception) {
        if (!CF::config('collector.exception')) {
            return null;
        }
        $data = null;
        if ($this->shouldCollect($exception)) {
            try {
                $data = $this->getDataFromException($exception);
                $pushUrl = CF::config('collector.exceptionPush');
                $pushed = $pushUrl ? $this->pushToDevcloud($data, $pushUrl) : false;
                if (!$pushed) {
                    $this->put($data);
                }
            } catch (Throwable $collectException) {
                $this->logCollectFailure($collectException, $exception);
            }
        }

        return $data;
    }

    /**
     * Push langsung ke devcloud's v1/exceptions - jalur utama, file+SSH-pull di put()
     * jadi cadangan yang hanya jalan kalau push ini gagal (lihat DCron_Cron_Collector_Exception
     * di devcloud). Fire-and-forget SENGAJA: request yang sedang menangani exception ini
     * tidak boleh menunggu balasan devcloud sama sekali, bahkan saat devcloud lambat/down -
     * jadi ini cuma membuka koneksi TCP dengan connect timeout pendek, menulis requestnya,
     * lalu menutup TANPA membaca respons.
     *
     * Konsekuensi dari fire-and-forget: return true di sini berarti "berhasil DIKIRIM",
     * bukan "berhasil DIPROSES" - devcloud yang menerima koneksi tapi lalu menolak/gagal
     * memprosesnya (key salah, 500, dll) tidak pernah diketahui di sini, dan exception itu
     * tidak jatuh ke fallback file. Ini trade-off sadar, bukan bug: menunggu respons untuk
     * memastikannya akan memberi setiap request yang crash tambahan latency saat devcloud
     * bermasalah, persis yang ingin dihindari fire-and-forget.
     *
     * @param array  $data
     * @param string $url  collector.exceptionPush (system/config/collector.php) - kosong/false
     *                     di sana berarti collect() tidak pernah memanggil method ini sama sekali
     *
     * @return bool true kalau permintaannya berhasil dikirim (bukan berhasil diproses devcloud)
     */
    protected function pushToDevcloud($data, $url) {
        $key = CF::config('devcloud.phpExceptionCollector.key');
        if (!$key) {
            return false;
        }

        $parts = parse_url($url);
        $host = carr::get($parts, 'host');
        if (!$host) {
            return false;
        }
        $scheme = carr::get($parts, 'scheme', 'https');
        $port = carr::get($parts, 'port', $scheme === 'https' ? 443 : 80);
        $path = carr::get($parts, 'path') ?: '/';
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $body = json_encode($data);
        if ($body === false) {
            return false;
        }

        $remote = ($scheme === 'https' ? 'ssl://' : '') . $host;
        $fp = @fsockopen($remote, $port, $errno, $errstr, 0.3);
        if ($fp === false) {
            return false;
        }

        $request = "POST {$path} HTTP/1.1\r\n"
            . "Host: {$host}\r\n"
            . "Authorization: Bearer {$key}\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n"
            . "Connection: Close\r\n\r\n"
            . $body;

        $sent = @fwrite($fp, $request);
        fclose($fp);

        return $sent !== false;
    }

    /**
     * Get data from exception object.
     *
     * The core fields never depend on request/app/org state and are always
     * present; the rest is gathered per section so a piece of context missing
     * under CLI/daemon doesn't cost the sections that are still available.
     *
     * @param Throwable $exception
     *
     * @return array
     */
    public function getDataFromException($exception) {
        $data = [
            'datetime' => date('Y-m-d H:i:s'),
            'error' => get_class($exception),
            'message' => $exception->getMessage(),
            'uuid' => cstr::uuid(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => json_encode($exception->getTrace()),
            'CFVersion' => CF::version(),
        ];

        return array_merge($data, $this->safeAppContext(), $this->safeRequestContext(), $this->safeReport($exception));
    }

    /**
     * @return array
     */
    protected function safeAppContext() {
        try {
            $app = CApp::instance();

            return [
                'appId' => $app->appId(),
                'appCode' => $app->code(),
                'user' => c::base()->username(),
                'role' => c::base()->roleName(),
                'orgId' => c::base()->orgId(),
                'orgCode' => c::base()->orgCode(),
                'domain' => CF::domain(),
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array
     */
    protected function safeRequestContext() {
        try {
            $route = c::request()->route();
            $routeData = $route && $route->getRouteData() ? $route->getRouteData()->toArray() : [];
            $controller = c::optional($route)->controller;
            $browser = new CBrowser();

            return [
                'controller' => $controller ? get_class($controller) : null,
                'method' => carr::get($routeData, 'method'),
                'browser' => $browser->getBrowser(),
                'browserVersion' => $browser->getVersion(),
                'platform' => $browser->getPlatform(),
                'userAgent' => carr::get($_SERVER, 'HTTP_USER_AGENT'),
                'httpReferer' => carr::get($_SERVER, 'HTTP_REFERER'),
                'remoteAddress' => CApp_Base::remoteAddress(),
                'fullUrl' => curl::current(),
                'protocol' => CApp_Base::protocol(),
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param Throwable $exception
     *
     * @return array
     */
    protected function safeReport($exception) {
        try {
            return CException::manager()->createReport($exception)->toArray();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Last-resort visibility for a collect() failure - must never re-throw, only be seen.
     *
     * @param Throwable $collectException
     * @param Throwable $original
     *
     * @return void
     */
    protected function logCollectFailure($collectException, $original) {
        $message = sprintf(
            'CDebug_Collector_Exception gagal mengumpulkan "%s: %s" - %s',
            get_class($original),
            $original->getMessage(),
            $collectException->getMessage()
        );
        if (CDaemon::isDaemon()) {
            CDaemon::log($message);
        } else {
            error_log($message);
        }
    }

    public function getType() {
        return CDebug::COLLECTOR_TYPE_EXCEPTION;
    }
}

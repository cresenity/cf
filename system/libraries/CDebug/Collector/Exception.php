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
                $this->put($data);
            } catch (Throwable $collectException) {
                $this->logCollectFailure($collectException, $exception);
            }
        }

        return $data;
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

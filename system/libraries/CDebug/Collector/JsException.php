<?php

/**
 * Browser-side counterpart of CDebug_Collector_Exception - same sink (writes into the SAME
 * `exception` collector type/file, so devcloud's existing harvester needs zero changes), same
 * payload shape, just sourced from a JS error report POSTed by cres.js instead of a PHP
 * Throwable. See system/controllers/cresenity.php's jsError() for the receiving endpoint and
 * media/js/cres/src/module/jsErrorCollector.js for the browser-side reporter.
 *
 * `language`/`language_version` on collector_exception_detail exist in devcloud's harvester
 * (DCron_Method_Collector_Exception.php) but are never set by CDebug_Collector_Exception - reused
 * here (`javascript`) as the signal that distinguishes a JS-origin row from a PHP one, so no new
 * column/devcloud change is needed to tell them apart.
 */
class CDebug_Collector_JsException extends CDebug_CollectorAbstract {
    /**
     * @var int
     */
    const MAX_MESSAGE_LENGTH = 2000;

    /**
     * @var int
     */
    const MAX_STACK_LENGTH = 8000;

    /**
     * @var int
     */
    const MAX_URL_LENGTH = 2000;

    /**
     * A JS error payload is untrusted input from the browser (unlike a PHP Throwable the server
     * itself constructs) - always bounded/truncated, never allowed to fail loudly back to a page
     * that is already in an error state.
     *
     * @param array $payload message, stack, filename, lineno, colno, name, url, type
     *
     * @return null|array
     */
    public function collect(array $payload) {
        if (!CF::config('collector.js_exception')) {
            return null;
        }

        $message = trim((string) carr::get($payload, 'message'));
        if ($message === '') {
            return null;
        }

        $data = null;

        try {
            $data = $this->getDataFromPayload($payload, $message);
            $this->put($data);
        } catch (Throwable $collectException) {
            $this->logCollectFailure($collectException);
        }

        return $data;
    }

    /**
     * @param array  $payload
     * @param string $message already trimmed/validated non-empty
     *
     * @return array
     */
    protected function getDataFromPayload($payload, $message) {
        $name = trim((string) carr::get($payload, 'name'));

        $data = [
            'datetime' => date('Y-m-d H:i:s'),
            'error' => 'JS: ' . ($name !== '' ? $name : 'Error'),
            'message' => cstr::substr($message, 0, static::MAX_MESSAGE_LENGTH),
            'uuid' => cstr::uuid(),
            'file' => cstr::substr((string) carr::get($payload, 'filename'), 0, 500),
            'line' => (int) carr::get($payload, 'lineno'),
            'stacktrace' => $this->normalizeStack(carr::get($payload, 'stack')),
            'CFVersion' => CF::version(),
            'language' => 'javascript',
        ];

        return array_merge($data, $this->safeAppContext(), $this->safeRequestContext($payload));
    }

    /**
     * A raw JS stack is one long string ("Error: x\n at foo (bar.js:1:2)\n ...") - split into
     * lines so it lands in `collector_exception.stacktrace` (cast `array` on the model) the same
     * shape a PHP trace would, instead of one unreadable blob.
     *
     * @param mixed $stack
     *
     * @return array
     */
    protected function normalizeStack($stack) {
        $stack = cstr::substr((string) $stack, 0, static::MAX_STACK_LENGTH);
        if (trim($stack) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $stack)), function ($line) {
            return $line !== '';
        }));
    }

    /**
     * Same app/org/user context a PHP exception gets - correct here too, since this method runs
     * inside the very request the browser POSTed the report to (this app, this session).
     *
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
     * Browser/platform/UA come from the real request that just POSTed the report (same value
     * CBrowser would parse for any PHP exception on this same request) - only `fullUrl` is
     * trusted from the client payload instead, since a HashRouter SPA's meaningful route lives
     * after `#`, which never reaches the server in `curl::current()`.
     *
     * @param array $payload
     *
     * @return array
     */
    protected function safeRequestContext($payload) {
        try {
            $browser = new CBrowser();
            $clientUrl = cstr::substr((string) carr::get($payload, 'url'), 0, static::MAX_URL_LENGTH);

            return [
                'controller' => 'JavaScript',
                'method' => null,
                'browser' => $browser->getBrowser(),
                'browserVersion' => $browser->getVersion(),
                'platform' => $browser->getPlatform(),
                'userAgent' => carr::get($_SERVER, 'HTTP_USER_AGENT'),
                'httpReferer' => carr::get($_SERVER, 'HTTP_REFERER'),
                'remoteAddress' => CApp_Base::remoteAddress(),
                'fullUrl' => $clientUrl !== '' ? $clientUrl : curl::current(),
                'protocol' => CApp_Base::protocol(),
                'context' => ['request' => ['url' => $clientUrl, 'method' => 'JS']],
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param Throwable $collectException
     *
     * @return void
     */
    protected function logCollectFailure($collectException) {
        $message = 'CDebug_Collector_JsException gagal mengumpulkan laporan - ' . $collectException->getMessage();
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

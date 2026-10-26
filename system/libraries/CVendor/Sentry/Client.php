<?php

/**
 * Klien minimal Sentry "Store Endpoint" (https://develop.sentry.dev/sdk/store/) lewat HTTP biasa -
 * system/vendor tidak punya SDK Sentry terpasang, dan endpoint ini cukup sederhana untuk dipanggil
 * langsung tanpa SDK resmi.
 */
class CVendor_Sentry_Client {
    /**
     * @var null|string
     */
    private $publicKey;

    /**
     * @var null|string
     */
    private $host;

    /**
     * @var null|string
     */
    private $projectId;

    /**
     * @var null|string jalur di depan /api/<projectId>/store/, untuk instalasi self-hosted di sub-path
     */
    private $pathPrefix;

    /**
     * @param string $dsn
     */
    public function __construct($dsn) {
        $parts = parse_url((string) $dsn);
        $this->publicKey = carr::get($parts, 'user');
        $host = carr::get($parts, 'host');
        $port = carr::get($parts, 'port');
        $this->host = $host ? $host . ($port ? ':' . $port : '') : null;
        $path = trim((string) carr::get($parts, 'path'), '/');
        $this->projectId = $path !== '' ? basename($path) : null;
        $prefix = $path !== '' ? trim(dirname($path), '.') : '';
        $this->pathPrefix = $prefix !== '' ? trim($prefix, '/') : null;
    }

    /**
     * @return bool
     */
    public function isValid() {
        return $this->publicKey && $this->host && $this->projectId;
    }

    /**
     * @return string
     */
    public function storeUrl() {
        return 'https://' . $this->host . '/' . ($this->pathPrefix ? $this->pathPrefix . '/' : '') . 'api/' . $this->projectId . '/store/';
    }

    /**
     * @return string
     */
    protected function authHeader() {
        return 'Sentry sentry_version=7, sentry_client=cf-collector/1.0, sentry_key=' . $this->publicKey;
    }

    /**
     * @param array $event payload event Sentry (event_id/message/exception/level/extra/...)
     *
     * @throws Exception DSN tidak valid
     *
     * @return CHTTP_Client_Response
     */
    public function send(array $event) {
        if (!$this->isValid()) {
            throw new Exception('DSN Sentry tidak valid atau tidak lengkap');
        }

        return CHTTP::client()
            ->connectTimeout(2)->timeout(3)
            ->withHeaders(['X-Sentry-Auth' => $this->authHeader()])
            ->post($this->storeUrl(), $event);
    }
}

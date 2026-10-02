<?php
class CMiddleware_TrustProxy {
    /**
     * The trusted proxies for the application.
     *
     * @var null|array|string
     */
    protected $proxies;

    /**
     * The proxy header mappings.
     *
     * @var int
     */
    protected $headers = CHTTP_Request::HEADER_X_FORWARDED_FOR | CHTTP_Request::HEADER_X_FORWARDED_HOST | CHTTP_Request::HEADER_X_FORWARDED_PORT | CHTTP_Request::HEADER_X_FORWARDED_PROTO | CHTTP_Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * Handle an incoming request.
     *
     * @param \CHTTP_Request $request
     * @param \Closure       $next
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *
     * @return mixed
     */
    public function handle(CHTTP_Request $request, $next) {
        $request::setTrustedProxies([], $this->getTrustedHeaderNames());

        $this->setTrustedProxyIpAddresses($request);

        return $next($request);
    }

    /**
     * Sets the trusted proxies on the request.
     *
     * @param \CHTTP_Request $request
     *
     * @return void
     */
    protected function setTrustedProxyIpAddresses(CHTTP_Request $request) {
        $trustedIps = $this->proxies();

        if ($trustedIps === '*' || $trustedIps === '**') {
            return $this->setTrustedProxyIpAddressesToTheCallingIp($request);
        }

        $trustedIps = is_string($trustedIps)
                ? array_map('trim', explode(',', $trustedIps))
                : $trustedIps;

        if (is_array($trustedIps)) {
            return $this->setTrustedProxyIpAddressesToSpecificIps($request, $trustedIps);
        }
    }

    /**
     * Specify the IP addresses to trust explicitly.
     *
     * @param \CHTTP_Request $request
     * @param array          $trustedIps
     *
     * @return void
     */
    protected function setTrustedProxyIpAddressesToSpecificIps(CHTTP_Request $request, array $trustedIps) {
        $request->setTrustedProxies($trustedIps, $this->getTrustedHeaderNames());
    }

    /**
     * Set the trusted proxy to be the IP address calling this servers.
     *
     * @param \CHTTP_Request $request
     *
     * @return void
     */
    protected function setTrustedProxyIpAddressesToTheCallingIp(CHTTP_Request $request) {
        $request->setTrustedProxies([$request->server->get('REMOTE_ADDR')], $this->getTrustedHeaderNames());
    }

    /**
     * Retrieve trusted header name(s), falling back to defaults if config not set.
     *
     * @return int a bit field of Request::HEADER_*, to set which headers to trust from your proxies
     */
    protected function getTrustedHeaderNames() {
        if (is_int($this->headers)) {
            return $this->headers;
        }
        switch ($this->headers) {
            case 'HEADER_X_FORWARDED_AWS_ELB':
            case CHTTP_Request::HEADER_X_FORWARDED_AWS_ELB:
                return CHTTP_Request::HEADER_X_FORWARDED_AWS_ELB;

            case 'HEADER_FORWARDED':
            case CHTTP_Request::HEADER_FORWARDED:
                return CHTTP_Request::HEADER_FORWARDED;

            case 'HEADER_X_FORWARDED_FOR':
            case CHTTP_Request::HEADER_X_FORWARDED_FOR:
                return CHTTP_Request::HEADER_X_FORWARDED_FOR;

            case 'HEADER_X_FORWARDED_HOST':
            case CHTTP_Request::HEADER_X_FORWARDED_HOST:
                return CHTTP_Request::HEADER_X_FORWARDED_HOST;

            case 'HEADER_X_FORWARDED_PORT':
            case CHTTP_Request::HEADER_X_FORWARDED_PORT:
                return CHTTP_Request::HEADER_X_FORWARDED_PORT;

            case 'HEADER_X_FORWARDED_PROTO':
            case CHTTP_Request::HEADER_X_FORWARDED_PROTO:
                return CHTTP_Request::HEADER_X_FORWARDED_PROTO;

            default:
                return CHTTP_Request::HEADER_X_FORWARDED_FOR | CHTTP_Request::HEADER_X_FORWARDED_HOST | CHTTP_Request::HEADER_X_FORWARDED_PORT | CHTTP_Request::HEADER_X_FORWARDED_PROTO | CHTTP_Request::HEADER_X_FORWARDED_AWS_ELB;
        }

        return $this->headers;
    }

    /**
     * Get the trusted proxies.
     *
     * @return null|array|string
     */
    protected function proxies() {
        return $this->proxies;
    }
}

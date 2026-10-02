<?php
/**
 * @see https://github.com/fideloper/TrustedProxy
 */
class CHTTP_Middleware_TrustProxies {
    /**
     * @var array
     */
    protected $config;

    /**
     * The trusted proxies for the application.
     *
     * @var null|string|array
     */
    protected $proxies;

    /**
     * The proxy header mappings.
     *
     * @var null|string|int
     */
    protected $headers;

    /**
     * Create a new trusted proxies middleware instance.
     */
    public function __construct() {
        $this->config = CF::config('http.trustedproxy', []);
    }

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
    public function handle(CHTTP_Request $request, Closure $next) {
        $request->setTrustedProxies([], $this->getTrustedHeaderNames()); // Reset trusted proxies between requests
        $this->setTrustedProxyIpAddresses($request);

        return $next($request);
    }

    /**
     * Sets the trusted proxies on the request to the value of trustedproxy.proxies.
     *
     * @param \CHTTP_Request $request
     */
    protected function setTrustedProxyIpAddresses(CHTTP_Request $request) {
        $trustedIps = $this->proxies();

        // Trust any IP address that calls us
        // `**` for backwards compatibility, but is deprecated
        if ($trustedIps === '*' || $trustedIps === '**') {
            return $this->setTrustedProxyIpAddressesToTheCallingIp($request);
        }

        // Support IPs addresses separated by comma
        $trustedIps = is_string($trustedIps) ? array_map('trim', explode(',', $trustedIps)) : $trustedIps;

        // Only trust specific IP addresses
        if (is_array($trustedIps)) {
            return $this->setTrustedProxyIpAddressesToSpecificIps($request, $trustedIps);
        }
    }

    /**
     * Specify the IP addresses to trust explicitly.
     *
     * @param \CHTTP_Request $request
     * @param array          $trustedIps
     */
    private function setTrustedProxyIpAddressesToSpecificIps(CHTTP_Request $request, $trustedIps) {
        $request->setTrustedProxies((array) $trustedIps, $this->getTrustedHeaderNames());
    }

    /**
     * Set the trusted proxy to be the IP address calling this servers.
     *
     * @param \CHTTP_Request $request
     */
    private function setTrustedProxyIpAddressesToTheCallingIp(CHTTP_Request $request) {
        $request->setTrustedProxies([$request->server->get('REMOTE_ADDR')], $this->getTrustedHeaderNames());
    }

    /**
     * Retrieve trusted header name(s), falling back to defaults if config not set.
     *
     * @return int a bit field of Request::HEADER_*, to set which headers to trust from your proxies
     */
    protected function getTrustedHeaderNames() {
        $headers = $this->headers ?: carr::get($this->config, 'headers');
        if (is_int($headers)) {
            return $headers;
        }
        switch ($headers) {
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
    }

    /**
     * Get the trusted proxies.
     *
     * @return null|array|string
     */
    protected function proxies() {
        return $this->proxies ?: carr::get($this->config, 'proxies', []);
    }
}

<?php

class CApi_OAuth_RouteManager {
    protected $prefix = 'oauth';

    protected $prefixResolver = null;

    public function __construct() {
    }

    public function getDefaultPrefixResolver() {
        return function () {
            $dispatcher = CApi::currentDispatcher();
            $prefix = '';
            if ($dispatcher) {
                $prefix = (string) trim($dispatcher->getPrefix(), '/');
            }

            if (strlen($prefix) > 0) {
                $prefix .= '/';
            }
            $prefix .= trim($this->prefix, '/');

            return $prefix;
        };
    }

    /**
     * @param string $prefix
     *
     * @return $this
     */
    public function setPrefix($prefix) {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @param Closure $resolver
     *
     * @return $this
     */
    public function setPrefixResolver($resolver) {
        $this->prefixResolver = $resolver;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrefix() {
        return $this->prefix;
    }

    protected function resolvePrefix() {
        $resolver = $this->prefixResolver ?: $this->getDefaultPrefixResolver();

        return $resolver();
    }

    public function getTokenUrl() {
        return c::url($this->resolvePrefix() . '/token');
    }

    public function getAuthorizeUrl() {
        return c::url($this->resolvePrefix() . '/authorize');
    }

    public function getLoginUrl() {
        return c::url($this->resolvePrefix() . '/authorization/login');
    }

    public function getApproveUrl() {
        return c::url($this->resolvePrefix() . '/authorization/approve');
    }

    public function getDenyUrl() {
        return c::url($this->resolvePrefix() . '/authorization/deny');
    }

    public function getSwaggerRedirectUrl() {
        return c::url($this->resolvePrefix() . '/swagger/redirect');
    }
}

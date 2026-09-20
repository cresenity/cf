<?php

/**
 * Description of CookieSessionHandler.
 */
use Symfony\Component\HttpFoundation\Request;

class CSession_Handler_CookieSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    use CTrait_Helper_InteractsWithTime;

    /**
     * The cookie jar instance.
     *
     * @var CHTTP_Cookie
     */
    protected $cookie;

    /**
     * The request instance.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    protected $seconds;

    /**
     * Create a new cookie driven handler instance.
     *
     * @param CHTTP_Cookie $cookie
     * @param int          $seconds
     *
     * @return void
     */
    public function __construct(CHTTP_Cookie $cookie, $seconds) {
        $this->cookie = $cookie;
        $this->seconds = $seconds;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName) {
        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function close() {
        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function read($sessionId) {
        $value = $this->request->cookies->get($sessionId) ?: '';

        if (!is_null($decoded = json_decode($value, true)) && is_array($decoded)) {
            if (isset($decoded['expires']) && $this->currentTime() <= $decoded['expires']) {
                return $decoded['data'];
            }
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function write($sessionId, $data) {
        $this->cookie->queue($sessionId, json_encode([
            'data' => $data,
            'expires' => $this->availableAt($this->seconds * 60),
        ]), $this->seconds);

        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function destroy($sessionId) {
        $this->cookie->queue($this->cookie->forget($sessionId));

        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function gc($lifetime) {
        return true;
    }

    /**
     * Set the request instance.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return void
     */
    public function setRequest(Request $request) {
        $this->request = $request;
    }

    /**
     * Benar bila id sesi ini ada dan belum kedaluwarsa di penyimpanan.
     *
     * @param string $sessionId
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function validateId($sessionId) {
        return $this->read($sessionId) !== '';
    }

    /**
     * Perbarui waktu akses sesi tanpa mengubah datanya.
     *
     * @param string $sessionId
     * @param string $data
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function updateTimestamp($sessionId, $data) {
        return $this->write($sessionId, $data);
    }
}

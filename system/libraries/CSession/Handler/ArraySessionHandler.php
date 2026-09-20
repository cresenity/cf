<?php

class CSession_Handler_ArraySessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    use CTrait_Helper_InteractsWithTime;

    /**
     * The array of stored values.
     *
     * @var array
     */
    protected $storage = [];

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    protected $minutes;

    /**
     * Create a new array driven handler instance.
     *
     * @param int $minutes
     *
     * @return void
     */
    public function __construct($minutes) {
        $this->minutes = $minutes;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName) {
        return true;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function close() {
        return true;
    }

    /**
     * @inheritdoc
     *
     * @return string|false
     */
    #[\ReturnTypeWillChange]
    public function read($sessionId) {
        if (!isset($this->storage[$sessionId])) {
            return '';
        }

        $session = $this->storage[$sessionId];

        $expiration = $this->calculateExpiration($this->minutes * 60);

        if (isset($session['time']) && $session['time'] >= $expiration) {
            return $session['data'];
        }

        return '';
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function write($sessionId, $data) {
        $this->storage[$sessionId] = [
            'data' => $data,
            'time' => $this->currentTime(),
        ];

        return true;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function destroy($sessionId) {
        if (isset($this->storage[$sessionId])) {
            unset($this->storage[$sessionId]);
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @return int|false
     */
    #[\ReturnTypeWillChange]
    public function gc($lifetime) {
        $expiration = $this->calculateExpiration($lifetime);

        foreach ($this->storage as $sessionId => $session) {
            if ($session['time'] < $expiration) {
                unset($this->storage[$sessionId]);
            }
        }

        return true;
    }

    /**
     * Get the expiration time of the session.
     *
     * @param int $seconds
     *
     * @return int
     */
    protected function calculateExpiration($seconds) {
        return $this->currentTime() - $seconds;
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
        return isset($this->storage[$sessionId]) && $this->read($sessionId) !== '';
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
        if (isset($this->storage[$sessionId])) {
            $this->storage[$sessionId]['time'] = $this->currentTime();
        }

        return true;
    }
}

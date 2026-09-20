<?php

class CSession_Handler_NullSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
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
        return '';
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function write($sessionId, $data) {
        return true;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function destroy($sessionId) {
        return true;
    }

    /**
     * @inheritdoc
     *
     * @return int|false
     */
    #[\ReturnTypeWillChange]
    public function gc($lifetime) {
        return true;
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
        return false;
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
        return true;
    }
}

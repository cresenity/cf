<?php

/**
 * Description of OutputBufferTrait.
 */
trait CHTTP_Trait_OutputBufferTrait {
    /**
     * Turns on output buffering.
     *
     * @return bool
     */
    public function startOutputBuffering() {
        return ob_start();
    }

    /**
     * @return string|false
     */
    public function cleanOutputBuffer() {
        return ob_get_clean();
    }

    /**
     * Bungkus output hasil echo menjadi respons beserta header yang sudah diset lewat header().
     *
     * @param mixed      $output
     * @param null|array $headerLines baris "Nama: nilai"; null memakai headers_list() dan http_response_code()
     * @param null|int   $statusCode  kode status native; dipakai bila $headerLines diberikan
     *
     * @return CHTTP_Response
     */
    public function makeResponseFromOutput($output, array $headerLines = null, $statusCode = null) {
        $response = c::response(is_string($output) ? $output : '');
        $native = $headerLines === null;
        if ($native) {
            $headerLines = headers_sent() ? [] : headers_list();
            $statusCode = http_response_code();
        }
        if (is_int($statusCode) && $statusCode !== 200 && $statusCode >= 100 && $statusCode < 600) {
            $response->setStatusCode($statusCode);
        }
        foreach ($headerLines as $line) {
            $position = strpos($line, ':');
            if ($position === false) {
                continue;
            }
            $name = trim(substr($line, 0, $position));
            // Set-Cookie dibiarkan di header PHP agar atributnya tidak rusak
            if ($name === '' || strtolower($name) === 'set-cookie') {
                continue;
            }
            if ($native) {
                header_remove($name);
            }
            $response->headers->set($name, trim(substr($line, $position + 1)));
        }

        return $response;
    }

    /**
     * @return int
     */
    public function getOutputBufferLevel() {
        return ob_get_level();
    }

    /**
     * @return bool
     */
    public function endOutputBuffering() {
        return ob_end_clean();
    }

    /**
     * @return void
     */
    public function flushOutputBuffer() {
        flush();
    }
}

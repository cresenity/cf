<?php

use Symfony\Component\HttpFoundation\StreamedResponse;

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since Dec 5, 2020
 */
trait CRouting_Concern_RouteOutputBufferRunner {
    use CHTTP_Trait_OutputBufferTrait;

    public function runWithOutputBuffer() {
        $this->startOutputBuffering();

        register_shutdown_function(function () {
            if (!CHTTP::kernel()->isHandled()) {
                $output = $this->cleanOutputBuffer();
                if (strlen($output) > 0) {
                    echo $output;
                }
            }
        });
        $output = '';
        $response = null;

        try {
            $response = $this->run();

            //$response = $this->invokeController($request);
        } catch (Exception $e) {
            throw $e;
        } finally {
            $output = $this->cleanOutputBuffer();
        }
        if ($response == null || is_bool($response)) {
            if (!is_string($output)) {
                $output = '';
            }
            //collect the header
            $response = c::response($output);

            if (!headers_sent()) {
                $headers = headers_list();

                foreach ($headers as $header) {
                    list($headerKey, $headerValue) = explode(':', $header);
                    header_remove($headerKey);

                    $response->headers->set($headerKey, $headerValue);
                }
            }
        }

        return $response;
    }
}

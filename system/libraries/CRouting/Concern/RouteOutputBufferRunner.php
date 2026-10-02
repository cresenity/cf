<?php

use Symfony\Component\HttpFoundation\StreamedResponse;

defined('SYSPATH') or die('No direct access allowed.');

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
            $response = $this->makeResponseFromOutput($output);
        }

        return $response;
    }
}

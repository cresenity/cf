<?php

/**
 * Description of Pipeline.
 */

/**
 * This extended pipeline catches any exceptions that occur during each slice.
 *
 * The exceptions are converted to HTTP responses for proper middleware handling.
 */
class CHTTP_Pipeline extends CBase_Pipeline {
    /**
     * Handles the value returned from each pipe before passing it to the next.
     *
     * @param mixed $carry
     *
     * @return mixed
     */
    protected function handleCarry($carry) {
        return $carry instanceof CInterface_Responsable ? $carry->toResponse(c::request()) : $carry;
    }

    /**
     * Handle the given exception.
     *
     * @param mixed      $passable
     * @param \Throwable $e
     *
     * @throws \Throwable
     *
     * @return mixed
     */
    protected function handleException($passable, $e) {
        if (!$passable instanceof CHTTP_Request) {
            throw $e;
        }

        $handler = CException::exceptionHandler();

        $handler->report($e);

        $response = $handler->render($passable, $e);

        if (is_object($response) && method_exists($response, 'withException')) {
            /** @var CHTTP_Response $response */
            $response->withException($e);
        }

        return $response;
    }
}

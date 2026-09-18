<?php

/**
 * Description of ResponseFactory.
 */

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CHTTP_ResponseFactory implements CHTTP_Contract_ResponseFactoryInterface {
    use CTrait_Macroable;

    private static $instance;

    private function __construct() {
    }

    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Create a new response instance.
     *
     * @param string $content
     * @param int    $status
     * @param array  $headers
     *
     * @return CHTTP_Response
     */
    public function make($content = '', $status = 200, array $headers = []) {
        return CHTTP::createResponse($content, $status, $headers);
    }

    /**
     * Create a new "no content" response.
     *
     * @param int   $status
     * @param array $headers
     *
     * @return CHTTP_Response
     */
    public function noContent($status = 204, array $headers = []) {
        return $this->make('', $status, $headers);
    }

    /**
     * Create a new response for a given view.
     *
     * @param string|CView_View $view
     * @param array             $data
     * @param int               $status
     * @param array             $headers
     *
     * @return CHTTP_Response
     */
    public function view($view, $data = [], $status = 200, array $headers = []) {
        if (!$view instanceof CView_View) {
            $view = CView::factory($view, $data);
        } else {
            if (is_array($data) && count($data) > 0) {
                $view->set($data);
            }
        }

        return $this->make($view, $status, $headers);
    }

    /**
     * Create a new JSON response instance.
     *
     * @param mixed $data
     * @param int   $status
     * @param array $headers
     * @param int   $options
     *
     * @return CHTTP_JsonResponse
     */
    public function json($data = [], $status = 200, array $headers = [], $options = 0) {
        return new CHTTP_JsonResponse($data, $status, $headers, $options);
    }

    /**
     * Create a new JSONP response instance.
     *
     * @param string $callback
     * @param mixed  $data
     * @param int    $status
     * @param array  $headers
     * @param int    $options
     *
     * @return CHTTP_JsonResponse
     */
    public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0) {
        return $this->json($data, $status, $headers, $options)->setCallback($callback);
    }

    /**
     * Create a new streamed response instance.
     *
     * @param \Closure $callback
     * @param int      $status
     * @param array    $headers
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function stream($callback, $status = 200, array $headers = []) {
        return new StreamedResponse($callback, $status, $headers);
    }

    /**
     * Create a new streamed response instance as a file download.
     *
     * @param \Closure    $callback
     * @param null|string $name
     * @param array       $headers
     * @param null|string $disposition
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment') {
        $response = new StreamedResponse($callback, 200, $headers);

        if (!is_null($name)) {
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                $disposition,
                $name,
                $this->fallbackName($name)
            ));
        }

        return $response;
    }

    /**
     * Create a new file download response.
     *
     * @param \SplFileInfo|string $file
     * @param null|string         $name
     * @param array               $headers
     * @param null|string         $disposition
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($file, $name = null, array $headers = [], $disposition = 'attachment') {
        $response = new BinaryFileResponse($file, 200, $headers, true, $disposition);

        if (!is_null($name)) {
            return $response->setContentDisposition($disposition, $name, $this->fallbackName($name));
        }

        return $response;
    }

    /**
     * Convert the string to ASCII characters that are equivalent to the given name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function fallbackName($name) {
        return str_replace('%', '', cstr::ascii($name));
    }

    /**
     * Return the raw contents of a binary file.
     *
     * @param \SplFileInfo|string $file
     * @param array               $headers
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function file($file, array $headers = []) {
        return new BinaryFileResponse($file, 200, $headers);
    }

    /**
     * Create a new redirect response to the given path.
     *
     * @param string    $path
     * @param int       $status
     * @param array     $headers
     * @param null|bool $secure
     *
     * @return CHTTP_RedirectResponse
     */
    public function redirectTo($path, $status = 302, $headers = [], $secure = null) {
        return CHTTP_Redirector::instance()->to($path, $status, $headers, $secure);
    }

    /**
     * Create a new redirect response to a named route.
     *
     * @param string $route
     * @param mixed  $parameters
     * @param int    $status
     * @param array  $headers
     *
     * @return CHTTP_RedirectResponse
     */
    public function redirectToRoute($route, $parameters = [], $status = 302, $headers = []) {
        return CHTTP_Redirector::instance()->route($route, $parameters, $status, $headers);
    }

    /**
     * Create a new redirect response to a controller action.
     *
     * @param string $action
     * @param mixed  $parameters
     * @param int    $status
     * @param array  $headers
     *
     * @return CHTTP_RedirectResponse
     */
    public function redirectToAction($action, $parameters = [], $status = 302, $headers = []) {
        return CHTTP_Redirector::instance()->action($action, $parameters, $status, $headers);
    }

    /**
     * Create a new redirect response, while putting the current URL in the session.
     *
     * @param string    $path
     * @param int       $status
     * @param array     $headers
     * @param null|bool $secure
     *
     * @return CHTTP_RedirectResponse
     */
    public function redirectGuest($path, $status = 302, $headers = [], $secure = null) {
        return CHTTP_Redirector::instance()->guest($path, $status, $headers, $secure);
    }

    /**
     * Create a new redirect response to the previously intended location.
     *
     * @param string    $default
     * @param int       $status
     * @param array     $headers
     * @param null|bool $secure
     *
     * @return CHTTP_RedirectResponse
     */
    public function redirectToIntended($default = '/', $status = 302, $headers = [], $secure = null) {
        return CHTTP_Redirector::instance()->intended($default, $status, $headers, $secure);
    }
}

<?php

/**
 * Rejects a request whose decoded path is not valid UTF-8 with a 400 instead of failing somewhere deeper.
 */
class CHTTP_Middleware_ValidatePathEncoding {
    /**
     * @param CHTTP_Request $request
     * @param Closure       $next
     *
     * @throws CHTTP_Exception_MalformedUrlException
     *
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $decodedPath = rawurldecode($request->path());

        if (!mb_check_encoding($decodedPath, 'UTF-8')) {
            throw new CHTTP_Exception_MalformedUrlException();
        }

        return $next($request);
    }
}

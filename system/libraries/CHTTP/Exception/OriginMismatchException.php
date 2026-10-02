<?php

/**
 * Thrown by CHTTP_Middleware_VerifyCsrfToken in origin-only mode; rendered like a token mismatch (419).
 */
class CHTTP_Exception_OriginMismatchException extends CSession_Exception_TokenMismatchException {
}

<?php

/**
 * Description of LegacyExceptionHandler.
 */
class CException_LegacyExceptionHandler {
    public static $errorLang = [
        E_RECOVERABLE_ERROR => [1, 'Recoverable Error', 'An error was detected which prevented the loading of this page. If this problem persists, please contact the website administrator.'],
        E_ERROR => [1, 'Fatal Error', ''],
        E_USER_ERROR => [1, 'Fatal Error', ''],
        E_PARSE => [1, 'Syntax Error', ''],
        E_WARNING => [1, 'Warning Message', ''],
        E_USER_WARNING => [1, 'Warning Message', ''],
        E_STRICT => [2, 'Strict Mode Error', ''],
        E_NOTICE => [2, 'Runtime Message', ''],
    ];

    public static function getContent($exception) {
        $code = $exception->getCode();
        $type = get_class($exception);
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTraceAsString();
        $uri = CFRouter::$current_uri;
        $template = 'errors/exception-legacy';
        if (is_numeric($code)) {
            $codes = static::$errorLang;

            if (!empty($codes[$code])) {
                list($level, $error, $description) = $codes[$code];
            } else {
                $level = 1;
                $error = get_class($exception);
                $description = '';
            }
        } else {
            // Custom error message, this will never be logged
            $level = 5;
            $error = $code;
            $description = '';
        }

        // Remove the DOCROOT from the path, as a security precaution
        $file = str_replace('\\', '/', realpath($file));
        $file = preg_replace('|^' . preg_quote(DOCROOT) . '|', '', $file);

        $output = c::view($template, compact('code', 'file', 'message', 'line', 'trace', 'level', 'uri', 'description', 'error'))->render();
        // require CF::findFile('views', $template);

        // $output = ob_get_clean();

        // Fetch memory usage in MB
        $memory = function_exists('memory_get_usage') ? (memory_get_usage() / 1024 / 1024) : 0;

        // Fetch benchmark for page execution time
        $benchmark = CFBenchmark::get(SYSTEM_BENCHMARK . '_total_execution');
        $output = str_replace(
            [
                '{cf_version}',
                '{cf_codename}',
                '{execution_time}',
                '{memory_usage}',
                '{included_files}',
            ],
            [
                CF::version(),
                CF::codeName(),
                $benchmark['time'],
                number_format($memory, 2) . 'MB',
                count(get_included_files()),
            ],
            $output
        );

        return $output;
    }
}

<?php

/**
 * Mengumpulkan pemakaian API yang sudah @deprecated, mengikuti pola CDebug_Collector_Exception:
 * push ke devcloud (`collector.deprecatedPush`) lalu fallback ke berkas temp/collector/deprecated.
 * Satu entri per (api, pesan, berkas & baris pemanggil) per proses; dimatikan bila `collector.deprecated` false.
 */
class CDebug_Collector_Deprecated extends CDebug_Collector_Exception {
    /**
     * Kunci entri yang sudah dikumpulkan di proses ini.
     *
     * @var array<string, true>
     */
    protected $collected = [];

    /**
     * @param string $message
     * @param array  $context ['api' => 'CEmail::sender', 'replacement' => 'CEmail::mailer()', 'since' => '1.9', 'appCallerOnly' => false]
     *
     * @return null|array data yang dikumpulkan, null bila dilewati
     */
    public function collect($message = '', array $context = []) {
        try {
            if (!CF::config('collector.deprecated')) {
                return null;
            }
            $limit = (int) CF::config('collector.deprecatedLimit', 50);
            if ($limit > 0 && count($this->collected) >= $limit) {
                return null;
            }

            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);
            if (carr::get($context, 'appCallerOnly') && !$this->isCalledFromApp($trace)) {
                return null;
            }
            $data = $this->getDataFromDeprecation((string) $message, $context, $trace);
            $key = md5($data['api'] . '|' . $data['message'] . '|' . $data['file'] . '|' . $data['line']);
            if (isset($this->collected[$key])) {
                return null;
            }
            $this->collected[$key] = true;

            $pushUrl = CF::config('collector.deprecatedPush');
            $pushed = $pushUrl ? $this->pushToDevcloud($data, $pushUrl) : false;
            if (!$pushed) {
                $this->put($data);
            }

            return $data;
        } catch (Throwable $collectException) {
            $this->logCollectFailure($collectException, new Exception((string) $message));

            return null;
        }
    }

    /**
     * Payload deprecation: siapa yang deprecated, dari mana dipanggil, konteks app/request.
     *
     * @param string $message
     * @param array  $context
     * @param array  $trace   hasil debug_backtrace()
     *
     * @return array
     */
    public function getDataFromDeprecation($message, array $context, array $trace) {
        list($deprecatedIn, $caller, $frames) = $this->resolveFrames($trace);
        $api = carr::get($context, 'api') ?: ($deprecatedIn ?: cstr::before($message, ' '));

        $data = [
            'datetime' => date('Y-m-d H:i:s'),
            'uuid' => cstr::uuid(),
            'error' => 'Deprecated',
            'api' => $api,
            'message' => $message !== '' ? $message : $api . ' sudah deprecated',
            'replacement' => carr::get($context, 'replacement'),
            'since' => carr::get($context, 'since'),
            'deprecatedIn' => $deprecatedIn,
            'file' => carr::get($caller, 'file'),
            'line' => carr::get($caller, 'line'),
            'trace' => json_encode($frames),
            'isCli' => CF::isCli(),
            'CFVersion' => CF::version(),
        ];

        return array_merge($data, $this->safeAppContext(), $this->safeRequestContext());
    }

    /**
     * Buang frame milik kolektor sendiri: collect(), collectDeprecated(), CDebug::collector(), CF::deprecated().
     *
     * @param array $trace
     *
     * @return array
     */
    protected function filterCollectorFrames(array $trace) {
        return array_values(array_filter($trace, function ($frame) {
            $class = carr::get($frame, 'class');
            $function = carr::get($frame, 'function');
            if ($class === 'CF' && $function === 'deprecated') {
                return false;
            }

            return $class !== 'CDebug' && $class !== 'CDebug_CollectorManager' && !is_a($class ?: 'stdClass', CDebug_Collector_Deprecated::class, true);
        }));
    }

    /**
     * Pisahkan trace menjadi: fungsi deprecated yang memanggil collect (class::method), frame pemanggilnya
     * (berkas/baris di kode app), dan ringkasan 5 frame.
     *
     * @param array $trace
     *
     * @return array [deprecatedIn, callerFrame, frames]
     */
    protected function resolveFrames(array $trace) {
        $frames = $this->filterCollectorFrames($trace);

        // frame[0] = fungsi deprecated (yang memanggil collectDeprecated); 'file'/'line' tiap frame = tempat ia dipanggil
        $deprecatedFrame = carr::get($frames, 0, []);
        $deprecatedIn = carr::get($deprecatedFrame, 'function');
        if (isset($deprecatedFrame['class'])) {
            $deprecatedIn = $deprecatedFrame['class'] . carr::get($deprecatedFrame, 'type', '::') . $deprecatedIn;
        }
        // pemanggil = frame pertama yang berkasnya di luar system/ (kode app), jatuh ke frame[0] bila semuanya di framework
        $caller = ['file' => carr::get($deprecatedFrame, 'file'), 'line' => carr::get($deprecatedFrame, 'line')];
        $systemPath = rtrim(SYSPATH, DS) . DS;
        foreach ($frames as $frame) {
            $file = carr::get($frame, 'file');
            if ($file && strpos($file, $systemPath) !== 0) {
                $caller = ['file' => $file, 'line' => carr::get($frame, 'line')];

                break;
            }
        }

        $summary = [];
        foreach (array_slice($frames, 0, 6) as $frame) {
            $function = isset($frame['class']) ? $frame['class'] . carr::get($frame, 'type', '::') . carr::get($frame, 'function') : carr::get($frame, 'function');
            $summary[] = $function . (isset($frame['file']) ? ' (' . $frame['file'] . ':' . carr::get($frame, 'line') . ')' : '');
        }

        return [$deprecatedIn, $caller, $summary];
    }

    /**
     * Benar bila fungsi deprecated (frame pertama setelah kolektor) dipanggil langsung dari berkas di luar system/.
     *
     * @param array $trace
     *
     * @return bool
     */
    protected function isCalledFromApp(array $trace) {
        $frames = $this->filterCollectorFrames($trace);
        $directCallerFile = carr::get(carr::get($frames, 0, []), 'file');

        return $directCallerFile !== null && strpos($directCallerFile, rtrim(SYSPATH, DS) . DS) !== 0;
    }

    /**
     * Lupakan entri yang sudah dikumpulkan (untuk test / worker berumur panjang).
     *
     * @return void
     */
    public function flush() {
        $this->collected = [];
    }

    /**
     * @return string
     */
    public function getType() {
        return CDebug::COLLECTOR_TYPE_DEPRECATED;
    }
}

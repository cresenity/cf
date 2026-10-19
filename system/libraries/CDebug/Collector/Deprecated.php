<?php

/**
 * Mengumpulkan pemakaian API yang sudah @deprecated, mengikuti pola CDebug_Collector_Exception:
 * dispatch() ke channel di `collector.deprecatedChannels` (default devcloud saja - push lalu
 * fallback ke berkas temp/collector/deprecated). Satu entri per (api, pesan, berkas & baris
 * pemanggil) per proses; dimatikan bila `collector.deprecated` false.
 */
class CDebug_Collector_Deprecated extends CDebug_Collector_Exception {
    /**
     * Kunci entri yang sudah dikumpulkan di proses ini.
     *
     * @var array<string, true>
     */
    protected $collected = [];

    /**
     * Entri yang menunggu dikirim setelah respons selesai (request web dengan deprecatedPush).
     *
     * @var array
     */
    protected $pending = [];

    /**
     * @var bool
     */
    protected $shutdownRegistered = false;

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
            //kunci dedup dari frame saja; payload (uuid, konteks app/request) hanya untuk entri baru
            $frames = $this->resolveFrames($trace);
            list($api, $resolvedMessage) = $this->resolveApiAndMessage((string) $message, $context, $frames[0]);
            $key = md5($api . '|' . $resolvedMessage . '|' . carr::get($frames[1], 'file') . '|' . carr::get($frames[1], 'line'));
            if (isset($this->collected[$key])) {
                return null;
            }
            $this->collected[$key] = true;
            $data = $this->buildData((string) $message, $context, $frames);

            $this->dispatch($data, 'collector.deprecatedPush', 'collector.deprecatedChannels');

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
     * @param array  $trace   hasil debug_backtrace()
     *
     * @return array
     */
    public function getDataFromDeprecation($message, array $context, array $trace) {
        return $this->buildData($message, $context, $this->resolveFrames($trace));
    }

    /**
     * @param string      $message
     * @param null|string $deprecatedIn
     *
     * @return array [api, message]
     */
    protected function resolveApiAndMessage($message, array $context, $deprecatedIn) {
        $api = carr::get($context, 'api') ?: ($deprecatedIn ?: cstr::before($message, ' '));

        return [$api, $message !== '' ? $message : $api . ' sudah deprecated'];
    }

    /**
     * @param string $message
     * @param array  $resolvedFrames hasil resolveFrames()
     *
     * @return array
     */
    protected function buildData($message, array $context, array $resolvedFrames) {
        list($deprecatedIn, $caller, $frames) = $resolvedFrames;
        list($api, $resolvedMessage) = $this->resolveApiAndMessage($message, $context, $deprecatedIn);

        $data = [
            'datetime' => date('Y-m-d H:i:s'),
            'uuid' => cstr::uuid(),
            'error' => 'Deprecated',
            'api' => $api,
            'message' => $resolvedMessage,
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
     * @return bool
     */
    protected function isCalledFromApp(array $trace) {
        $frames = $this->filterCollectorFrames($trace);
        $directCallerFile = carr::get(carr::get($frames, 0, []), 'file');

        return $directCallerFile !== null && strpos($directCallerFile, rtrim(SYSPATH, DS) . DS) !== 0;
    }

    /**
     * Push ke devcloud (koneksi TLS sinkron, 15-30 ms) di request web ditunda sampai respons
     * selesai dikirim. CLI/daemon/worker tetap langsung: shutdown baru terjadi saat proses berhenti.
     *
     * @param array  $data
     * @param string $pushConfigKey
     * @param string $channelsConfigKey
     *
     * @return void
     */
    protected function dispatch($data, $pushConfigKey, $channelsConfigKey) {
        if (!$this->shouldDeferDispatch($pushConfigKey)) {
            parent::dispatch($data, $pushConfigKey, $channelsConfigKey);

            return;
        }
        $this->pending[] = [$data, $pushConfigKey, $channelsConfigKey];
        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function([$this, 'dispatchPending']);
        }
    }

    /**
     * Shutdown: tutup respons ke klien dulu, baru kirim entri yang tertunda.
     *
     * @return void
     */
    public function dispatchPending() {
        if (count($this->pending) === 0) {
            return;
        }
        $this->finishRequest();
        $pending = $this->pending;
        $this->pending = [];
        foreach ($pending as $item) {
            try {
                parent::dispatch($item[0], $item[1], $item[2]);
            } catch (Throwable $e) {
                $this->logCollectFailure($e, new Exception((string) carr::get($item[0], 'message')));
            }
        }
    }

    /**
     * @param string $pushConfigKey
     *
     * @return bool
     */
    protected function shouldDeferDispatch($pushConfigKey) {
        return !CF::isCli() && CF::config($pushConfigKey) && $this->canFinishRequest();
    }

    /**
     * @return bool
     */
    protected function canFinishRequest() {
        return function_exists('litespeed_finish_request') || function_exists('fastcgi_finish_request');
    }

    /**
     * @return void
     */
    protected function finishRequest() {
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
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

<?php

use Carbon\CarbonInterface;

class CLogger_Reader_Log {
    public int $index;

    public CarbonInterface $time;

    public CLogger_Level $level;

    public string $environment;

    public string $text;

    public string $fullText;

    public bool $fullTextIncomplete = false;

    public int $fullTextLength;

    public string $fileIdentifier;

    public int $filePosition;

    public function __construct(
        int $index,
        string $text,
        string $fileIdentifier,
        int $filePosition
    ) {
        $this->index = $index;
        $this->fileIdentifier = $fileIdentifier;
        $this->filePosition = $filePosition;
        $text = mb_convert_encoding(rtrim($text, "\t\n\r"), 'UTF-8', 'UTF-8');
        $this->fullTextLength = strlen($text);

        $matches = [];
        list($firstLine, $theRestOfIt) = explode("\n", cstr::finish($text, "\n"), 2);

        // sometimes, even the first line will have a HUGE exception with tons of debug data all in one line,
        // so in order to properly match, we must have a smaller first line...
        $firstLineSplit = str_split($firstLine, 1000);
        $firstLinePart = array_shift($firstLineSplit);
        if (!preg_match(CLogger_Reader::logRegexPattern(), $firstLinePart, $matches)) {
            // diawali [timestamp] tapi bukan format "env.LEVEL: pesan" (mis. keluaran cron): sisa baris jadi teks
            preg_match('/^\[([^\]]+)\]\s*(.*)$/s', $firstLinePart, $fallback);
            $matches = [1 => $fallback[1] ?? null, 7 => $fallback[2] ?? $firstLinePart];
        }

        $this->time = CCarbon::parse($matches[1])->tz(
            CF::config('log.reader.timezone', CF::config('app.timezone', 'UTC'))
        );

        // $matches[2] contains microseconds, which is already handled
        // $matches[3] contains timezone offset, which is already handled

        $this->environment = $matches[5] ?? '';

        // There might be something in the middle between the timestamp
        // and the environment/level. Let's put that at the beginning of the first line.
        $middle = $matches[4] ?? '';
        if (cstr::endsWith($middle, $this->environment . '.')) {
            $middle = substr($middle, 0, -strlen($this->environment . '.'));
        }
        $middle = trim($middle);

        $this->level = CLogger_Level::from(strtolower($matches[6] ?? ''));

        $firstLineText = $matches[7] ?? '';

        if (!empty($middle)) {
            $firstLineText = $middle . ' ' . $firstLineText;
        }

        $this->text = trim($firstLineText);
        $text = $firstLineText . ($matches[8] ?? '') . implode('', $firstLineSplit) . "\n" . $theRestOfIt;

        if (c::session()->get('capp-log-reader:shorter-stack-traces', false)) {
            $excludes = CF::config('log.reader.shorter_stack_trace_excludes', []);
            $emptyLineCharacter = '    ...';
            $lines = explode("\n", $text);
            $filteredLines = [];
            foreach ($lines as $line) {
                $shouldExclude = false;
                foreach ($excludes as $excludePattern) {
                    if (str_starts_with($line, '#') && str_contains($line, $excludePattern)) {
                        $shouldExclude = true;

                        break;
                    }
                }

                if ($shouldExclude && end($filteredLines) !== $emptyLineCharacter) {
                    $filteredLines[] = $emptyLineCharacter;
                } elseif (!$shouldExclude) {
                    $filteredLines[] = $line;
                }
            }
            $text = implode("\n", $filteredLines);
        }

        if (strlen($text) > CLogger_Reader::instance()->maxLogSize()) {
            $text = cstr::limit($text, CLogger_Reader::instance()->maxLogSize());
            $this->fullTextIncomplete = true;
        }

        $this->fullText = trim($text);
    }

    public function fullTextMatches(?string $query = null): bool {
        if (empty($query)) {
            return true;
        }

        // keyword mentah, atau regex yang sudah dibangun LogReader::setQuery()
        if (!(cstr::startsWith($query, '~') && cstr::endsWith($query, '~i'))) {
            $query = CLogger_Reader_Util_Utils::searchRegex($query);
        }

        return (bool) preg_match($query, $this->fullText);
    }

    public function fullTextLengthFormatted(): string {
        return CLogger_Reader_Util_Utils::bytesForHumans($this->fullTextLength);
    }
}

<?php

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactory;
use Symfony\Component\Uid\Ulid;
use League\CommonMark\MarkdownConverter;
use Ramsey\Uuid\Generator\CombGenerator;
use League\CommonMark\Environment\Environment;
use Ramsey\Uuid\Codec\TimestampFirstCombCodec;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\InlinesOnly\InlinesOnlyExtension;

// @codingStandardsIgnoreStart
class cstr {
    // @codingStandardsIgnoreEnd

    /**
     * The cache of snake-cased words.
     *
     * @var array
     */
    protected static $snakeCache = [];

    /**
     * The cache of camel-cased words.
     *
     * @var array
     */
    protected static $camelCache = [];

    /**
     * The callback that should be used to generate UUIDs.
     *
     * @var callable
     */
    protected static $uuidFactory;

    /**
     * The cache of studly-cased words.
     *
     * @var array
     */
    protected static $studlyCache = [];

    /**
     * Get the length of the given string.
     *
     * @param string $str
     *
     * @return int
     */
    public static function len($str) {
        return static::length($str);
    }

    /**
     * Get the length of the given string.
     *
     * @param string $str
     *
     * @return int
     */
    public static function toupper($str) {
        return strtoupper($str);
    }

    /**
     * Convert a string to lowercase.
     *
     * @param string $str
     *
     * @return string
     */
    public static function tolower($str) {
        return strtolower($str);
    }

    /**
     * Find the position of the first occurrence of a substring in a string.
     *
     * @param string $string
     * @param string $needle
     * @param int    $offset
     *
     * @return int
     */
    public static function pos($string, $needle, $offset = 0) {
        return strpos($string, $needle, $offset);
    }

    /**
     * Get the portion of a string between two given values.
     *
     * @param string $subject
     * @param string $from
     * @param string $to
     *
     * @return string
     */
    public static function between($subject, $from, $to) {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return static::beforeLast(static::after($subject, $from), $to);
    }

    /**
     * Get the smallest possible portion of a string between two given values.
     *
     * @param string $subject
     * @param string $from
     * @param string $to
     *
     * @return string
     */
    public static function betweenFirst($subject, $from, $to) {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return static::before(static::after($subject, $from), $to);
    }

    public static function sanitize($string = '', $is_filename = false) {
        // Replace all weird characters with dashes
        $string = preg_replace('/[^\w\-' . ($is_filename ? '~_\.' : '') . ']+/u', '-', $string);

        // Only allow one dash separator at a time (and make string lowercase)
        return mb_strtolower(preg_replace('/--+/u', '-', $string), 'UTF-8');
    }

    /**
     * Limit the number of characters in a string and give ... in end of string.
     *
     * @param string $value
     * @param int    $limit
     *
     * @return string
     */
    public static function ellipsis($value, $limit = 100) {
        return static::limit($value, $limit);
    }

    /**
     * Limit the number of characters in a string.
     *
     * @param string $value
     * @param int    $limit
     * @param string $end
     *
     * @return string
     */
    public static function limit($value, $limit = 100, $end = '...') {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    /**
     * Limit the number of words in a string.
     *
     * @param string $value
     * @param int    $words
     * @param string $end
     *
     * @return string
     */
    public static function limitWords($value, $words = 100, $end = '...') {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || static::length($value) === static::length($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Generate a more truly "random" alpha-numeric string.
     *
     * @param int $length
     *
     * @return string
     */
    public static function random($length = 16) {
        if (!is_callable('random_bytes')) {
            require_once \CF::getFile('vendor', 'random_compat/random');
        }

        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;

            $bytes = random_bytes($size);

            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    /**
     * Repeat the given string.
     *
     * @param string $string
     * @param int    $times
     *
     * @return string
     */
    public static function repeat($string, $times) {
        return str_repeat($string, $times);
    }

    /**
     * Replace a given value in the string sequentially with an array.
     *
     * @param string $search
     * @param string $subject
     *
     * @return string
     */
    public static function replaceArray($search, array $replace, $subject) {
        $segments = explode($search, $subject);

        $result = array_shift($segments);

        foreach ($segments as $segment) {
            $result .= (array_shift($replace) ?: $search) . $segment;
        }

        return $result;
    }

    /**
     * Replace the given value in the given string.
     *
     * @param string|string[] $search
     * @param string|string[] $replace
     * @param string|string[] $subject
     *
     * @return string
     */
    public static function replace($search, $replace, $subject) {
        return str_replace($search, $replace, $subject);
    }

    /**
     * Replace the first occurrence of a given value in the string.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     */
    public static function replaceFirst($search, $replace, $subject) {
        if ($search == '') {
            return $subject;
        }

        $position = strpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Replace the first occurrence of the given value if it appears at the start of the string.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     */
    public static function replaceStart($search, $replace, $subject) {
        $search = (string) $search;

        if ($search === '') {
            return $subject;
        }

        if (static::startsWith($subject, $search)) {
            return static::replaceFirst($search, $replace, $subject);
        }

        return $subject;
    }

    /**
     * Replace the last occurrence of a given value in the string.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     */
    public static function replaceLast($search, $replace, $subject) {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Replace the last occurrence of a given value if it appears at the end of the string.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     */
    public static function replaceEnd($search, $replace, $subject) {
        $search = (string) $search;

        if ($search === '') {
            return $subject;
        }

        if (static::endsWith($subject, $search)) {
            return static::replaceLast($search, $replace, $subject);
        }

        return $subject;
    }

    /**
     * Replace the patterns matching the given regular expression.
     *
     * @param string          $pattern
     * @param \Closure|string $replace
     * @param array|string    $subject
     * @param int             $limit
     *
     * @return null|string|string[]
     */
    public static function replaceMatches($pattern, $replace, $subject, $limit = -1) {
        if ($replace instanceof Closure) {
            return preg_replace_callback($pattern, $replace, $subject, $limit);
        }

        return preg_replace($pattern, $replace, $subject, $limit);
    }

    /**
     * Remove any occurrence of the given string in the subject.
     *
     * @param string|array<string> $search
     * @param string               $subject
     * @param bool                 $caseSensitive
     *
     * @return string
     */
    public static function remove($search, $subject, $caseSensitive = true) {
        $subject = $caseSensitive
                    ? str_replace($search, '', $subject)
                    : str_ireplace($search, '', $subject);

        return $subject;
    }

    /**
     * Reverse the given string.
     *
     * @param string $value
     *
     * @return string
     */
    public static function reverse($value) {
        return implode(array_reverse(mb_str_split($value)));
    }

    /**
     * Determine if a given string contains a given substring.
     *
     * @param string       $haystack
     * @param string|array $needles
     *
     * @return bool
     */
    public static function contains($haystack, $needles) {
        //dipanggil dari mana-mana, termasuk dengan nilai null; PHP 8.1 mengeluh
        //kalau null sampai ke mb_strpos, jadi dijinakkan di satu tempat ini
        $haystack = (string) $haystack;

        foreach ((array) $needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string contains all array values.
     *
     * @param string   $haystack
     * @param string[] $needles
     *
     * @return bool
     */
    public static function containsAll($haystack, array $needles) {
        foreach ($needles as $needle) {
            if (!static::contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert a string to snake case.
     *
     * @param string $value
     * @param string $delimiter
     *
     * @return string
     */
    public static function snake($value, $delimiter = '_') {
        $key = $value;

        if (isset(static::$snakeCache[$key][$delimiter])) {
            return static::$snakeCache[$key][$delimiter];
        }

        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));

            $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }

        return static::$snakeCache[$key][$delimiter] = $value;
    }

    /**
     * Convert a string to kebab case.
     *
     * @param string $value
     *
     * @return string
     */
    public static function kebab($value) {
        return static::snake($value, '-');
    }

    /**
     * Begin a string with a single instance of a given value.
     *
     * @param string $value
     * @param string $prefix
     *
     * @return string
     */
    public static function start($value, $prefix) {
        $quoted = preg_quote($prefix, '/');

        return $prefix . preg_replace('/^(?:' . $quoted . ')+/u', '', $value);
    }

    /**
     * Convert the given string to title case.
     *
     * @param string $value
     *
     * @return string
     */
    public static function title($value) {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Humanize the given value into a proper name.
     *
     * @param string $value
     *
     * @return string
     */
    public static function humanize($value) {
        if (is_object($value)) {
            return static::humanize(c::classBasename(get_class($value)));
        }

        return cstr::title(cstr::snake($value, ' '));
    }

    /**
     * Convert the given string to title case for each word.
     *
     * @param string $value
     *
     * @return string
     */
    public static function headline($value) {
        $parts = explode(' ', $value);

        $parts = count($parts) > 1
            ? array_map([static::class, 'title'], $parts)
            : array_map([static::class, 'title'], static::ucsplit(implode('_', $parts)));

        $collapsed = static::replace(['-', '_', ' '], '_', implode('_', $parts));

        return implode(' ', array_filter(explode('_', $collapsed)));
    }

    /**
     * Convert the given string to APA-style title case.
     *
     * See: https://apastyle.apa.org/style-grammar-guidelines/capitalization/title-case
     *
     * @param string $value
     *
     * @return string
     */
    public static function apa($value) {
        if (trim($value) === '') {
            return $value;
        }

        $minorWords = [
            'and', 'as', 'but', 'for', 'if', 'nor', 'or', 'so', 'yet', 'a', 'an',
            'the', 'at', 'by', 'for', 'in', 'of', 'off', 'on', 'per', 'to', 'up', 'via',
        ];

        $endPunctuation = ['.', '!', '?', ':', '—', ','];

        $words = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        $words[0] = ucfirst(mb_strtolower($words[0]));

        for ($i = 0; $i < count($words); $i++) {
            $lowercaseWord = mb_strtolower($words[$i]);

            if (str_contains($lowercaseWord, '-')) {
                $hyphenatedWords = explode('-', $lowercaseWord);

                $hyphenatedWords = array_map(function ($part) use ($minorWords) {
                    return (in_array($part, $minorWords) && mb_strlen($part) <= 3) ? $part : ucfirst($part);
                }, $hyphenatedWords);

                $words[$i] = implode('-', $hyphenatedWords);
            } else {
                if (in_array($lowercaseWord, $minorWords)
                    && mb_strlen($lowercaseWord) <= 3
                    && !($i === 0 || in_array(mb_substr($words[$i - 1], -1), $endPunctuation))
                ) {
                    $words[$i] = $lowercaseWord;
                } else {
                    $words[$i] = ucfirst($lowercaseWord);
                }
            }
        }

        return implode(' ', $words);
    }

    /**
     * Remove all "extra" blank space from the given string.
     *
     * @param string $value
     *
     * @return string
     */
    public static function squish($value) {
        return preg_replace('~(\s|\x{3164})+~u', ' ', preg_replace('~^[\s]+|[\s]+$~u', '', $value));
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param string       $haystack string to search
     * @param string|array $needles  substring
     *
     * @return bool
     */
    public static function startsWith($haystack, $needles) {
        foreach ((array) $needles as $needle) {
            if ((string) $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string ends with a given substring.
     *
     * @param string       $haystack string to search
     * @param string|array $needles  substring
     *
     * @return bool
     */
    public static function endsWith($haystack, $needles) {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && $needle !== null
                && substr($haystack, -strlen($needle)) === (string) $needle
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts an excerpt from text that matches the first instance of a phrase.
     *
     * @param string $text
     * @param string $phrase
     * @param array  $options
     *
     * @return null|string
     */
    public static function excerpt($text, $phrase = '', $options = []) {
        $radius = carr::get($options, 'radius', 100);
        $omission = carr::get($options, 'omission', '...');

        preg_match('/^(.*?)(' . preg_quote((string) $phrase, '/') . ')(.*)$/iu', (string) $text, $matches);

        if (empty($matches)) {
            return null;
        }

        $start = ltrim($matches[1]);

        $start = c::str(mb_substr($start, max(mb_strlen($start, 'UTF-8') - $radius, 0), $radius, 'UTF-8'))->ltrim()->unless(
            function ($startWithRadius) use ($start) {
                return $startWithRadius->exactly($start);
            },
            function ($startWithRadius) use ($omission) {
                return $startWithRadius->prepend($omission);
            }
        );

        $end = rtrim($matches[3]);

        $end = c::str(mb_substr($end, 0, $radius, 'UTF-8'))->rtrim()->unless(
            function ($endWithRadius) use ($end) {
                return $endWithRadius->exactly($end);
            },
            function ($endWithRadius) use ($omission) {
                return $endWithRadius->append($omission);
            }
        );

        return $start->append($matches[2], $end)->toString();
    }

    /**
     * Convert a value to studly caps case.
     *
     * @param string $value
     *
     * @return string
     */
    public static function studly($value) {
        $key = $value;

        if (isset(static::$studlyCache[$key])) {
            return static::$studlyCache[$key];
        }

        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return static::$studlyCache[$key] = str_replace(' ', '', $value);
    }

    /**
     * Convert a value to camel case.
     *
     * @param string $value
     *
     * @return string
     */
    public static function camel($value) {
        if (isset(static::$camelCache[$value])) {
            return static::$camelCache[$value];
        }

        return static::$camelCache[$value] = lcfirst(static::studly($value));
    }

    /**
     * Get the character at the specified index.
     *
     * @param string $subject
     * @param int    $index
     *
     * @return string|false
     */
    public static function charAt($subject, $index) {
        $length = mb_strlen($subject);

        if ($index < 0 ? $index < -$length : $index > $length - 1) {
            return false;
        }

        return mb_substr($subject, $index, 1);
    }

    /**
     * Convert the given string to lower-case.
     *
     * @param string $value
     *
     * @return string
     */
    public static function lower($value) {
        return CUTF8::strtolower($value);
    }

    /**
     * Get the plural form of an English word.
     *
     * @param string $value
     * @param int    $count
     *
     * @return string
     */
    public static function plural($value, $count = 2) {
        return CPluralizer::plural($value, $count);
    }

    /**
     * Pluralize the last word of an English, studly caps case string.
     *
     * @param string $value
     * @param int    $count
     *
     * @return string
     */
    public static function pluralStudly($value, $count = 2) {
        $parts = preg_split('/(.)(?=[A-Z])/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        $lastWord = array_pop($parts);

        return implode('', $parts) . self::plural($lastWord, $count);
    }

    /**
     * Get the singular form of an English word.
     *
     * @param string $value
     *
     * @return string
     */
    public static function singular($value) {
        return CPluralizer::singular($value);
    }

    /**
     * Generate a URL friendly "slug" from a given string.
     *
     * @param string $title
     * @param string $separator
     * @param string $language
     *
     * @return string
     */
    public static function slug($title, $separator = '-', $language = 'en') {
        $title = $language ? static::ascii($title, $language) : $title;

        // Convert all dashes/underscores into separator
        $flip = $separator === '-' ? '_' : '-';

        $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);

        // Replace @ with the word 'at'
        $title = str_replace('@', $separator . 'at' . $separator, $title);

        // Remove all characters that are not the separator, letters, numbers, or whitespace.
        $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', static::lower($title));

        // Replace all separator characters and whitespace by a single separator
        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

        return trim($title, $separator);
    }

    /**
     * Convert the given string to upper-case.
     *
     * @param string $value
     *
     * @return string
     */
    public static function upper($value) {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Parse a Class@method style callback into class and method.
     *
     * @param string      $callback
     * @param null|string $default
     *
     * @return array
     */
    public static function parseCallback($callback, $default = null) {
        return static::contains($callback, '@') ? explode('@', $callback, 2) : [$callback, $default];
    }

    /**
     * Cap a string with a single instance of a given value.
     *
     * @param string $value
     * @param string $cap
     *
     * @return string
     */
    public static function finish($value, $cap) {
        $quoted = preg_quote($cap, '/');

        return preg_replace('/(?:' . $quoted . ')+$/u', '', $value) . $cap;
    }

    /**
     * Wrap the string with the given strings.
     *
     * @param string      $before
     * @param null|string $after
     * @param mixed       $value
     *
     * @return string
     */
    public static function wrap($value, $before, $after = null) {
        return $before . $value . ($after ?: $before);
    }

    /**
     * Determine if a given string matches a given pattern.
     *
     * @param string|array $pattern
     * @param string       $value
     *
     * @return bool
     */
    public static function is($pattern, $value) {
        $patterns = carr::wrap($pattern);

        $value = (string) $value;

        if (empty($patterns)) {
            return false;
        }

        foreach ($patterns as $singlePattern) {
            $singlePattern = (string) $singlePattern;
            // If the given value is an exact match we can of course return true right
            // from the beginning. Otherwise, we will translate asterisks and do an
            // actual pattern match against the two strings to see if they match.
            if ($singlePattern == $value) {
                return true;
            }

            $singlePattern = preg_quote($singlePattern, '#');

            // Asterisks are translated into zero-or-more regular expression wildcards
            // to make it convenient to check if the strings starts with the given
            // pattern such as "library/*", making any string check convenient.
            $singlePattern = str_replace('\*', '.*', $singlePattern);

            if (preg_match('#^' . $singlePattern . '\z#u', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the portion of string specified by the start and length parameters.
     *
     * @param string   $string
     * @param int      $start
     * @param null|int $length
     *
     * @return string
     */
    public static function substr($string, $start, $length = null) {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    /**
     * Returns the number of substring occurrences.
     *
     * @param string   $haystack
     * @param string   $needle
     * @param int      $offset
     * @param null|int $length
     *
     * @return int
     */
    public static function substrCount($haystack, $needle, $offset = 0, $length = null) {
        if (!is_null($length)) {
            return substr_count($haystack, $needle, $offset, $length);
        } else {
            return substr_count($haystack, $needle, $offset);
        }
    }

    /**
     * Replace text within a portion of a string.
     *
     * @param string|string[] $string
     * @param string|string[] $replace
     * @param int|int[]       $offset
     * @param null|int|int[]  $length
     *
     * @return string|string[]
     */
    public static function substrReplace($string, $replace, $offset = 0, $length = null) {
        if ($length === null) {
            $length = strlen($string);
        }

        return substr_replace($string, $replace, $offset, $length);
    }

    /**
     * Swap multiple keywords in a string with other keywords.
     *
     * @param string $subject
     *
     * @return string
     */
    public static function swap(array $map, $subject) {
        return strtr($subject, $map);
    }

    /**
     * Return the length of the given string.
     *
     * @param string $value
     * @param string $encoding
     *
     * @return int
     */
    public static function length($value, $encoding = null) {
        if ($encoding) {
            return mb_strlen($value, $encoding);
        }

        return mb_strlen($value);
    }

    /**
     * Return the remainder of a string after a given value.
     *
     * @param string $subject
     * @param string $search
     *
     * @return string
     */
    public static function after($subject, $search) {
        return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    /**
     * Return the remainder of a string after the last occurrence of a given value.
     *
     * @param string $subject
     * @param string $search
     *
     * @return string
     */
    public static function afterLast($subject, $search) {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, (string) $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, $position + strlen($search));
    }

    /**
     * Get the portion of a string before a given value.
     *
     * @param string $subject
     * @param string $search
     *
     * @return string
     */
    public static function before($subject, $search) {
        return $search === '' ? $subject : explode($search, $subject)[0];
    }

    /**
     * Get the portion of a string before the last occurrence of a given value.
     *
     * @param string $subject
     * @param string $search
     *
     * @return string
     */
    public static function beforeLast($subject, $search) {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strrpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return static::substr($subject, 0, $pos);
    }

    /**
     * Transliterate a UTF-8 value to ASCII.
     *
     * @param string $value
     * @param string $language
     *
     * @return string
     */
    public static function ascii($value, $language = 'en') {
        return CASCII::toAscii((string) $value, $language);
    }

    /**
     * Transliterate a string to its closest ASCII representation.
     *
     * @param string      $string
     * @param null|string $unknown
     * @param null|bool   $strict
     *
     * @return string
     */
    public static function transliterate($string, $unknown = '?', $strict = false) {
        return CASCII::toTransliterate($string, $unknown, $strict);
    }

    /**
     * Returns the replacements for the ascii method.
     *
     * Note: Adapted from Stringy\Stringy.
     *
     * @see https://github.com/danielstjules/Stringy/blob/3.1.0/LICENSE.txt
     *
     * @return array
     */
    protected static function charsArray() {
        static $charsArray;

        if (isset($charsArray)) {
            return $charsArray;
        }

        return $charsArray = [
            '0' => ['°', '₀', '۰', '０'],
            '1' => ['¹', '₁', '۱', '１'],
            '2' => ['²', '₂', '۲', '２'],
            '3' => ['³', '₃', '۳', '３'],
            '4' => ['⁴', '₄', '۴', '٤', '４'],
            '5' => ['⁵', '₅', '۵', '٥', '５'],
            '6' => ['⁶', '₆', '۶', '٦', '６'],
            '7' => ['⁷', '₇', '۷', '７'],
            '8' => ['⁸', '₈', '۸', '８'],
            '9' => ['⁹', '₉', '۹', '９'],
            'a' => ['à', 'á', 'ả', 'ã', 'ạ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ', 'ā', 'ą', 'å', 'α', 'ά', 'ἀ', 'ἁ', 'ἂ', 'ἃ', 'ἄ', 'ἅ', 'ἆ', 'ἇ', 'ᾀ', 'ᾁ', 'ᾂ', 'ᾃ', 'ᾄ', 'ᾅ', 'ᾆ', 'ᾇ', 'ὰ', 'ά', 'ᾰ', 'ᾱ', 'ᾲ', 'ᾳ', 'ᾴ', 'ᾶ', 'ᾷ', 'а', 'أ', 'အ', 'ာ', 'ါ', 'ǻ', 'ǎ', 'ª', 'ა', 'अ', 'ا', 'ａ', 'ä'],
            'b' => ['б', 'β', 'ب', 'ဗ', 'ბ', 'ｂ'],
            'c' => ['ç', 'ć', 'č', 'ĉ', 'ċ', 'ｃ'],
            'd' => ['ď', 'ð', 'đ', 'ƌ', 'ȡ', 'ɖ', 'ɗ', 'ᵭ', 'ᶁ', 'ᶑ', 'д', 'δ', 'د', 'ض', 'ဍ', 'ဒ', 'დ', 'ｄ'],
            'e' => ['é', 'è', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ', 'ë', 'ē', 'ę', 'ě', 'ĕ', 'ė', 'ε', 'έ', 'ἐ', 'ἑ', 'ἒ', 'ἓ', 'ἔ', 'ἕ', 'ὲ', 'έ', 'е', 'ё', 'э', 'є', 'ə', 'ဧ', 'ေ', 'ဲ', 'ე', 'ए', 'إ', 'ئ', 'ｅ'],
            'f' => ['ф', 'φ', 'ف', 'ƒ', 'ფ', 'ｆ'],
            'g' => ['ĝ', 'ğ', 'ġ', 'ģ', 'г', 'ґ', 'γ', 'ဂ', 'გ', 'گ', 'ｇ'],
            'h' => ['ĥ', 'ħ', 'η', 'ή', 'ح', 'ه', 'ဟ', 'ှ', 'ჰ', 'ｈ'],
            'i' => ['í', 'ì', 'ỉ', 'ĩ', 'ị', 'î', 'ï', 'ī', 'ĭ', 'į', 'ı', 'ι', 'ί', 'ϊ', 'ΐ', 'ἰ', 'ἱ', 'ἲ', 'ἳ', 'ἴ', 'ἵ', 'ἶ', 'ἷ', 'ὶ', 'ί', 'ῐ', 'ῑ', 'ῒ', 'ΐ', 'ῖ', 'ῗ', 'і', 'ї', 'и', 'ဣ', 'ိ', 'ီ', 'ည်', 'ǐ', 'ი', 'इ', 'ی', 'ｉ'],
            'j' => ['ĵ', 'ј', 'Ј', 'ჯ', 'ج', 'ｊ'],
            'k' => ['ķ', 'ĸ', 'к', 'κ', 'Ķ', 'ق', 'ك', 'က', 'კ', 'ქ', 'ک', 'ｋ'],
            'l' => ['ł', 'ľ', 'ĺ', 'ļ', 'ŀ', 'л', 'λ', 'ل', 'လ', 'ლ', 'ｌ'],
            'm' => ['м', 'μ', 'م', 'မ', 'მ', 'ｍ'],
            'n' => ['ñ', 'ń', 'ň', 'ņ', 'ŉ', 'ŋ', 'ν', 'н', 'ن', 'န', 'ნ', 'ｎ'],
            'o' => ['ó', 'ò', 'ỏ', 'õ', 'ọ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ', 'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ', 'ø', 'ō', 'ő', 'ŏ', 'ο', 'ὀ', 'ὁ', 'ὂ', 'ὃ', 'ὄ', 'ὅ', 'ὸ', 'ό', 'о', 'و', 'θ', 'ို', 'ǒ', 'ǿ', 'º', 'ო', 'ओ', 'ｏ', 'ö'],
            'p' => ['п', 'π', 'ပ', 'პ', 'پ', 'ｐ'],
            'q' => ['ყ', 'ｑ'],
            'r' => ['ŕ', 'ř', 'ŗ', 'р', 'ρ', 'ر', 'რ', 'ｒ'],
            's' => ['ś', 'š', 'ş', 'с', 'σ', 'ș', 'ς', 'س', 'ص', 'စ', 'ſ', 'ს', 'ｓ'],
            't' => ['ť', 'ţ', 'т', 'τ', 'ț', 'ت', 'ط', 'ဋ', 'တ', 'ŧ', 'თ', 'ტ', 'ｔ'],
            'u' => ['ú', 'ù', 'ủ', 'ũ', 'ụ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự', 'û', 'ū', 'ů', 'ű', 'ŭ', 'ų', 'µ', 'у', 'ဉ', 'ု', 'ူ', 'ǔ', 'ǖ', 'ǘ', 'ǚ', 'ǜ', 'უ', 'उ', 'ｕ', 'ў', 'ü'],
            'v' => ['в', 'ვ', 'ϐ', 'ｖ'],
            'w' => ['ŵ', 'ω', 'ώ', 'ဝ', 'ွ', 'ｗ'],
            'x' => ['χ', 'ξ', 'ｘ'],
            'y' => ['ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ', 'ÿ', 'ŷ', 'й', 'ы', 'υ', 'ϋ', 'ύ', 'ΰ', 'ي', 'ယ', 'ｙ'],
            'z' => ['ź', 'ž', 'ż', 'з', 'ζ', 'ز', 'ဇ', 'ზ', 'ｚ'],
            'aa' => ['ع', 'आ', 'آ'],
            'ae' => ['æ', 'ǽ'],
            'ai' => ['ऐ'],
            'ch' => ['ч', 'ჩ', 'ჭ', 'چ'],
            'dj' => ['ђ', 'đ'],
            'dz' => ['џ', 'ძ'],
            'ei' => ['ऍ'],
            'gh' => ['غ', 'ღ'],
            'ii' => ['ई'],
            'ij' => ['ĳ'],
            'kh' => ['х', 'خ', 'ხ'],
            'lj' => ['љ'],
            'nj' => ['њ'],
            'oe' => ['ö', 'œ', 'ؤ'],
            'oi' => ['ऑ'],
            'oii' => ['ऒ'],
            'ps' => ['ψ'],
            'sh' => ['ш', 'შ', 'ش'],
            'shch' => ['щ'],
            'ss' => ['ß'],
            'sx' => ['ŝ'],
            'th' => ['þ', 'ϑ', 'ث', 'ذ', 'ظ'],
            'ts' => ['ц', 'ც', 'წ'],
            'ue' => ['ü'],
            'uu' => ['ऊ'],
            'ya' => ['я'],
            'yu' => ['ю'],
            'zh' => ['ж', 'ჟ', 'ژ'],
            '(c)' => ['©'],
            'A' => ['Á', 'À', 'Ả', 'Ã', 'Ạ', 'Ă', 'Ắ', 'Ằ', 'Ẳ', 'Ẵ', 'Ặ', 'Â', 'Ấ', 'Ầ', 'Ẩ', 'Ẫ', 'Ậ', 'Å', 'Ā', 'Ą', 'Α', 'Ά', 'Ἀ', 'Ἁ', 'Ἂ', 'Ἃ', 'Ἄ', 'Ἅ', 'Ἆ', 'Ἇ', 'ᾈ', 'ᾉ', 'ᾊ', 'ᾋ', 'ᾌ', 'ᾍ', 'ᾎ', 'ᾏ', 'Ᾰ', 'Ᾱ', 'Ὰ', 'Ά', 'ᾼ', 'А', 'Ǻ', 'Ǎ', 'Ａ', 'Ä'],
            'B' => ['Б', 'Β', 'ब', 'Ｂ'],
            'C' => ['Ç', 'Ć', 'Č', 'Ĉ', 'Ċ', 'Ｃ'],
            'D' => ['Ď', 'Ð', 'Đ', 'Ɖ', 'Ɗ', 'Ƌ', 'ᴅ', 'ᴆ', 'Д', 'Δ', 'Ｄ'],
            'E' => ['É', 'È', 'Ẻ', 'Ẽ', 'Ẹ', 'Ê', 'Ế', 'Ề', 'Ể', 'Ễ', 'Ệ', 'Ë', 'Ē', 'Ę', 'Ě', 'Ĕ', 'Ė', 'Ε', 'Έ', 'Ἐ', 'Ἑ', 'Ἒ', 'Ἓ', 'Ἔ', 'Ἕ', 'Έ', 'Ὲ', 'Е', 'Ё', 'Э', 'Є', 'Ə', 'Ｅ'],
            'F' => ['Ф', 'Φ', 'Ｆ'],
            'G' => ['Ğ', 'Ġ', 'Ģ', 'Г', 'Ґ', 'Γ', 'Ｇ'],
            'H' => ['Η', 'Ή', 'Ħ', 'Ｈ'],
            'I' => ['Í', 'Ì', 'Ỉ', 'Ĩ', 'Ị', 'Î', 'Ï', 'Ī', 'Ĭ', 'Į', 'İ', 'Ι', 'Ί', 'Ϊ', 'Ἰ', 'Ἱ', 'Ἳ', 'Ἴ', 'Ἵ', 'Ἶ', 'Ἷ', 'Ῐ', 'Ῑ', 'Ὶ', 'Ί', 'И', 'І', 'Ї', 'Ǐ', 'ϒ', 'Ｉ'],
            'J' => ['Ｊ'],
            'K' => ['К', 'Κ', 'Ｋ'],
            'L' => ['Ĺ', 'Ł', 'Л', 'Λ', 'Ļ', 'Ľ', 'Ŀ', 'ल', 'Ｌ'],
            'M' => ['М', 'Μ', 'Ｍ'],
            'N' => ['Ń', 'Ñ', 'Ň', 'Ņ', 'Ŋ', 'Н', 'Ν', 'Ｎ'],
            'O' => ['Ó', 'Ò', 'Ỏ', 'Õ', 'Ọ', 'Ô', 'Ố', 'Ồ', 'Ổ', 'Ỗ', 'Ộ', 'Ơ', 'Ớ', 'Ờ', 'Ở', 'Ỡ', 'Ợ', 'Ø', 'Ō', 'Ő', 'Ŏ', 'Ο', 'Ό', 'Ὀ', 'Ὁ', 'Ὂ', 'Ὃ', 'Ὄ', 'Ὅ', 'Ὸ', 'Ό', 'О', 'Θ', 'Ө', 'Ǒ', 'Ǿ', 'Ｏ', 'Ö'],
            'P' => ['П', 'Π', 'Ｐ'],
            'Q' => ['Ｑ'],
            'R' => ['Ř', 'Ŕ', 'Р', 'Ρ', 'Ŗ', 'Ｒ'],
            'S' => ['Ş', 'Ŝ', 'Ș', 'Š', 'Ś', 'С', 'Σ', 'Ｓ'],
            'T' => ['Ť', 'Ţ', 'Ŧ', 'Ț', 'Т', 'Τ', 'Ｔ'],
            'U' => ['Ú', 'Ù', 'Ủ', 'Ũ', 'Ụ', 'Ư', 'Ứ', 'Ừ', 'Ử', 'Ữ', 'Ự', 'Û', 'Ū', 'Ů', 'Ű', 'Ŭ', 'Ų', 'У', 'Ǔ', 'Ǖ', 'Ǘ', 'Ǚ', 'Ǜ', 'Ｕ', 'Ў', 'Ü'],
            'V' => ['В', 'Ｖ'],
            'W' => ['Ω', 'Ώ', 'Ŵ', 'Ｗ'],
            'X' => ['Χ', 'Ξ', 'Ｘ'],
            'Y' => ['Ý', 'Ỳ', 'Ỷ', 'Ỹ', 'Ỵ', 'Ÿ', 'Ῠ', 'Ῡ', 'Ὺ', 'Ύ', 'Ы', 'Й', 'Υ', 'Ϋ', 'Ŷ', 'Ｙ'],
            'Z' => ['Ź', 'Ž', 'Ż', 'З', 'Ζ', 'Ｚ'],
            'AE' => ['Æ', 'Ǽ'],
            'Ch' => ['Ч'],
            'Dj' => ['Ђ'],
            'Dz' => ['Џ'],
            'Gx' => ['Ĝ'],
            'Hx' => ['Ĥ'],
            'Ij' => ['Ĳ'],
            'Jx' => ['Ĵ'],
            'Kh' => ['Х'],
            'Lj' => ['Љ'],
            'Nj' => ['Њ'],
            'Oe' => ['Œ'],
            'Ps' => ['Ψ'],
            'Sh' => ['Ш'],
            'Shch' => ['Щ'],
            'Ss' => ['ẞ'],
            'Th' => ['Þ'],
            'Ts' => ['Ц'],
            'Ya' => ['Я'],
            'Yu' => ['Ю'],
            'Zh' => ['Ж'],
            ' ' => ["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83", "\xE2\x80\x84", "\xE2\x80\x85", "\xE2\x80\x86", "\xE2\x80\x87", "\xE2\x80\x88", "\xE2\x80\x89", "\xE2\x80\x8A", "\xE2\x80\xAF", "\xE2\x81\x9F", "\xE3\x80\x80", "\xEF\xBE\xA0"],
        ];
    }

    /**
     * Returns the language specific replacements for the ascii method.
     *
     * Note: Adapted from Stringy\Stringy.
     *
     * @param string $language
     *
     * @see https://github.com/danielstjules/Stringy/blob/3.1.0/LICENSE.txt
     *
     * @return null|array
     */
    protected static function languageSpecificCharsArray($language) {
        static $languageSpecific;

        if (!isset($languageSpecific)) {
            $languageSpecific = [
                'bg' => [
                    ['х', 'Х', 'щ', 'Щ', 'ъ', 'Ъ', 'ь', 'Ь'],
                    ['h', 'H', 'sht', 'SHT', 'a', 'А', 'y', 'Y'],
                ],
                'de' => [
                    ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü'],
                    ['ae', 'oe', 'ue', 'AE', 'OE', 'UE'],
                ],
            ];
        }

        return isset($languageSpecific[$language]) ? $languageSpecific[$language] : null;
    }

    /**
     * Determine if a given string contains uppercase character(s).
     *
     * @param string $string
     *
     * @return bool
     */
    public static function haveUpper($string) {
        return (bool) preg_match('~^\p{Lu}~u', $string);
    }

    /**
     * Convert a value to kebab case.
     *
     * @param string $string
     *
     * @return string
     */
    public static function kebabCase($string) {
        return static::kebab($string);
    }

    /**
     * Limit the number of words in a string.
     *
     * @param string $value
     * @param int    $words
     * @param string $end
     *
     * @return string
     */
    public static function words($value, $words = 100, $end = '...') {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || static::length($value) === static::length($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Converts GitHub flavored Markdown into HTML.
     *
     * @param string $string
     *
     * @return string
     */
    public static function markdown($string, array $options = []) {
        $converter = new GithubFlavoredMarkdownConverter($options);

        return (string) $converter->convertToHtml($string);
    }

    /**
     * Converts inline Markdown into HTML.
     *
     * @param string $string
     *
     * @return string
     */
    public static function inlineMarkdown($string, array $options = []) {
        $environment = new Environment($options);

        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new InlinesOnlyExtension());

        $converter = new MarkdownConverter($environment);

        return (string) $converter->convertToHtml($string);
    }

    /**
     * Splits a Unicode `string` into an array of its words.
     *
     * @param string $string the string to inspect
     *
     * @return array returns the words of `string`
     */
    public static function unicodeWords($string) {
        $rsAstralRange = '\\x{e800}-\\x{efff}';
        $rsComboMarksRange = '\\x{0300}-\\x{036f}';
        $reComboHalfMarksRange = '\\x{fe20}-\\x{fe2f}';
        $rsComboSymbolsRange = '\\x{20d0}-\\x{20ff}';
        $rsComboRange = $rsComboMarksRange . $reComboHalfMarksRange . $rsComboSymbolsRange;
        $rsDingbatRange = '\\x{2700}-\\x{27bf}';
        $rsLowerRange = 'a-z\\xdf-\\xf6\\xf8-\\xff';
        $rsMathOpRange = '\\xac\\xb1\\xd7\\xf7';
        $rsNonCharRange = '\\x00-\\x2f\\x3a-\\x40\\x5b-\\x60\\x7b-\\xbf';
        $rsPunctuationRange = '\\x{2000}-\\x{206f}';
        $rsSpaceRange = ' \\t\\x0b\\f\\xa0\\x{feff}\\n\\r\\x{2028}\\x{2029}\\x{1680}\\x{180e}\\x{2000}\\x{2001}\\x{2002}\\x{2003}\\x{2004}\\x{2005}\\x{2006}\\x{2007}\\x{2008}\\x{2009}\\x{200a}\\x{202f}\\x{205f}\\x{3000}';
        $rsUpperRange = 'A-Z\\xc0-\\xd6\\xd8-\\xde';
        $rsVarRange = '\\x{fe0e}\\x{fe0f}';
        $rsBreakRange = $rsMathOpRange . $rsNonCharRange . $rsPunctuationRange . $rsSpaceRange;
        /* Used to compose unicode capture groups. */
        $rsApos = '[\\x{2019}]';
        $rsBreak = '[' . $rsBreakRange . ']';
        $rsCombo = '[' . $rsComboRange . ']';
        $rsDigits = '\\d+';
        $rsDingbat = '[' . $rsDingbatRange . ']';
        $rsLower = '[' . $rsLowerRange . ']';
        $rsMisc = '[^' . $rsAstralRange . $rsBreakRange . $rsDigits . $rsDingbatRange . $rsLowerRange . $rsUpperRange . ']';
        $rsFitz = '\\x{e83c}[\\x{effb}-\\x{efff}]';
        $rsModifier = '(?:' . $rsCombo . '|' . $rsFitz . ')';
        $rsNonAstral = '[^' . $rsAstralRange . ']';
        $rsRegional = '(?:\\x{e83c}[\\x{ede6}-\\x{edff}]){2}';
        $rsSurrPair = '[\\x{e800}-\\x{ebff}][\\x{ec00}-\\x{efff}]';
        $rsUpper = '[' . $rsUpperRange . ']';
        $rsZWJ = '\\x{200d}';
        /* Used to compose unicode regexes. */
        $rsMiscLower = '(?:' . $rsLower . '|' . $rsMisc . ')';
        $rsMiscUpper = '(?:' . $rsUpper . '|' . $rsMisc . ')';
        $rsOptContrLower = '(?:' . $rsApos . '(?:d|ll|m|re|s|t|ve))?';
        $rsOptContrUpper = '(?:' . $rsApos . '(?:D|LL|M|RE|S|T|VE))?';
        $reOptMod = $rsModifier . '?';
        $rsOptVar = '[' . $rsVarRange . ']?';
        $rsOptJoin = '(?:' . $rsZWJ . '(?:' . implode('|', [$rsNonAstral, $rsRegional, $rsSurrPair]) . ')' . $rsOptVar . $reOptMod . ')*';
        $rsOrdLower = '\\d*(?:(?:1st|2nd|3rd|(?![123])\\dth)\\b)';
        $rsOrdUpper = '\\d*(?:(?:1ST|2ND|3RD|(?![123])\\dTH)\\b)';
        $rsSeq = $rsOptVar . $reOptMod . $rsOptJoin;
        $rsEmoji = '(?:' . implode('|', [$rsDingbat, $rsRegional, $rsSurrPair]) . ')' . $rsSeq;
        $rsAstral = '[' . $rsAstralRange . ']';
        $rsNonAstralCombo = $rsNonAstral . $rsCombo . '?';
        $rsSymbol = '(?:' . implode('|', [$rsNonAstralCombo, $rsCombo, $rsRegional, $rsSurrPair, $rsAstral]) . ')';
        /* Used to match [string symbols](https://mathiasbynens.be/notes/javascript-unicode). */
        $reUnicode = $rsFitz . '(?=' . $rsFitz . ')|' . $rsSymbol . $rsSeq;

        $regex = '#' . \implode('|', [
            $rsUpper . '?' . $rsLower . '+' . $rsOptContrLower . '(?=' . \implode('|', [$rsBreak, $rsUpper, '$']) . ')',
            $rsMiscUpper . '+' . $rsOptContrUpper . '(?=' . \implode('|', [$rsBreak, $rsUpper . $rsMiscLower, '$']) . ')',
            $rsUpper . '?' . $rsMiscLower . '+' . $rsOptContrLower,
            $rsUpper . '+' . $rsOptContrUpper,
            $rsOrdUpper,
            $rsOrdLower,
            $rsDigits,
            $rsEmoji,
        ]) . '#u';
        if (\preg_match_all($regex, $string, $matches) > 0) {
            return $matches[0];
        }

        return [];
    }

    /**
     * Escapes the `RegExp` special characters "^", "$", "\", ".", "*", "+",
     * "?", "(", ")", "[", "]", "{", "}", and "|" in `string`.
     *
     * @param string $string the string to escape
     *
     * @return string returns the escaped string
     *
     * @example
     * <code>
     * escapeRegExp('[lodash](https://lodash.com/)')
     * // => '\[lodash\]\(https://lodash\.com/\)'
     * </code>
     */
    public static function escapeRegExp($string) {
        $reRegExpChar = '/([\\^$.*+?()[\]{}|])/';

        return \preg_replace($reRegExpChar, '\\\$0', $string);
    }

    /**
     * Get a new stringable object from the given string.
     *
     * @param string $string
     *
     * @return CBase_String
     */
    public static function of($string) {
        return new CBase_String($string);
    }

    /**
     * Make a string's first character lowercase.
     *
     * @param string $string
     *
     * @return string
     */
    public static function lcfirst($string) {
        return static::lower(static::substr($string, 0, 1)) . static::substr($string, 1);
    }

    /**
     * Make a string's first character uppercase.
     *
     * @param string $string
     *
     * @return string
     */
    public static function ucfirst($string) {
        return static::upper(static::substr($string, 0, 1)) . static::substr($string, 1);
    }

    /**
     * Split a string into pieces by uppercase characters.
     *
     * @param string $string
     *
     * @return string[]
     */
    public static function ucsplit($string) {
        return preg_split('/(?=\p{Lu})/u', $string, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Get the number of words a string contains.
     *
     * @param string $string
     *
     * @return int
     */
    public static function wordCount($string) {
        return str_word_count($string);
    }

    /**
     * Generate a UUID (version 4).
     *
     * @return \Ramsey\Uuid\UuidInterface
     */
    public static function uuid() {
        return static::$uuidFactory
            ? call_user_func(static::$uuidFactory)
            : Uuid::uuid4();
    }

    /**
     * Generate a time-ordered UUID (version 4).
     *
     * Falls back to CUuid_NativeNumberConverter when neither the gmp extension nor
     * moontoast/math (bcmath) is installed, instead of fataling with
     * UnsatisfiedDependencyException - see #-13845.
     *
     * @return \Ramsey\Uuid\UuidInterface
     */
    public static function orderedUuid() {
        if (static::$uuidFactory) {
            return call_user_func(static::$uuidFactory);
        }

        $factory = new UuidFactory();

        $numberConverter = $factory->getNumberConverter();
        if ($numberConverter instanceof Ramsey\Uuid\Converter\Number\DegradedNumberConverter) {
            $numberConverter = new CUuid_NativeNumberConverter();
        }

        $factory->setRandomGenerator(new CombGenerator(
            $factory->getRandomGenerator(),
            $numberConverter
        ));

        $factory->setCodec(new TimestampFirstCombCodec(
            $factory->getUuidBuilder()
        ));

        return $factory->uuid4();
    }

    /**
     * Set the callable that will be used to generate UUIDs.
     *
     * @return void
     */
    public static function createUuidsUsing(?callable $factory = null) {
        static::$uuidFactory = $factory;
    }

    /**
     * Set the sequence that will be used to generate UUIDs.
     *
     * @param null|callable $whenMissing
     *
     * @return void
     */
    public static function createUuidsUsingSequence(array $sequence, $whenMissing = null) {
        $next = 0;

        if ($whenMissing == null) {
            $whenMissing = function () use (&$next) {
                $factoryCache = static::$uuidFactory;

                static::$uuidFactory = null;

                $uuid = static::uuid();

                static::$uuidFactory = $factoryCache;

                $next++;

                return $uuid;
            };
        }

        static::createUuidsUsing(function () use (&$next, $sequence, $whenMissing) {
            if (array_key_exists($next, $sequence)) {
                return $sequence[$next++];
            }

            return $whenMissing();
        });
    }

    /**
     * Always return the same UUID when generating new UUIDs.
     *
     * @return \Ramsey\Uuid\UuidInterface
     */
    public static function freezeUuids(?Closure $callback = null) {
        $uuid = cstr::uuid();

        cstr::createUuidsUsing(function () use ($uuid) {
            return $uuid;
        });

        if ($callback !== null) {
            try {
                $callback($uuid);
            } finally {
                cstr::createUuidsNormally();
            }
        }

        return $uuid;
    }

    /**
     * Indicate that UUIDs should be created normally and not using a custom factory.
     *
     * @return void
     */
    public static function createUuidsNormally() {
        static::$uuidFactory = null;
    }

    /**
     * Generate a ULID.
     *
     * @return \Symfony\Component\Uid\Ulid
     */
    public static function ulid() {
        return new Ulid();
    }

    /**
     * Masks a portion of a string with a repeated character.
     *
     * @param string   $string
     * @param string   $character
     * @param int      $index
     * @param null|int $length
     * @param string   $encoding
     *
     * @return string
     */
    public static function mask($string, $character, $index, $length = null, $encoding = 'UTF-8') {
        if ($character === '') {
            return $string;
        }

        if (is_null($length) && PHP_MAJOR_VERSION < 8) {
            $length = mb_strlen($string, $encoding);
        }

        $segment = mb_substr($string, $index, $length, $encoding);

        if ($segment === '') {
            return $string;
        }

        $start = mb_substr($string, 0, mb_strpos($string, $segment, 0, $encoding), $encoding);
        $end = mb_substr($string, mb_strpos($string, $segment, 0, $encoding) + mb_strlen($segment, $encoding));

        return $start . str_repeat(mb_substr($character, 0, 1, $encoding), mb_strlen($segment, $encoding)) . $end;
    }

    /**
     * Get the string matching the given pattern.
     *
     * @param string $pattern
     * @param string $subject
     *
     * @return string
     */
    public static function match($pattern, $subject) {
        preg_match($pattern, $subject, $matches);

        if (!$matches) {
            return '';
        }

        return isset($matches[1]) ? $matches[1] : $matches[0];
    }

    /**
     * Determine if a given string matches a given pattern.
     *
     * @param string|iterable<string> $pattern
     * @param string                  $value
     *
     * @return bool
     */
    public static function isMatch($pattern, $value) {
        $value = (string) $value;

        if (!is_iterable($pattern)) {
            $pattern = [$pattern];
        }

        foreach ($pattern as $singlePattern) {
            $singlePattern = (string) $singlePattern;

            if (preg_match($singlePattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the string matching the given pattern.
     *
     * @param string $pattern
     * @param string $subject
     *
     * @return \CCollection
     */
    public static function matchAll($pattern, $subject) {
        preg_match_all($pattern, $subject, $matches);

        if (empty($matches[0])) {
            return c::collect();
        }

        return c::collect(isset($matches[1]) ? $matches[1] : $matches[0]);
    }

    /**
     * Pad both sides of a string with another.
     *
     * @param string $value
     * @param int    $length
     * @param string $pad
     *
     * @return string
     */
    public static function padBoth($value, $length, $pad = ' ') {
        return str_pad($value, $length, $pad, STR_PAD_BOTH);
    }

    /**
     * Pad the left side of a string with another.
     *
     * @param string $value
     * @param int    $length
     * @param string $pad
     *
     * @return string
     */
    public static function padLeft($value, $length, $pad = ' ') {
        return str_pad($value, $length, $pad, STR_PAD_LEFT);
    }

    /**
     * Pad the right side of a string with another.
     *
     * @param string $value
     * @param int    $length
     * @param string $pad
     *
     * @return string
     */
    public static function padRight($value, $length, $pad = ' ') {
        return str_pad($value, $length, $pad, STR_PAD_RIGHT);
    }

    /**
     * Determine if a given string is 7 bit ASCII.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isAscii($value) {
        return CASCII::isAscii((string) $value);
    }

    /**
     * Determine if a given string is valid JSON.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isJson($value) {
        if (!is_string($value)) {
            return false;
        }

        if (PHP_MAJOR_VERSION >= 7 && defined(JSON_THROW_ON_ERROR)) {
            try {
                json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $ex) {
                return false;
            } catch (Exception $ex) {
                return false;
            }
        } else {
            json_decode($value, true, 512);
            if (json_last_error() != JSON_ERROR_NONE) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if a given string is a valid UUID.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isUuid($value) {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/iD', $value) > 0;
    }

    /**
     * Determine if a given string is a valid ULID.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isUlid($value) {
        if (!is_string($value)) {
            return false;
        }

        return Ulid::isValid($value);
    }

    /**
     * Convert the string into a `CBase_HtmlString` instance.
     *
     * @param string $string
     *
     * @return \CBase_HtmlString
     */
    public function toHtmlString($string) {
        return new CBase_HtmlString($string);
    }

    /**
     * Encode a string to base64 URL safe.
     *
     * @param string $input
     *
     * @return string
     */
    public function base64UrlEncode($input) {
        return strtr(base64_encode($input), '+/=', '._-');
    }

    /**
     * Decode a base64 URL safe string.
     *
     * @param string $input
     *
     * @return string
     */
    public function base64UrlDecode($input) {
        return base64_decode(strtr($input, '._-', '+/='));
    }
}

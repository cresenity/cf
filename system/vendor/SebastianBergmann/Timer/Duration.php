<?php 
/*
 * This file is part of phpunit/php-timer.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace SebastianBergmann\Timer;

use function floor;
use function sprintf;

/**
 * @psalm-immutable
 */
final class Duration
{
    /**
     * @var float
     */
    private $nanoseconds;

    /**
     * @var int
     */
    private $hours;

    /**
     * @var int
     */
    private $minutes;

    /**
     * @var int
     */
    private $seconds;

    /**
     * @var int
     */
    private $milliseconds;

    public static function fromMicroseconds(float $microseconds)
    {
        return new self($microseconds * 1000);
    }

    public static function fromNanoseconds(float $nanoseconds)
    {
        return new self($nanoseconds);
    }

    private function __construct(float $nanoseconds)
    {
        $this->nanoseconds     = $nanoseconds;
        $timeInMilliseconds    = $nanoseconds / 1000000;
        $hours                 = floor($timeInMilliseconds / 60 / 60 / 1000);
        $hoursInMilliseconds   = $hours * 60 * 60 * 1000;
        $minutes               = floor($timeInMilliseconds / 60 / 1000) % 60;
        $minutesInMilliseconds = $minutes * 60 * 1000;
        $seconds               = floor(($timeInMilliseconds - $hoursInMilliseconds - $minutesInMilliseconds) / 1000);
        $secondsInMilliseconds = $seconds * 1000;
        $milliseconds          = $timeInMilliseconds - $hoursInMilliseconds - $minutesInMilliseconds - $secondsInMilliseconds;
        $this->hours           = (int) $hours;
        $this->minutes         = $minutes;
        $this->seconds         = (int) $seconds;
        $this->milliseconds    = (int) $milliseconds;
    }

    public function asNanoseconds()
    {
        return $this->nanoseconds;
    }

    public function asMicroseconds()
    {
        return $this->nanoseconds / 1000;
    }

    public function asMilliseconds()
    {
        return $this->nanoseconds / 1000000;
    }

    public function asSeconds()
    {
        return $this->nanoseconds / 1000000000;
    }

    public function asString()
    {
        $result = '';

        if ($this->hours > 0) {
            $result = sprintf('%02d', $this->hours) . ':';
        }

        $result .= sprintf('%02d', $this->minutes) . ':';
        $result .= sprintf('%02d', $this->seconds);

        if ($this->milliseconds > 0) {
            $result .= '.' . sprintf('%03d', $this->milliseconds);
        }

        return $result;
    }
}

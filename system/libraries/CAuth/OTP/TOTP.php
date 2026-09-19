<?php

use Psr\Clock\ClockInterface;

/**
 * @see \OTPHP\Test\TOTPTest
 */
final class CAuth_OTP_TOTP extends CAuth_OTP_OTPAbstract implements CAuth_OTP_Contract_TOTPInterface {
    private ClockInterface $clock;

    public function __construct(string $secret, ?ClockInterface $clock = null) {
        parent::__construct($secret);
        if ($clock === null) {
            $clock = new CAuth_OTP_InternalClock();
        }

        $this->clock = $clock;
    }

    public static function create(
        ?string $secret = null,
        int $period = self::DEFAULT_PERIOD,
        string $digest = self::DEFAULT_DIGEST,
        int $digits = self::DEFAULT_DIGITS,
        int $epoch = self::DEFAULT_EPOCH,
        ?ClockInterface $clock = null
    ): self {
        $totp = $secret !== null
            ? self::createFromSecret($secret, $clock)
            : self::generate($clock);
        $totp->setPeriod($period);
        $totp->setDigest($digest);
        $totp->setDigits($digits);
        $totp->setEpoch($epoch);

        return $totp;
    }

    public static function createFromSecret(string $secret, ?ClockInterface $clock = null): self {
        $totp = new self($secret, $clock);
        $totp->setPeriod(self::DEFAULT_PERIOD);
        $totp->setDigest(self::DEFAULT_DIGEST);
        $totp->setDigits(self::DEFAULT_DIGITS);
        $totp->setEpoch(self::DEFAULT_EPOCH);

        return $totp;
    }

    public static function generate(?ClockInterface $clock = null): self {
        return self::createFromSecret(self::generateSecret(), $clock);
    }

    public function getPeriod(): int {
        $value = $this->getParameter('period');
        if (is_int($value) && $value > 0) {
            return $value;
        }

        throw new InvalidArgumentException('Invalid "period" parameter.');
    }

    public function getEpoch(): int {
        $value = $this->getParameter('epoch');
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        throw new InvalidArgumentException('Invalid "epoch" parameter.');
    }

    public function expiresIn(): int {
        $period = $this->getPeriod();

        return $period - ($this->clock->now()->getTimestamp() % $this->getPeriod());
    }

    public function at(int $input): string {
        return $this->generateOTP($this->timecode($input));
    }

    public function now(): string {
        return $this->at($this->clock->now()->getTimestamp());
    }

    /**
     * If no timestamp is provided, the OTP is verified at the actual timestamp. When used, the leeway parameter will
     * allow time drift. The passed value is in seconds.
     */
    public function verify(string $otp, ?int $timestamp = null, ?int $leeway = null): bool {
        $timestamp ??= $this->clock->now()
            ->getTimestamp();
        if ($timestamp <= 0) {
            throw new InvalidArgumentException('Timestamp must be at least 0.');
        }

        if ($leeway === null) {
            return $this->compareOTP($this->at($timestamp), $otp);
        }

        $leeway = abs($leeway);
        if ($leeway >= $this->getPeriod()) {
            throw new InvalidArgumentException(
                'The leeway must be lower than the TOTP period'
            );
        }

        return $this->compareOTP($this->at($timestamp - $leeway), $otp)
            || $this->compareOTP($this->at($timestamp), $otp)
            || $this->compareOTP($this->at($timestamp + $leeway), $otp);
    }

    public function getProvisioningUri(): string {
        $params = [];
        if ($this->getPeriod() !== 30) {
            $params['period'] = $this->getPeriod();
        }

        if ($this->getEpoch() !== 0) {
            $params['epoch'] = $this->getEpoch();
        }

        return $this->generateURI('totp', $params);
    }

    public function setPeriod(int $period): void {
        $this->setParameter('period', $period);
    }

    public function setEpoch(int $epoch): void {
        $this->setParameter('epoch', $epoch);
    }

    /**
     * @return array<non-empty-string, callable>
     */
    protected function getParameterMap(): array {
        // array_merge, bukan spread: unpack array berkunci string baru ada di PHP 8.1
        return array_merge(parent::getParameterMap(), [
            'period' => function ($value) {
                if ((int) $value > 0) {
                    return (int) $value;
                }

                throw new InvalidArgumentException('Period must be at least 1.');
            },
            'epoch' => function ($value) {
                if ((int) $value >= 0) {
                    return (int) $value;
                }

                throw new InvalidArgumentException(
                    'Epoch must be greater than or equal to 0.'
                );
            },
        ]);
    }

    /**
     * @param array<non-empty-string, mixed> $options
     */
    protected function filterOptions(array &$options): void {
        parent::filterOptions($options);

        if (isset($options['epoch']) && $options['epoch'] === 0) {
            unset($options['epoch']);
        }

        ksort($options);
    }

    private function timecode(int $timestamp): int {
        $timecode = (int) floor(($timestamp - $this->getEpoch()) / $this->getPeriod());
        assert($timecode >= 0);

        return $timecode;
    }
}

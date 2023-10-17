<?php

/**
 * @see \OTPHP\Test\HOTPTest
 */
final class CAuth_OTP_HOTP extends CAuth_OTP_OTPAbstract implements CAuth_OTP_Contract_HOTPInterface {
    private const DEFAULT_WINDOW = 0;

    public static function create(
        string $secret = null,
        int $counter = self::DEFAULT_COUNTER,
        string $digest = self::DEFAULT_DIGEST,
        int $digits = self::DEFAULT_DIGITS
    ): self {
        $htop = $secret !== null
            ? self::createFromSecret($secret)
            : self::generate();
        $htop->setCounter($counter);
        $htop->setDigest($digest);
        $htop->setDigits($digits);

        return $htop;
    }

    public static function createFromSecret(string $secret): self {
        $htop = new self($secret);
        $htop->setCounter(self::DEFAULT_COUNTER);
        $htop->setDigest(self::DEFAULT_DIGEST);
        $htop->setDigits(self::DEFAULT_DIGITS);

        return $htop;
    }

    public static function generate(): self {
        return self::createFromSecret(self::generateSecret());
    }

    public function getCounter(): int {
        $value = $this->getParameter('counter');
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        throw new InvalidArgumentException('Invalid "counter" parameter.');
    }

    public function getProvisioningUri(): string {
        return $this->generateURI('hotp', [
            'counter' => $this->getCounter(),
        ]);
    }

    /**
     * If the counter is not provided, the OTP is verified at the actual counter.
     */
    public function verify(string $otp, int $counter = null, int $window = null): bool {
        if ($counter < 0) {
            throw new InvalidArgumentException('The counter must be at least 0.');
        }

        if ($counter === null) {
            $counter = $this->getCounter();
        } elseif ($counter < $this->getCounter()) {
            return false;
        }

        return $this->verifyOtpWithWindow($otp, $counter, $window);
    }

    public function setCounter(int $counter): void {
        $this->setParameter('counter', $counter);
    }

    /**
     * @return array<string, callable>
     */
    protected function getParameterMap(): array {
        return [...parent::getParameterMap(), ...[
            'counter' => function ($value) {
                $value = (int) $value;
                if ($value >= 0) {
                    return $value;
                }

                throw new InvalidArgumentException('Counter must be at least 0.');
            },
        ]];
    }

    private function updateCounter(int $counter): void {
        $this->setCounter($counter);
    }

    /**
     * @param null|int $window
     */
    private function getWindow(int $window): int {
        return abs($window ?? self::DEFAULT_WINDOW);
    }

    /**
     * @param string$otp
     * @param null|int $window
     */
    private function verifyOtpWithWindow(string $otp, int $counter, int $window): bool {
        $window = $this->getWindow($window);

        for ($i = $counter; $i <= $counter + $window; ++$i) {
            if ($this->compareOTP($this->at($i), $otp)) {
                $this->updateCounter($i + 1);

                return true;
            }
        }

        return false;
    }
}

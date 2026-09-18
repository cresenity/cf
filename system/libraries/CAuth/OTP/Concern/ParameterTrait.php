<?php

trait CAuth_OTP_Concern_ParameterTrait {
    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];

    /**
     * @var null|string
     */
    private $issuer = null;

    /**
     * @var null|string
     */
    private $label = null;

    private bool $issuer_included_as_parameter = true;

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array {
        $parameters = $this->parameters;

        if ($this->getIssuer() !== null && $this->isIssuerIncludedAsParameter() === true) {
            $parameters['issuer'] = $this->getIssuer();
        }

        return $parameters;
    }

    public function getSecret(): string {
        $value = $this->getParameter('secret');
        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw new InvalidArgumentException('Invalid "secret" parameter.');
    }

    /**
     * @return null|string
     */
    public function getLabel() {
        return $this->label;
    }

    public function setLabel(string $label): void {
        $this->setParameter('label', $label);
    }

    /**
     * @return null|string
     */
    public function getIssuer() {
        return $this->issuer;
    }

    public function setIssuer(string $issuer): void {
        $this->setParameter('issuer', $issuer);
    }

    public function isIssuerIncludedAsParameter(): bool {
        return $this->issuer_included_as_parameter;
    }

    public function setIssuerIncludedAsParameter(bool $issuer_included_as_parameter): void {
        $this->issuer_included_as_parameter = $issuer_included_as_parameter;
    }

    public function getDigits(): int {
        $value = $this->getParameter('digits');

        if (is_int($value) && $value > 0) {
            return $value;
        }

        throw new InvalidArgumentException('Invalid "digits" parameter.');
    }

    public function getDigest(): string {
        $value = $this->getParameter('algorithm');
        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw new InvalidArgumentException('Invalid "algorithm" parameter.');
    }

    public function hasParameter(string $parameter): bool {
        return array_key_exists($parameter, $this->parameters);
    }

    public function getParameter(string $parameter) {
        if ($this->hasParameter($parameter)) {
            return $this->getParameters()[$parameter];
        }

        throw new InvalidArgumentException(sprintf('Parameter "%s" does not exist', $parameter));
    }

    public function setParameter(string $parameter, mixed $value): void {
        $map = $this->getParameterMap();

        if (array_key_exists($parameter, $map) === true) {
            $callback = $map[$parameter];
            $value = $callback($value);
        }

        if (property_exists($this, $parameter)) {
            $this->{$parameter} = $value;
        } else {
            $this->parameters[$parameter] = $value;
        }
    }

    public function setSecret(string $secret): void {
        $this->setParameter('secret', $secret);
    }

    public function setDigits(int $digits): void {
        $this->setParameter('digits', $digits);
    }

    public function setDigest(string $digest) {
        $this->setParameter('algorithm', $digest);
    }

    /**
     * @return array<string, callable>
     */
    protected function getParameterMap() {
        return [
            'label' => function ($value) {
                assert($value !== '');
                if ($this->hasColon($value) === false) {
                    return $value;
                }

                throw new InvalidArgumentException(
                    'Label must not contain a colon.'
                );
            },
            'secret' => function ($value) {
                return mb_strtoupper(trim($value, '='));
            },
            'algorithm' => function ($value) {
                $value = mb_strtolower($value);
                if (in_array($value, hash_algos(), true)) {
                    return $value;
                }

                throw new InvalidArgumentException(sprintf(
                    'The "%s" digest is not supported.',
                    $value
                ));
            },
            'digits' => function ($value) {
                if ($value > 0) {
                    return (int) $value;
                }

                throw new InvalidArgumentException('Digits must be at least 1.');
            },
            'issuer' => function ($value) {
                assert($value !== '');
                if ($this->hasColon($value) === false) {
                    return $value;
                }

                throw new InvalidArgumentException(
                    'Issuer must not contain a colon.'
                );
            },
        ];
    }

    /**
     * @param string $value
     */
    private function hasColon(string $value): bool {
        $colons = [':', '%3A', '%3a'];
        foreach ($colons as $colon) {
            if (str_contains($value, $colon)) {
                return true;
            }
        }

        return false;
    }
}

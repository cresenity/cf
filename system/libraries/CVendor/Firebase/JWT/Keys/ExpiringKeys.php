<?php

/**
 * @internal
 */
final class CVendor_Firebase_JWT_Keys_ExpiringKeys implements CVendor_Firebase_JWT_Contract_KeysInterface, CVendor_Firebase_JWT_Contract_ExpirableInterface {
    use CVendor_Firebase_JWT_Concern_KeysTrait;
    use CVendor_Firebase_JWT_Concern_ExpirableTrait;

    private function __construct() {
        $this->expirationTime = new DateTimeImmutable('0001-01-01'); // Very distant past :)
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return self
     */
    public static function withValuesAndExpirationTime(array $values, DateTimeImmutable $expirationTime) {
        $keys = new self();
        $keys->values = $values;
        $keys->expirationTime = $expirationTime;

        return $keys;
    }
}

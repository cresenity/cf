<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Immutable type of {@see TimeType}.
 */
class CDatabase_Type_TimeImmutableType extends CDatabase_Type_TimeType {
    /**
     * @inheritdoc
     */
    public function getName() {
        return CDatabase_Type::TIME_IMMUTABLE;
    }

    /**
     * @inheritdoc
     */
    public function convertToDatabaseValue($value, CDatabase_Platform $platform) {
        if (null === $value) {
            return $value;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value->format($platform->getTimeFormatString());
        }

        throw CDatabase_Schema_Exception_ConversionException::conversionFailedInvalidType(
            $value,
            $this->getName(),
            ['null', \DateTimeImmutable::class]
        );
    }

    /**
     * @inheritdoc
     */
    public function convertToPHPValue($value, CDatabase_Platform $platform) {
        if ($value === null || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        $dateTime = \DateTimeImmutable::createFromFormat('!' . $platform->getTimeFormatString(), $value);

        if (!$dateTime) {
            throw CDatabase_Schema_Exception_ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                $platform->getTimeFormatString()
            );
        }

        return $dateTime;
    }

    /**
     * @inheritdoc
     */
    public function requiresSQLCommentHint(CDatabase_Platform $platform) {
        return true;
    }
}

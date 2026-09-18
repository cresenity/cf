<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Type that maps a database SMALLINT to a PHP integer.
 */
class CDatabase_Type_SmallIntType extends CDatabase_Type implements CDatabase_Type_Interface_PhpIntegerMappingTypeInterface {
    /**
     * @inheritdoc
     */
    public function getName() {
        return CDatabase_Type::SMALLINT;
    }

    /**
     * @inheritdoc
     */
    public function getSQLDeclaration(array $fieldDeclaration, CDatabase_Platform $platform) {
        return $platform->getSmallIntTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * @inheritdoc
     */
    public function convertToPHPValue($value, CDatabase_Platform $platform) {
        return (null === $value) ? null : (int) $value;
    }

    /**
     * @inheritdoc
     */
    public function getBindingType() {
        return CDatabase_ParameterType::INTEGER;
    }
}

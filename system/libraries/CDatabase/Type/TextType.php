<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Type that maps an SQL CLOB to a PHP string.
 *
 * @since 2.0
 */
class CDatabase_Type_TextType extends CDatabase_Type {
    /**
     * @inheritdoc
     */
    public function getSQLDeclaration(array $fieldDeclaration, CDatabase_Platform $platform) {
        return $platform->getClobTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * @inheritdoc
     */
    public function convertToPHPValue($value, CDatabase_Platform $platform) {
        return (is_resource($value)) ? stream_get_contents($value) : $value;
    }

    /**
     * @inheritdoc
     */
    public function getName() {
        return CDatabase_Type::TEXT;
    }
}

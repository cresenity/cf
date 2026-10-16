<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Adapter generik: membungkus `Doctrine\DBAL\Platforms\AbstractPlatform` ASLI supaya memenuhi
 * type-hint `CDatabase_Platform` milik CF, mendelegasikan semua DDL ke Doctrine.
 *
 * @internal
 */
final class CDatabase_Platform_Doctrine extends CDatabase_Platform {
    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $doctrinePlatform;

    public function __construct(\Doctrine\DBAL\Platforms\AbstractPlatform $doctrinePlatform) {
        parent::__construct();
        $this->doctrinePlatform = $doctrinePlatform;
    }

    /**
     * @inheritdoc
     */
    public function getAlterTableSQL(CDatabase_Schema_Table_Diff $diff) {
        $doctrineDiff = CDatabase_Schema_DoctrineBridge::tableDiffToDoctrine($diff);

        return (array) $this->doctrinePlatform->getAlterTableSQL($doctrineDiff);
    }

    /**
     * @inheritdoc
     */
    public function getName() {
        return $this->doctrinePlatform->getName();
    }

    /**
     * @inheritdoc
     */
    public function getIdentifierQuoteCharacter() {
        return $this->doctrinePlatform->getIdentifierQuoteCharacter();
    }

    /**
     * @inheritdoc
     */
    public function quoteStringLiteral($str) {
        return $this->doctrinePlatform->quoteStringLiteral($str);
    }

    /**
     * @inheritdoc
     */
    public function getBooleanTypeDeclarationSQL(array $field) {
        return $this->doctrinePlatform->getBooleanTypeDeclarationSQL($field);
    }

    /**
     * @inheritdoc
     */
    public function getIntegerTypeDeclarationSQL(array $field) {
        return $this->doctrinePlatform->getIntegerTypeDeclarationSQL($field);
    }

    /**
     * @inheritdoc
     */
    public function getBigIntTypeDeclarationSQL(array $field) {
        return $this->doctrinePlatform->getBigIntTypeDeclarationSQL($field);
    }

    /**
     * @inheritdoc
     */
    public function getSmallIntTypeDeclarationSQL(array $field) {
        return $this->doctrinePlatform->getSmallIntTypeDeclarationSQL($field);
    }

    /**
     * @inheritdoc
     */
    public function getClobTypeDeclarationSQL(array $field) {
        return $this->doctrinePlatform->getClobTypeDeclarationSQL($field);
    }

    /**
     * @inheritdoc
     */
    public function getBlobTypeDeclarationSQL(array $field) {
        return $this->doctrinePlatform->getBlobTypeDeclarationSQL($field);
    }

    /**
     * Tidak pernah dipanggil - method publik yang memakainya sudah dioverride di atas.
     *
     * @inheritdoc
     */
    protected function getCommonIntegerTypeDeclarationSQL(array $columnDef) {
        return '';
    }

    /**
     * Tidak memengaruhi SQL yang dihasilkan - semua DDL didelegasikan ke $doctrinePlatform,
     * yang punya registry type-mapping internal sendiri.
     *
     * @inheritdoc
     */
    protected function initializeDoctrineTypeMappings() {
        $this->doctrineTypeMapping = [];
    }
}

<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 30, 2019, 3:49:00 PM
 */
class CModel_HasTranslation_Exception_AttributeIsNotTranslatable extends Exception {
    /**
     * @param string $key
     * @param CModel $model
     *
     * @return \static
     */
    public static function make($key, $model) {
        $translatableAttributes = implode(', ', $model->getTranslatableAttributes());
        return new static("Cannot translate attribute `{$key}` as it's not one of the translatable attributes: `$translatableAttributes`");
    }
}

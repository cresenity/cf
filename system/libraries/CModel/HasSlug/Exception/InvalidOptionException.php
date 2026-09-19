<?php

defined('SYSPATH') or die('No direct access allowed.');

class CModel_HasSlug_Exception_InvalidOptionException extends CModel_Exception {
    public static function missingFromField() {
        return new static('Could not determine which fields should be sluggified');
    }

    public static function missingSlugField() {
        return new static('Could not determine in which field the slug should be saved');
    }

    public static function invalidMaximumLength() {
        return new static('Maximum length should be greater than zero');
    }
}

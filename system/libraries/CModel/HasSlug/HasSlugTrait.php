<?php

defined('SYSPATH') or die('No direct access allowed.');

use CModel_HasSlug_Exception_InvalidOptionException as InvalidOptionException;

trait CModel_HasSlug_HasSlugTrait {
    /**
     * @var CModel_HasSlug_SlugOptions
     */
    protected $slugOptions;

    abstract public function getSlugOptions();

    protected static function bootHasSlugTrait() {
        // static::observe(new CModel_HasSlug_Observer());
        // Auto generate slugs early before validation
        if (static::usesValidating()) {
            static::validating(function (CModel $model) {
                if ($model->exists && $model->getSlugOptions()->generateSlugsOnUpdate) {
                    $model->generateSlugOnUpdate();
                } elseif (!$model->exists && $model->getSlugOptions()->generateSlugsOnCreate) {
                    $model->generateSlugOnCreate();
                }
            });
        } else {
            static::creating(function (CModel $model) {
                $model->generateSlugOnCreate();
            });
            static::updating(function (CModel $model) {
                $model->generateSlugOnUpdate();
            });
        }
    }

    protected function generateSlugOnCreate() {
        $this->slugOptions = $this->getSlugOptions();
        if (!$this->slugOptions->generateSlugsOnCreate) {
            return;
        }
        $this->addSlug();
    }

    protected function generateSlugOnUpdate() {
        $this->slugOptions = $this->getSlugOptions();
        if (!$this->slugOptions->generateSlugsOnUpdate) {
            return;
        }
        $this->addSlug();
    }

    public function generateSlug() {
        $this->slugOptions = $this->getSlugOptions();
        $this->addSlug();
    }

    protected function addSlug() {
        $this->ensureValidSlugOptions();
        $slug = $this->generateNonUniqueSlug();
        if ($this->slugOptions->generateUniqueSlugs) {
            $slug = $this->makeSlugUnique($slug);
        }
        $slugField = $this->slugOptions->slugField;
        $this->$slugField = $slug;
    }

    protected function generateNonUniqueSlug() {
        $slugField = $this->slugOptions->slugField;
        if ($this->hasCustomSlugBeenUsed() && !empty($this->$slugField)) {
            return $this->$slugField;
        }

        return cstr::slug($this->getSlugSourceString(), $this->slugOptions->slugSeparator, $this->slugOptions->slugLanguage);
    }

    protected function hasCustomSlugBeenUsed() {
        $slugField = $this->slugOptions->slugField;

        return $this->getOriginal($slugField) != $this->$slugField;
    }

    protected function getSlugSourceString() {
        if (is_callable($this->slugOptions->generateSlugFrom)) {
            $slugSourceString = call_user_func($this->slugOptions->generateSlugFrom, $this);

            return substr($slugSourceString, 0, $this->slugOptions->maximumLength);
        }
        $slugSourceString = c::collect($this->slugOptions->generateSlugFrom)
            ->map(function ($fieldName) {
                return carr::get($this, $fieldName, '');
            })
            ->implode($this->slugOptions->slugSeparator);

        return substr($slugSourceString, 0, $this->slugOptions->maximumLength);
    }

    protected function makeSlugUnique($slug) {
        $originalSlug = $slug;
        $i = 1;
        while ($this->otherRecordExistsWithSlug($slug) || $slug === '') {
            $slug = $originalSlug . $this->slugOptions->slugSeparator . $i++;
        }

        return $slug;
    }

    protected function otherRecordExistsWithSlug($slug) {
        $key = $this->getKey();
        if ($this->incrementing) {
            $key = $key ? $key : '0';
        }
        $query = static::where($this->slugOptions->slugField, $slug)
            ->where($this->getKeyName(), '!=', $key)
            ->withoutGlobalScopes();
        if ($this->usesSoftDelete()) {
            $query->withTrashed();
        }

        return $query->exists();
    }

    protected static function usesValidating() {
        if (in_array('CModel_Validating_ValidatingTrait', class_uses(static::class))) {
            return true;
        }

        return false;
    }

    protected function ensureValidSlugOptions() {
        if (is_array($this->slugOptions->generateSlugFrom) && !count($this->slugOptions->generateSlugFrom)) {
            throw InvalidOptionException::missingFromField();
        }
        if (!strlen($this->slugOptions->slugField)) {
            throw InvalidOptionException::missingSlugField();
        }
        if ($this->slugOptions->maximumLength <= 0) {
            throw InvalidOptionException::invalidMaximumLength();
        }
    }
}

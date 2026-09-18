<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Nov 29, 2020
 */
class CTemplate_Factory {
    /**
     * Get the appropriate view engine for the given path.
     *
     * @param string $path
     *
     * @return CView_EngineAbstract
     *
     * @throws \InvalidArgumentException
     */
    public function getEngineFromPath($path) {
        if (!$extension = $this->getExtension($path)) {
            throw new InvalidArgumentException("Unrecognized extension in file: {$path}.");
        }

        $engine = $this->extensions[$extension];

        return CView_EngineResolver::instance()->resolve($engine);
    }

    /**
     * Get the extension used by the view file.
     *
     * @param string $path
     *
     * @return string|null
     */
    protected function getExtension($path) {
        $extensions = array_keys($this->extensions);

        return carr::first($extensions, function ($value) use ($path) {
            return cstr::endsWith($path, '.' . $value);
        });
    }
}

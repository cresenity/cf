<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 20, 2020
 */
interface CTemplate_CompilerInterface {
    /**
     * Get the path to the compiled version of a view.
     *
     * @param string $path
     *
     * @return string
     */
    public function getCompiledPath($path);

    /**
     * Determine if the given view is expired.
     *
     * @param string $path
     *
     * @return bool
     */
    public function isExpired($path);

    /**
     * Compile the view at the given path.
     *
     * @param string $path
     *
     * @return void
     */
    public function compile($path);
}

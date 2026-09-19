<?php
interface CBase_CachesRoutesInterface {
    /**
     * Determine if the application routes are cached.
     *
     * @return bool
     */
    public function routesAreCached();

    /**
     * Get the path to the routes cache file.
     *
     * @return string
     */
    public function getCachedRoutesPath();
}

<?php

interface CApi_Session_DriverInterface {
    /**
     * Check session exists
     *
     * @param string $id
     *
     * @return bool
     */
    public function exists($id);

    /**
     * Read session
     *
     * @param string $id
     *
     * @return array
     */
    public function read($id);

    public function write($id);
}

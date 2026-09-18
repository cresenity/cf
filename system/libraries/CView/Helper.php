<?php

/**
 * Description of Helper
 */
class CView_Helper {
    /**
     * Normalize the given view name.
     *
     * @param string $name
     *
     * @return string
     */
    public static function normalize($name) {
        $delimiter = CView::HINT_PATH_DELIMITER;

        if (strpos($name, $delimiter) === false) {
            return str_replace('/', '.', $name);
        }

        list($namespace, $name) = explode($delimiter, $name);

        return $namespace . $delimiter . str_replace('/', '.', $name);
    }
}

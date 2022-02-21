<?php

class CRouting_RouteGroup {
    /**
     * Merge route groups into a new array.
     *
     * @param array $new
     * @param array $old
     * @param bool  $prependExistingPrefix
     *
     * @return array
     */
    public static function merge($new, $old, $prependExistingPrefix = true) {
        if (isset($new['domain'])) {
            unset($old['domain']);
        }

        $new = array_merge(static::formatAs($new, $old), [
            'namespace' => static::formatNamespace($new, $old),
            'prefix' => static::formatPrefix($new, $old, $prependExistingPrefix),
            'where' => static::formatWhere($new, $old),
        ]);

        return array_merge_recursive(carr::except(
            $old,
            ['namespace', 'prefix', 'where', 'as']
        ), $new);
    }

    /**
     * Format the namespace for the new group attributes.
     *
     * @param array $new
     * @param array $old
     *
     * @return null|string
     */
    protected static function formatNamespace($new, $old) {
        if (isset($new['namespace'])) {
            return isset($old['namespace']) && strpos($new['namespace'], '\\') !== 0
                    ? trim($old['namespace'], '\\') . '\\' . trim($new['namespace'], '\\')
                    : trim($new['namespace'], '\\');
        }

        return isset($old['namespace']) ? $old['namespace'] : null;
    }

    /**
     * Format the prefix for the new group attributes.
     *
     * @param array $new
     * @param array $old
     * @param bool  $prependExistingPrefix
     *
     * @return null|string
     */
    protected static function formatPrefix($new, $old, $prependExistingPrefix = true) {
        $old = isset($old['prefix']) ? $old['prefix'] : '';

        if ($prependExistingPrefix) {
            return isset($new['prefix']) ? trim($old, '/') . '/' . trim($new['prefix'], '/') : $old;
        } else {
            return isset($new['prefix']) ? trim($new['prefix'], '/') . '/' . trim($old, '/') : $old;
        }
    }

    /**
     * Format the "wheres" for the new group attributes.
     *
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected static function formatWhere($new, $old) {
        return array_merge(
            isset($old['where']) ? $old['where'] : [],
            isset($new['where']) ? $new['where'] : []
        );
    }

    /**
     * Format the "as" clause of the new group attributes.
     *
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected static function formatAs($new, $old) {
        if (isset($old['as'])) {
            $new['as'] = $old['as'] . (isset($new['as']) ? $new['as'] : '');
        }

        return $new;
    }
}

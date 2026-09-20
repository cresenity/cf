<?php

//@codingStandardsIgnoreStart
/**
 * @deprecated since 1.2 change to c::app()->user()
 */
class cuser {
    public static function get($id) {
        CF::deprecated(__METHOD__, 'c::app()->user()', '1.2', null, true);
        return c::app()->getUser($id);
    }

    public static function hit_count($user_id) {
        CF::deprecated(__METHOD__, 'c::app()->user()', '1.2', null, true);
        $db = c::db();

        return cdbutils::get_value('select count(*) from log_request where user_id=' . $db->escape($user_id));
    }
}
//@codingStandardsIgnoreEnd

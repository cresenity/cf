<?php

//@codingStandardsIgnoreStart
/**
 * @deprecated 1.2, use c::session()
 */
class csess {
    public static function get($key) {
        CF::deprecated(__METHOD__, 'c::session()', '1.2', null, true);
        $session = c::session();

        return $session->get($key);
    }

    public static function set($key, $val) {
        CF::deprecated(__METHOD__, 'c::session()', '1.2', null, true);
        $session = CSession::instance();

        return $session->set($key, $val);
    }

    public static function refresh_user_session() {
        CF::deprecated(__METHOD__, 'c::session()', '1.2', null, true);
        $user = static::get('user');
        if ($user != null) {
            $user = cuser::get($user->user_id);
            static::set('user', $user);
        }
    }

    public static function session_id() {
        CF::deprecated(__METHOD__, 'c::session()', '1.2', null, true);
        $session = CSession::instance();

        return $session->id();
    }
}
//@codingStandardsIgnoreEnd

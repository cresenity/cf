<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Feb 17, 2018, 3:42:16 AM
 * @see CObject
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Object {
    /**
     * @deprecated since version 1.2
     *
     * @return string
     */
    public function regenerate_id() {
        return $this->regenerateId();
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $classname
     */
    public function add_friend($classname) {
        return $this->addFriend($classname);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $domain
     */
    public function set_domain($domain) {
        return $this->setDomain($domain);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return string
     */
    public function class_name() {
        return $this->className();
    }
}
//@codingStandardsIgnoreEnd

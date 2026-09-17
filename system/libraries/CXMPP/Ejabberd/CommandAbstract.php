<?php

/**
 * @deprecated 1.8
 */
abstract class CXMPP_Ejabberd_CommandAbstract {
    abstract public function getCommandName();

    abstract public function getCommandData();
}

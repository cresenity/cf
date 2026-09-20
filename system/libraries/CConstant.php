<?php

defined('SYSPATH') or die('No direct access allowed.');

class CConstant {
    const ALL = 'ALL';

    const NONE = 'NONE';

    const NO_LABEL = 'NO';

    const YES_LABEL = 'YES';

    /**
     * Handler Constant.
     */
    const HANDLER_TYPE_DIALOG = CObservable_Listener_Handler::TYPE_DIALOG;

    const HANDLER_TYPE_REMOVE = CObservable_Listener_Handler::TYPE_REMOVE;

    const HANDLER_TYPE_SUBMIT = CObservable_Listener_Handler::TYPE_SUBMIT;

    const HANDLER_TYPE_EMPTY = CObservable_Listener_Handler::TYPE_EMPTY;

    const HANDLER_TYPE_CUSTOM = CObservable_Listener_Handler::TYPE_CUSTOM;

    const HANDLER_TYPE_APPEND = CObservable_Listener_Handler::TYPE_APPEND;

    const HANDLER_TYPE_RELOAD = CObservable_Listener_Handler::TYPE_RELOAD;

    /**
     * Path Constant. Aset bawaan framework kini di system/media (modules/ deprecated).
     */
    const CRESENITY_PATH = DOCROOT . 'system';

    const CRESENITY_MEDIA_PATH = DOCROOT . 'system/media';

    const CRESENITY_FONT_PATH = DOCROOT . 'system/media/font';

    const CRESENITY_IMAGE_PATH = DOCROOT . 'system/media/img';

    /**
     * Align Constant.
     */
    const ALIGN_LEFT = 'left';

    const ALIGN_RIGHT = 'right';

    const ALIGN_CENTER = 'center';

    /**
     * Table View Constant.
     */
    const TABLE_VIEW_COL = 'col';

    const TABLE_VIEW_ROW = 'row';

    public static function yesNoList() {
        return [self::NO_LABEL, self::YES_LABEL];
    }
}

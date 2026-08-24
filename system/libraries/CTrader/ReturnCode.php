<?php

class CTrader_ReturnCode {
    const SUCCESS = 0;

    const BAD_PARAM = 1;

    const OUT_OF_RANGE_START_INDEX = 2;

    const OUT_OF_RANGE_END_INDEX = 3;

    const ALLOC_ERROR = 4;

    const INTERNAL_ERROR = 5;

    const UNEVEN_PARAMETERS = 6;

    public static function getMessage($key) {
        $messageMap = [
            self::SUCCESS => 'Success',
            self::BAD_PARAM => 'Bad parameter',
            self::ALLOC_ERROR => 'Allocation error',
            self::OUT_OF_RANGE_START_INDEX => 'Out of range on start index',
            self::OUT_OF_RANGE_END_INDEX => 'Out of range on end index',
            self::INTERNAL_ERROR => 'Internal error',
            self::UNEVEN_PARAMETERS => 'The count of the input arrays do not match each other',
        ];

        return carr::get($messageMap, $key);
    }
}

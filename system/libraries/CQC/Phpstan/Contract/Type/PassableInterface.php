<?php

use PHPStan\Type\Type;

/** @internal */
interface CQC_Phpstan_Contract_Type_PassableContract {
    public function getType(): Type;

    public function setType(Type $type): void;
}

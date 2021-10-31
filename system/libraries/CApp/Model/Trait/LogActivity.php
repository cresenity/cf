<?php

/**
 * @property string  $createdby
 * @property string  $updatedby
 * @property CCarbon $created
 * @property CCarbon $updated
 * @property int     $status
 */
trait CApp_Model_Trait_LogActivity {
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
        $this->primaryKey = 'log_activity_id';
        $this->table = 'log_activity';
        $this->guarded = ['log_activity_id'];
    }
}

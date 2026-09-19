<?php

use Egulias\EmailValidator\Validation\DNSRecords;
use Egulias\EmailValidator\Validation\DNSGetRecordWrapper;

/**
 * Menjawab setiap pencarian DNS dengan satu record A, dipakai `email:dns`
 * saat `CValidation_Validator::fakeDnsLookups()` aktif.
 */
class CValidation_FakeDnsGetRecordWrapper extends DNSGetRecordWrapper {
    /**
     * @param string $host
     * @param int    $type
     *
     * @return \Egulias\EmailValidator\Validation\DNSRecords
     */
    public function getRecords(string $host, int $type): DNSRecords {
        return new DNSRecords([['type' => 'A']]);
    }
}

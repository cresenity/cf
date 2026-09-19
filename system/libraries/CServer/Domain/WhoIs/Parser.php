<?php

class CServer_Domain_WhoIs_Parser {
    public function parseDomain($data, $domain, $tld) {
        $domainWords = [
            'def' => 'Domain Name:',
            'com' => 'Domain Name:',
            'org' => 'Domain Name:',
            'net' => 'Domain Name:',
            'biz' => 'Domain Name:',
            'info' => 'Domain Name:',
            'name' => 'Domain Name:',
            'asia' => 'Domain Name:',
            'pl' => 'DOMAIN NAME:',
            'de' => 'Domain:',
            'fr' => 'domain:',
            'eu' => 'Domain:',
            'us' => 'Domain Name:',
            'cn' => 'Domain Name:',
            'hk' => 'Domain Name:',
            'tw' => 'Domain Name:'
        ];

        $domainsKeywords = [
            ['id' => ['Domain ID', 'Domain Name ID', 'Registry Domain ID', 'ROID']],
            ['domain' => ['Domain name', 'Domain Name', 'DOMAIN NAME', 'Domain', 'domain']],
            ['bundled_domain' => ['Bundled Domain Name']],
            ['dns' => ['Name Server', 'Nameservers', 'Name servers', 'Name Servers Information', 'Domain servers in listed order', 'nserver', 'nameservers']],
            ['registrar' => ['Registrar', 'registrar', 'Registrant', 'Registrar Name', 'Created by Registrar']],
            ['registrar_url' => ['Registrar URL', 'Registrar URL (registration services)']],
            ['sponsoring_registrar' => ['Sponsoring Registrar']],
            ['whois_server' => ['Whois Server', 'WHOIS SERVER', 'Registrar WHOIS Server']],
            ['created' => ['Creation Date', 'Created On', 'Registration Time', 'Domain Create Date', 'Domain Registration Date', 'Domain Name Commencement Date', 'created']],
            ['updated' => ['last-update', 'Updated Date', 'Domain Last Updated Date', 'last modified']],
            ['expires' => ['Expiry Date', 'Expiration Date', 'Expiration Time', 'Domain Expiration Date', 'Registrar Registration Expiration Date', 'Record expires on', 'Registry Expiry Date', 'renewal date']],
            ['status' => ['Status', 'status', 'Domain Status']],
        ];

        $toBeParseKeywords = [];

        foreach ($domainsKeywords as $domainKeywords) {
            foreach ($domainKeywords as $var => $keywords) {
                foreach ($keywords as $keyword) {
                    $toBeParseKeywords[$keyword] = $var;
                }
            }
        }

        $contactInfoCategories = [
            ['id' => ['ID']],
            ['name' => ['Name']],
            ['organization' => ['Organization']],
            ['city' => ['City']],
            ['country' => ['Country', 'Country/Economy']],
            ['address' => ['Street', 'Address', 'Address1', 'Address2', 'Address3', 'Address4']],
            ['state_province' => ['State/Province']],
            ['postal_code' => ['Postal Code']],
            ['email' => ['Email']],
            ['phone' => ['Phone', 'Phone Number']],
            ['phone_ext' => ['Phone Ext', 'Phone Ext.']],
            ['fax' => ['Fax', 'FAX', 'Facsimile Number']],
            ['fax_ext' => ['Fax Ext', 'FAX Ext.']]
        ];

        $contactInfoKeywords = [
            ['admin' => ['Admin', 'Administrative', 'Administrative Contact']],
            ['registrant' => ['Registrant']],
            ['tech' => ['Tech', 'Technical', 'Technical Contact']],
            ['billing' => ['Bill', 'Billing', 'Billing Contact']]
        ];

        foreach ($contactInfoKeywords as $contactInfoKeyword) {
            foreach ($contactInfoKeyword as $contactKey => $contactKeywords) {
                foreach ($contactKeywords as $contactKeyword) {
                    foreach ($contactInfoCategories as $contactInfoCategory) {
                        foreach ($contactInfoCategory as $var => $keywords) {
                            foreach ($keywords as $keyword) {
                                $toBeParseKeywords[$contactKeyword . ' ' . $keyword] = $contactKey . '_' . $var;
                            }
                        }
                    }
                }
            }
        }

        if (array_key_exists($tld, $domainWords)) {
            $domainWord = $domainWords[$tld];
        } else {
            $domainWord = $domainWords['def'];
        }

        $data = array_filter(array_map(function ($el) {
            return CServer_Domain_WhoIs_Parser::ifWhiteSpace($el);
        }, $data));
        //print_r($data);
        $parseResult = $this->parse($data, $domain, $domainWord, $toBeParseKeywords, true);

        $needToBeSingles = ['domain', 'id'];
        foreach ($needToBeSingles as $needToBeSingle) {
            if (!empty($parseResult[$needToBeSingle])) {
                $parseResult[$needToBeSingle] = $parseResult[$needToBeSingle][0];
            }
        }
        if (!empty($parseResult['domain'])) {
            $parseResult['domain'] = strtolower($parseResult['domain']);
        }

        $needToBeParsedTimeKeys = ['created', 'expires', 'updated'];
        foreach ($needToBeParsedTimeKeys as $needToBeParsedTimeKey) {
            if (!empty($parseResult[$needToBeParsedTimeKey])) {
                $domainTime = $parseResult[$needToBeParsedTimeKey][0];
                //if ( (date_parse($domainTime)['warning_count'] == 0) && (date_parse($domainTime)['error_count'] == 0) ) {
                if (date_parse($domainTime)['error_count'] == 0) {
                    $parsed_date = date_parse($domainTime);
                    $parseResult[$needToBeParsedTimeKey . '_parsed'] = $parsed_date;
                    $date_string = date('Y-m-d H:i:s', mktime($parsed_date['hour'], $parsed_date['minute'], $parsed_date['second'], $parsed_date['month'], $parsed_date['day'], $parsed_date['year']));
                    $parseResult[$needToBeParsedTimeKey . '_parsed_string'] = $date_string;
                } else {
                    $formatedTime = $domainTime;
                    $formatedTime = str_replace('T', ' ', $formatedTime);
                    $formatedTime = str_replace('Z', ' ', $formatedTime);
                    $formatedTime = trim($formatedTime);
                    $parsed_date = date_parse_from_format('Y-m-d H:i:s.u', $formatedTime);
                    $parseResult[$needToBeParsedTimeKey . '_parsed'] = $parsed_date;
                    $date_string = date('Y-m-d H:i:s', mktime($parsed_date['hour'], $parsed_date['minute'], $parsed_date['second'], $parsed_date['month'], $parsed_date['day'], $parsed_date['year']));
                    $parseResult[$needToBeParsedTimeKey . '_parsed_string'] = $date_string;
                }
            }
        }

        foreach ($parseResult as $eachParseResultKey => $eachParseResult) {
            if (substr($eachParseResultKey, 0, 6) == 'admin_') {
                unset($parseResult[$eachParseResultKey]);
                $key = substr($eachParseResultKey, 6);
                if ($key != 'address') {
                    $eachParseResult = $eachParseResult[0];
                }
                $parseResult['admin'][$key] = $eachParseResult;
            }
            if (substr($eachParseResultKey, 0, 11) == 'registrant_') {
                unset($parseResult[$eachParseResultKey]);
                $key = substr($eachParseResultKey, 11);
                if ($key != 'address') {
                    $eachParseResult = $eachParseResult[0];
                }
                $parseResult['registrant'][$key] = $eachParseResult;
            }
            if (substr($eachParseResultKey, 0, 5) == 'tech_') {
                unset($parseResult[$eachParseResultKey]);
                $key = substr($eachParseResultKey, 5);
                if ($key != 'address') {
                    $eachParseResult = $eachParseResult[0];
                }
                $parseResult['tech'][$key] = $eachParseResult;
            }
            if (substr($eachParseResultKey, 0, 8) == 'billing_') {
                unset($parseResult[$eachParseResultKey]);
                $key = substr($eachParseResultKey, 8);
                if ($key != 'address') {
                    $eachParseResult = $eachParseResult[0];
                }
                $parseResult['bill'][$key] = $eachParseResult;
            }
        }

        $needToBeLowerCasedValueFathers = ['registrant', 'admin', 'tech', 'billing'];
        foreach ($needToBeLowerCasedValueFathers as $needToBeLowerCasedValueFather) {
            if (!empty($parseResult[$needToBeLowerCasedValueFather])) {
                $needToBeLowerCasedValue = carr::get($parseResult[$needToBeLowerCasedValueFather], 'email');
                if (!empty($needToBeLowerCasedValue)) {
                    $parseResult[$needToBeLowerCasedValueFather]['email'] = strtolower($parseResult[$needToBeLowerCasedValueFather]['email']);
                }
            }
        }

        $needToAddCompletelyAddressArrays = ['registrant', 'admin', 'tech', 'billing'];
        foreach ($needToAddCompletelyAddressArrays as $needToAddCompletelyAddressArray) {
            if (!empty($parseResult[$needToAddCompletelyAddressArray])) {
                if (!empty($parseResult[$needToAddCompletelyAddressArray]['address'])) {
                    $parseResult[$needToAddCompletelyAddressArray]['completely_address'] = implode(', ', $parseResult[$needToAddCompletelyAddressArray]['address']);
                    /* WILL CITY & COUNTRY WILL BE INCLUDE IN FULL ADDRESS? */
                    /*$allExtraInfomations = ["city", "country"];
                    foreach($allExtraInfomations as $extraInfomation){
                        if(!empty($parseResult[$needToAddCompletelyAddressArray][$extraInfomation])){
                            $parseResult[$needToAddCompletelyAddressArray]["completely_address"] .= ", ".$parseResult[$needToAddCompletelyAddressArray][$extraInfomation];
                        }
                    }*/
                    $parseResult[$needToAddCompletelyAddressArray]['completely_address'] = ucwords(strtolower($parseResult[$needToAddCompletelyAddressArray]['completely_address']));
                }
            }
        }

        return $parseResult;
    }

    private function parse($data, $domain, $domainWord, $keywords, $breakOnEnter) {
        $found = false;
        $domainWordLen = strlen($domainWord);

        $res = [];
        $keyword = null;
        foreach ($data as $d) {
            $d = trim($d);

            if ($d == '') {
                if ($breakOnEnter) {
                    $found = false;
                }

                continue;
            }

            $pos = strpos($d, $domainWord);
            if ($pos !== false) {
                $dom = strtolower(trim(substr($d, $pos + $domainWordLen)));
                if ($dom == $domain) {
                    $found = true;
                }
            }

            if ($found) {
                $pos = strpos($d, ':');
                if ($pos !== false) {
                    $keyword = substr($d, 0, $pos);

                    if (isset($keywords[$keyword])) {
                        $t = trim(substr($d, $pos + 1));
                        if ($t != '') {
                            $res[$keywords[$keyword]][] = $t;
                        }
                    } else {
                        $keyword = '';
                    }
                } elseif ($keyword) {
                    $res[$keywords[$keyword]][] = $d;
                }
            }
        }

        return $res;
    }

    private static function ifWhiteSpace($el) {
        return trim($el, ' ');
    }
}

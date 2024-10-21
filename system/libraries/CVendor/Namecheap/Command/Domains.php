<?php

class CVendor_Namecheap_Command_Domains extends CVendor_Namecheap_AbstractCommand {
    protected $command = 'namecheap.domains.';

    /**
     * @todo Returns a list of domains for the particular user..
     *
     * @param null|string $searchTerm Possible values are ALL, EXPIRING, or EXPIRED | Default : ALL
     * @param null|string $listType   Keyword to look for in the domain list
     * @param null|int    $page       Page to return | Default Value: 1
     * @param null|int    $pageSize   Number of domains to be listed on a page. Minimum value is 10, and maximum value is 100. | Default Value: 20
     * @param null|string $sortBy     Possible values are NAME, NAME_DESC, EXPIREDATE, EXPIREDATE_DESC, CREATEDATE, CREATEDATE_DESC
     */
    public function getList($searchTerm = null, $listType = null, $page = null, $pageSize = null, $sortBy = null) {
        $data = [
            'ListType' => $listType,
            'SearchTerm' => $searchTerm,
            'Page' => $page,
            'PageSize' => $pageSize,
            'SortBy' => $sortBy
        ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Gets contact information of the requested domain.
     *
     * @param string $domainName Domain to get contacts
     */
    public function getContacts($domainName) {
        return $this->api->get($this->command . __FUNCTION__, ['DomainName' => $domainName]);
    }

    /**
     * @todo Registers a new domain name.
     *
     * @param string|domainName|Req : Domain name to register
     * @param num|years|Req : Number of years to register Default Value: 2
     * @param string|registrantFirstName|Req : First name of the Registrant user
     * @param string|registrantLastName|Req : Second name of the Registrant user
     * @param string|registrantAddress1|Req : Address1 of the Registrant user
     * @param string|registrantCity|Req : City of the Registrant user
     * @param string|registrantStateProvince|Req : State/Province of the Registrant user
     * @param string|registrantPostalCode|Req : PostalCode of the Registrant user
     * @param string|registrantCountry|Req : Country of the Registrant user
     * @param string|registrantPhone|Req : Phone number in the format +NNN.NNNNNNNNNN
     * @param string|registrantEmailAddress|Req : Email address of the Registrant user
     * @param string|registrantOrganizationName|Opt : Organization of the Registrant user
     * @param string|registrantJobTitle|Opt : Job title of the Registrant user
     * @param string|registrantAddress2|Opt : Address2 of the Registrant user
     * @param string|registrantStateProvinceChoice|Opt : StateProvinceChoice of the Registrant user
     * @param string|registrantPhoneExt|Opt : PhoneExt of the Registrant user
     * @param string|registrantFax|Opt : Fax number in the format +NNN.NNNNNNNNNN
     * @param string|techFirstName|Req : First name of the tech user
     * @param string|techLastName|Req : Second name of the tech user
     * @param string|techAddress1|Req : Address1 of the tech user
     * @param string|techCity|Req : City of the tech user
     * @param string|techStateProvince|Req : State/Province of the tech user
     * @param string|techPostalCode|Req : PostalCode of the tech user
     * @param string|techCountry|Req : Country of the tech user
     * @param string|techPhone|Req : Phone number in the format +NNN.NNNNNNNNNN
     * @param string|techEmailAddress|Req : Email address of the tech user
     * @param string|techOrganizationName|Opt : Organization of the tech user
     * @param string|techJobTitle|Opt : Job title of the tech user
     * @param string|techAddress2|Opt : Address2 of the tech user
     * @param string|techStateProvinceChoice|Opt : StateProvinceChoice of the tech user
     * @param string|techPhoneExt|Opt : PhoneExt of the tech user
     * @param string|techFax|Opt : Fax number in the format +NNN.NNNNNNNNNN
     * @param string|adminFirstName|Req : First name of the admin user
     * @param string|adminLastName|Req : Second name of the admin user
     * @param string|adminAddress1|Req : Address1 of the admin user
     * @param string|adminCity|Req : City of the admin user
     * @param string|adminStateProvince|Req : State/Province of the admin user
     * @param string|adminPostalCode|Req : PostalCode of the admin user
     * @param string|adminCountry|Req : Country of the admin user
     * @param string|adminPhone|Req : Phone number in the format +NNN.NNNNNNNNNN
     * @param string|adminEmailAddress|Req : Email address of the admin user
     * @param string|adminOrganizationName|Opt : Organization of the admin user
     * @param string|adminJobTitle|Opt : Job title of the admin user
     * @param string|adminAddress2|Opt : Address2 of the admin user
     * @param string|adminStateProvinceChoice|Opt : StateProvinceChoice of the admin user
     * @param string|adminPhoneExt|Opt : PhoneExt of the admin user
     * @param string|adminFax|Opt : Fax number in the format +NNN.NNNNNNNNNN
     * @param string|auxBillingFirstName|Req : First name of the auxBilling user
     * @param string|auxBillingLastName|Req : Second name of the auxBilling user
     * @param string|auxBillingAddress1|Req : Address1 of the auxBilling user
     * @param string|auxBillingCity|Req : City of the auxBilling user
     * @param string|auxBillingStateProvince|Req : State/Province of the auxBilling user
     * @param string|auxBillingPostalCode|Req : PostalCode of the auxBilling user
     * @param string|auxBillingCountry|Req : Country of the auxBilling user
     * @param string|auxBillingPhone|Req : Phone number in the format +NNN.NNNNNNNNNN
     * @param string|auxBillingEmailAddress|Req : Email address of the auxBilling user
     * @param string|auxBillingOrganizationName|Opt : Organization of the auxBilling user
     * @param string|auxBillingJobTitle|Opt : Job title of the auxBilling user
     * @param string|auxBillingAddress2|Opt : Address2 of the auxBilling user
     * @param string|auxBillingStateProvinceChoice|Opt : StateProvinceChoice of the auxBilling user
     * @param string|auxBillingPhoneExt|Opt : PhoneExt of the auxBilling user
     * @param string|auxBillingFax|Opt : Fax number in the format +NNN.NNNNNNNNNN
     * @param string|billingFirstName|Opt : First name of the billing user
     * @param string|billingLastName|Opt : Second name of the billing user
     * @param string|billingAddress1|Opt : Address1 of the billing user
     * @param string|billingCity|Opt : City of the billing user
     * @param string|billingStateProvince|Opt : State/Province of the billing user
     * @param string|billingPostalCode|Opt : PostalCode of the billing user
     * @param string|billingCountry|Opt : Country of the billing user
     * @param string|billingPhone|Opt : Phone number in the format +NNN.NNNNNNNNNN
     * @param string|billingEmailAddress|Opt : Email address of the billing user
     * @param string|billingAddress2|Opt : Address2 of the billing user
     * @param string|billingStateProvinceChoice|Opt : StateProvinceChoice of the billing user
     * @param string|billingPhoneExt|Opt : PhoneExt of the billing user
     * @param string|billingFax|Opt : Fax number in the format +NNN.NNNNNNNNNN
     * @param string|idnCode|Opt : Code of Internationalized Domain Name (please refer to the note below)
     * @param string|nameservers|Opt : Comma-separated list of custom nameservers to be associated with the domain name
     * @param string|addFreeWhoisguard|Opt : Adds free WhoisGuard for the domain Default Value: no
     * @param string|wGEnabled|Opt : Enables free WhoisGuard for the domain Default Value: no
     * @param bool|isPremiumDomain|Opt : Indication if the domain name is premium
     * @param Currency|premiumPrice|Opt : Registration price for the premium domain
     * @param Currency|eapFee|Opt : Purchase fee for the premium domain during Early Access Program (EAP)*
     */
    public function create(array $domainInfo, array $contactInfo) {
        $data = $this->parseDomainData($domainInfo, $contactInfo);

        return $this->api->post($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Returns a list of tlds available in namecheap
     */
    public function getTldList() {
        return $this->api->get($this->command . __FUNCTION__);
    }

    /**
     * @todo Sets contact information for the domain.
     *
     * @param string|registrantFirstName|Req : First name of the Registrant user
     * @param string|registrantLastName|Req : Second name of the Registrant user
     * @param string|registrantAddress1|Req : Address1 of the Registrant user
     * @param string|registrantCity|Req : City of the Registrant user
     * @param string|registrantStateProvince|Req : State/Province of the Registrant user
     * @param string|registrantPostalCode|Req : PostalCode of the Registrant user
     * @param string|registrantCountry|Req : Country of the Registrant user
     * @param string|registrantPhone|Req : Phone number in the format +NNN.NNNNNNNNNN
     * @param string|registrantEmailAddress|Req : Email address of the Registrant user
     * @param string|registrantOrganizationName|Opt : Organization of the Registrant user
     * @param string|registrantJobTitle|Opt : Job title of the Registrant user
     * @param string|registrantAddress2|Opt : Address2 of the Registrant user
     * @param string|registrantStateProvinceChoice|Opt : StateProvinceChoice of the Registrant user
     * @param string|registrantPhoneExt|Opt : PhoneExt of the Registrant user
     * @param string|registrantFax|Opt : Fax number in the format +NNN.NNNNNNNNNN
     * @param string|techFirstName|Req : First name of the tech user
     * @param string|techLastName|Req : Second name of the tech user
     * @param string|techAddress1|Req : Address1 of the tech user
     * @param string|techCity|Req : City of the tech user
     * @param string|techStateProvince|Req : State/Province of the tech user
     * @param string|techPostalCode|Req : PostalCode of the tech user
     * @param string|techCountry|Req : Country of the tech user
     * @param string|techPhone|Req : Phone number in the format +NNN.NNNNNNNNNN
     * @param string|techEmailAddress|Req : Email address of the tech user
     * @param string|techOrganizationName|Opt : Organization of the tech user
     * @param string|techJobTitle|Opt : Job title of the tech user
     * @param string|techAddress2|Opt : Address2 of the tech user
     * @param string|techStateProvinceChoice|Opt : StateProvinceChoice of the tech user
     * @param string|techPhoneExt|Opt : PhoneExt of the tech user
     * @param string|techFax|Opt : Fax number in the format +NNN.NNNNNNNNNN
     * @param string|adminFirstName|Req : First name of the admin user
     * @param string|adminLastName|Req : Second name of the admin user
     * @param string|adminAddress1|Req : Address1 of the admin user
     * @param string|adminCity|Req : City of the admin user
     * @param string|adminStateProvince|Req : State/Province of the admin user
     * @param string|adminPostalCode|Req : PostalCode of the admin user
     * @param string|adminCountry|Req : Country of the admin user
     * @param string|adminPhone|Req : Phone number in the format +NNN.NNNNNNNNNN
     * @param string|adminEmailAddress|Req : Email address of the admin user
     * @param string|adminOrganizationName|Opt : Organization of the admin user
     * @param string|adminJobTitle|Opt : Job title of the admin user
     * @param string|adminAddress2|Opt : Address2 of the admin user
     * @param string|adminStateProvinceChoice|Opt : StateProvinceChoice of the admin user
     * @param string|adminPhoneExt|Opt : PhoneExt of the admin user
     * @param string|adminFax|Opt : Fax number in the format +NNN.NNNNNNNNNN
     * @param string|auxBillingFirstName|Req : First name of the auxBilling user
     * @param string|auxBillingLastName|Req : Second name of the auxBilling user
     * @param string|auxBillingAddress1|Req : Address1 of the auxBilling user
     * @param string|auxBillingCity|Req : City of the auxBilling user
     * @param string|auxBillingStateProvince|Req : State/Province of the auxBilling user
     * @param string|auxBillingPostalCode|Req : PostalCode of the auxBilling user
     * @param string|auxBillingCountry|Req : Country of the auxBilling user
     * @param string|auxBillingPhone|Req : Phone number in the format +NNN.NNNNNNNNNN
     * @param string|auxBillingEmailAddress|Req : Email address of the auxBilling user
     * @param string|auxBillingOrganizationName|Opt : Organization of the auxBilling user
     * @param string|auxBillingJobTitle|Opt : Job title of the auxBilling user
     * @param string|auxBillingAddress2|Opt : Address2 of the auxBilling user
     * @param string|auxBillingStateProvinceChoice|Opt : StateProvinceChoice of the auxBilling user
     * @param string|auxBillingPhoneExt|Opt : PhoneExt of the auxBilling user
     * @param string|auxBillingFax|Opt : Fax number in the format +NNN.NNNNNNNNNN
     */
    public function setContacts(array $domainInfo, array $contactInfo) {
        $data = $this->parseContactInfo($contactInfo);

        return $this->api->post($this->command . __FUNCTION__, array_merge($domainInfo, $data));
    }

    /**
     * @todo Checks the availability of domains.
     *
     * @param string|array $domain The list of domains or a single domain name
     */
    public function check($domain) {
        if (is_array($domain)) {
            $domainString = implode(',', $domain);
            $data['DomainList'] = $domainString;
        } elseif (is_string($domain)) {
            $data['DomainList'] = $domain;
        }

        $response = $this->api->get($this->command . __FUNCTION__, $data);

        return $response;
    }

    /**
     * @todo Reactivates an expired domain.
     *
     * @param string      $domainName      Domain name to reactivate
     * @param null|string $promotionCode   Promotional (coupon) code for reactivating the domain
     * @param null|int    $yearsToAdd      Number of years after expiry
     * @param null|bool   $isPremiumDomain Indication if the domain name is premium
     * @param null|float  $premiumPrice    Reactivation price for the premium domain
     */
    public function reactivate($domainName, $promotionCode = null, $yearsToAdd = null, $isPremiumDomain = null, $premiumPrice = null) {
        $data = [
            'DomainName' => $domainName,
            'PromotionCode' => $promotionCode,
            'YearsToAdd' => $yearsToAdd,
            'IsPremiumDomain' => $isPremiumDomain,
            'PremiumPrice' => $premiumPrice,
        ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Renew an expired domain.
     *
     * @param string      $domainName      Domain name to reactivate
     * @param int         $years           Number of years to renew
     * @param null|string $promotionCode   Promotional (coupon) code for renewing the domain
     * @param null|bool   $isPremiumDomain Indication if the domain name is premium
     * @param null|float  $premiumPrice    Reactivation price for the premium domain
     */
    public function renew($domainName, $years, $promotionCode = null, $isPremiumDomain = null, $premiumPrice = null) {
        $data = [
            'DomainName' => $domainName,
            'Years' => $years,
            'PromotionCode' => $promotionCode,
            'IsPremiumDomain' => $isPremiumDomain,
            'PremiumPrice' => $premiumPrice,
        ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Gets the RegistrarLock status of the requested domain.
     *
     * @param string $domainName Domain name to get status for
     */
    public function getRegistrarLock($domainName) {
        $data = ['DomainName' => $domainName];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Sets the RegistrarLock status for a domain.
     *
     * @param string      $domainName Domain name to get status for
     * @param null|string $lockAction Possible values: LOCK, UNLOCK. | Default Value: LOCK.
     */
    public function setRegistrarLock($domainName, $lockAction = null) {
        $data = [
            'DomainName' => $domainName,
            'LockAction' => $lockAction,
        ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Returns information about the requested domain.
     *
     * @param string      $domainName Domain name for which domain information needs to be requested
     * @param null|string $hostName   Hosted domain name for which domain information needs to be requested
     */
    public function getInfo($domainName, $hostName = null) {
        $data = [
            'DomainName' => $domainName,
            'HostName' => $hostName,
        ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    # Helper methods

    private function parseDomainData($dd, $cd) {
        //Extended attributes : not used
        $domainInfo = [
            #Req
            'DomainName' => !empty($dd['domainName']) ? $dd['domainName'] : null,
            'Years' => !empty($dd['years']) ? $dd['years'] : null,
            #Opt
            'PromotionCode' => !empty($dd['promotionCode']) ? $dd['promotionCode'] : null,
        ];
        $billing = [
            #opt
            'BillingFirstName' => !empty($cd['billingFirstName']) ? $cd['billingFirstName'] : null,
            'BillingLastName' => !empty($cd['billingLastName']) ? $cd['billingLastName'] : null,
            'BillingAddress1' => !empty($cd['billingAddress1']) ? $cd['billingAddress1'] : null,
            'BillingAddress2' => !empty($cd['billingAddress2']) ? $cd['billingAddress2'] : null,
            'BillingCity' => !empty($cd['billingCity']) ? $cd['billingCity'] : null,
            'BillingStateProvince' => !empty($cd['billingStateProvince']) ? $cd['billingStateProvince'] : null,
            'BillingStateProvinceChoice' => !empty($cd['billingStateProvinceChoice']) ? $cd['billingStateProvinceChoice'] : null,
            'BillingPostalCode' => !empty($cd['billingPostalCode']) ? $cd['billingPostalCode'] : null,
            'BillingCountry' => !empty($cd['billingCountry']) ? $cd['billingCountry'] : null,
            'BillingPhone' => !empty($cd['billingPhone']) ? $cd['billingPhone'] : null,
            'BillingPhoneExt' => !empty($cd['billingPhoneExt']) ? $cd['billingPhoneExt'] : null,
            'BillingFax' => !empty($cd['billingFax']) ? $cd['billingFax'] : null,
            'BillingEmailAddress' => !empty($cd['billingEmailAddress']) ? $cd['billingEmailAddress'] : null,
        ];
        $extra = [
            #Req
            #opt
            'IdnCode' => !empty($dd['idnCode']) ? $dd['idnCode'] : null,
            'Nameservers' => !empty($dd['nameservers']) ? $dd['nameservers'] : null,
            'AddFreeWhoisguard' => !empty($dd['addFreeWhoisguard']) ? $dd['addFreeWhoisguard'] : null,
            'WGEnabled' => !empty($dd['wGEnabled']) ? $dd['wGEnabled'] : null,
            'IsPremiumDomain' => !empty($dd['isPremiumDomain']) ? $dd['isPremiumDomain'] : null,
            'PremiumPrice' => !empty($dd['premiumPrice']) ? $dd['premiumPrice'] : null,
            'EapFee' => !empty($dd['eapFee']) ? $dd['eapFee'] : null,
        ];

        return array_merge($domainInfo, $this->parseContactInfo($cd), $billing, $extra);
    }

    private function parseContactInfo($d) {
        $requiredFields = [
            'FirstName', 'LastName', 'Address1', 'City', 'StateProvince', 'PostalCode', 'Country', 'Phone', 'EmailAddress',
        ];
        $requiredRegistrant = array_map(function ($f) {
            return 'Registrant' . $f;
        }, $requiredFields);
        $requiredTech = array_map(function ($f) {
            return 'Tech' . $f;
        }, $requiredFields);
        $requiredAdmin = array_map(function ($f) {
            return 'Admin' . $f;
        }, $requiredFields);
        $requiredAuxBilling = array_map(function ($f) {
            return 'AuxBilling' . $f;
        }, $requiredFields);
        $registrant = [
            'RegistrantFirstName' => !empty($d['registrantFirstName']) ? $d['registrantFirstName'] : null,
            'RegistrantLastName' => !empty($d['registrantLastName']) ? $d['registrantLastName'] : null,
            'RegistrantAddress1' => !empty($d['registrantAddress1']) ? $d['registrantAddress1'] : null,
            'RegistrantCity' => !empty($d['registrantCity']) ? $d['registrantCity'] : null,
            'RegistrantStateProvince' => !empty($d['registrantStateProvince']) ? $d['registrantStateProvince'] : null,
            'RegistrantPostalCode' => !empty($d['registrantPostalCode']) ? $d['registrantPostalCode'] : null,
            'RegistrantCountry' => !empty($d['registrantCountry']) ? $d['registrantCountry'] : null,
            'RegistrantPhone' => !empty($d['registrantPhone']) ? $d['registrantPhone'] : null,
            'RegistrantEmailAddress' => !empty($d['registrantEmailAddress']) ? $d['registrantEmailAddress'] : null,
            #opt
            'RegistrantOrganizationName' => !empty($d['RegistrantOrganizationName']) ? $d['RegistrantOrganizationName'] : null,
            'RegistrantJobTitle' => !empty($d['registrantJobTitle']) ? $d['registrantJobTitle'] : null,
            'RegistrantAddress2' => !empty($d['registrantAddress2']) ? $d['registrantAddress2'] : null,
            'RegistrantStateProvinceChoice' => !empty($d['registrantStateProvinceChoice']) ? $d['registrantStateProvinceChoice'] : null,
            'RegistrantPhoneExt' => !empty($d['registrantPhoneExt']) ? $d['registrantPhoneExt'] : null,
            'RegistrantFax' => !empty($d['registrantFax']) ? $d['registrantFax'] : null,
        ];

        $tech = [
            #Req
            'TechFirstName' => !empty($d['techFirstName']) ? $d['techFirstName'] : null,
            'TechLastName' => !empty($d['techLastName']) ? $d['techLastName'] : null,
            'TechAddress1' => !empty($d['techAddress1']) ? $d['techAddress1'] : null,
            'TechCity' => !empty($d['techCity']) ? $d['techCity'] : null,
            'TechStateProvince' => !empty($d['techStateProvince']) ? $d['techStateProvince'] : null,
            'TechPostalCode' => !empty($d['techPostalCode']) ? $d['techPostalCode'] : null,
            'TechCountry' => !empty($d['techCountry']) ? $d['techCountry'] : null,
            'TechPhone' => !empty($d['techPhone']) ? $d['techPhone'] : null,
            'TechEmailAddress' => !empty($d['techEmailAddress']) ? $d['techEmailAddress'] : null,
            #opt
            'TechOrganizationName' => !empty($d['techOrganizationName']) ? $d['techOrganizationName'] : null,
            'TechJobTitle' => !empty($d['techJobTitle']) ? $d['techJobTitle'] : null,
            'TechAddress2' => !empty($d['techAddress2']) ? $d['techAddress2'] : null,
            'TechStateProvinceChoice' => !empty($d['techStateProvinceChoice']) ? $d['techStateProvinceChoice'] : null,
            'TechPhoneExt' => !empty($d['techPhoneExt']) ? $d['techPhoneExt'] : null,
            'TechFax' => !empty($d['techFax']) ? $d['techFax'] : null,
        ];
        $admin = [
            #Req
            'AdminFirstName' => !empty($d['adminFirstName']) ? $d['adminFirstName'] : null,
            'AdminLastName' => !empty($d['adminLastName']) ? $d['adminLastName'] : null,
            'AdminAddress1' => !empty($d['adminAddress1']) ? $d['adminAddress1'] : null,
            'AdminCity' => !empty($d['adminCity']) ? $d['adminCity'] : null,
            'AdminStateProvince' => !empty($d['adminStateProvince']) ? $d['adminStateProvince'] : null,
            'AdminPostalCode' => !empty($d['adminPostalCode']) ? $d['adminPostalCode'] : null,
            'AdminCountry' => !empty($d['adminCountry']) ? $d['adminCountry'] : null,
            'AdminPhone' => !empty($d['adminPhone']) ? $d['adminPhone'] : null,
            'AdminEmailAddress' => !empty($d['adminEmailAddress']) ? $d['adminEmailAddress'] : null,
            #opt
            'AdminOrganizationName' => !empty($d['adminOrganizationName']) ? $d['adminOrganizationName'] : null,
            'AdminJobTitle' => !empty($d['adminJobTitle']) ? $d['adminJobTitle'] : null,
            'AdminAddress2' => !empty($d['adminAddress2']) ? $d['adminAddress2'] : null,
            'AdminStateProvinceChoice' => !empty($d['adminStateProvinceChoice']) ? $d['adminStateProvinceChoice'] : null,
            'AdminPhoneExt' => !empty($d['adminPhoneExt']) ? $d['adminPhoneExt'] : null,
            'AdminFax' => !empty($d['adminFax']) ? $d['adminFax'] : null,
        ];
        $auxBilling = [
            #Req
            'AuxBillingFirstName' => !empty($d['auxBillingFirstName']) ? $d['auxBillingFirstName'] : null,
            'AuxBillingLastName' => !empty($d['auxBillingLastName']) ? $d['auxBillingLastName'] : null,
            'AuxBillingAddress1' => !empty($d['auxBillingAddress1']) ? $d['auxBillingAddress1'] : null,
            'AuxBillingCity' => !empty($d['auxBillingCity']) ? $d['auxBillingCity'] : null,
            'AuxBillingStateProvince' => !empty($d['auxBillingStateProvince']) ? $d['auxBillingStateProvince'] : null,
            'AuxBillingPostalCode' => !empty($d['auxBillingPostalCode']) ? $d['auxBillingPostalCode'] : null,
            'AuxBillingCountry' => !empty($d['auxBillingCountry']) ? $d['auxBillingCountry'] : null,
            'AuxBillingPhone' => !empty($d['auxBillingPhone']) ? $d['auxBillingPhone'] : null,
            'AuxBillingEmailAddress' => !empty($d['auxBillingEmailAddress']) ? $d['auxBillingEmailAddress'] : null,
            #opt
            'AuxBillingOrganizationName' => !empty($d['auxBillingOrganizationName']) ? $d['auxBillingOrganizationName'] : null,
            'AuxBillingJobTitle' => !empty($d['auxBillingJobTitle']) ? $d['auxBillingJobTitle'] : null,
            'AuxBillingAddress2' => !empty($d['auxBillingAddress2']) ? $d['auxBillingAddress2'] : null,
            'AuxBillingStateProvinceChoice' => !empty($d['auxBillingStateProvinceChoice']) ? $d['auxBillingStateProvinceChoice'] : null,
            'AuxBillingPhoneExt' => !empty($d['auxBillingPhoneExt']) ? $d['auxBillingPhoneExt'] : null,
            'AuxBillingFax' => !empty($d['auxBillingFax']) ? $d['auxBillingFax'] : null,
        ];
        # Validation fields
        $reqFields = $this->api->checkRequiredFields($registrant, $requiredRegistrant);
        if (count($reqFields)) {
            $flist = implode(', ', $reqFields);

            throw new \Exception($flist . ' : these fields are required!', 2010324);
        } else {
            // validate / replaced values with $registrant array for tech, admin, auxBilling
            $reqFields = $this->api->checkRequiredFields($tech, $requiredTech);
            foreach ($reqFields as $k) {
                $tech[$k] = $registrant['Registrant' . substr($k, strlen('Tech'))];
            }
            $reqFields = $this->api->checkRequiredFields($admin, $requiredAdmin);
            foreach ($reqFields as $k) {
                $admin[$k] = $registrant['Registrant' . substr($k, strlen('Admin'))];
            }
            $reqFields = $this->api->checkRequiredFields($auxBilling, $requiredAuxBilling);
            foreach ($reqFields as $k) {
                $auxBilling[$k] = $registrant['Registrant' . substr($k, strlen('AuxBilling'))];
            }
        }

        return array_merge($registrant, $tech, $admin, $auxBilling);
    }
}

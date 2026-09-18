<?php

class CVendor_Namecheap_Command_Domains_Dns extends CVendor_Namecheap_AbstractCommand {
    protected $command = 'namecheap.domains.dns.';

    /**
     * @todo Sets domain to use our default DNS servers. Required for free services like Host record management, URL forwarding, email forwarding, dynamic dns and other value added services.
     *
     * @param string $std SLD of the DomainName
     * @param string $tld TLD of the DomainName
     */
    public function setDefault($std, $tld) {
        return $this->api->get($this->command . __FUNCTION__, ['SLD' => $std, 'TLD' => $tld]);
    }

    /**
     * @todo Sets domain to use custom DNS servers. NOTE: Services like URL forwarding, Email forwarding, Dynamic DNS will not work for domains using custom nameservers.
     *
     * @param string $std SLD of the DomainName
     * @param string $tld TLD of the DomainName
     * @param string $ns  A comma-separated list of name servers to be associated with this domain
     *
     * @NOTE: Services like URL forwarding, Email forwarding, Dynamic DNS will not work for domains using custom nameservers
     */
    public function setCustom($std, $tld, $ns) {
        return $this->api->get($this->command . __FUNCTION__, ['SLD' => $std, 'TLD' => $tld, 'Nameservers' => $ns]);
    }

    /**
     * @todo Gets a list of DNS servers associated with the requested domain
     *
     * @param string $std SLD of the DomainName
     * @param string $tld TLD of the DomainName
     */
    public function getList($std, $tld) {
        return $this->api->get($this->command . __FUNCTION__, ['SLD' => $std, 'TLD' => $tld]);
    }

    /**
     * @todo Retrieves DNS host record settings for the requested domain
     *
     * @param string $std SLD of the DomainName
     * @param string $tld TLD of the DomainName
     */
    public function getHosts($std, $tld) {
        return $this->api->get($this->command . __FUNCTION__, ['SLD' => $std, 'TLD' => $tld]);
    }

    /**
     * @todo Gets email forwarding settings for the requested domain
     *
     * @param string $domainName Domain name to get settings
     */
    public function getEmailForwarding($domainName) {
        return $this->api->get($this->command . __FUNCTION__, ['DomainName' => $domainName]);
    }

    /**
     * @todo Sets email forwarding for a domain name
     *
     * @param string $domainName Domain name to set settings
     *
     * @NOTE: The [ ] brackets are used to represent optional values (e.g.[1...n]). Do not include the [ ] brackets in your API requests.Please refer to the example API request given below.
     */
    public function setEmailForwarding($domainName, array $mailBox, array $forwardTo) {
        # mailBox Example : ['mailbox1' => 'info', 'mailbox2' => 'careers'];
        # ForwardTo Example : ['ForwardTo1' => 'domaininfo@gmail.com', 'ForwardTo2' => 'domaincareer@gmail.com'];
        $data = ['DomainName' => $domainName];

        return $this->api->get($this->command . __FUNCTION__, array_merge($data, $mailBox, $forwardTo));
    }

    /**
     * @todo Sets DNS host records settings for the requested domain.
     *
     * @IMPORTANT:  We recommend you use HTTPPOST method when setting more than 10 hostnames. All host records that are not included into the API call will be deleted, so add them in addition to new host records.
     *
     * @param string      $sld
     * @param string      $tld
     * @param null|string $emailType
     *
     * @NOTE: The [ ] brackets are used to represent optional values (e.g.[1...n]). Do not include the [ ] brackets in your API requests.
     */
    public function setHosts($sld, $tld, array $hostName, array $recordType, array $address, array $mXPref, $emailType = null, array $ttl = []) {
        $data = ['SLD' => $sld, 'TLD' => $tld, 'EmailType' => $emailType];

        return $this->api->post($this->command . __FUNCTION__, array_merge($data, $hostName, $recordType, $address, $mXPref, $ttl));
    }
}

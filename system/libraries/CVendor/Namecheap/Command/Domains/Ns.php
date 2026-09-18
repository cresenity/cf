<?php

/**
 * Namecheap API wrapper.
 *
 * Method DomainsNs
 * Manage Domains Name server
 */
class CVendor_Namecheap_Command_Domains_Ns extends CVendor_Namecheap_AbstractCommand {
    protected $command = 'namecheap.domains.ns.';

    /**
     * @todo Creates a new nameserver.
     *
     * @param string $std SLD of the DomainName
     * @param string $tld TLD of the DomainName
     * @param string $ns  Nameserver to create
     * @param string $ip  Nameserver IP address
     */
    public function create($std, $tld, $ns, $ip) {
        $data = ['SLD' => $std, 'TLD' => $tld, 'Nameserver' => $ns, 'IP' => $ip];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Deletes a nameserver associated with the requested domain.
     *
     * @param mixed $std
     * @param mixed $tld
     * @param mixed $ns
     */
    public function delete($std, $tld, $ns) {
        $data = ['SLD' => $std, 'TLD' => $tld, 'Nameserver' => $ns];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Retrieves information about a registered nameserver.
     *
     * @param mixed $std
     * @param mixed $tld
     * @param mixed $ns
     */
    public function getInfo($std, $tld, $ns) {
        $data = ['SLD' => $std, 'TLD' => $tld, 'Nameserver' => $ns];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Updates the IP address of a registered nameserver.
     *
     * @param mixed $std
     * @param mixed $tld
     * @param mixed $ns
     * @param mixed $oldIp
     * @param mixed $ip
     */
    public function update($std, $tld, $ns, $oldIp, $ip) {
        $data = ['SLD' => $std, 'TLD' => $tld, 'Nameserver' => $ns, 'OldIP' => $oldIp, 'IP' => $ip];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }
}

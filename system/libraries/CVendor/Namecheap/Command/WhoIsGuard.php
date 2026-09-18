<?php

/**
 * Namecheap API wrapper.
 *
 * Method whoisguard
 * Manage whoisguard
 */
class CVendor_Namecheap_Command_WhoIsGuard extends CVendor_Namecheap_AbstractCommand {
    protected $command = 'namecheap.whoisguard.';

    /**
     * @todo Changes WhoisGuard email address
     *
     * @param int $whoisguardID The unique WhoisGuardID that you wish to change
     */
    public function changeEmailAddress($whoisguardID) {
        return $this->api->get($this->command . strtolower(__FUNCTION__), ['WhoisguardID' => $whoisguardID]);
    }

    /**
     * @todo Enables WhoisGuard privacy protection.
     *
     * @param int    $whoisguardID     The unique WhoisGuardID which you get
     * @param string $forwardedToEmail The email address to which WhoisGuard emails are to be forwarded
     */
    public function enable($whoisguardID, $forwardedToEmail) {
        return $this->api->get($this->command . __FUNCTION__, ['WhoisguardID' => $whoisguardID, 'ForwardedToEmail' => $forwardedToEmail]);
    }

    /**
     * @todo Disables WhoisGuard privacy protection.
     * @todo num|WhoisguardID|req : The unique WhoisGuardID which has to be disabled.
     *
     * @param mixed $whoisguardID
     */
    public function disable($whoisguardID) {
        return $this->api->get($this->command . __FUNCTION__, ['WhoisguardID' => $whoisguardID]);
    }

    /**
     * @todo Unallots WhoisGuard privacy protection.
     *
     * @param int $whoisguardID The unique WhoisGuardID that has to be unalloted
     */
    public function unallot($whoisguardID) {
        return $this->api->get($this->command . __FUNCTION__, ['WhoisguardID' => $whoisguardID]);
    }

    /**
     * @todo Discards whoisguard.
     * @todo num|WhoisguardID|req : The WhoisGuardID you wish to discard
     *
     * @param mixed $whoisguardID
     */
    public function discard($whoisguardID) {
        return $this->api->get($this->command . __FUNCTION__, ['WhoisguardID' => $whoisguardID]);
    }

    /**
     * @todo Allots WhoisGuard
     *
     * @param mixed      $whoisguardId
     * @param mixed      $domainName
     * @param null|mixed $forwardedToEmail
     * @param null|mixed $enableWG
     */
    public function allot($whoisguardId, $domainName, $forwardedToEmail = null, $enableWG = null) {
        $data = [
            'WhoisguardId' => $whoisguardId,
            'DomainName' => $domainName,
            'ForwardedToEmail' => $forwardedToEmail,
            'EnableWG' => $enableWG,
        ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Gets the list of WhoisGuard privacy protection.
     *
     * @param null|mixed $listType
     * @param null|mixed $page
     * @param null|mixed $pageSize
     */
    public function getList($listType = null, $page = null, $pageSize = null) {
        $data = [
            'ListType' => $listType,
            'Page' => $page,
            'PageSize' => $pageSize,
        ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Renews WhoisGuard privacy protection.
     *
     * @param mixed      $whoisguardID
     * @param mixed      $years
     * @param null|mixed $promotionCode
     */
    public function renew($whoisguardID, $years = 1, $promotionCode = null) {
        $data = [
            'WhoisguardID' => $whoisguardID,
            'Years' => $years,
            'PromotionCode' => $promotionCode,
        ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }
}

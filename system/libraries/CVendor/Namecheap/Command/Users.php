<?php

/**
 * Namecheap API wrapper.
 *
 * Method Users
 * Manage Users
 */
class CVendor_Namecheap_Command_Users extends CVendor_Namecheap_AbstractCommand {
    protected $command = 'namecheap.users.';

    /**
     * @todo Returns pricing information for a requested product type.
     *
     * @param string      $productType     Product Type to get pricing information
     * @param null|string $productCategory Specific category within a product type
     * @param null|string $promotionCode   Promotional (coupon) code for the user
     * @param null|string $actionName      Specific action within a product type
     * @param null|string $productName     The name of the product within a product type
     *
     * @note : Possible values for ProductType, ProductCategory, ActionName and ProductName parameters:
     * DOMAIN : ActionName -> REGISTER,RENEW,REACTIVATE,TRANSFER
     * SSLCERTIFICATE : ActionName -> PURCHASE,RENEW
     * WHOISGUARD : ActionName -> PURCHASE,RENEW
     */
    public function getPricing($productType = 'DOMAIN', $productCategory = null, $promotionCode = null, $actionName = null, $productName = null) {
        $data = [
            'ProductType' => $productType,
            'ProductCategory' => $productCategory,
            'PromotionCode' => $promotionCode,
            'ActionName' => $actionName,
            'ProductName' => $productName,
        ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Gets information about fund in the user's account.This method returns the following information: Available Balance, Account Balance, Earned Amount, Withdrawable Amount and Funds Required for AutoRenew.
     */
    public function getBalances() {
        return $this->api->get($this->command . __FUNCTION__);
    }

    /**
     * @todo Changes password of the particular user's account.
     *
     * @param string $oldPasswordOrResetCode
     * @param string $newPassword
     * @param bool   $resetPass
     */
    public function changePassword($oldPasswordOrResetCode, $newPassword, $resetPass = false) {
        if ($resetPass) {
            $data = ['ResetCode' => $oldPasswordOrResetCode, 'NewPassword' => $newPassword];
        } else {
            $data = ['OldPassword' => $oldPasswordOrResetCode, 'NewPassword' => $newPassword];
        }

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Updates user account information for the particular user.
     */
    public function update(array $param) {
        $requiredParams = ['FirstName', 'LastName', 'Address1', 'City', 'StateProvince', 'Zip', 'Country', 'EmailAddress', 'Phone'];
        $data = [
            'FirstName' => !empty($param['firstName']) ? $param['firstName'] : null,
            'LastName' => !empty($param['lastName']) ? $param['lastName'] : null,
            'Address1' => !empty($param['address1']) ? $param['address1'] : null,
            'City' => !empty($param['city']) ? $param['city'] : null,
            'StateProvince' => !empty($param['stateProvince']) ? $param['stateProvince'] : null,
            'Zip' => !empty($param['zip']) ? $param['zip'] : null,
            'Country' => !empty($param['country']) ? $param['country'] : null,
            'EmailAddress' => !empty($param['emailAddress']) ? $param['emailAddress'] : null,
            'Phone' => !empty($param['phone']) ? $param['phone'] : null,
            'JobTitle' => !empty($param['jobTitle']) ? $param['jobTitle'] : null,
            'Organization' => !empty($param['organization']) ? $param['organization'] : null,
            'Address2' => !empty($param['address2']) ? $param['address2'] : null,
            'PhoneExt' => !empty($param['phoneExt']) ? $param['phoneExt'] : null,
            'Fax' => !empty($param['fax']) ? $param['fax'] : null,
        ];
        $reqFields = $this->checkRequiredFields($data, $requiredParams);
        if (count($reqFields)) {
            $flist = implode(', ', $reqFields);

            throw new \Exception($flist . ' : these fields are required!', 2010324);
        }

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Creates a request to add funds through a credit card
     * Once payment is processed, you will be automatically redirected to the URL you've specified in the createaddfundsrequest call.
     *
     * @param mixed $username
     * @param mixed $paymentType
     * @param mixed $amount
     * @param mixed $returnUrl
     */
    public function createaddfundsrequest($username, $paymentType, $amount, $returnUrl) {
        $data = [
            'username' => $username,
            'paymentType' => $paymentType,
            'amount' => $amount,
            'returnUrl' => $returnUrl,
        ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Gets the status of add funds request.
     *
     * @param string $tokenId The Unique ID that you received after calling namecheap.users.createaddfundsrequest method
     */
    public function getAddFundsStatus($tokenId) {
        return $this->api->get($this->command . __FUNCTION__, ['TokenId' => $tokenId]);
    }

    /**
     * @todo Creates a new account at NameCheap under this ApiUser.
     */
    public function create(array $param) {
        $requiredParams = ['NewUserName', 'NewUserPassword', 'EmailAddress', 'FirstName', 'LastName', 'AcceptTerms', 'Address1', 'City', 'StateProvince', 'Zip', 'Country', 'Phone'];
        $data = [
            'NewUserName' => !empty($param['newUserName']) ? $param['newUserName'] : null,
            'NewUserPassword' => !empty($param['newUserPassword']) ? $param['newUserPassword'] : null,
            'EmailAddress' => !empty($param['emailAddress']) ? $param['emailAddress'] : null,
            'FirstName' => !empty($param['firstName']) ? $param['firstName'] : null,
            'LastName' => !empty($param['lastName']) ? $param['lastName'] : null,
            'AcceptTerms' => !empty($param['acceptTerms']) ? $param['acceptTerms'] : null,
            'Address1' => !empty($param['address1']) ? $param['address1'] : null,
            'City' => !empty($param['city']) ? $param['city'] : null,
            'StateProvince' => !empty($param['stateProvince']) ? $param['stateProvince'] : null,
            'Zip' => !empty($param['zip']) ? $param['zip'] : null,
            'Country' => !empty($param['country']) ? $param['country'] : null,
            'Phone' => !empty($param['phone']) ? $param['phone'] : null,
            'IgnoreDuplicateEmailAddress' => !empty($param['ignoreDuplicateEmailAddress']) ? $param['ignoreDuplicateEmailAddress'] : null,
            'AcceptNews' => !empty($param['acceptNews']) ? $param['acceptNews'] : null,
            'JobTitle' => !empty($param['jobTitle']) ? $param['jobTitle'] : null,
            'Organization' => !empty($param['organization']) ? $param['organization'] : null,
            'Address2' => !empty($param['address2']) ? $param['address2'] : null,
            'PhoneExt' => !empty($param['phoneExt']) ? $param['phoneExt'] : null,
            'Fax' => !empty($param['fax']) ? $param['fax'] : null,
        ];
        $reqFields = $this->checkRequiredFields($data, $requiredParams);
        if (count($reqFields)) {
            $flist = implode(', ', $reqFields);

            throw new \Exception($flist . ' : these fields are required!', 2010324);
        }

        return $this->api->get($this->command . __FUNCTION__, $data);
    }

    /**
     * @todo Validates the username and password of user accounts you have created using the API command namecheap.users.create.
     *
     * @note : You cannot use this command to validate user accounts created directly at namecheap.com
     *
     * @IMPORTANT NOTE: Use the global parameter UserName to specify the username of the user account.
     *
     * @param string $password
     */
    public function login($password) {
        return $this->api->get($this->command . __FUNCTION__, ['Password' => $password]);
    }

    /**
     * @todo When you call this API, a link to reset password will be emailed to the end user's profile email id.The end user needs to click on the link to reset password.
     *
     * @note : UserName should be omitted for this API call.All other Global parameters must be included.
     *
     * @param mixed      $findBy
     * @param null|mixed $findByValue
     * @param null|mixed $emailFromName
     * @param null|mixed $emailFrom
     * @param null|mixed $uRLPattern
     */
    public function resetPassword($findBy = 'EMAILADDRESS', $findByValue = null, $emailFromName = null, $emailFrom = null, $uRLPattern = null) {
        $data = [
            'FindBy' => $findBy, 'FindByValue' => $findByValue,
            'EmailFromName' => $emailFromName, 'EmailFrom' => $emailFrom,
            'URLPattern' => $uRLPattern, ];

        return $this->api->get($this->command . __FUNCTION__, $data);
    }
}

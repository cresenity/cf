<?php

/**
 * Class EWallets
 *
 * @category Class
 * @package  Xendit
 *
 *
 * @link     https://api.xendit.co
 */
class CVendor_Xendit_EWallet extends CVendor_Xendit_Base {
    /**
     * Instantiate base URL
     *
     * @return string
     */
    protected function classUrl() {
        return '/ewallets';
    }

    /**
     * Instantiate required params for Create
     *
     * @return array
     */
    protected function createReqParams() {
        return [];
    }

    /**
     * Instantiate required params for Update
     *
     * @return array
     */
    protected function updateReqParams() {
        return [];
    }

    /**
     * Send a create request
     *
     * @param array $params user's parameters
     *
     * @return array please check for responses for each e-wallet type
     *               https://xendit.github.io/apireference/?bash#create-payment
     *
     * @throws CVendor_Xendit_Exception_ApiException
     */
    public function create($params = []) {
        $requiredParams = [];

        if (!array_key_exists('ewallet_type', $params)) {
            $message = 'Please specify ewallet_type inside your parameters.';
            throw new InvalidArgumentException($message);
        }

        if ($params['ewallet_type'] === 'OVO') {
            $requiredParams = ['external_id', 'amount', 'phone'];
        } elseif ($params['ewallet_type'] === 'DANA') {
            $requiredParams = ['external_id', 'amount',
                'callback_url', 'redirect_url'];
        } elseif ($params['ewallet_type'] === 'LINKAJA') {
            $requiredParams = ['external_id', 'amount', 'phone',
                'items', 'callback_url', 'redirect_url'];
        }

        $this->validateParams($params, $requiredParams);

        $url = $this->classUrl();

        return $this->request('POST', $url, $params);
    }

    /**
     * Get Payment Status
     *
     * @param string $external_id  external ID
     * @param string $ewallet_type E-wallet type (OVO, DANA, LINKAJA
     *
     * @return array please check for responses for each e-wallet type
     *               https://xendit.github.io/apireference/?bash#get-payment-status
     *
     * @throws CVendor_Xendit_Exception_ApiException
     */
    public function getPaymentStatus($external_id, $ewallet_type) {
        $url = $this->classUrl()
            . '?external_id=' . $external_id
            . '&ewallet_type=' . $ewallet_type;

        return $this->request('GET', $url);
    }

    /**
     * Send a create e-wallet charge request
     *
     * @param array $params user's parameters
     *
     * @return array please check for responses parameters here
     *               https://developers.xendit.co/api-reference/?bash#create-ewallet-charge
     *
     * @throws CVendor_Xendit_Exception_ApiException
     */
    public function createEWalletCharge($params = []) {
        $requiredParams = ['reference_id', 'currency', 'amount', 'checkout_method'];

        $this->validateParams($params, $requiredParams);

        $url = $this->classUrl() . '/charges';

        return $this->request('POST', $url, $params);
    }

    /**
     * Get e-wallet charge status
     *
     * @param string $charge_id chargee ID
     *
     * @return array please check for responses parameters here
     *               https://developers.xendit.co/api-reference/?bash#get-ewallet-charge-status
     *
     * @throws CVendor_Xendit_Exception_ApiException
     */
    public function getEWalletChargeStatus($charge_id) {
        $url = $this->classUrl()
            . '/charges/' . $charge_id;

        return $this->request('GET', $url);
    }
}

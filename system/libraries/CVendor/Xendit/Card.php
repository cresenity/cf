<?php

/**
 * Class Cards
 *
 * @category Class
 * @package  Xendit
 *
 *
 * @link     https://api.xendit.co
 */
class CVendor_Xendit_Card extends CVendor_Xendit_Base {
    use CVendor_Xendit_ApiOperation_Create;
    use CVendor_Xendit_ApiOperation_Retrieve;

    /**
     * Instantiate base URL
     *
     * @return string
     */
    protected function classUrl() {
        return '/credit_card_charges';
    }

    /**
     * Capture charge, see https://xendit.github.io/apireference/?bash#capture-charge
     * for more details
     *
     * @param string $id     charge ID
     * @param array  $params user parameters
     *
     * @return array [
     *               'created' => string,
     *               'status' => string,
     *               'business_id' => string,
     *               'authorized_amount' => int,
     *               'external_id' => string,
     *               'merchant_id' => string,
     *               'merchant_reference_code' => string,
     *               'card_type' => string,
     *               'masked_card_number' => string,
     *               'charge_type' => string,
     *               'card_brand' => string,
     *               'bank_reconciliation_id' => string,
     *               'capture_amount' => int,
     *               'descriptor' => string,
     *               'id' => string
     *               ]
     *
     * @throws CVendor_Xendit_Exception_ApiException
     */
    public function capture($id, $params = []) {
        $url = $this->classUrl() . '/' . $id . '/capture';
        $requiredParams = ['amount'];

        $this->validateParams($params, $requiredParams);

        return $this->request('POST', $url, $params);
    }

    /**
     * Instantiate required params for Create
     *
     * @return array
     */
    protected function createReqParams() {
        return ['token_id', 'external_id', 'amount'];
    }

    /**
     * Instantiate required params for Create
     *
     * @return array
     */
    protected function updateReqParams() {
        return [];
    }

    /**
     * Reverse authorized charge
     *
     * @param string $id     charge ID
     * @param array  $params user params
     *
     * @return array [
     *               'created' => string,
     *               'credit_card_charge_id' => string,
     *               'external_id' => string,
     *               'business_id' => string,
     *               'amount' => int,
     *               'status' => string,
     *               'id' => string
     *               ]
     *
     * @throws CVendor_Xendit_Exception_ApiException
     */
    public function reverseAuthorization($id, $params = []) {
        $url = $this->classUrl() . '/' . $id . '/auth_reversal';
        $requiredParams = ['external_id'];

        $this->validateParams($params, $requiredParams);

        return $this->request('POST', $url, $params);
    }

    /**
     * Create refund, see https://xendit.github.io/apireference/?bash#capture-charge
     * for more details
     *
     * @param string $id     charge ID
     * @param array  $params user parameters
     *
     * @return array [
     *               'updated' => string,
     *               'created' => string,
     *               'credit_card_charge_id' => string,
     *               'user_id' => string,
     *               'amount' => int,
     *               'external_id' => string,
     *               'status' => 'SUCCEEDED' || 'FAILED',
     *               'fee_refund_amount' => int,
     *               'id' => string
     *               ]
     *
     * @throws CVendor_Xendit_Exception_ApiException
     */
    public function createRefund($id, $params = []) {
        $url = $this->classUrl() . '/' . $id . '/refunds';
        $requiredParams = ['amount', 'external_id'];

        $this->validateParams($params, $requiredParams);

        return $this->request('POST', $url, $params);
    }

    public function getChargeOption($params) {
        $url = $this->classUrl() . '/option';
        $requiredParams = ['amount'];

        $this->validateParams($params, $requiredParams);

        return $this->request('GET', $url, $params);
    }
}

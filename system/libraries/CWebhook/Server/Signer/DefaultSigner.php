<?php

class CWebhook_Server_Signer_DefaultSigner implements CWebhook_Server_Contract_SignerInterface {
    /**
     * @param string $webhookUrl
     * @param array  $payload
     * @param string $secret
     *
     * @return string
     */
    public function calculateSignature($webhookUrl, array $payload, $secret) {
        $payloadJson = json_encode($payload);

        return hash_hmac('sha256', $payloadJson, $secret);
    }

    /**
     * @return string
     */
    public function signatureHeaderName() {
        return CF::config('webhook.server.default.signature_header_name', 'Signature');
    }
}

<?php

class CWebhook_Client_Exception_InvalidConfigException extends Exception {
    /**
     * @param string $notFoundConfigName
     *
     * @return self
     */
    public static function couldNotFindConfig($notFoundConfigName) {
        return new static("Could not find the configuration for `{$notFoundConfigName}`");
    }

    /**
     * @param string $invalidSignatureValidator
     *
     * @return self
     */
    public static function invalidSignatureValidator($invalidSignatureValidator) {
        $signatureValidatorInterface = CWebhook_Client_Contract_SignatureValidatorInterface::class;

        return new static("`{$invalidSignatureValidator}` is not a valid signature validator class. A valid signature validator is a class that implements `{$signatureValidatorInterface}`.");
    }

    /**
     * @param string $webhookProfile
     *
     * @return self
     */
    public static function invalidWebhookProfile(string $webhookProfile): self {
        $webhookProfileInterface = WebhookProfile::class;

        return new static("`{$webhookProfile}` is not a valid webhook profile class. A valid web hook profile is a class that implements `{$webhookProfileInterface}`.");
    }

    public static function invalidWebhookResponse(string $webhookResponse): self {
        $webhookResponseInterface = RespondsToWebhook::class;

        return new static("`{$webhookResponse}` is not a valid webhook response class. A valid webhook response is a class that implements `{$webhookResponseInterface}`.");
    }

    public static function invalidProcessWebhookJob(string $processWebhookJob): self {
        $abstractProcessWebhookJob = CWebhook_Client_TaskQueue_AbstractProcessWebhookTask::class;

        return new static("`{$processWebhookJob}` is not a valid process webhook job class. A valid class should implement `{$abstractProcessWebhookJob}`.");
    }

    public static function signingSecretNotSet(): self {
        return new static('The webhook signing secret is not set. Make sure that the `signing_secret` config key is set to the correct value.');
    }
}

<?php

class CWebhook_Client_Config {
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $signingSecret;

    /**
     * @var string
     */
    public $signatureHeaderName;

    /**
     * @var CWebhook_Client_Contract_SignatureValidatorInterface
     */
    public $signatureValidator;

    /**
     * @var CWebhook_Client_Contract_WebhookProfileInterface
     */
    public $webhookProfile;

    public CWebhook_Client_Contract_WebhookResponseInterface $webhookResponse;

    public string $webhookModel;

    /**
     * @var array|string
     */
    public $storeHeaders;

    public string $processWebhookJobClass;

    public function __construct(array $properties) {
        $this->name = $properties['name'];

        $this->signingSecret = $properties['signing_secret'] ?? '';

        $this->signatureHeaderName = $properties['signature_header_name'] ?? '';

        if (!is_subclass_of($properties['signature_validator'], CWebhook_Client_Contract_SignatureValidatorInterface::class)) {
            throw CWebhook_Client_Exception_InvalidConfigException::invalidSignatureValidator($properties['signature_validator']);
        }
        $this->signatureValidator = c::container()->make($properties['signature_validator']);

        if (!is_subclass_of($properties['webhook_profile'], CWebhook_Client_Contract_WebhookProfileInterface::class)) {
            throw CWebhook_Client_Exception_InvalidConfigException::invalidWebhookProfile($properties['webhook_profile']);
        }
        $this->webhookProfile = c::container()->make($properties['webhook_profile']);

        $webhookResponseClass = carr::get($properties, 'webhook_response', CWebhook_Client_WebhookResponse_DefaultWebhookResponse::class);
        if (!is_subclass_of($webhookResponseClass, CWebhook_Client_Contract_WebhookResponseInterface::class)) {
            throw CWebhook_Client_Exception_InvalidConfigException::invalidWebhookResponse($webhookResponseClass);
        }
        $this->webhookResponse = c::container()->make($webhookResponseClass);

        $this->webhookModel = $properties['webhook_model'];

        $this->storeHeaders = $properties['store_headers'] ?? [];

        if (!is_subclass_of($properties['process_webhook_job'], CWebhook_Client_TaskQueue_AbstractProcessWebhookTask::class)) {
            throw CWebhook_Client_Exception_InvalidConfigException::invalidProcessWebhookJob($properties['process_webhook_job']);
        }

        $this->processWebhookJobClass = $properties['process_webhook_job'];
    }
}

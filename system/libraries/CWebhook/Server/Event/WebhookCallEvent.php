<?php

use GuzzleHttp\TransferStats;
use Psr\Http\Message\ResponseInterface;

/**
 * Data satu percobaan panggilan webhook keluar; dasar untuk event sukses/gagal/gagal final.
 */
abstract class CWebhook_Server_Event_WebhookCallEvent {
    /** @var string */
    public $httpVerb;

    /** @var string */
    public $webhookUrl;

    /** @var array */
    public $payload;

    /** @var array */
    public $headers;

    /** @var array */
    public $meta;

    /** @var array */
    public $tags;

    /** @var int */
    public $attempt;

    /** @var null|ResponseInterface */
    public $response;

    /** @var null|string */
    public $errorType;

    /** @var null|string */
    public $errorMessage;

    /** @var string */
    public $uuid;

    /** @var null|TransferStats */
    public $transferStats;

    /**
     * @param string                 $httpVerb
     * @param string                 $webhookUrl
     * @param array                  $payload
     * @param array                  $headers
     * @param array                  $meta
     * @param array                  $tags
     * @param int                    $attempt
     * @param null|ResponseInterface $response
     * @param null|string            $errorType
     * @param null|string            $errorMessage
     * @param string                 $uuid
     * @param null|TransferStats     $transferStats
     */
    public function __construct($httpVerb, $webhookUrl, array $payload, array $headers, array $meta, array $tags, $attempt, $response, $errorType, $errorMessage, $uuid, $transferStats = null) {
        $this->httpVerb = $httpVerb;
        $this->webhookUrl = $webhookUrl;
        $this->payload = $payload;
        $this->headers = $headers;
        $this->meta = $meta;
        $this->tags = $tags;
        $this->attempt = $attempt;
        $this->response = $response;
        $this->errorType = $errorType;
        $this->errorMessage = $errorMessage;
        $this->uuid = $uuid;
        $this->transferStats = $transferStats;
    }
}

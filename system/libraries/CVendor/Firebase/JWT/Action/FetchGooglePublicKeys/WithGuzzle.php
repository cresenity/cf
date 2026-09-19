<?php

use Psr\Clock\ClockInterface;
use GuzzleHttp\ClientInterface;

use GuzzleHttp\Exception\GuzzleException;
use Fig\Http\Message\RequestMethodInterface as RequestMethod;

/**
 * @internal
 */
final class CVendor_Firebase_JWT_Action_FetchGooglePublicKeys_WithGuzzle implements CVendor_Firebase_JWT_Action_FetchGooglePublicKeys_HandlerInterface {
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var ClockInterface
     */
    private $clock;

    /**
     * @param ClientInterface $client
     * @param ClockInterface  $clock
     */
    public function __construct(ClientInterface $client, ClockInterface $clock) {
        $this->client = $client;
        $this->clock = $clock;
    }

    /**
     * @param CVendor_Firebase_JWT_Action_FetchGooglePublicKeys $action
     *
     * @return CVendor_Firebase_JWT_Contract_KeysInterface
     */
    public function handle(CVendor_Firebase_JWT_Action_FetchGooglePublicKeys $action) {
        $keys = [];
        $ttls = [];

        foreach ($action->urls() as $url) {
            $result = $this->fetchKeysFromUrl($url);

            $keys[] = $result['keys'];
            $ttls[] = $result['ttl'];
        }

        $keys = \array_merge(...$keys);
        $ttl = \min($ttls);
        $now = $this->clock->now();

        $expiresAt = $ttl > 0
            ? $now->setTimestamp($now->getTimestamp() + $ttl)
            : $now->add($action->getFallbackCacheDuration()->value());

        return CVendor_Firebase_JWT_Keys_ExpiringKeys::withValuesAndExpirationTime($keys, $expiresAt);
    }

    /**
     * @param string $url
     *
     * @return array{
     *                keys: array<string, string>,
     *                ttl: int
     *                }
     */
    private function fetchKeysFromUrl($url) {
        try {
            $response = $this->client->request('GET', $url, [
                'http_errors' => false,
                'headers' => [
                    'Content-Type' => 'Content-Type: application/json; charset=UTF-8',
                ],
            ]);
        } catch (GuzzleException $e) {
            throw CVendor_Firebase_JWT_Exception_FetchingGooglePublicKeysFailedException::because("The connection to {$url} failed: " . $e->getMessage(), $e->getCode(), $e);
        }

        if (($statusCode = $response->getStatusCode()) !== 200) {
            throw CVendor_Firebase_JWT_Exception_FetchingGooglePublicKeysFailedException::because("Unexpected status code {$statusCode}");
        }

        $response = $this->client->request(RequestMethod::METHOD_GET, $url, [
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'Content-Type: application/json; charset=UTF-8',
            ],
        ]);

        $ttl = \preg_match('/max-age=(\d+)/i', $response->getHeaderLine('Cache-Control'), $matches)
            ? (int) $matches[1]
            : 0;

        try {
            $keys = \json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw CVendor_Firebase_JWT_Exception_FetchingGooglePublicKeysFailedException::because('Unexpected response: ' . $e->getMessage());
        }

        return [
            'keys' => $keys,
            'ttl' => $ttl,
        ];
    }
}

<?php
use Webmozart\Assert\Assert;
use Http\Discovery\Psr18ClientDiscovery;
use Http\Client\Common\HttpMethodsClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Inisiatif\WhatsappQontakPhp\Message\Message;
use Http\Client\Common\HttpMethodsClientInterface;
use Psr\Http\Client\ClientInterface as HttpClient;

final class CVendor_Qontak_Client implements CVendor_Qontak_ClientInterface {
    /**
     * @var HttpMethodsClientInterface
     */
    private $httpClient;

    /**
     * @var null|string
     */
    private $accessToken = null;

    /**
     * @var CVendor_Qontak_Credential
     */
    private $credential;

    public function __construct(CVendor_Qontak_Credential $credential, ?HttpClient $httpClient = null) {
        /** @psalm-suppress PropertyTypeCoercion */
        $this->httpClient = $httpClient ?? new HttpMethodsClient(
            Psr18ClientDiscovery::find(),
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory()
        );

        $this->credential = $credential;
    }

    public function getChannelIntegrationList($targetChannel = null) {
        $this->getAccessToken();
        $url = 'https://chat-service.qontak.com/api/open/v1/integrations';
        if ($targetChannel) {
            $url .= '?target_channel=' . $targetChannel;
        }
        $response = $this->httpClient->get(
            $url,
            [
                'content-type' => 'application/json',
                'Authorization' => \sprintf('Bearer %s', $this->accessToken ?? ''),
            ]
        );

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            /** @var array $responseBody */
            $responseBody = \json_decode((string) $response->getBody(), true);

            return $responseBody;
        }

        throw CVendor_Qontak_Exception_ClientSendingException::make($response);
    }

    /**
     * @param mixed $templateId
     *
     * @return array
     */
    public function getWhatsAppTemplate($templateId) {
        $this->getAccessToken();

        $response = $this->httpClient->get(
            'https://chat-service.qontak.com/api/open/v1/templates/' . $templateId . '/whatsapp',
            [
                'content-type' => 'application/json',
                'Authorization' => \sprintf('Bearer %s', $this->accessToken ?? ''),
            ]
        );
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            /** @var array $responseBody */
            $responseBody = \json_decode((string) $response->getBody(), true);

            return $responseBody;
        }

        throw CVendor_Qontak_Exception_ClientSendingException::make($response);
    }

    /**
     * @return array
     */
    public function getWhatsAppTemplateList() {
        $this->getAccessToken();
        $response = $this->httpClient->get(
            'https://service-chat.qontak.com/api/open/v1/templates/whatsapp?query=&offset=1&limit=10&cursor_direction=after&order_by=created_at&order_direction=desc&status=Approved&hsm_chat=true&is_counted=true',
            [
                'content-type' => 'application/json',
                'Authorization' => \sprintf('Bearer %s', $this->accessToken ?? ''),
            ]
        );

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            /** @var array $responseBody */
            $responseBody = \json_decode((string) $response->getBody(), true);

            return $responseBody;
        }

        throw CVendor_Qontak_Exception_ClientSendingException::make($response);
    }

    public function getRoomList() {
        $this->getAccessToken();
        // $url = 'https://service-chat.qontak.com/api/open/v1/contacts/contact_lists?limit=5&offset=1&is_counted=true&query=&cursor=x&cursor_direction=after&order_by=created_at&order_direction=desc&finished=true';
        // $url = 'https://service-chat.qontak.com/api/open/v1/contacts/contact_lists';
        $url = 'https://service-chat.qontak.com/api/open/v1/rooms?status=unassigned&offset=1&limit=10';
        $url = 'https://service-chat.qontak.com/api/open/v1/rooms';
        $response = $this->httpClient->get(
            $url,
            [
                'content-type' => 'application/json',
                'Authorization' => \sprintf('Bearer %s', $this->accessToken ?? ''),
            ]
        );

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            /** @var array $responseBody */
            $responseBody = \json_decode((string) $response->getBody(), true);

            return $responseBody;
        }

        throw CVendor_Qontak_Exception_ClientSendingException::make($response);
    }

    public function getContactList() {
        $this->getAccessToken();
        // $url = 'https://service-chat.qontak.com/api/open/v1/contacts/contact_lists?limit=5&offset=1&is_counted=true&query=&cursor=x&cursor_direction=after&order_by=created_at&order_direction=desc&finished=true';
        // $url = 'https://service-chat.qontak.com/api/open/v1/contacts/contact_lists';
        $url = 'https://service-chat.qontak.com/api/open/v1/contacts/contact_lists';
        $response = $this->httpClient->get(
            $url,
            [
                'content-type' => 'application/json',
                'Authorization' => \sprintf('Bearer %s', $this->accessToken ?? ''),
            ]
        );

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            /** @var array $responseBody */
            $responseBody = \json_decode((string) $response->getBody(), true);

            return $responseBody;
        }

        throw CVendor_Qontak_Exception_ClientSendingException::make($response);
    }

    public function send(string $templateId, string $channelId, CVendor_Qontak_Message $message): CVendor_Qontak_Response {
        $this->getAccessToken();

        $response = $this->httpClient->post(
            'https://service-chat.qontak.com/api/open/v1/broadcasts/whatsapp/direct',
            [
                'content-type' => 'application/json',
                'Authorization' => \sprintf('Bearer %s', $this->accessToken ?? ''),
            ],
            \json_encode(
                [
                    'message_template_id' => $templateId,
                    'channel_integration_id' => $channelId,
                ] + $this->makeRequestBody($message)
            )
        );

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $responseBody = \json_decode((string) $response->getBody(), true);
            if (!is_array($responseBody)) {
                throw CVendor_Qontak_Exception_ClientSendingException::make($response);
            }

            Assert::keyExists($responseBody, 'data');

            /** @var array<string, string|int> $responseData */
            $responseData = $responseBody['data'];
            Assert::keyExists($responseData, 'id');
            Assert::keyExists($responseData, 'name');

            return new CVendor_Qontak_Response((string) $responseData['id'], (string) $responseData['name'], $responseData);
        }

        throw CVendor_Qontak_Exception_ClientSendingException::make($response);
    }

    private function getAccessToken(): void {

        // cdbg::dd($this->credential->getOAuthCredential());
        if ($this->accessToken === null) {
            $response = $this->httpClient->post(
                'https://service-chat.qontak.com/oauth/token',
                [
                    'content-type' => 'application/json',
                ],
                \json_encode($this->credential->getOAuthCredential())
            );
            // cdbg::dd($response->getBody()->getContents());
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw CVendor_Qontak_Exception_ClientSendingException::make($response);
            }

            $body = \json_decode((string) $response->getBody(), true);
            // body bukan JSON saat Qontak membalas halaman galat, jangan diteruskan ke Assert
            if (!is_array($body)) {
                throw CVendor_Qontak_Exception_ClientSendingException::make($response);
            }

            Assert::keyExists($body, 'access_token');

            $this->accessToken = $body['access_token'];
        }
    }

    private function makeRequestBody(CVendor_Qontak_Message $message): array {
        return CVendor_Qontak_MessageUtil::makeRequestBody($message);
    }
}

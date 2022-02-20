<?php

class CVendor_Firebase_Messaging {
    /**
     * @var string
     */
    private $projectId;

    /**
     * @var CVendor_Firebase_Messaging_ApiClient
     */
    private $messagingApi;

    /**
     * @var CVendor_Firebase_Messaging_AppInstanceApiClient
     */
    private $appInstanceApi;

    /**
     * @param null|mixed $projectId
     *
     * @internal
     */
    public function __construct(CVendor_Firebase_Messaging_ApiClient $messagingApiClient, CVendor_Firebase_Messaging_AppInstanceApiClient $appInstanceApiClient, $projectId = null) {
        $this->messagingApi = $messagingApiClient;
        $this->appInstanceApi = $appInstanceApiClient;
        $this->projectId = $projectId ?: $this->determineProjectIdFromMessagingApiClient($messagingApiClient);
    }

    private function determineProjectIdFromMessagingApiClient(CVendor_Firebase_Messaging_ApiClient $client) {
        $baseUri = $client->getConfig('base_uri');
        $uriParts = \explode('/', (string) $baseUri);

        if (!($projectId = \array_pop($uriParts))) {
            throw new InvalidArgumentException("Project ID could not be determined from {$baseUri}");
        }

        return $projectId;
    }

    /**
     * @param array|Message|mixed $message
     *
     * @throws InvalidArgumentException
     * @throws MessagingException
     * @throws CVendor_Firebase_Exception
     */
    public function send($message) {
        $message = $this->makeMessage($message);

        $request = new CVendor_Firebase_Messaging_Request_SendMessageRequest($this->projectId, $message);
        $response = $this->messagingApi->send($request);

        return CHelper::json()->decode((string) $response->getBody(), true);
    }

    /**
     * @param array|Message|mixed                             $message
     * @param RegistrationToken[]|string[]|RegistrationTokens $registrationTokens
     *
     * @throws InvalidArgumentException   if the message is invalid
     * @throws MessagingException         if the API request failed
     * @throws CVendor_Firebase_Exception if something very unexpected happened (never :))
     *
     * @return CVendor_Firebase_Messaging_MulticastSendReport
     */
    public function sendMulticast($message, $registrationTokens) {
        $message = $this->makeMessage($message);
        $registrationTokens = $this->ensureNonEmptyRegistrationTokens($registrationTokens);

        $request = new CVendor_Firebase_Messaging_Request_SendMessageToTokensRequest($this->projectId, $message, $registrationTokens);
        /** @var CVendor_Firebase_Http_ResponseWithSubResponses $response */
        $response = $this->messagingApi->send($request);

        return CVendor_Firebase_Messaging_MulticastSendReport::fromRequestsAndResponses($request->subRequests(), $response->subResponses());
    }

    /**
     * @param array[]|Message[]|Messages $messages
     *
     * @throws InvalidArgumentException   if the message is invalid
     * @throws MessagingException         if the API request failed
     * @throws CVendor_Firebase_Exception if something very unexpected happened (never :))
     */
    public function sendAll($messages) {
        $ensuredMessages = [];

        foreach ($messages as $message) {
            $ensuredMessages[] = $this->makeMessage($message);
        }

        $request = new CVendor_Firebase_Messaging_Request_SendMessagesRequest($this->projectId, new CVendor_Firebase_Messaging_Messages(...$ensuredMessages));
        /** @var ResponseWithSubResponses $response */
        $response = $this->messagingApi->send($request);

        return CVendor_Firebase_Messaging_MulticastSendReport::fromRequestsAndResponses($request->subRequests(), $response->subResponses());
    }

    /**
     * @param array|Message|mixed $message
     *
     * @throws InvalidArgumentException
     * @throws InvalidMessage
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function validate($message) {
        $message = $this->makeMessage($message);

        $request = new CVendor_Firebase_Messaging_Request_ValidateMessageRequest($this->projectId, $message);

        try {
            $response = $this->messagingApi->send($request);
        } catch (CVendor_Firebase_Messaging_Exception_NotFoundException $e) {
            $error = new CVendor_Firebase_Messaging_Exception_InvalidMessageException($e->getMessage(), $e->getCode(), $e->getPrevious());
            $error = $error->withErrors($e->errors());

            if ($response = $e->response()) {
                $error = $error->withResponse($response);
            }

            throw $error;
        }

        return CHelper::json()->decode((string) $response->getBody(), true);
    }

    /**
     * @param string|CVendor_Firebase_Messaging_Topic $topic
     * @param mixed                                   $registrationTokenOrTokens
     *
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function subscribeToTopic($topic, $registrationTokenOrTokens) {
        $topic = $topic instanceof CVendor_Firebase_Messaging_Topic ? $topic : CVendor_Firebase_Messaging_Topic::fromValue($topic);
        $tokens = $this->ensureNonEmptyRegistrationTokens($registrationTokenOrTokens);

        $response = $this->appInstanceApi->subscribeToTopic($topic, $tokens->asStrings());

        return CHelper::json()->decode((string) $response->getBody(), true);
    }

    /**
     * @param string|CVendor_Firebase_Messaging_Topic $topic
     * @param mixed                                   $registrationTokenOrTokens
     *
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function unsubscribeFromTopic($topic, $registrationTokenOrTokens) {
        $topic = $topic instanceof CVendor_Firebase_Messaging_Topic ? $topic : CVendor_Firebase_Messaging_Topic::fromValue($topic);
        $tokens = $this->ensureNonEmptyRegistrationTokens($registrationTokenOrTokens);

        $response = $this->appInstanceApi->unsubscribeFromTopic($topic, $tokens->asStrings());

        return CHelper::json()->decode((string) $response->getBody(), true);
    }

    /**
     * @param CVendor_Firebase_Messaging_RegistrationToken|string $registrationToken
     *
     * @see https://developers.google.com/instance-id/reference/server#results
     *
     * @throws InvalidArgument                     if the registration token is invalid
     * @throws CVendor_Firebase_ExceptionInterface
     */
    public function getAppInstance($registrationToken) {
        $token = $registrationToken instanceof CVendor_Firebase_Messaging_RegistrationToken ? $registrationToken : CVendor_Firebase_Messaging_RegistrationToken::fromValue($registrationToken);

        try {
            $response = $this->appInstanceApi->getAppInstance((string) $token);
        } catch (CVendor_Firebase_Messaging_ExceptionInterface $e) {
            // The token is invalid
            throw new CVendor_Firebase_Messaging_Exception_InvalidArgumentException("The registration token '{$token}' is invalid");
        }

        $data = CHelper::json()->decode((string) $response->getBody(), true);

        return CVendor_Firebase_Messaging_AppInstance::fromRawData($token, $data);
    }

    /**
     * @param mixed $message
     *
     * @throws InvalidArgumentException
     */
    private function makeMessage($message) {
        if ($message instanceof CVendor_Firebase_Messaging_MessageInterface) {
            return $message;
        }

        if (!\is_array($message)) {
            throw new CVendor_Firebase_Exception_InvalidArgumentException(
                'Unsupported message type. Use an array or a class implementing %s' . CVendor_Firebase_Messaging_MessageInterface::class
            );
        }

        return CVendor_Firebase_Messaging_CloudMessage::fromArray($message);
    }

    /**
     * @param mixed $value
     *
     * @throws CVendor_Firebase_Messaging_Exception_InvalidArgumentException
     */
    private function ensureNonEmptyRegistrationTokens($value) {
        try {
            $tokens = CVendor_Firebase_Messaging_RegistrationTokens::fromValue($value);
        } catch (CVendor_Firebase_Exception_InvalidArgumentException $e) {
            // We have to wrap the exception for BC reasons
            throw new CVendor_Firebase_Messaging_Exception_InvalidArgumentException($e->getMessage());
        }

        if ($tokens->isEmpty()) {
            throw new CVendor_Firebase_Messaging_Exception_InvalidArgumentException('Empty list of registration tokens.');
        }

        return $tokens;
    }

    /**
     * @return CVendor_Firebase_Messaging_CloudMessage
     */
    public static function createCloudMessage() {
        return CVendor_Firebase_Messaging_CloudMessage::create();
    }

    /**
     * @param null|mixed $title
     * @param null|mixed $body
     * @param null|mixed $imageUrl
     *
     * @return CVendor_Firebase_Messaging_Notification
     */
    public static function createNotification($title = null, $body = null, $imageUrl = null) {
        return CVendor_Firebase_Messaging_Notification::create($title, $body, $imageUrl);
    }
}

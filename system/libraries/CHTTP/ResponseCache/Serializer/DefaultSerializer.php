<?php

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class CHTTP_ResponseCache_Serializer_DefaultSerializer implements CHTTP_ResponseCache_Serializer_SerializerInterface {
    const RESPONSE_TYPE_NORMAL = 'normal';

    const RESPONSE_TYPE_FILE = 'file';

    public function serialize(Response $response) {
        return serialize($this->getResponseData($response));
    }

    public function unserialize($serializedResponse) {
        $responseProperties = @unserialize($serializedResponse, ['allowed_classes' => [ResponseHeaderBag::class, Cookie::class]]);

        if (!$this->containsValidResponseProperties($responseProperties)) {
            throw CHTTP_ResponseCache_Exception_CouldNotUnserializeException::serializedResponse($serializedResponse);
        }

        $response = $this->buildResponse($responseProperties);

        $response->headers = $this->withoutCookies($responseProperties['headers']);

        return $response;
    }

    /**
     * @param Response $response
     *
     * @return array
     */
    protected function getResponseData(Response $response) {
        $statusCode = $response->getStatusCode();
        $headers = $this->withoutCookies(clone $response->headers);

        if ($response instanceof BinaryFileResponse) {
            $content = $response->getFile()->getPathname();
            $type = static::RESPONSE_TYPE_FILE;

            return compact('statusCode', 'headers', 'content', 'type');
        }

        $content = $response->getContent();
        $type = static::RESPONSE_TYPE_NORMAL;

        return compact('statusCode', 'headers', 'content', 'type');
    }

    /**
     * Cookie milik pengunjung pertama tidak boleh ikut tersimpan maupun diputar ulang ke pengunjung lain.
     *
     * @param mixed $headers
     *
     * @return mixed
     */
    protected function withoutCookies($headers) {
        if (!$headers instanceof ResponseHeaderBag) {
            return $headers;
        }
        foreach ($headers->getCookies() as $cookie) {
            $headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }
        $headers->remove('Set-Cookie');

        return $headers;
    }

    /**
     * @param mixed $properties
     *
     * @return bool
     */
    protected function containsValidResponseProperties($properties) {
        if (!is_array($properties)) {
            return false;
        }

        if (!isset($properties['content'], $properties['statusCode'])) {
            return false;
        }

        if (!isset($properties['headers']) || !$properties['headers'] instanceof ResponseHeaderBag) {
            return false;
        }

        return true;
    }

    protected function buildResponse(array $responseProperties) {
        $type = isset($responseProperties['type']) && $responseProperties['type'] != null ? $responseProperties['type'] : static::RESPONSE_TYPE_NORMAL;

        if ($type === static::RESPONSE_TYPE_FILE) {
            return new BinaryFileResponse(
                $responseProperties['content'],
                $responseProperties['statusCode']
            );
        }

        return new CHTTP_Response($responseProperties['content'], $responseProperties['statusCode']);
    }
}

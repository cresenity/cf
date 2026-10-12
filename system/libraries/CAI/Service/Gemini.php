<?php

/**
 * Google Gemini REST API (https://ai.google.dev) - text generation only for
 * now (image() not implemented; Gemini also has Imagen, nothing here needs
 * it yet).
 *
 * @see https://ai.google.dev/api/generate-content
 */
class CAI_Service_Gemini extends CAI_ServiceAbstract {
    /**
     * @var string
     */
    const DEFAULT_MODEL = 'gemini-2.5-flash';

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @param array $options
     */
    public function __construct($options = []) {
        $apiKey = carr::get($options, 'api_key');
        if (empty(trim((string) $apiKey))) {
            throw new InvalidArgumentException('Gemini API key cannot be empty');
        }
        $this->apiKey = $apiKey;
    }

    /**
     * @param array $options prompt, model (default gemini-2.5-flash), temperature (default 0.7)
     *
     * @throws CAI_Exception_ClientException
     *
     * @return string konten teks dari candidate pertama
     */
    public function ask(array $options = []) {
        $prompt = carr::get($options, 'prompt', c::optional($this)->prompt);
        if (empty($prompt)) {
            throw new CAI_Exception_ClientException('prompt cannot be empty');
        }

        $model = trim((string) carr::get($options, 'model', self::DEFAULT_MODEL), '/');

        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => (float) carr::get($options, 'temperature', 0.7)],
        ];

        try {
            $response = CHTTP::client()
                ->withHeaders(['x-goog-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                ->timeout(60)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $payload);
        } catch (Throwable $ex) {
            throw new CAI_Exception_ClientException('Gemini request failed: ' . $ex->getMessage(), 0, $ex);
        }

        if ($response->failed()) {
            $body = $response->json();
            $message = carr::get($body, 'error.message') ?: $response->body();

            throw new CAI_Exception_ClientException('Gemini request failed (HTTP ' . $response->status() . '): ' . $message);
        }

        $data = $response->json();
        $text = carr::get($data, 'candidates.0.content.parts.0.text');

        if (empty($text)) {
            $finishReason = carr::get($data, 'candidates.0.finishReason');

            throw new CAI_Exception_ClientException('Gemini returned no text' . ($finishReason ? " (finishReason: {$finishReason})" : ''));
        }

        return $text;
    }

    /**
     * Gemini also offers Imagen, not wired up here - this provider was only
     * asked for to generate/analyze text so far.
     *
     * @param array $options
     *
     * @throws CAI_Exception_ClientException
     */
    public function image(array $options = []) {
        throw new CAI_Exception_ClientException('CAI_Service_Gemini::image() is not implemented - this service only supports ask() for now.');
    }

    /**
     * Full decoded response of the last ask() call's underlying API, for
     * callers that need usageMetadata (token counts) alongside the text.
     * ask() itself only returns the text, matching every other CAI service's
     * ask() signature - use askRaw() instead when usage accounting matters.
     *
     * @param array $options sama seperti ask()
     *
     * @throws CAI_Exception_ClientException
     *
     * @return array respons JSON lengkap (candidates, usageMetadata, dst)
     */
    public function askRaw(array $options = []) {
        $prompt = carr::get($options, 'prompt', c::optional($this)->prompt);
        if (empty($prompt)) {
            throw new CAI_Exception_ClientException('prompt cannot be empty');
        }

        $model = trim((string) carr::get($options, 'model', self::DEFAULT_MODEL), '/');

        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => (float) carr::get($options, 'temperature', 0.7)],
        ];

        try {
            $response = CHTTP::client()
                ->withHeaders(['x-goog-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                ->timeout(60)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $payload);
        } catch (Throwable $ex) {
            throw new CAI_Exception_ClientException('Gemini request failed: ' . $ex->getMessage(), 0, $ex);
        }

        if ($response->failed()) {
            $body = $response->json();
            $message = carr::get($body, 'error.message') ?: $response->body();

            throw new CAI_Exception_ClientException('Gemini request failed (HTTP ' . $response->status() . '): ' . $message);
        }

        return $response->json();
    }
}

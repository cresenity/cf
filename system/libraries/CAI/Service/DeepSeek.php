<?php

use OpenAI\OpenAI;

/**
 * DeepSeek REST API (https://api-docs.deepseek.com) - wire-compatible with
 * OpenAI's chat completions endpoint, so this reuses the same `openai-php`
 * client as CAI_Service_OpenAIService, just pointed at DeepSeek's base URI
 * instead of OpenAI's. Text generation only - DeepSeek has no image
 * endpoint.
 *
 * @see https://api-docs.deepseek.com/
 */
class CAI_Service_DeepSeek extends CAI_ServiceAbstract {
    /**
     * @var string
     */
    const DEFAULT_MODEL = 'deepseek-chat';

    /**
     * @var \OpenAI\Client
     */
    protected $client;

    /**
     * @param array $options
     */
    public function __construct($options = []) {
        $apiKey = carr::get($options, 'api_key');
        if (empty(trim((string) $apiKey))) {
            throw new InvalidArgumentException('DeepSeek API key cannot be empty');
        }
        $this->client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri('api.deepseek.com/v1')
            ->withHttpClient(new \GuzzleHttp\Client(['timeout' => CF::config('ai.deepseek.request_timeout', 60)]))
            ->make();
    }

    /**
     * @param array $options prompt, model (default deepseek-chat), temperature (default 0.7)
     *
     * @throws CAI_Exception_ClientException
     *
     * @return string
     */
    public function ask(array $options = []) {
        $prompt = carr::get($options, 'prompt', c::optional($this)->prompt);
        if (empty($prompt)) {
            throw new CAI_Exception_ClientException('prompt cannot be empty');
        }

        try {
            $result = $this->client->chat()->create([
                'model' => carr::get($options, 'model', self::DEFAULT_MODEL),
                'temperature' => (float) carr::get($options, 'temperature', 0.7),
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
        } catch (Throwable $ex) {
            throw new CAI_Exception_ClientException('DeepSeek request failed: ' . $ex->getMessage(), 0, $ex);
        }

        $content = c::optional($result)->choices[0]->message->content;
        if (empty($content)) {
            throw new CAI_Exception_ClientException('DeepSeek returned no content');
        }

        return $content;
    }

    /**
     * @param array $options
     *
     * @throws CAI_Exception_ClientException
     */
    public function image(array $options = []) {
        throw new CAI_Exception_ClientException('CAI_Service_DeepSeek::image() is not implemented - DeepSeek has no image endpoint.');
    }

    /**
     * Expone the raw OpenAI-compatible client in case a caller needs usage
     * data (chat()->create() response ->usage) that ask() discards, same
     * reasoning as CAI_Service_OpenAIService::getOpenAI().
     *
     * @return \OpenAI\Client
     */
    public function getClient() {
        return $this->client;
    }
}

<?php

use OpenAI\OpenAI;

/**
 * Qwen (Alibaba Cloud Model Studio / DashScope) - compatible-mode endpoint,
 * wire-compatible with OpenAI's chat completions, same reasoning as
 * CAI_Service_DeepSeek: reuse the openai-php client pointed at DashScope's
 * base URI instead of OpenAI's.
 *
 * DashScope has two separate regions with different base URIs and API
 * keys - a key created in one region is rejected by the other's endpoint.
 *
 * @see https://help.aliyun.com/en/model-studio/compatible-mode
 */
class CAI_Service_Qwen extends CAI_ServiceAbstract {
    /**
     * @var string
     */
    const DEFAULT_MODEL = 'qwen-plus';

    /**
     * @var string
     */
    const REGION_INTL = 'intl';

    /**
     * @var string
     */
    const REGION_CHINA = 'china';

    /**
     * @var array region => base URI (tanpa skema, mengikuti pola withBaseUri DeepSeek)
     */
    const BASE_URI_BY_REGION = [
        self::REGION_INTL => 'dashscope-intl.aliyuncs.com/compatible-mode/v1',
        self::REGION_CHINA => 'dashscope.aliyuncs.com/compatible-mode/v1',
    ];

    /**
     * @var \OpenAI\Client
     */
    protected $client;

    /**
     * @param array $options api_key, region (intl|china, default intl)
     */
    public function __construct($options = []) {
        $apiKey = carr::get($options, 'api_key');
        if (empty(trim((string) $apiKey))) {
            throw new InvalidArgumentException('Qwen API key cannot be empty');
        }
        $region = carr::get($options, 'region', self::REGION_INTL);
        $baseUri = carr::get(self::BASE_URI_BY_REGION, $region, self::BASE_URI_BY_REGION[self::REGION_INTL]);

        $this->client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri($baseUri)
            ->withHttpClient(new \GuzzleHttp\Client(['timeout' => CF::config('ai.qwen.request_timeout', 60)]))
            ->make();
    }

    /**
     * @param array $options prompt, model (default qwen-plus), temperature (default 0.7)
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
            throw new CAI_Exception_ClientException('Qwen request failed: ' . $ex->getMessage(), 0, $ex);
        }

        $content = c::optional($result)->choices[0]->message->content;
        if (empty($content)) {
            throw new CAI_Exception_ClientException('Qwen returned no content');
        }

        return $content;
    }

    /**
     * Qwen punya model image generation sendiri (mis. wanx/qwen-image), tapi
     * lewat API async terpisah (task create lalu poll), bukan endpoint
     * compatible-mode synchronous seperti chat() - belum diimplementasikan.
     *
     * @param array $options
     *
     * @throws CAI_Exception_ClientException
     */
    public function image(array $options = []) {
        throw new CAI_Exception_ClientException('CAI_Service_Qwen::image() is not implemented yet - Qwen image generation uses a separate async task API.');
    }

    /**
     * @return \OpenAI\Client
     */
    public function getClient() {
        return $this->client;
    }
}

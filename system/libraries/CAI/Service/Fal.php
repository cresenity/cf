<?php

/**
 * fal.ai REST API (https://fal.ai) - image generation only for now (the whole
 * point of adding this provider was cheap FLUX image generation; fal.ai also
 * hosts text/LLM endpoints, but nothing here needs them yet, see ask()).
 *
 * Uses the synchronous `fal.run/<model>` endpoint (not `queue.fal.run`) -
 * fal's own docs reserve the queue+webhook pattern for long-running requests,
 * and FLUX schnell (the default model here) is fast enough that a plain
 * synchronous POST is enough, no polling needed.
 *
 * @see https://fal.ai/models/fal-ai/flux/schnell/api
 */
class CAI_Service_Fal extends CAI_ServiceAbstract {
    /**
     * @var string
     */
    const DEFAULT_MODEL = 'fal-ai/flux/schnell';

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
            throw new InvalidArgumentException('fal.ai API key cannot be empty');
        }
        $this->apiKey = $apiKey;
    }

    /**
     * fal.ai's chat/text-generation endpoints aren't wired up here - this
     * service was only ever asked for to generate images cheaply. Add a real
     * implementation if/when a text use case actually shows up.
     *
     * @param array $options
     *
     * @throws CAI_Exception_ClientException
     */
    public function ask(array $options = []) {
        throw new CAI_Exception_ClientException('CAI_Service_Fal::ask() is not implemented - this service only supports image() for now.');
    }

    /**
     * @param array $options prompt, model (fal.ai model id, default
     *                        fal-ai/flux/schnell), image_size (fal enum:
     *                        square_hd|square|portrait_4_3|portrait_16_9|
     *                        landscape_4_3|landscape_16_9, default square_hd),
     *                        num_images (default 1)
     *
     * @throws CAI_Exception_ClientException
     *
     * @return array decoded fal.ai response: images (array of {url, width,
     *               height, content_type}), seed, has_nsfw_concepts, timings
     */
    public function image(array $options = []) {
        $prompt = carr::get($options, 'prompt', c::optional($this)->prompt);

        if (empty($prompt)) {
            throw new CAI_Exception_ClientException('prompt cannot be empty');
        }

        $model = trim((string) carr::get($options, 'model', self::DEFAULT_MODEL), '/');

        $payload = [
            'prompt' => $prompt,
            'image_size' => carr::get($options, 'image_size', 'square_hd'),
            'num_images' => carr::get($options, 'num_images', 1),
        ];

        try {
            $response = CHTTP::client()
                ->withHeaders(['Authorization' => 'Key ' . $this->apiKey])
                ->timeout(60)
                ->post('https://fal.run/' . $model, $payload);
        } catch (Throwable $ex) {
            throw new CAI_Exception_ClientException('fal.ai request failed: ' . $ex->getMessage(), 0, $ex);
        }

        if ($response->failed()) {
            $body = $response->json();
            $message = carr::get($body, 'detail') ?: carr::get($body, 'message') ?: $response->body();

            throw new CAI_Exception_ClientException('fal.ai request failed (HTTP ' . $response->status() . '): ' . $message);
        }

        $data = $response->json();

        if (empty(carr::get($data, 'images'))) {
            throw new CAI_Exception_ClientException('fal.ai returned no images');
        }

        return $data;
    }
}

<?php

/**
 * fal.ai REST API (https://fal.ai) - image and video generation (fal.ai also
 * hosts text/LLM endpoints, but nothing here needs them yet, see ask()).
 *
 * image() uses the synchronous `fal.run/<model>` endpoint (not
 * `queue.fal.run`) - fal's own docs reserve the queue+webhook pattern for
 * long-running requests, and FLUX schnell (the default model here) is fast
 * enough that a plain synchronous POST is enough, no polling needed.
 *
 * video() is different: a video generation takes on the order of 1-3 minutes
 * (confirmed against the default model), far past what a synchronous POST
 * should block on - it goes through fal's queue API instead (`submit` ->
 * poll `status_url` -> fetch `response_url`), see
 * https://fal.ai/docs/documentation/model-apis/inference/queue. Callers must
 * not call video() inline in a web request for the same reason any other
 * minutes-long operation in this framework doesn't run inline (see the root
 * CLAUDE.md's "long-running process goes through the queue" rule) - run it
 * from a daemon/task-queue context instead.
 *
 * @see https://fal.ai/models/fal-ai/flux/schnell/api
 * @see https://fal.ai/models/fal-ai/wan-25-preview/text-to-video/api
 */
class CAI_Service_Fal extends CAI_ServiceAbstract {
    /**
     * @var string
     */
    const DEFAULT_MODEL = 'fal-ai/flux/schnell';

    /**
     * @var string
     */
    const DEFAULT_VIDEO_MODEL = 'fal-ai/wan-25-preview/text-to-video';

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

    /**
     * Blocks until the video finishes generating (typically 1-3 minutes) -
     * do not call inline in a web request, see the class docblock.
     *
     * @param array $options prompt, model (fal.ai model id, default
     *                        fal-ai/wan-25-preview/text-to-video),
     *                        poll_interval_seconds (default 5),
     *                        timeout_seconds (default 300), plus any other
     *                        key is passed straight through to fal.ai as a
     *                        request field (resolution, aspect_ratio,
     *                        duration, negative_prompt, seed, ...) - the
     *                        accepted fields are model-specific, see the
     *                        model's own API page
     *
     * @throws CAI_Exception_ClientException
     *
     * @return array decoded fal.ai response - shape is model-specific, but
     *               video models return a `video` object (at least `url`)
     */
    public function video(array $options = []) {
        $prompt = carr::get($options, 'prompt', c::optional($this)->prompt);

        if (empty($prompt)) {
            throw new CAI_Exception_ClientException('prompt cannot be empty');
        }

        $model = trim((string) carr::get($options, 'model', self::DEFAULT_VIDEO_MODEL), '/');
        $pollIntervalSeconds = max(1, (int) carr::get($options, 'poll_interval_seconds', 5));
        $timeoutSeconds = max($pollIntervalSeconds, (int) carr::get($options, 'timeout_seconds', 300));

        $payload = carr::except($options, ['model', 'poll_interval_seconds', 'timeout_seconds']);
        $payload['prompt'] = $prompt;

        try {
            $submitResponse = CHTTP::client()
                ->withHeaders(['Authorization' => 'Key ' . $this->apiKey])
                ->timeout(30)
                ->post('https://queue.fal.run/' . $model, $payload);
        } catch (Throwable $ex) {
            throw new CAI_Exception_ClientException('fal.ai video request failed: ' . $ex->getMessage(), 0, $ex);
        }

        if ($submitResponse->failed()) {
            $body = $submitResponse->json();
            $message = carr::get($body, 'detail') ?: carr::get($body, 'message') ?: $submitResponse->body();

            throw new CAI_Exception_ClientException('fal.ai video request failed (HTTP ' . $submitResponse->status() . '): ' . $message);
        }

        $submitData = $submitResponse->json();
        $statusUrl = carr::get($submitData, 'status_url');
        $responseUrl = carr::get($submitData, 'response_url');
        if (!$statusUrl || !$responseUrl) {
            throw new CAI_Exception_ClientException('fal.ai did not return status_url/response_url for the queued video request');
        }

        $status = $this->pollUntilComplete($statusUrl, $pollIntervalSeconds, $timeoutSeconds);
        if ($status !== 'COMPLETED') {
            throw new CAI_Exception_ClientException('fal.ai video generation did not complete within ' . $timeoutSeconds . ' seconds (last status: ' . $status . ')');
        }

        try {
            $resultResponse = CHTTP::client()
                ->withHeaders(['Authorization' => 'Key ' . $this->apiKey])
                ->timeout(30)
                ->get($responseUrl);
        } catch (Throwable $ex) {
            throw new CAI_Exception_ClientException('fal.ai failed to fetch the video result: ' . $ex->getMessage(), 0, $ex);
        }

        if ($resultResponse->failed()) {
            throw new CAI_Exception_ClientException('fal.ai failed to fetch the video result (HTTP ' . $resultResponse->status() . ')');
        }

        $data = $resultResponse->json();
        if (empty(carr::get($data, 'video.url')) && empty(carr::get($data, 'video_url'))) {
            throw new CAI_Exception_ClientException('fal.ai returned no video');
        }

        return $data;
    }

    /**
     * @param string $statusUrl
     * @param int    $pollIntervalSeconds
     * @param int    $timeoutSeconds
     *
     * @throws CAI_Exception_ClientException
     *
     * @return null|string status terakhir yang diterima (COMPLETED kalau berhasil)
     */
    protected function pollUntilComplete($statusUrl, $pollIntervalSeconds, $timeoutSeconds) {
        $deadline = time() + $timeoutSeconds;
        $status = null;

        while (time() < $deadline) {
            try {
                $statusResponse = CHTTP::client()
                    ->withHeaders(['Authorization' => 'Key ' . $this->apiKey])
                    ->timeout(15)
                    ->get($statusUrl);
            } catch (Throwable $ex) {
                throw new CAI_Exception_ClientException('fal.ai status check failed: ' . $ex->getMessage(), 0, $ex);
            }

            if ($statusResponse->failed()) {
                throw new CAI_Exception_ClientException('fal.ai status check failed (HTTP ' . $statusResponse->status() . ')');
            }

            $status = carr::get($statusResponse->json(), 'status');
            if ($status === 'COMPLETED') {
                break;
            }

            sleep($pollIntervalSeconds);
        }

        return $status;
    }
}

<?php

/**
 * ElevenLabs REST API (https://elevenlabs.io/docs) - text-to-speech only.
 * Doesn't fit CAI_ServiceAbstract's text/image shape at all (output is raw
 * audio bytes, not JSON), same reasoning CAI_Service_Fal added video() as a
 * new method beyond the abstract contract - ask()/image() both throw "not
 * implemented" here, the real capability is speech().
 *
 * @see https://elevenlabs.io/docs/api-reference/text-to-speech/convert
 */
class CAI_Service_ElevenLabs extends CAI_ServiceAbstract {
    /**
     * @var string
     */
    const DEFAULT_MODEL = 'eleven_multilingual_v2';

    /**
     * Rachel - a stock voice ElevenLabs' own docs use as the default
     * example; [VERIFIKASI] against a real account before production use,
     * voice availability can change.
     *
     * @var string
     */
    const DEFAULT_VOICE_ID = '21m00Tcm4TlvDq8ikWAM';

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
            throw new InvalidArgumentException('ElevenLabs API key cannot be empty');
        }
        $this->apiKey = $apiKey;
    }

    /**
     * @param array $options
     *
     * @throws CAI_Exception_ClientException
     */
    public function ask(array $options = []) {
        throw new CAI_Exception_ClientException('CAI_Service_ElevenLabs::ask() is not implemented - this service only supports speech().');
    }

    /**
     * @param array $options
     *
     * @throws CAI_Exception_ClientException
     */
    public function image(array $options = []) {
        throw new CAI_Exception_ClientException('CAI_Service_ElevenLabs::image() is not implemented - this service only supports speech().');
    }

    /**
     * @param array $options text, voice_id (default DEFAULT_VOICE_ID),
     *                        model (default eleven_multilingual_v2),
     *                        output_format (default mp3_44100_128),
     *                        stability/similarity_boost/style/use_speaker_boost
     *                        (voice_settings, all optional)
     *
     * @throws CAI_Exception_ClientException
     *
     * @return array {audio: raw bytes (string), content_type: string, characters: int}
     */
    public function speech(array $options = []) {
        $text = carr::get($options, 'text', carr::get($options, 'prompt'));
        if (empty($text)) {
            throw new CAI_Exception_ClientException('text cannot be empty');
        }

        $voiceId = carr::get($options, 'voice_id', self::DEFAULT_VOICE_ID);
        $model = carr::get($options, 'model', self::DEFAULT_MODEL);
        $outputFormat = carr::get($options, 'output_format', 'mp3_44100_128');

        $voiceSettings = [];
        foreach (['stability', 'similarity_boost', 'style', 'use_speaker_boost'] as $key) {
            if (array_key_exists($key, $options)) {
                $voiceSettings[$key] = $options[$key];
            }
        }

        $payload = ['text' => $text, 'model_id' => $model];
        if (!empty($voiceSettings)) {
            $payload['voice_settings'] = $voiceSettings;
        }

        try {
            $response = CHTTP::client()
                ->withHeaders(['xi-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                ->timeout(60)
                ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}?output_format={$outputFormat}", $payload);
        } catch (Throwable $ex) {
            throw new CAI_Exception_ClientException('ElevenLabs request failed: ' . $ex->getMessage(), 0, $ex);
        }

        if ($response->failed()) {
            $message = $response->body();
            // error responses are JSON, successful ones are raw audio - only try decoding on failure
            $decoded = json_decode($message, true);
            if (is_array($decoded)) {
                $message = carr::get($decoded, 'detail.message') ?: carr::get($decoded, 'detail') ?: $message;
            }

            throw new CAI_Exception_ClientException('ElevenLabs request failed (HTTP ' . $response->status() . '): ' . (is_string($message) ? $message : json_encode($message)));
        }

        $audio = $response->body();
        if (empty($audio)) {
            throw new CAI_Exception_ClientException('ElevenLabs returned no audio');
        }

        return [
            'audio' => $audio,
            'content_type' => $response->header('Content-Type') ?: 'audio/mpeg',
            'characters' => mb_strlen($text),
        ];
    }
}

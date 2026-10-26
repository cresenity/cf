<?php

class CAI {
    /**
     * @param array $options
     *
     * @return CAI_Service_OpenAIService
     */
    public static function openAI($options = []) {
        return CAI_Manager::instance()->createOpenAIService($options);
    }

    /**
     * @param array $options
     *
     * @return CAI_Service_HuggingFace
     */
    public static function huggingFace($options = []) {
        return CAI_Manager::instance()->createHuggingFace($options);
    }

    /**
     * @param array $options
     *
     * @return CAI_Service_Fal
     */
    public static function fal($options = []) {
        return CAI_Manager::instance()->createFal($options);
    }

    /**
     * @param array $options
     *
     * @return CAI_Service_Gemini
     */
    public static function gemini($options = []) {
        return CAI_Manager::instance()->createGemini($options);
    }

    /**
     * @param array $options
     *
     * @return CAI_Service_DeepSeek
     */
    public static function deepSeek($options = []) {
        return CAI_Manager::instance()->createDeepSeek($options);
    }

    /**
     * @param array $options
     *
     * @return CAI_Service_ElevenLabs
     */
    public static function elevenLabs($options = []) {
        return CAI_Manager::instance()->createElevenLabs($options);
    }

    /**
     * @param array $options
     *
     * @return CAI_Service_Qwen
     */
    public static function qwen($options = []) {
        return CAI_Manager::instance()->createQwen($options);
    }

    /**
     * @param string $name
     * @param string $version
     *
     * @return CAI_MCP_Server
     */
    public static function mcp($name, $version = '1.0.0') {
        return CAI_MCP::server($name, $version);
    }
}

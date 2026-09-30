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
}

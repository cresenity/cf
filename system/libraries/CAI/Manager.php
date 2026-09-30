<?php

class CAI_Manager {
    private static $instance;

    /**
     * @return CAI_Manager
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param array $options
     *
     * @return CAI_Service_OpenAIServicevoid
     */
    public function createOpenAIService($options = []) {
        return new CAI_Service_OpenAIService($options);
    }

    /**
     * @param array $options
     *
     * @return CAI_Service_HuggingFace
     */
    public function createHuggingFace($options = []) {
        return new CAI_Service_HuggingFace($options);
    }

    /**
     * @param array $options
     *
     * @return CAI_Service_Fal
     */
    public function createFal($options = []) {
        return new CAI_Service_Fal($options);
    }

    public function createOllamaService() {
    }

    public function service($name = null) {
    }

    public function resolveService($name) {
    }
}

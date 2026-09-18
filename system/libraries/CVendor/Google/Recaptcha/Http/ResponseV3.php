<?php

/**
 * Class     ResponseV3.
 */
class CVendor_Google_Recaptcha_Http_ResponseV3 extends CVendor_Google_Recaptcha_Http_AbstractResponse implements CVendor_Google_Recaptcha_Http_ResponseInterface {
    /**
     * Invalid JSON received.
     */
    const E_INVALID_JSON = 'invalid-json';

    /**
     * Could not connect to service.
     */
    const E_CONNECTION_FAILED = 'connection-failed';

    /**
     * Not a success, but no error codes received!
     */
    const E_UNKNOWN_ERROR = 'unknown-error';

    /**
     * Expected hostname did not match.
     */
    const E_HOSTNAME_MISMATCH = 'hostname-mismatch';

    /**
     * Expected APK package name did not match.
     */
    const E_APK_PACKAGE_NAME_MISMATCH = 'apk_package_name-mismatch';

    /**
     * Expected action did not match.
     */
    const E_ACTION_MISMATCH = 'action-mismatch';

    /**
     * Score threshold not met.
     */
    const E_SCORE_THRESHOLD_NOT_MET = 'score-threshold-not-met';

    /**
     * Challenge timeout.
     */
    const E_CHALLENGE_TIMEOUT = 'challenge-timeout';

    /* -----------------------------------------------------------------
      |  Properties
      | -----------------------------------------------------------------
     */

    /**
     * Score assigned to the request.
     *
     * @var null|float
     */
    private $score;

    /**
     * Action as specified by the page.
     *
     * @var string
     */
    private $action;

    /* -----------------------------------------------------------------
      |  Constructor
      | -----------------------------------------------------------------
     */

    /**
     * Response constructor.
     *
     * @param bool        $success
     * @param array       $errorCodes
     * @param null|string $hostname
     * @param null|string $challengeTs
     * @param null|string $apkPackageName
     * @param null|float  $score
     * @param null|string $action
     */
    public function __construct($success, array $errorCodes = [], $hostname = null, $challengeTs = null, $apkPackageName = null, $score = null, $action = null) {
        parent::__construct($success, $errorCodes, $hostname, $challengeTs, $apkPackageName);

        $this->score = $score;
        $this->action = $action;
    }

    /* -----------------------------------------------------------------
      |  Getters
      | -----------------------------------------------------------------
     */

    /**
     * Get score.
     *
     * @return float
     */
    public function getScore() {
        return $this->score;
    }

    /**
     * Get action.
     *
     * @return string
     */
    public function getAction() {
        return $this->action;
    }

    /* -----------------------------------------------------------------
      |  Main Methods
      | -----------------------------------------------------------------
     */

    /**
     * Build the response from an array.
     *
     * @param array $array
     *
     * @return \Arcanedev\NoCaptcha\Utilities\ResponseV3|mixed
     */
    public static function fromArray(array $array) {
        $hostname = carr::get($array, 'hostname');
        $challengeTs = carr::get($array, 'challenge_ts');
        $apkPackageName = carr::get($array, 'apk_package_name');
        $score = isset($array['score']) ? floatval($array['score']) : null;
        $action = carr::get($array, 'action');

        if (isset($array['success']) && $array['success'] == true) {
            return new static(true, [], $hostname, $challengeTs, $apkPackageName, $score, $action);
        }

        if (!(isset($array['error-codes']) && is_array($array['error-codes']))) {
            $array['error-codes'] = [self::E_UNKNOWN_ERROR];
        }

        return new static(false, $array['error-codes'], $hostname, $challengeTs, $apkPackageName, $score, $action);
    }

    /**
     * Convert the response object to array.
     *
     * @return array
     */
    public function toArray() {
        return [
            'success' => $this->isSuccess(),
            'hostname' => $this->getHostname(),
            'challenge_ts' => $this->getChallengeTs(),
            'apk_package_name' => $this->getApkPackageName(),
            'score' => $this->getScore(),
            'action' => $this->getAction(),
            'error-codes' => $this->getErrorCodes(),
        ];
    }

    /* -----------------------------------------------------------------
      |  Check Methods
      | -----------------------------------------------------------------
     */

    /**
     * Check the score.
     *
     * @param float $score
     *
     * @return bool
     */
    public function isScore($score) {
        return $this->getScore() >= floatval($score);
    }

    /**
     * Check the action name.
     *
     * @param string $action
     *
     * @return bool
     */
    public function isAction($action) {
        return $this->getAction() === $action;
    }
}

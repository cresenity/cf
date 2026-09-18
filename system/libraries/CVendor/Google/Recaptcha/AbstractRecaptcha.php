<?php

use Psr\Http\Message\ServerRequestInterface;

/**
 * Class     AbstractNoCaptcha.
 */
abstract class CVendor_Google_Recaptcha_AbstractRecaptcha implements CVendor_Google_Recaptcha_RecaptchaInterface {
    const CAPTCHA_NAME = 'g-recaptcha-response';

    /**
     * Use the global domain.
     *
     * @var bool
     */
    public static $useGlobalDomain = false;

    /**
     * The shared key between your site and ReCAPTCHA.
     *
     * @var string
     */
    protected $secret;

    /**
     * Your site key.
     *
     * @var string
     */
    protected $siteKey;

    /**
     * Forces the widget to render in a specific language.
     * Auto-detects the user's language if unspecified.
     *
     * @var string
     */
    protected $lang;

    /**
     * HTTP Request Client.
     *
     * @var CVendor_Google_Recaptcha_Http_Request
     */
    protected $request;

    /**
     * ReCaptcha's response.
     *
     * @var null|CVendor_Google_Recaptcha_Http_ResponseV3
     */
    protected $response;

    /* -----------------------------------------------------------------
      |  Constructor
      | -----------------------------------------------------------------
     */

    /**
     * NoCaptcha constructor.
     *
     * @param string      $secret
     * @param string      $siteKey
     * @param null|string $lang
     */
    public function __construct($secret, $siteKey, $lang = null) {
        $this->setSecret($secret);
        $this->setSiteKey($siteKey);
        $this->setLang($lang);

        $this->setRequestClient(new CVendor_Google_Recaptcha_Http_Request());
    }

    /* -----------------------------------------------------------------
      |  Getters & Setters
      | -----------------------------------------------------------------
     */

    /**
     * Set the secret key.
     *
     * @param string $secret
     *
     * @return $this
     */
    protected function setSecret($secret) {
        self::checkKey('secret key', $secret);

        $this->secret = $secret;

        return $this;
    }

    /**
     * Get the site key.
     *
     * @return string
     */
    public function getSiteKey() {
        return $this->siteKey;
    }

    /**
     * Set Site key.
     *
     * @param string $siteKey
     *
     * @return $this
     */
    protected function setSiteKey($siteKey) {
        self::checkKey('site key', $siteKey);

        $this->siteKey = $siteKey;

        return $this;
    }

    /**
     * Set language code.
     *
     * @param string $lang
     *
     * @return $this
     */
    public function setLang($lang) {
        $this->lang = $lang;

        return $this;
    }

    /**
     * Set HTTP Request Client.
     *
     * @param CVendor_Google_Recaptcha_Http_Request $request
     *
     * @return $this
     */
    public function setRequestClient(CVendor_Google_Recaptcha_Http_Request $request) {
        $this->request = $request;

        return $this;
    }

    /**
     * Get the last response.
     *
     * @return null|CVendor_Google_Recaptcha_Http_AbstractResponse
     */
    public function getLastResponse() {
        return $this->response;
    }

    /**
     * Get the client url.
     *
     * @return string
     */
    public static function getClientUrl() {
        return static::$useGlobalDomain ? 'https://www.recaptcha.net/recaptcha/api.js' : 'https://www.google.com/recaptcha/api.js';
    }

    /**
     * Get the verification url.
     *
     * @return string
     */
    public static function getVerificationUrl() {
        return static::$useGlobalDomain ? 'https://www.recaptcha.net/recaptcha/api/siteverify' : 'https://www.google.com/recaptcha/api/siteverify';
    }

    /* -----------------------------------------------------------------
      |  Main Methods
      | -----------------------------------------------------------------
     */

    /**
     * Verify Response.
     *
     * @param string $response
     * @param string $clientIp
     *
     * @return CVendor_Google_Recaptcha_Http_AbstractResponse|mixed
     */
    public function verify($response, $clientIp = null) {
        return $this->response = $this->sendVerifyRequest([
            'secret' => $this->secret,
            'response' => $response,
            'remoteip' => $clientIp
        ]);
    }

    /**
     * Calls the reCAPTCHA siteverify API to verify whether the user passes CAPTCHA
     * test using a PSR-7 ServerRequest object.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     *
     * @return CVendor_Google_Recaptcha_Http_AbstractResponse
     */
    public function verifyRequest(ServerRequestInterface $request) {
        $body = $request->getParsedBody();
        $server = $request->getServerParams();

        return $this->verify(
            isset($body[self::CAPTCHA_NAME]) ? $body[self::CAPTCHA_NAME] : '',
            isset($server['REMOTE_ADDR']) ? $server['REMOTE_ADDR'] : null
        );
    }

    /**
     * Send verify request to API and get response.
     *
     * @param array $query
     *
     * @return CVendor_Google_Recaptcha_Http_AbstractResponse
     */
    protected function sendVerifyRequest(array $query = []) {
        $query = array_filter($query);
        $json = $this->request->send(
            $this->getVerificationUrl() . '?' . http_build_query($query)
        );

        return $this->parseResponse($json);
    }

    /**
     * Parse the response.
     *
     * @param string $json
     *
     * @return CVendor_Google_Recaptcha_Http_AbstractResponse|mixed
     */
    abstract protected function parseResponse($json);

    /* -----------------------------------------------------------------
      |  Check Methods
      | -----------------------------------------------------------------
     */

    /**
     * Check if has lang.
     *
     * @return bool
     */
    protected function hasLang() {
        return !empty($this->lang);
    }

    /**
     * Check key.
     *
     * @param string $name
     * @param string $value
     */
    private static function checkKey($name, &$value) {
        self::checkIsString($name, $value);

        $value = trim($value);

        self::checkIsNotEmpty($name, $value);
    }

    /**
     * Check if the value is a string value.
     *
     * @param string $name
     * @param string $value
     *
     * @throws CVendor_Google_Recaptcha_Exception_ApiException
     */
    private static function checkIsString($name, $value) {
        if (!is_string($value)) {
            throw new CVendor_Google_Recaptcha_Exception_ApiException(
                "The {$name} must be a string value, " . gettype($value) . ' given.'
            );
        }
    }

    /**
     * Check if the value is not empty.
     *
     * @param string $name
     * @param string $value
     *
     * @throws CVendor_Google_Recaptcha_Exception_ApiException
     */
    private static function checkIsNotEmpty($name, $value) {
        if (empty($value)) {
            throw new CVendor_Google_Recaptcha_Exception_ApiException("The {$name} must not be empty");
        }
    }

    public function createControl($inputName = 'g-recaptcha-response', $type = 'simple', $options = []) {
        $label = carr::get($options, 'label', 'Send');

        $controlRecaptcha = new CElement_FormInput_GoogleRecaptcha('');
        $controlRecaptcha->setRecaptcha($this);
        $controlRecaptcha->setRecaptchaLabel($label);
        $controlRecaptcha->setRecaptchaType($type);

        $controlRecaptcha->setRecaptchaInputName($inputName);

        $scriptSrc = $this->getScriptSrc();
        CManager::instance()->registerJs($scriptSrc);

        return $controlRecaptcha;
    }
}

<?php

/**
 * Talks to the DevCloud OAuth2 API (password grant) and caches the
 * resulting access/refresh token on disk, under the DevSuite home folder.
 */
class CDevSuite_DevCloud_Api {
    /**
     * @var string
     */
    const BASE_URL = 'https://devcloud.cresenity.com';

    /**
     * The "Devcloud Internal" OAuth client, flagged as a password_client.
     *
     * @var int
     */
    const CLIENT_ID = 1;

    /**
     * @var string
     */
    const CLIENT_SECRET = '4YjEZtDdaPa70y0DS3gWnqwGxF50tLo6dtiDrfd2';

    /**
     * @var CDevSuite_Filesystem
     */
    protected $files;

    public function __construct() {
        $this->files = CDevSuite::filesystem();
    }

    /**
     * @return string
     */
    public function tokenPath() {
        return CDevSuite::homePath() . 'devcloud' . DS . 'oauth.json';
    }

    /**
     * Log in with a DevCloud username/password and cache the token pair.
     *
     * @param string $username
     * @param string $password
     *
     * @return array
     */
    public function login($username, $password) {
        $token = $this->requestToken([
            'grant_type' => 'password',
            'client_id' => static::CLIENT_ID,
            'client_secret' => static::CLIENT_SECRET,
            'username' => $username,
            'password' => $password,
        ]);

        return $this->storeToken($token);
    }

    /**
     * Exchange the stored refresh token for a new access/refresh token pair.
     *
     * @return array
     */
    public function refresh() {
        $stored = $this->readToken();

        if (empty($stored['refresh_token'])) {
            throw new Exception('Not logged in to DevCloud, run `phpcf devcloud:login` first.');
        }

        $token = $this->requestToken([
            'grant_type' => 'refresh_token',
            'client_id' => static::CLIENT_ID,
            'client_secret' => static::CLIENT_SECRET,
            'refresh_token' => $stored['refresh_token'],
        ]);

        return $this->storeToken($token);
    }

    /**
     * Get a valid access token, transparently refreshing it if it has expired.
     *
     * @return null|string
     */
    public function accessToken() {
        $stored = $this->readToken();

        if (empty($stored)) {
            return null;
        }

        if ($this->isExpired($stored)) {
            $stored = $this->refresh();
        }

        return carr::get($stored, 'access_token');
    }

    /**
     * @return bool
     */
    public function isLoggedIn() {
        return !empty($this->readToken());
    }

    /**
     * Report the local token file state, plus a live check of whether the
     * cached credentials are actually usable right now (refreshing if the
     * access token looks expired).
     *
     * @return array
     */
    public function session() {
        $path = $this->tokenPath();
        $stored = $this->readToken();

        $status = [
            'token_path' => $path,
            'logged_in' => !empty($stored),
            'file_modified_at' => file_exists($path) ? filemtime($path) : null,
            'access_token_expires_at' => carr::get($stored, 'expires_at'),
            'access_token_expired' => empty($stored) ? null : $this->isExpired($stored),
            'has_refresh_token' => !empty(carr::get($stored, 'refresh_token')),
            'live_valid' => false,
            'live_error' => null,
        ];

        if (!empty($stored)) {
            try {
                $status['live_valid'] = !empty($this->accessToken());
            } catch (Exception $e) {
                $status['live_error'] = $e->getMessage();
            }
        }

        return $status;
    }

    /**
     * Call an authenticated Devcloud API method (e.g. "app/getInfo"), using
     * the cached (and auto-refreshed) access token. Returns the "data" key
     * of the {errCode, errMessage, data} envelope, or throws on failure.
     *
     * @param string $path
     * @param array  $params
     * @param string $method "get" for reads, "post" for state-changing calls
     *
     * @return mixed
     */
    public function request($path, array $params = [], $method = 'get') {
        $token = $this->accessToken();

        if (empty($token)) {
            throw new Exception('Not logged in to DevCloud, run `phpcf devcloud:login` first.');
        }

        try {
            $pending = CHTTP::client()
                ->withToken($token)
                ->withUserAgent(static::userAgent())
                ->acceptJson()
                ->timeout(15);

            $url = static::BASE_URL . '/api/devcloud/' . ltrim($path, '/');

            $response = strtolower($method) === 'post' ? $pending->post($url, $params) : $pending->get($url, $params);
        } catch (Exception $e) {
            throw new Exception('Unable to reach DevCloud API: ' . $e->getMessage());
        }

        $data = $response->json();

        if ($response->failed() || !is_array($data)) {
            throw new Exception('Request to ' . $path . ' failed.');
        }

        if ((int) carr::get($data, 'errCode', 0) !== 0) {
            throw new Exception(carr::get($data, 'errMessage', 'Request to ' . $path . ' failed.'));
        }

        return carr::get($data, 'data');
    }

    /**
     * Forget the cached token pair.
     *
     * @return void
     */
    public function logout() {
        if ($this->files->exists($this->tokenPath())) {
            $this->files->unlink($this->tokenPath());
        }
    }

    /**
     * A descriptive User-Agent identifying this as the phpcf CLI on a given
     * OS - Account Setting > Login Devices on the server parses this to show
     * a friendly device name, the way Google's "Your devices" page does.
     * The default Guzzle User-Agent ("GuzzleHttp/7") carries no OS info at all.
     *
     * @return string
     */
    protected static function userAgent() {
        return 'phpcf-devcloud-cli/1.0 (' . PHP_OS_FAMILY . ')';
    }

    /**
     * @param array $token
     *
     * @return bool
     */
    protected function isExpired($token) {
        $expiresAt = carr::get($token, 'expires_at');

        return $expiresAt === null || time() >= $expiresAt;
    }

    /**
     * @return array
     */
    protected function readToken() {
        if (!$this->files->exists($this->tokenPath())) {
            return [];
        }

        return (array) json_decode($this->files->get($this->tokenPath()), true);
    }

    /**
     * @param array $token
     *
     * @return array
     */
    protected function storeToken(array $token) {
        $data = [
            'token_type' => carr::get($token, 'token_type'),
            'access_token' => carr::get($token, 'access_token'),
            'refresh_token' => carr::get($token, 'refresh_token'),
            'expires_at' => time() + (int) carr::get($token, 'expires_in', 0),
        ];

        $this->files->ensureDirExists(dirname($this->tokenPath()));
        $this->files->putAsUser($this->tokenPath(), json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL);

        return $data;
    }

    /**
     * @param array $params
     *
     * @return array
     */
    protected function requestToken(array $params) {
        try {
            $response = CHTTP::client()
                ->asForm()
                ->withUserAgent(static::userAgent())
                ->timeout(15)
                ->post(static::BASE_URL . '/api/devcloud/oauth/token', $params);
        } catch (Exception $e) {
            throw new Exception('Unable to reach DevCloud API: ' . $e->getMessage());
        }

        $data = $response->json();

        if ($response->failed() || !is_array($data) || empty($data['access_token'])) {
            $message = carr::get($data, 'message', carr::get($data, 'error_description', carr::get($data, 'error', 'Login failed.')));

            throw new Exception($message);
        }

        return $data;
    }
}

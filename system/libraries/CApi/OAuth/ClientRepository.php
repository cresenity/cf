<?php

class CApi_OAuth_ClientRepository {
    /**
     * CApi OAuth.
     *
     * @var CApi_OAuth
     */
    protected $oauth;

    /**
     * The personal access client ID.
     *
     * @var null|int|string
     */
    protected $personalAccessClientId;

    /**
     * The personal access client secret.
     *
     * @var null|string
     */
    protected $personalAccessClientSecret;

    /**
     * Create a new client repository.
     *
     * @param null|int|string $personalAccessClientId
     * @param null|string     $personalAccessClientSecret
     *
     * @return void
     */
    public function __construct(CApi_OAuth $oauth, $personalAccessClientId = null, $personalAccessClientSecret = null) {
        $this->oauth = $oauth;

        $this->personalAccessClientId = $personalAccessClientId;
        $this->personalAccessClientSecret = $personalAccessClientSecret;
    }

    /**
     * Get a client by the given ID.
     *
     * @param int $id
     *
     * @return null|\CApi_OAuth_Model_OAuthClient
     */
    public function find($id) {
        $client = $this->oauth->client();

        return $client->where($client->getKeyName(), $id)->first();
    }

    /**
     * Get an active client by the given ID.
     *
     * @param int $id
     *
     * @return null|\CApi_OAuth_Model_OAuthClient
     */
    public function findActive($id) {
        $client = $this->find($id);

        return $client && !$client->revoked ? $client : null;
    }

    /**
     * Get a client instance for the given ID and user ID.
     *
     * @param int   $clientId
     * @param mixed $userId
     *
     * @return null|\CApi_OAuth_Model_OAuthClient
     */
    public function findForUser($clientId, $userId) {
        $client = $this->oauth->client();

        return $client
            ->where($client->getKeyName(), $clientId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Get the client instances for the given user ID.
     *
     * @param mixed $userId
     *
     * @return \CModel_Collection
     */
    public function forUser($userId) {
        return $this->oauth->client()
            ->where('user_id', $userId)
            ->orderBy('name', 'asc')->get();
    }

    /**
     * Get the active client instances for the given user ID.
     *
     * @param mixed $userId
     *
     * @return \CModel_Collection
     */
    public function activeForUser($userId) {
        return $this->forUser($userId)->reject(function ($client) {
            return $client->revoked;
        })->values();
    }

    /**
     * Get the personal access token client for the application.
     *
     * @throws \RuntimeException
     *
     * @return \CApi_OAuth_Model_OAuthClient
     */
    public function personalAccessClient() {
        if ($this->personalAccessClientId) {
            return $this->find($this->personalAccessClientId);
        }

        $client = $this->oauth->personalAccessClient();

        if (!$client->exists()) {
            throw new RuntimeException('Personal access client not found. Please create one.');
        }

        return $client->orderBy($client->getKeyName(), 'desc')->first()->client;
    }

    /**
     * Store a new client.
     *
     * @param int         $orgId
     * @param int         $userId
     * @param string      $userType
     * @param string      $name
     * @param string      $redirect
     * @param null|string $provider
     * @param bool        $personalAccess
     * @param bool        $password
     * @param bool        $confidential
     *
     * @return \CApi_OAuth_Model_OAuthClient
     */
    public function create($orgId, $userId, $userType, $name, $redirect, $provider = null, $personalAccess = false, $password = false, $confidential = true) {
        $client = $this->oauth->client()->forceFill([
            'org_id' => $orgId,
            'user_id' => $userId,
            'user_type' => $userType,
            'name' => $name,
            'secret' => ($confidential || $personalAccess) ? cstr::random(40) : null,
            'provider' => $provider,
            'redirect' => $redirect,
            'personal_access_client' => $personalAccess,
            'password_client' => $password,
            'revoked' => false,
        ]);

        $client->save();

        return $client;
    }

    /**
     * Store a new personal access token client.
     *
     * @param int    $userId
     * @param string $name
     * @param string $redirect
     * @param mixed  $orgId
     * @param mixed  $userType
     *
     * @return \CApi_OAuth_Model_OAuthClient
     */
    public function createPersonalAccessClient($orgId, $userId, $userType, $name, $redirect) {
        return c::tap($this->create($orgId, $userId, $userType, $name, $redirect, null, true), function ($client) {
            $accessClient = $this->oauth->personalAccessClient();
            $accessClient->oauth_client_id = $client->oauth_client_id;
            $accessClient->save();
        });
    }

    /**
     * Store a new password grant client.
     *
     * @param int         $orgId
     * @param int         $userId
     * @param string      $name
     * @param string      $redirect
     * @param null|string $provider
     * @param mixed       $userType
     *
     * @return \CApi_OAuth_Model_OAuthClient
     */
    public function createPasswordGrantClient($orgId, $userId, $userType, $name, $redirect, $provider = null) {
        return $this->create($orgId, $userId, $userType, $name, $redirect, $provider, false, true);
    }

    /**
     * Update the given client.
     *
     * @param \CApi_OAuth_Model_OAuthClient $client
     * @param string                        $name
     * @param string                        $redirect
     *
     * @return \CApi_OAuth_Model_OAuthClient
     */
    public function update(CApi_OAuth_Model_OAuthClient $client, $name, $redirect) {
        $client->forceFill([
            'name' => $name, 'redirect' => $redirect,
        ])->save();

        return $client;
    }

    /**
     * Regenerate the client secret.
     *
     * @param \CApi_OAuth_Model_OAuthClient $client
     *
     * @return \CApi_OAuth_Model_OAuthClient
     */
    public function regenerateSecret(CApi_OAuth_Model_OAuthClient $client) {
        $client->forceFill([
            'secret' => cstr::random(40),
        ])->save();

        return $client;
    }

    /**
     * Determine if the given client is revoked.
     *
     * @param int $id
     *
     * @return bool
     */
    public function revoked($id) {
        $client = $this->find($id);

        return is_null($client) || $client->revoked;
    }

    /**
     * Delete the given client.
     *
     * @param \CApi_OAuth_Model_OAuthClient $client
     *
     * @return void
     */
    public function delete(CApi_OAuth_Model_OAuthClient $client) {
        $client->oauthAccessToken()->update(['revoked' => true]);

        $client->forceFill(['revoked' => true])->save();
    }

    /**
     * Get the personal access client id.
     *
     * @return null|int|string
     */
    public function getPersonalAccessClientId() {
        return $this->personalAccessClientId;
    }

    /**
     * Get the personal access client secret.
     *
     * @return null|string
     */
    public function getPersonalAccessClientSecret() {
        return $this->personalAccessClientSecret;
    }

    /**
     * @return CApi_OAuth
     */
    public function oauth() {
        return $this->oauth;
    }
}

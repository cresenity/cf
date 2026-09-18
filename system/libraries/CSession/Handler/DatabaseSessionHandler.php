<?php

/**
 * Description of DatabaseSessionHandler.
 */
class CSession_Handler_DatabaseSessionHandler implements SessionHandlerInterface {
    use CTrait_Helper_InteractsWithTime;

    /**
     * The database connection instance.
     *
     * @var CDatabase
     */
    protected $connection;

    /**
     * The name of the session table.
     *
     * @var string
     */
    protected $table;

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    protected $seconds;

    /**
     * The existence state of the session.
     *
     * @var bool
     */
    protected $exists;

    /**
     * Create a new database session handler instance.
     *
     * @param \CDatabase_Connection $connection
     * @param string                $table
     * @param int                   $seconds
     *
     * @return void
     */
    public function __construct(CDatabase_Connection $connection, $table, $seconds) {
        $this->table = $table;
        $this->seconds = $seconds;
        $this->connection = $connection;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName) {
        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function close() {
        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function read($sessionId) {
        $session = (object) $this->getQuery()->where('key', '=', $sessionId)->first();

        if ($this->expired($session)) {
            $this->exists = true;

            return '';
        }

        if (isset($session->payload)) {
            $this->exists = true;

            return base64_decode($session->payload);
        }

        return '';
    }

    /**
     * Determine if the session is expired.
     *
     * @param \stdClass $session
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    protected function expired($session) {
        return isset($session->last_activity)
            && $session->last_activity < CCarbon::now()->subSeconds($this->seconds)->getTimestamp();
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function write($sessionId, $data) {
        $payload = $this->getDefaultPayload($data);

        if (!$this->exists) {
            $this->read($sessionId);
        }

        if ($this->exists) {
            $this->performUpdate($sessionId, $payload);
        } else {
            $this->performInsert($sessionId, $payload);
        }

        return $this->exists = true;
    }

    /**
     * Perform an insert operation on the session ID.
     *
     * @param string               $sessionId
     * @param array<string, mixed> $payload
     *
     * @return null|bool
     */
    protected function performInsert($sessionId, $payload) {
        try {
            carr::set($payload, 'key', $sessionId);
            carr::set($payload, 'created', c::now());
            carr::set($payload, 'updated', c::now());

            return $this->getQuery()->insert($payload);
        } catch (CDatabase_Exception_QueryException $e) {
            throw $e;
            //$this->performUpdate($sessionId, $payload);
        }
    }

    /**
     * Perform an update operation on the session ID.
     *
     * @param string $sessionId
     * @param array  $payload
     *
     * @return int
     */
    protected function performUpdate($sessionId, $payload) {
        carr::set($payload, 'updated', c::now());

        return $this->getQuery()->where('key', $sessionId)->update($payload);
    }

    /**
     * Get the default payload for the session.
     *
     * @param string $data
     *
     * @return array
     */
    protected function getDefaultPayload($data) {
        $payload = [
            'payload' => base64_encode($data),
            'last_activity' => $this->currentTime(),
        ];

        return c::tap($payload, function (&$payload) {
            $this->addUserInformation($payload)
                ->addRequestInformation($payload);
        });
    }

    /**
     * Add the user information to the session payload.
     *
     * @param array $payload
     *
     * @return $this
     */
    protected function addUserInformation(&$payload) {
        $payload['user_id'] = $this->userId();

        return $this;
    }

    /**
     * Get the currently authenticated user's ID.
     *
     * @return mixed
     */
    protected function userId() {
        return c::app()->auth()->id();
    }

    /**
     * Add the request information to the session payload.
     *
     * @param array $payload
     *
     * @return $this
     */
    protected function addRequestInformation(&$payload) {
        $payload = array_merge($payload, [
            'ip_address' => $this->ipAddress(),
            'user_agent' => $this->userAgent(),
        ]);

        return $this;
    }

    /**
     * Get the IP address for the current request.
     *
     * @return string
     */
    protected function ipAddress() {
        return c::request()->ip();
    }

    /**
     * Get the user agent for the current request.
     *
     * @return string
     */
    protected function userAgent() {
        return substr((string) c::request()->header('User-Agent'), 0, 500);
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function destroy($sessionId) {
        $this->getQuery()->where('key', $sessionId)->delete();

        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function gc($lifetime) {
        $this->getQuery()->where('last_activity', '<=', $this->currentTime() - $lifetime)->delete();
    }

    /**
     * Get a fresh query builder instance for the table.
     *
     * @return \CDatabase_Query_Builder
     */
    protected function getQuery() {
        return $this->connection->table($this->table);
    }

    /**
     * Set the existence state for the session.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setExists($value) {
        $this->exists = $value;

        return $this;
    }
}

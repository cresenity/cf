<?php

class CApi_OAuth_Model_OAuthDeviceCode extends CModel {
    const STATUS_PENDING = 'PENDING';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_DENIED = 'DENIED';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'oauth_device_code';

    /**
     * The guarded attributes on the model.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'last_polled_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'expires_at',
        'last_polled_at',
    ];

    /**
     * Get the client that owns the device code.
     *
     * @return \CModel_Relation_BelongsTo
     */
    public function oauthClient() {
        return $this->belongsTo(CApi::oauth()->clientModel());
    }

    /**
     * Get the current connection name for the model.
     *
     * @return null|string
     */
    public function getConnectionName() {
        return CF::config('api.oauth.storage.database.connection') ?: $this->connection;
    }
}

<?php

/**
 * Penyimpanan baris `oauth_device_code` (RFC 8628). Bukan implementasi
 * interface League mana pun -- League tidak punya padanan device grant sama
 * sekali (lihat CApi_OAuth_Bridge_DeviceCodeGrant), jadi kelas ini dipakai
 * langsung oleh grant tersebut maupun oleh endpoint inisiasi/approve/deny,
 * bukan di-auto-wire oleh AuthorizationServer::enableGrantType().
 */
class CApi_OAuth_DeviceCodeRepository {
    /**
     * @var CApi_OAuth
     */
    protected $oauth;

    public function __construct(CApi_OAuth $oauth) {
        $this->oauth = $oauth;
    }

    /**
     * @param \CApi_OAuth_Bridge_Client $client
     * @param string[]                  $scopeIds identifier scope saja (string), bukan entity --
     *                                             sama dengan bentuk yang dibaca balik oleh
     *                                             AbstractGrant::validateScopes() di DeviceCodeGrant
     * @param string                    $verificationUri
     *
     * @return \CApi_OAuth_Model_OAuthDeviceCode
     */
    public function create($client, array $scopeIds, $verificationUri) {
        $userType = $this->oauth->getUserModelFromProvider();

        $deviceCode = $this->oauth->deviceCode();
        $deviceCode->forceFill([
            'oauth_client_id' => $client->getIdentifier(),
            'device_code' => $this->generateCode(40),
            'user_code' => $this->generateUserCode(),
            'user_type' => $userType,
            'scopes' => $scopeIds,
            'verification_uri' => $verificationUri,
            'device_code_status' => CApi_OAuth_Model_OAuthDeviceCode::STATUS_PENDING,
            'expires_at' => CCarbon::now()->add($this->oauth->deviceCodeExpireIn()),
            'createdby' => 'device-authorization',
            'updatedby' => 'device-authorization',
        ]);
        $deviceCode->save();

        return $deviceCode;
    }

    /**
     * @param string $deviceCode
     *
     * @return null|\CApi_OAuth_Model_OAuthDeviceCode
     */
    public function findByDeviceCode($deviceCode) {
        return $this->oauth->deviceCode()->where('device_code', $deviceCode)->first();
    }

    /**
     * Baris yang masih PENDING dan belum kedaluwarsa saja -- user_code yang sudah
     * dipakai/expired tidak boleh bisa di-approve lagi lewat pencarian ini.
     *
     * @param string $userCode
     *
     * @return null|\CApi_OAuth_Model_OAuthDeviceCode
     */
    public function findPendingByUserCode($userCode) {
        return $this->oauth->deviceCode()
            ->where('user_code', $userCode)
            ->where('device_code_status', CApi_OAuth_Model_OAuthDeviceCode::STATUS_PENDING)
            ->where('expires_at', '>', CCarbon::now())
            ->first();
    }

    /**
     * @param \CApi_OAuth_Model_OAuthDeviceCode $deviceCode
     * @param mixed                             $userIdentifier
     *
     * @return void
     */
    public function approve(CApi_OAuth_Model_OAuthDeviceCode $deviceCode, $userIdentifier) {
        $deviceCode->user_id = $userIdentifier;
        $deviceCode->device_code_status = CApi_OAuth_Model_OAuthDeviceCode::STATUS_APPROVED;
        $deviceCode->save();
    }

    /**
     * @param \CApi_OAuth_Model_OAuthDeviceCode $deviceCode
     *
     * @return void
     */
    public function deny(CApi_OAuth_Model_OAuthDeviceCode $deviceCode) {
        $deviceCode->device_code_status = CApi_OAuth_Model_OAuthDeviceCode::STATUS_DENIED;
        $deviceCode->save();
    }

    /**
     * Tandai baris ini sudah dipakai (token sudah diterbitkan) -- single use,
     * supaya device_code yang sama tidak bisa dipakai lagi untuk polling berikutnya.
     *
     * @param \CApi_OAuth_Model_OAuthDeviceCode $deviceCode
     *
     * @return void
     */
    public function consume(CApi_OAuth_Model_OAuthDeviceCode $deviceCode) {
        $deviceCode->delete();
    }

    /**
     * @param \CApi_OAuth_Model_OAuthDeviceCode $deviceCode
     *
     * @return void
     */
    public function touchPolled(CApi_OAuth_Model_OAuthDeviceCode $deviceCode) {
        $deviceCode->last_polled_at = CCarbon::now();
        $deviceCode->save();
    }

    /**
     * @param \CApi_OAuth_Model_OAuthDeviceCode $deviceCode
     *
     * @return bool
     */
    public function isPolledTooSoon(CApi_OAuth_Model_OAuthDeviceCode $deviceCode) {
        if ($deviceCode->last_polled_at === null) {
            return false;
        }

        $minInterval = $this->oauth->deviceCodePollingInterval();

        return CCarbon::now()->lessThan((clone $deviceCode->last_polled_at)->add($minInterval));
    }

    /**
     * @param int $length
     *
     * @return string
     */
    protected function generateCode($length) {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    /**
     * Kode pendek yang diketik manual manusia -- format XXXX-XXXX, tanpa karakter
     * ambigu (0/O, 1/I/L), dijamin unik di antara baris yang masih PENDING.
     *
     * @return string
     */
    protected function generateUserCode() {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $raw = '';
            for ($i = 0; $i < 8; $i++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        } while ($this->oauth->deviceCode()->where('user_code', $code)
            ->where('device_code_status', CApi_OAuth_Model_OAuthDeviceCode::STATUS_PENDING)
            ->where('expires_at', '>', CCarbon::now())
            ->exists());

        return $code;
    }
}

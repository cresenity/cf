<?php

/**
 * `POST oauth/device_authorization` (RFC 8628 §3.1) -- TANPA auth (client belum
 * punya token sama sekali di titik ini), pola sama dengan Method/Token.php:
 * endpoint protokol OAuth murni, bukan DApi_Console_Method_* app-level.
 *
 * Menolak jelas kalau CApi_OAuth::$deviceCodeGrantEnabled mati untuk grup api ini
 * (lihat Loader::registerAuthorizationServer()) -- endpoint ini memang ada di
 * semua app yang memakai CApi_OAuth (kode framework dibagi bersama), tapi hanya
 * benar-benar bisa dipakai oleh app yang menyalakan opt-in-nya; app lain selalu
 * dapat error jelas di sini, tidak diam-diam menerbitkan device_code yang tidak
 * akan pernah bisa ditukar token (grant-nya tidak ter-enable di /oauth/token).
 */
class CApi_OAuth_Method_DeviceAuthorization extends CApi_OAuth_MethodAbstract {
    public function execute() {
        $oauth = $this->getOAuth();

        if (!$oauth->deviceCodeGrantEnabled) {
            $this->fail('Device authorization grant tidak diaktifkan untuk API group ini');

            return $this;
        }
        if (!$oauth->deviceVerificationUri) {
            $this->fail('deviceVerificationUri belum dikonfigurasi untuk API group ini');

            return $this;
        }

        $request = $this->request();
        $clientId = carr::get($request, 'client_id');
        if ($clientId === null || $clientId === '') {
            $this->fail('client_id wajib diisi');

            return $this;
        }

        $clientEntity = $oauth->loader()->getBridgeClientRepository()->getClientEntity($clientId);
        if ($clientEntity === null) {
            $this->fail('client_id tidak dikenal');

            return $this;
        }

        $requestedScopes = array_filter(explode(' ', (string) carr::get($request, 'scope', '')));
        if (count($requestedScopes) === 0) {
            $requestedScopes = array_filter(explode(' ', (string) $oauth->defaultScope));
        }
        $scopeIds = array_values(array_filter($requestedScopes, function ($scopeId) use ($oauth) {
            return $oauth->hasScope($scopeId);
        }));

        $verificationUri = $oauth->deviceVerificationUri;

        $deviceCode = $oauth->deviceCodeRepository()->create($clientEntity, $scopeIds, $verificationUri);

        $this->data = [
            'device_code' => $deviceCode->device_code,
            'user_code' => $deviceCode->user_code,
            'verification_uri' => $verificationUri,
            'verification_uri_complete' => $verificationUri . '?code=' . rawurlencode($deviceCode->user_code),
            'expires_in' => $deviceCode->expires_at->diffInSeconds(CCarbon::now()),
            'interval' => $this->intervalSeconds($oauth->deviceCodePollingInterval()),
        ];

        return $this;
    }

    /**
     * @param \DateInterval $interval
     *
     * @return int
     */
    protected function intervalSeconds(DateInterval $interval) {
        $reference = new DateTimeImmutable();

        return $reference->add($interval)->getTimestamp() - $reference->getTimestamp();
    }
}

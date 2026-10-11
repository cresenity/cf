<?php

/**
 * `POST oauth/device_authorization` (RFC 8628 §3.1) -- TANPA auth (client belum
 * punya token sama sekali di titik ini), pola sama dengan Method/Token.php:
 * endpoint protokol OAuth murni, bukan DApi_Console_Method_* app-level.
 *
 * Balasannya WAJIB JSON bare di level atas (device_code/user_code/dst langsung
 * sebagai key, bukan dibungkus {errCode,errMessage,data} seperti kebanyakan
 * DApi_*_Method lain) -- itu kontrak RFC 8628 yang dibaca client OAuth generik
 * mana pun (termasuk devcloud-cli), bukan sesuatu yang CF-spesifik. Makanya
 * execute() di sini SELALU return objek Response (CHTTP_JsonResponse, lewat
 * c::response()->json()), tidak pernah `return $this`/`$this->data` -- CApi_
 * Kernel::dispatchMethod() cuma menerapkan envelope errCode/errMessage/data
 * kalau nilai baliknya BUKAN instance Symfony\Component\HttpFoundation\Response.
 * Pola yang sama dipakai Method/Token.php untuk alasan yang sama persis.
 *
 * Menolak jelas kalau CApi_OAuth::$deviceCodeGrantEnabled mati untuk grup api ini
 * (lihat Loader::registerAuthorizationServer()) -- endpoint ini memang ada di
 * semua app yang memakai CApi_OAuth (kode framework dibagi bersama), tapi hanya
 * benar-benar bisa dipakai oleh app yang menyalakan opt-in-nya; app lain selalu
 * dapat error jelas di sini, tidak diam-diam menerbitkan device_code yang tidak
 * akan pernah bisa ditukar token (grant-nya tidak ter-enable di /oauth/token).
 */
class CApi_OAuth_Method_DeviceAuthorization extends CApi_OAuth_MethodAbstract {
    /**
     * @return \CHTTP_JsonResponse
     */
    public function execute() {
        $oauth = $this->getOAuth();

        if (!$oauth->deviceCodeGrantEnabled) {
            return $this->errorResponse('unauthorized_client', 'Device authorization grant tidak diaktifkan untuk API group ini', 400);
        }
        if (!$oauth->deviceVerificationUri) {
            return $this->errorResponse('server_error', 'deviceVerificationUri belum dikonfigurasi untuk API group ini', 500);
        }

        $request = $this->request();
        $clientId = carr::get($request, 'client_id');
        if ($clientId === null || $clientId === '') {
            return $this->errorResponse('invalid_request', 'client_id wajib diisi', 400);
        }

        $clientEntity = $oauth->loader()->getBridgeClientRepository()->getClientEntity($clientId);
        if ($clientEntity === null) {
            return $this->errorResponse('invalid_client', 'client_id tidak dikenal', 400);
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

        return c::response()->json([
            'device_code' => $deviceCode->device_code,
            'user_code' => $deviceCode->user_code,
            'verification_uri' => $verificationUri,
            'verification_uri_complete' => $verificationUri . '?code=' . rawurlencode($deviceCode->user_code),
            'expires_in' => $deviceCode->expires_at->diffInSeconds(CCarbon::now()),
            'interval' => $this->intervalSeconds($oauth->deviceCodePollingInterval()),
        ]);
    }

    /**
     * Bentuk error OAuth standar (`error`/`error_description`), sama dengan yang
     * dibalas League lewat oauth/token -- supaya client generik yang sudah tahu
     * membaca `error` dari endpoint token juga langsung paham balasan di sini.
     *
     * @param string $error
     * @param string $description
     * @param int    $status
     *
     * @return \CHTTP_JsonResponse
     */
    protected function errorResponse($error, $description, $status) {
        return c::response()->json([
            'error' => $error,
            'error_description' => $description,
        ], $status);
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

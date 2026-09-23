<?php

trait CApi_OAuth_Trait_RetrieveAuthRequestFromSessionTrait {
    /**
     * Make sure the auth token matches the one in the session.
     *
     * @param \CHTTP_Request $request
     *
     * @throws \CApi_OAuth_Exception_InvalidAuthTokenException
     *
     * @return void
     */
    protected function assertValidAuthToken(CHTTP_Request $request) {
        if ($request->has('auth_token') && $request->session()->get('authToken') !== $request->get('auth_token')) {
            $request->session()->forget(['authToken', 'authRequest']);

            throw CApi_OAuth_Exception_InvalidAuthTokenException::different();
        }
    }

    /**
     * Get the authorization request from the session.
     *
     * @param \CHTTP_Request $request
     *
     * @throws \Exception
     *
     * @return \League\OAuth2\Server\RequestTypes\AuthorizationRequest
     */
    protected function getAuthRequestFromSession(CHTTP_Request $request) {
        return c::tap($request->session()->get('authRequest'), function ($authRequest) use ($request) {
            if (!$authRequest) {
                throw new Exception('Authorization request was not present in the session.');
            }

            //sama seperti CApi_OAuth_Method_Authorize::execute() - $request->user()
            //null untuk request API biasa (bukan token Bearer), user sungguhan
            //ada di session guard karena inilah yang login lewat halaman
            //authorization/login sebelumnya. Tanpa fallback ini,
            //getAuthIdentifier() dipanggil di atas null tiap kali tombol
            //Authorize/Cancel ditekan.
            $user = $request->user();
            if ($user === null && method_exists($this, 'getOAuth')) {
                $user = $this->getOAuth()->createSessionGuard()->user();
            }
            if ($user === null) {
                throw new Exception('No authenticated user found for this authorization request.');
            }

            $authRequest->setUser(new CApi_OAuth_Bridge_User($user->getAuthIdentifier()));

            $authRequest->setAuthorizationApproved(true);
        });
    }
}

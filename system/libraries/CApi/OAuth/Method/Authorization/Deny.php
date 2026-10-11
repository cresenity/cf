<?php

class CApi_OAuth_Method_Authorization_Deny extends CApi_OAuth_MethodAbstract {
    use CApi_OAuth_Trait_RetrieveAuthRequestFromSessionTrait;

    public function __construct() {
        parent::__construct();
    }

    public function execute() {
        $request = $this->apiRequest;
        $this->assertValidAuthToken($request);

        $authRequest = $this->getAuthRequestFromSession($request);

        $clientUris = carr::wrap($authRequest->getClient()->getRedirectUri());

        if (!in_array($uri = $authRequest->getRedirectUri(), $clientUris)) {
            $uri = carr::first($clientUris);
        }

        $separator = $authRequest->getGrantTypeId() === 'implicit' ? '#' : (strstr($uri, '?') ? '&' : '?');

        return c::response()->redirectTo(
            $this->getOAuth()->appendIssuerParam(
                $uri . $separator . 'error=access_denied&state=' . $request->input('state')
            )
        );
    }
}

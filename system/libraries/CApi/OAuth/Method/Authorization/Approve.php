<?php

use Nyholm\Psr7\Response as Psr7Response;

class CApi_OAuth_Method_Authorization_Approve extends CApi_OAuth_MethodAbstract {
    use CApi_OAuth_Trait_ConvertPsrResponseTrait;
    use CApi_OAuth_Trait_RetrieveAuthRequestFromSessionTrait;

    public function __construct() {
        parent::__construct();
    }

    public function execute() {
        $request = $this->apiRequest;
        $oauth = $this->getOAuth();
        $server = $oauth->authorizationServer();

        $authRequest = $this->getAuthRequestFromSession($request);

        return $this->convertResponse(
            $server->completeAuthorizationRequest($authRequest, new Psr7Response())
        );
    }
}

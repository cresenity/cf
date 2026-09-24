<?php

use Nyholm\Psr7\Response as Psr7Response;

class CApi_OAuth_Method_Authorize extends CApi_OAuth_MethodAbstract {
    use CApi_OAuth_Trait_HandleOAuthErrorTrait;
    use CApi_OAuth_Trait_FactoryTrait;

    public function __construct() {
        parent::__construct();
        //$this->middleware(SEApi_Middleware_MethodGetMiddleware::class);
    }

    public function execute() {
        $request = $this->apiRequest;
        $oauth = $this->getOAuth();
        $redirectUri = $request->redirect_uri;
        $server = $oauth->authorizationServer();
        $psrRequest = $this->toServerRequestInterface($request);

        $authRequest = $this->withErrorHandling(function () use ($psrRequest, $server) {
            return $server->validateAuthorizationRequest($psrRequest);
        });

        $scopes = $this->parseScopes($authRequest);
        $tokens = $oauth->tokenRepository();
        $clients = $oauth->clientRepository();
        $user = $request->user();
        if ($user == null) {
            $auth = $oauth->createSessionGuard();
            $user = $auth->user();
        }

        $client = $clients->find($authRequest->getClient()->getIdentifier());
        $token = null;
        if ($user) {
            $token = $tokens->findValidToken($user, $client);
        }

        if (($token && $token->scopes === c::collect($scopes)->pluck('id')->all())
            || $client->skipsAuthorization()
        ) {
            return $this->approveRequest($authRequest, $user, $server);
        }

        $request->session()->put('authToken', $authToken = cstr::random());
        $request->session()->put('authRequest', $authRequest);

        $view = $oauth->viewManager()->getAuthorizeView();
        if ($user == null) {
            $view = $oauth->viewManager()->getLoginView();
        }
        $loginUri = $oauth->routeManager()->getLoginUrl();
        $approveUri = $oauth->routeManager()->getApproveUrl();
        $denyUri = $oauth->routeManager()->getDenyUrl();

        return c::response()->view($view, [
            'client' => $client,
            'user' => $user,
            'scopes' => $scopes,
            'request' => $request,
            'authToken' => $authToken,
            'redirectUri' => $redirectUri,
            'loginUri' => $loginUri,
            'approveUri' => $approveUri,
            'denyUri' => $denyUri,
        ]);
    }

    /**
     * Transform the authorization requests's scopes into Scope instances.
     *
     * @param \League\OAuth2\Server\RequestTypes\AuthorizationRequest $authRequest
     *
     * @return array
     */
    protected function parseScopes($authRequest) {
        return $this->getOAuth()->scopesFor(
            c::collect($authRequest->getScopes())->map(function ($scope) {
                return $scope->getIdentifier();
            })->unique()->all()
        );
    }

    /**
     * Approve the authorization request.
     *
     * @param \League\OAuth2\Server\RequestTypes\AuthorizationRequest $authRequest
     * @param \CModel|\CAuth_AuthenticatableInterface                 $user
     * @param \League\OAuth2\Server\AuthorizationServer               $server
     *
     * @return \CHTTP_Response
     */
    protected function approveRequest($authRequest, $user, $server) {
        $authRequest->setUser(new CApi_OAuth_Bridge_User($user->getAuthIdentifier()));

        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(function () use ($authRequest, $server) {
            return $this->convertResponse(
                $server->completeAuthorizationRequest($authRequest, new Psr7Response())
            );
        });
    }
}

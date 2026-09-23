<?php

class CApi_OAuth_Method_Authorization_Login extends CApi_OAuth_MethodAbstract {
    use CApi_OAuth_Trait_ConvertPsrResponseTrait;
    use CApi_OAuth_Trait_RetrieveAuthRequestFromSessionTrait;

    public function __construct() {
        parent::__construct();
    }

    public function execute() {
        $request = $this->apiRequest;
        $oauth = $this->getOAuth();
        $email = $request->email;
        $password = $request->password;
        $state = $request->state;
        $clientId = $request->client_id;
        $authToken = $request->auth_token;
        $redirectUri = $request->redirect_uri;
        $codeChallenge = $request->code_challenge;
        $codeChallengeMethod = $request->code_challenge_method;
        $auth = $oauth->createSessionGuard();
        $successLogin = $auth->attempt(['email' => $email, 'password' => $password], false);

        //code_challenge/code_challenge_method wajib ikut dibawa balik ke
        //authorize - tanpa ini client publik (PKCE, tanpa secret) selalu
        //ditolak "Code challenge must be provided for public clients" begitu
        //user belum login sebelumnya (baru diketahui lewat percobaan
        //devcloud-cli login pertama kali di luar sesi tinker/curl yang sudah
        //terlanjur login duluan).
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
        ];
        if ($codeChallenge) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = $codeChallengeMethod;
        }
        $params['error'] = $successLogin ? '' : 'Invalid username or password';

        $query = http_build_query($params);

        $authorizeUrl = $oauth->routeManager()->getAuthorizeUrl();

        return c::redirect($authorizeUrl . '?' . $query);
    }
}

<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 15, 2019, 8:00:25 PM
 */
interface CSocialLogin_OAuth2_ProviderInterface {
    /**
     * Redirect the user to the authentication page for the provider.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect();

    /**
     * Get the User instance for the authenticated user.
     *
     * @return CSocialLogin_OAuth2_User
     */
    public function user();
}

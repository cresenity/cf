<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 15, 2019, 8:09:10 PM
 */
interface CSocialLogin_AbstractProviderInterface {
    /**
     * Redirect the user to the authentication page for the provider.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\CHTTP_RedirectResponse
     */
    public function redirect();

    /**
     * Get the User instance for the authenticated user.
     *
     * @return \CSocialLogin_Contract_UserInterface
     */
    public function user();
}

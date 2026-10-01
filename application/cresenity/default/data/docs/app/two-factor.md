# Application - Two Factor Authentication

### Overview

`CAuth_TwoFactor_Manager` adds time based one-time codes (TOTP, the kind authenticator apps produce) and recovery codes to any user model. It needs no request, session or `Features`, so a controller, a `CApi` method or a job can call it. Opening the database transaction around it is up to the caller.

### Setup

Add three nullable columns to the user table and use the trait on the model:

```sql
ALTER TABLE users
    ADD two_factor_secret text NULL,
    ADD two_factor_recovery_codes text NULL,
    ADD two_factor_confirmed_at datetime NULL;
```

```php
<?php
class MyModel_User extends CModel implements CAuth_AuthenticatableInterface {
    use CAuth_TwoFactor_AuthenticatableTrait;

    protected $hidden = ['two_factor_secret', 'two_factor_recovery_codes'];
}
```

The secret and the recovery codes are stored encrypted with the app key. If the key is lost they cannot be read again. A model without the `two_factor_confirmed_at` column treats a stored secret as active at once.

### Enabling

```php
<?php
$manager = CAuth_TwoFactor_Manager::instance();

$manager->enable($user);                      // new secret and recovery codes, still pending
$svg = $user->twoFactorQrCodeSvg();           // show it, the user scans it
$manager->confirm($user, $request->input('code'));   // throws CValidation_Exception when the code is wrong
$codes = $user->recoveryCodes();              // show them once
```

Until `confirm()` succeeds the user is not challenged at login, so an abandoned setup never locks anyone out. `disable()` clears everything and `regenerateRecoveryCodes()` replaces all codes.

A code can be used once. Right after confirming, the same code is rejected, so ask the user to wait for the next one.

### Login Challenge

After the password is accepted, park the login instead of finishing it when two factor is on:

```php
<?php
if (CAuth_TwoFactor_Manager::instance()->isEnabled($user)) {
    CApp_Auth_TwoFactorChallenge::begin(CBase::session(), $user, $remember);

    return c::redirect('login/twofactor');
}
```

On the challenge page, accept `code` or `recovery_code`:

```php
<?php
$challenge = CApp_Auth_TwoFactorChallenge::make();
if (!$challenge->hasChallengedUser()) {
    return c::redirect('login');
}

try {
    $challenge->complete(c::request());      // logs the user in
} catch (CValidation_Exception $e) {
    $errors = $e->errors();                  // 'code' or 'recovery_code'
}
```

A pending login expires after five minutes. Five wrong codes lock the challenge for a minute, even for a valid code. A recovery code works once and is replaced by a new one. The login pipeline of `CApp_Auth` stores the pending login the same way.

### Confirming the Password

`CApp_Auth_PasswordConfirmation` asks for the password again before a sensitive step, such as disabling two factor:

```php
<?php
$confirmation = CApp_Auth_PasswordConfirmation::make();

if (!$confirmation->isConfirmed()) {            // within the last 15 minutes
    if (!$confirmation->confirm($user, $request->input('password'))) {
        // wrong password
    }
}
```

### Feature Options

`CApp_Auth_Features::twoFactorAuthentication()` keeps the options it is given. Read them with `optionEnabled()`:

```php
<?php
CApp_Auth_Features::setFeatures([
    CApp_Auth_Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
]);

CApp_Auth_Features::optionEnabled(CApp_Auth_Features::twoFactorAuthentication(), 'confirmPassword'); // true
```

### Events

`CAuth_Event_TwoFactorEnabled`, `TwoFactorConfirmed`, `TwoFactorDisabled`, `TwoFactorRecoveryCodesGenerated`, `TwoFactorCodeVerified`, `TwoFactorRecoveryCodeUsed` and `TwoFactorFailed` carry the user in `$event->user`.

### Using Another Provider

Bind your own implementation of `CAuth_TwoFactor_ProviderInterface` (secret, provisioning URI, verification) and pass it to the manager:

```php
<?php
CAuth_TwoFactor_Manager::setInstance(new CAuth_TwoFactor_Manager(new MyTwoFactorProvider()));
```

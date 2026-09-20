<?php

/**
 * @deprecated 1.9 pabrik driver lama; CEmail_MailManager membangun transport dari CEmail_Config::toMailerConfig()
 */
class CEmail_Factory {
    protected static $driverMap = [
        'sendgrid' => CEmail_Driver_SendGridDriver::class,
        'mailgun' => CEmail_Driver_MailgunDriver::class,
        'brevo' => CEmail_Driver_BrevoDriver::class,
        'mail' => CEmail_Driver_MailDriver::class,
        'kirimemail' => CEmail_Driver_KirimEmailDriver::class,
        'ses' => CEmail_Driver_SesDriver::class,
        'sesV2' => CEmail_Driver_SesV2Driver::class,
        'smtp' => CEmail_Driver_SmtpDriver::class,
        'null' => CEmail_Driver_NullDriver::class,
    ];

    /**
     * @param string $driver
     *
     * @return CEmail_DriverAbstract
     */
    public static function createDriver(CEmail_Config $config) {
        $driver = (string) $config->getDriver();
        $class = carr::get(static::$driverMap, $driver);
        if (!$class) {
            $normalized = strtolower(str_replace(['_', '-'], '', $driver));
            foreach (static::$driverMap as $name => $mapped) {
                if (strtolower($name) === $normalized) {
                    $class = $mapped;
                }
            }
        }
        if (!$class) {
            if (class_exists('CEmail_Driver_' . cstr::ucfirst(cstr::camel($driver)) . 'Driver')) {
                $class = 'CEmail_Driver_' . cstr::ucfirst(cstr::camel($driver)) . 'Driver';
            }
        }

        if ($class) {
            CF::deprecated($class, "CEmail::mailer() dengan transport '" . CEmail_Config::transportForDriver($driver) . "' (atau email.legacy_sender_via_mailer)", '1.9');

            return new $class($config);
        }

        throw new CEmail_Exception_DriverNotFoundException('Mail driver [' . $driver . '] not found; known drivers: ' . implode(', ', array_keys(static::$driverMap)));
    }
}

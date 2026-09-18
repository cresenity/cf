<?php

defined('SYSPATH') or die('No direct access allowed.');

class CConsole_Command_App_AppCodeCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:code {appCode?} ';

    public function handle() {
        $domain = CF::domain();
        $currentAppCode = CF::appCode();

        $appCode = $this->argument('appCode');

        if (strlen($appCode) > 0) {
            $data = CDomain::get($domain);
            $data['app_code'] = $appCode;
            CDomain::set($domain, $data);
            $currentAppCode = $appCode;
        }

        $this->info('current app code for ' . $domain . ' is: ' . $currentAppCode);
    }
}

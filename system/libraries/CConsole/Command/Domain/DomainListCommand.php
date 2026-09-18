<?php

/**
 * Description of DomainListCommand.
 */
class CConsole_Command_Domain_DomainListCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:list';

    public function handle() {
        $allFiles = CFile::allFiles(CFData::path() . 'domain');
        $rows = c::collect($allFiles)->map(function ($file) {
            $domain = basename($file);
            if (substr($domain, -4) == '.php') {
                $domain = substr($domain, 0, strlen($domain) - 4);
            }
            $domainData = include $file;

            return [
                'domain' => $domain,
                'appCode' => carr::get($domainData, 'app_code'),
                'orgCode' => carr::get($domainData, 'org_code'),
                'created' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        })->sortBy('domain')->all();

        $this->table(['Domain', 'AppCode', 'OrgCode', 'Created'], $rows);
    }
}

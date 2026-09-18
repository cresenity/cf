<?php

/**
 * Description of DomainCreateCommand
 */
use Symfony\Component\Console\Input\InputArgument;

class CConsole_Command_Domain_DomainCreateCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:create'
    . ' {domain : String domain to create}'
    . ' {--appId=1 : app_id for specified domain}'
    . ' {--appCode=cresenity : app_code for specified domain}'
    . ' {--orgId= : org_id for specified domain}'
    . ' {--orgCode= : org_code for specified domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the domain in data/domain';

    public function handle() {
        $domain = $this->argument('domain');

        $errCode = 0;
        $errMessage = '';
        if (CF::domainExists($domain)) {
            $errCode++;
            $errMessage = $domain . ' already exists';
        }

        $appId = null;
        $appCode = null;
        $orgId = null;
        $orgCode = null;

        if ($errCode == 0) {
            $appId = $this->option('appId');
            $appCode = $this->option('appCode');
            $orgId = $this->option('orgId');
            $orgCode = $this->option('orgCode');
        }
        if ($errCode == 0) {
            if (strlen($appId) == 0) {
                $errCode++;
                $errMessage = 'appId required';
            }
        }
        if ($errCode == 0) {
            if (strlen($appCode) == 0) {
                $errCode++;
                $errMessage = 'appCode required';
            }
        }

        if ($errCode == 0) {
            if (strlen($orgId) == 0) {
                $orgId = null;
            }
            if (strlen($orgCode) == 0) {
                $orgId = null;
            }

            $domainData = [
                'app_id' => $appId,
                'app_code' => $appCode,
                'org_id' => $orgId,
                'org_code' => $orgCode,
                'domain' => $domain,
            ];

            CF::createDomain($domain, $domainData);

            $this->info('Domain ' . $domain . ' successfully created');
        }

        if ($errCode > 0) {
            $this->error($errMessage);
            return 1;
        }

        return 0;
    }
}

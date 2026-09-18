<?php

/**
 * Description of DomainDeleteCommand
 */
class CConsole_Command_Domain_DomainDeleteCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:delete {domain : String domain to delete}';

    public function handle() {
        $domain = $this->argument('domain');

        $errCode = 0;
        $errMessage = '';

        if (!CF::domainExists($domain)) {
            $errCode++;
            $errMessage = $domain . ' not exists';
        }

        if ($errCode == 0) {
            $filename = CFData::path() . 'domain' . DS . $domain . EXT;
            unlink($filename);
            $this->info('Domain ' . $domain . ' successfully deleted');
        }

        if ($errCode > 0) {
            $this->error($errMessage);
            return 1;
        }

        return 0;
    }
}

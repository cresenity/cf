<?php

/**
 * Description of DomainSwitchCommand
 *
 * @author Hery
 */
class CConsole_Command_Domain_DomainSwitchCommand extends CConsole_Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:switch {domain}';

    public function handle() {
        $domain = $this->argument('domain');
        if (CConsole::domain() == $domain) {
            $this->info('You are already on domain:' . $domain);
        } else {
            if (!CF::domainExists($domain)) {
                $this->error('Failed switch domain, ' . $domain . ' not exists');
            } else {
                $fileData = DOCROOT . 'data/current-domain';
                cfs::atomic_write($fileData, $domain);
                $this->info('Switched to ' . $domain);
            }
        }
    }

}

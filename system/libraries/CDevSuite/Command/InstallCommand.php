<?php

class CDevSuite_Command_InstallCommand extends CDevSuite_CommandAbstract {
    public function getSignatureArguments() {
        switch (CServer::getOS()) {
            case CServer::OS_LINUX:
                return '{--ignore-selinux}';
            default:
                return '';
        }
    }

    public function run(CConsole_Command $cfCommand) {
        CDevSuite::devCloud()->install();

        switch (CServer::getOS()) {
            case CServer::OS_LINUX:
                //passthru('DOCROOT="'.DOCROOT. '" '.CDevSuite::scriptsPath().'linux/bootstrap.sh '.$cfCommand->getName()); // Clean up cruft

                passthru(CDevSuite::scriptsPath() . 'linux/update.sh'); // Clean up cruft

                $ignoreSELinux = $cfCommand->option('ignore-selinux');

                CDevSuite::linuxRequirements()->setIgnoreSELinux($ignoreSELinux)->check();
                CDevSuite::configuration()->install();
                CDevSuite::nginx()->install();
                CDevSuite::phpFpm()->install();
                CDevSuite::dnsMasq()->install(CDevSuite::configuration()->read()['tld']);
                CDevSuite::nginx()->restart();
                CDevSuite::system()->symlinkToUsersBin();

                break;
            case CServer::OS_WINNT:
                CDevSuite::nginx()->stop();
                CDevSuite::phpFpm()->stop();
                CDevSuite::acrylic()->stop();

                CDevSuite::configuration()->install();

                CDevSuite::nginx()->install();
                CDevSuite::phpFpm()->install();

                $tld = CDevSuite::configuration()->read()['tld'];
                CDevSuite::acrylic()->install($tld);

                CDevSuite::nginx()->restart();

                break;
            case CServer::OS_DARWIN:
                CDevSuite::nginx()->stop();

                CDevSuite::configuration()->install();
                CDevSuite::nginx()->install();
                CDevSuite::phpFpm()->install();
                $tld = CDevSuite::configuration()->read()['tld'];
                CDevSuite::dnsMasq()->install($tld);
                CDevSuite::nginx()->restart();
                CDevSuite::system()->symlinkToUsersBin();
                break;
            default:
                throw new Exception('Dev Suite not available for this OS:' . CServer::getOS());
                break;
        }
        CDevSuite::output(PHP_EOL . '<info>Dev Suite installed successfully!</info>');
    }
}

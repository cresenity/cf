<?php

/**
 * Description of Winsw
 *
 * @author Hery
 */
class CDevSuite_Winsw {

    /**
     *
     * @var CDevSuite_Windows_CommandLine
     */
    protected $cli;

    /**
     *
     * @var CDevSuite_Windows_Filesystem
     */
    protected $files;

    /**
     * Create a new WinSW instance.
     *
     * @param CommandLine $cli
     * @param Filesystem  $files
     */
    public function __construct() {
        $this->cli = CDevSuite::commandLine();
        $this->files = CDevSuite::filesystem();
    }

    /**
     * Install a Windows service.
     *
     * @param string $service
     * @param array  $args
     *
     * @return void
     */
    public function install($service, $args = []) {
        $this->createConfiguration($service, $args);

        $bin = realpath(CDevSuite::binPath());
        $this->files->copy("$bin/winsw.exe", CDevSuite::homePath() . "Services/$service.exe");
        $command = 'cmd "/C ' . CDevSuite::homePath() . 'Services/' . $service . ' install"';
        $this->cli->runOrDie($command, function ($code, $output) use ($service) {
            CDevSuite::warning("Could not install the $service service. Check ~/.devsuite/Log for errors.");
            CDevSuite::warning("Output:" . $output);
        });
    }

    /**
     * Create the service XML configuration file.
     *
     * @param string $service
     * @param array  $args
     *
     * @return void
     */
    protected function createConfiguration($service, $args = []) {
        $args['DEVSUITE_HOME_PATH'] = CDevSuite::homePath();

        $contents = $this->files->get(CDevSuite::stubsPath() . "win/$service.xml");

        $this->files->putAsUser(
                CDevSuite::homePath() . "/Services/$service.xml", str_replace(array_keys($args), array_values($args), $contents)
        );
    }

    /**
     * Uninstall a Windows service.
     *
     * @param string $service
     *
     * @return void
     */
    public function uninstall($service) {
        $this->stop($service);

        $this->cli->run('cmd "/C ' . CDevSuite::homePath() . 'Services/' . $service . ' uninstall"');

        $this->files->unlink(CDevSuite::homePath() . "Services/$service.exe");
        $this->files->unlink(CDevSuite::homePath() . "Services/$service.xml");
    }

    /**
     * Restart a Windows service.
     *
     * @param string $service
     *
     * @return void
     */
    public function restart($service) {
        $this->stop($service);

        $command = 'cmd "/C ' . CDevSuite::homePath() . 'Services/' . $service . ' start"';

        $this->cli->run($command, function () use ($service, $command) {
            sleep(2);

            $this->cli->runOrDie($command, function () use ($service) {
                CDevSuite::warning("Could not start the $service service. Check ~/.devsuite/Log for errors.");
            });
        });
    }

    /**
     * Stop a Windows service.
     *
     * @param string $service
     *
     * @return void
     */
    public function stop($service) {
        $this->cli->run('cmd "/C ' . CDevSuite::homePath() . 'Services/' . $service . ' stop"');
    }

}

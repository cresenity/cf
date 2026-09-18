<?php

/**
 * Description of ProcessRunner
 */
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class CQC_ProcessRunner {

    use CTrait_HasOptions;

    /**
     *
     * @var string
     */
    protected $className;

    public function __construct($className, $options) {
        $this->className = $className;
        $this->options = $options;
    }

    /**
     * @return false|string
     */
    protected function getPhpBinary() {
        $executableFinder = new PhpExecutableFinder();
        return $executableFinder->find();
    }

    /**
     * @param string $job
     * @param array  $config
     *
     * @return string
     */
    protected function getExecutableCommand() {
        $domain = $this->getOption('domain', CF::domain());
        $script = $this->getOption('script', DOCROOT . 'index.php');
        $uri = $this->getOption('uri', 'cresenity/qc/' . $this->className);

        $args = c::collect($this->options)->forget('script')->forget('uri')->toArray();

        $cmd = sprintf('"%s" "%s" "%s" "%s"', $script, $uri, $domain, http_build_query($args));
        return $cmd;
    }

    
    
    /**
     * 
     * @return string
     */
    protected function getCommand() {
        return $this->getPhpBinary() . " " . $this->getExecutableCommand();
    }

    /**
     * 
     * @return \CQC_ProcessRunnerResult
     */
    public function run() {
        
        $process = new Process($this->getCommand());
        $process->run();

        return new CQC_ProcessRunnerResult($process->getOutput(), $process->getErrorOutput());
    }

}

<?php

/**
 * Description of ProfilerBootstrapper.
 */
class CBootstrap_ProfilerBootstrapper extends CBootstrap_BootstrapperAbstract {
    /**
     * Reserved memory so that errors can be displayed properly on memory exhaustion.
     *
     * @var string
     */
    public static $reservedMemory;

    /**
     * Bootstrap the given application.
     *
     * @return void
     */
    public function bootstrap() {
    }
}

<?php

/**
 * Description of Console
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\Table;

class CServer_Console {
    /**
     * Output the given text to the console.
     *
     * @param string $output
     *
     * @return void
     */
    public static function info($output) {
        static::output('<info>' . $output . '</info>');
    }

    /**
     * Output the given text to the console.
     *
     * @param string $output
     *
     * @return void
     */
    public static function warning($output) {
        static::output('<fg=red>' . $output . '</>');
    }

    /**
     * Output a table to the console.
     *
     * @param array $headers
     * @param array $rows
     *
     * @return void
     */
    public static function table(array $headers = [], array $rows = []) {
        $table = new Table(new ConsoleOutput);

        $table->setHeaders($headers)->setRows($rows);

        $table->render();
    }

    /**
     * Output the given text to the console.
     *
     * @param string $output
     *
     * @return void
     */
    public static function output($output) {
        if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing') {
            return;
        }

        (new ConsoleOutput)->writeln($output);
    }
}

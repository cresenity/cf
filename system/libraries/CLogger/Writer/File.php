<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * File log writer. Writes out messages and stores them in a YYYY/MM directory.
 *
 * @deprecated 1.9 warisan Kohana, tidak dipakai CLogger; pakai driver single/daily. Dihapus di 2.0
 */
class CLogger_Writer_File extends CLogger_Writer {
    /**
     * @var string Directory to place log files in
     */
    protected $directory;

    /**
     * Creates a new file logger. Checks that the directory exists and
     * is writable.
     *
     *     $writer = new Log_File($directory);
     *
     * @param mixed $options
     *
     * @return void
     */
    public function __construct($options) {
        $basicPath = DOCROOT;

        $dir = $basicPath . 'logs' . DS;
        if (!is_dir($dir)) {
            mkdir($dir, 02777);
            // Set permissions (must be manually set to fix umask issues)
            chmod($dir, 02777);
        }

        $appCode = CF::appCode();
        if (strlen($appCode) > 0) {
            $dir .= $appCode . DS;
            if (!is_dir($dir)) {
                mkdir($dir, 02777);
                // Set permissions (must be manually set to fix umask issues)
                chmod($dir, 02777);
            }
        }

        if (!is_dir($dir) or !is_writable($dir)) {
            throw new Exception(c::__('Directory :dir must be writable', ['dir' => $dir]));
        }

        // Determine the directory path
        $this->directory = realpath($dir) . DIRECTORY_SEPARATOR;
    }

    /**
     * Writes each of the messages into the log file. The log file will be
     * appended to the `YYYY/MM/DD.log.php` file, where YYYY is the current
     * year, MM is the current month, and DD is the current day.
     *
     *     $writer->write($messages);
     *
     * @return void
     */
    public function write(array $messages) {
        // Set the yearly directory name
        $date = date('Y-m-d');
        list($year, $month, $day) = explode('-', $date);
        $directory = $this->directory . $year;

        if (!is_dir($directory)) {
            // Create the yearly directory
            mkdir($directory, 02777);

            // Set permissions (must be manually set to fix umask issues)
            chmod($directory, 02777);
        }

        // Add the month to the directory
        $directory .= DIRECTORY_SEPARATOR . $month;

        if (!is_dir($directory)) {
            // Create the monthly directory
            mkdir($directory, 02777);

            // Set permissions (must be manually set to fix umask issues)
            chmod($directory, 02777);
        }

        // Set the name of the log file
        $filename = $directory . DIRECTORY_SEPARATOR . $year . $month . $day . EXT;

        if (!file_exists($filename)) {
            // Create the log file
            file_put_contents($filename, CF::FILE_SECURITY . ' ?>' . PHP_EOL);

            // Allow anyone to write to log files
            chmod($filename, 0666);
        }

        foreach ($messages as $message) {
            // Write each message into the log file
            file_put_contents($filename, PHP_EOL . $this->formatMessage($message), FILE_APPEND);
        }

        $rotator = CLogger_Rotator::createRotate($filename);
        $rotator->run();
    }
}

<?php

/**
 * Description of MakeConfigCommand
 */
class CConsole_Command_Make_MakeConfigCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:config {config} {--value=}';

    public function handle() {
        CConsole::domainRequired($this);
        $config = $this->argument('config');
        $configPath = c::fixPath(CF::appDir()) . 'default' . DS . 'config' . DS;
        $configFile = $configPath . $config . EXT;
        $stubFile = CF::findFile('stubs', 'config/' . $config, false, 'stub');
        if (!CFile::isDirectory($configPath)) {
            CFile::makeDirectory($configPath);
        }
        if (file_exists($configFile)) {
            $this->info('Config ' . $config . ' already created, no changes');
            return CConsole::SUCCESS_EXIT;
        }
        if (!$stubFile) {
            $this->error('config not available');
            return CConsole::FAILURE_EXIT;
        }
        $content = CFile::get($stubFile);

        $allOption = $this->option();
        preg_match_all("/{([\w]*)}/", $content, $matches, PREG_SET_ORDER);
        $jsonOptionValue = carr::get($allOption, 'value');
        $arrayOptionValue = json_decode($jsonOptionValue, true);

        foreach ($matches as $val) {
            $key = $val[1]; //matches str without bracket {}
            $bracketKey = $val[0]; //matches str with bracket {}

            $option = carr::get($arrayOptionValue, $key);

            $defaultOption = CF::config($config . '.' . $key);
            if ($key == 'title' && $config == 'app') {
                $defaultOption = CF::appCode();
            }
            if ($option == null) {
                if (!carr::get($allOption, 'no-interaction', false)) {
                    //try to get the option
                    $option = $this->ask($key, var_export($defaultOption, true));
                }
            }

            if ($option != null) {
                if (is_string($defaultOption)) {
                    if (!cstr::startsWith($option, ["'", '"'])) {
                        $option = "'" . $option . "'";
                    }
                }
                if (is_bool($defaultOption) && is_string($option)) {
                    $option = var_export(filter_var($option, FILTER_VALIDATE_BOOLEAN), true);
                }
            }

            if ($option == null) {
                $option = var_export($defaultOption, true);
            }
            $content = str_replace('{' . $key . '}', $option, $content);
        }

        CFile::put($configFile, $content);

        $this->info('Config ' . $config . ' created on:' . $configFile);
    }
}

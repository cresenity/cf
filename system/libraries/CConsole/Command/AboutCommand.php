<?php


class CConsole_Command_AboutCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'about {--only= : The section to display}
    {--json : Output the information as JSON}';

    /**
     * The data to display.
     *
     * @var array
     */
    protected static $data = [];

    /**
     * The registered callables that add custom data to the command output.
     *
     * @var array
     */
    protected static $customDataResolvers = [];

    public function handle() {
        $this->gatherApplicationInformation();
        c::collect(static::$data)
            ->map(function ($items) {
                    return c::collect($items)->map(function ($value) {
                        if (is_array($value)) {
                            return [$value];
                        }

                        if (is_string($value)) {
                            $value = c::container()->make($value);
                        }

                        return c::collect(c::container()->call($value))
                            ->map(function ($value, $key) {
                                return [$key, $value];
                            })->values()
                            ->all();
                    })->flatten(1);
            })->sortBy(function ($data, $key) {
                $index = array_search($key, ['Environment', 'Cache', 'Drivers']);

                return $index === false ? 99 : $index;
            })
            ->filter(function ($data, $key) {
                return $this->option('only') ? in_array(cstr::of($key)->lower()->snake(), $this->sections()) : true;
            })
            ->pipe(function ($data) {
                return $this->display($data);
            });

        $this->newLine();

        return 0;
    }

    /**
     * Display the application information.
     *
     * @param \CCollection $data
     *
     * @return void
     */
    protected function display($data) {
        $this->option('json') ? $this->displayJson($data) : $this->displayDetail($data);
    }

    /**
     * Display the application information as a detail view.
     *
     * @param \CCollection $data
     *
     * @return void
     */
    protected function displayDetail($data) {
        $data->each(function ($data, $section) {
            // $this->newLine();
            // $this->line($section);
            // //$this->components->twoColumnDetail('  <fg=green;options=bold>' . $section . '</>');

            // $data->pipe(fn ($data) => $section !== 'Environment' ? $data->sort() : $data)->each(function ($detail) {
            //     list($label, $value) = $detail;

            //     //$this->components->twoColumnDetail($label, c::value($value));
            //     $value = c::value($value);
            //     $this->line($label . ':' . is_array($value) ? var_export($value, true) : $value);
            // });
            $this->info('======================');
            $this->info($section);
            $this->info('======================');

            $results = new CConsole_Result();
            $data->pipe(function ($data) use ($section) {
                return ($section !== 'Environment') ? $data->sort() : $data;
            })->each(function ($detail) use ($results) {
                list($label, $value) = $detail;

                //$this->components->twoColumnDetail($label, c::value($value));
                $value = c::value($value);
                $results->add($label, json_encode($value));
            });

            $results->printToConsole($this, ['Description', 'Value']);
        });
    }

    /**
     * Display the application information as JSON.
     *
     * @param \CCollection $data
     *
     * @return void
     */
    protected function displayJson($data) {
        $output = $data->flatMap(function ($data, $section) {
            return [(string) cstr::of($section)->snake() => $data->mapWithKeys(function ($item, $key) {
                return [(string) cstr::of($item[0])->lower()->snake() => c::value($item[1])];
            })];
        });

        $this->output->writeln(strip_tags(json_encode($output)));
    }

    /**
     * Section names requested through --only, in snake_case.
     *
     * @return array
     */
    protected function sections() {
        return c::collect(explode(',', (string) $this->option('only')))
            ->filter()
            ->map(function ($only) {
                return (string) cstr::of(trim($only))->lower()->snake();
            })
            ->all();
    }

    /**
     * Add additional data to the output of the "about" command.
     *
     * @param string                $section
     * @param callable|string|array $data
     * @param null|string           $value
     *
     * @return void
     */
    protected static function addToSection(string $section, $data, ?string $value = null) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                self::$data[$section][] = [$key, $value];
            }
        } elseif (is_callable($data) || ($value === null && class_exists($data))) {
            self::$data[$section][] = $data;
        } else {
            self::$data[$section][] = [$data, $value];
        }
    }

    /**
     * Gather information about the application.
     *
     * @return void
     */
    protected function gatherApplicationInformation() {
        static::addToSection('Environment', function () {
            return [
                'Application Name' => CF::config('app.name'),
                'Application Prefix' => CF::config('app.prefix'),
                'Application Code' => CF::appCode(),
                'CF Version' => CF::version(),
                'PHP Version' => phpversion(),
                'Composer Version' => (new CBase_Composer())->getVersion() ?? '<fg=yellow;options=bold>-</>',
                'Environment' => CF::environment(),
                'Debug Mode' => CF::config('app.debug') ? '<fg=yellow;options=bold>ENABLED</>' : 'OFF',
                'URL' => cstr::of(CF::config('app.url'))->replace(['http://', 'https://'], ''),
                'Maintenance Mode' => CF::isDownForMaintenance() ? '<fg=yellow;options=bold>ENABLED</>' : 'OFF',
            ];
        });

        // static::addToSection('Cache', fn () => [
        //     'Config' => file_exists($this->laravel->bootstrapPath('cache/config.php')) ? '<fg=green;options=bold>CACHED</>' : '<fg=yellow;options=bold>NOT CACHED</>',
        //     'Events' => file_exists($this->laravel->bootstrapPath('cache/events.php')) ? '<fg=green;options=bold>CACHED</>' : '<fg=yellow;options=bold>NOT CACHED</>',
        //     'Routes' => file_exists($this->laravel->bootstrapPath('cache/routes-v7.php')) ? '<fg=green;options=bold>CACHED</>' : '<fg=yellow;options=bold>NOT CACHED</>',
        //     'Views' => $this->hasPhpFiles($this->laravel->storagePath('framework/views')) ? '<fg=green;options=bold>CACHED</>' : '<fg=yellow;options=bold>NOT CACHED</>',
        // ]);

        $logChannel = CF::config('log.default');

        if (CF::config('log.channels.' . $logChannel . '.driver') === 'stack') {
            $secondary = c::collect(CF::config('log.channels.' . $logChannel . '.channels'))
                ->implode(', ');

            $logs = '<fg=yellow;options=bold>' . $logChannel . '</> <fg=green;options=bold>/</> ' . $secondary;
        } else {
            $logs = $logChannel;
        }

        static::addToSection('Drivers', function () use ($logs) {
            return array_filter([
                'Broadcasting' => CF::config('broadcasting.default'),
                'Cache' => CF::config('cache.default'),
                'Database' => CF::config('database.default'),
                'Logs' => $logs,
                'Mail' => CF::config('email.default'),
                'Octane' => CF::config('octane.server'),
                'Queue' => CF::config('queue.default'),
                'Scout' => CF::config('model.scout.driver'),
                'Session' => CF::config('session.driver'),
            ]);
        });

        c::collect(static::$customDataResolvers)->each->__invoke();
    }

    /**
     * Determine whether the given directory has PHP files.
     *
     * @param string $path
     *
     * @return bool
     */
    protected function hasPhpFiles(string $path): bool {
        return count(glob($path . '/*.php')) > 0;
    }

    /**
     * Add additional data to the output of the "about" command.
     *
     * @param string                $section
     * @param callable|string|array $data
     * @param null|string           $value
     *
     * @return void
     */
    public static function add(string $section, $data, ?string $value = null) {
        static::$customDataResolvers[] = function () use ($section, $data, $value) {
            return static::addToSection($section, $data, $value);
        };
    }
}

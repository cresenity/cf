<?php

class CConsole_Command_Resource_ResourceRegenerateCommand extends CConsole_Command {
    use CConsole_Trait_ConfirmableTrait;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'resource:regenerate {modelType? : Only resources attached to this model type}
                {--ids=* : Only these resource ids (comma separated or repeated)}
                {--only=* : Regenerate specific conversions}
                {--starting-from-id= : Resources with an id equal to or higher than this}
                {--X|exclude-starting-id : Exclude the starting id itself}
                {--only-missing : Regenerate only missing conversions}
                {--with-responsive-images : Regenerate responsive images too}
                {--queue-all : Queue every conversion, non-queued ones included}
                {--force : Run without confirmation in production}';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var null|string
     *
     * @deprecated
     */
    protected static $defaultName = 'resource:regenerate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate the conversions of the resources';

    /**
     * @var array
     */
    protected $errorMessages = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        if (!$this->confirmToProceed()) {
            return 1;
        }
        $repository = new CResources_Repository();
        $fileManipulator = CResources_Factory::createFileManipulator();
        $query = $this->queryToRegenerate($repository);
        if (CF::config('resource.queue_connection_name') === 'sync') {
            set_time_limit(0);
        }

        $progressBar = $this->output->createProgressBar($query->count());
        $query->chunkById(200, function ($resources) use ($fileManipulator, $progressBar) {
            foreach ($resources as $resource) {
                try {
                    $fileManipulator->createDerivedFiles(
                        $resource,
                        carr::wrap($this->option('only')),
                        (bool) $this->option('only-missing'),
                        (bool) $this->option('with-responsive-images'),
                        (bool) $this->option('queue-all')
                    );
                } catch (Exception $exception) {
                    $this->errorMessages[$resource->getKey()] = $exception->getMessage();
                }
                $progressBar->advance();
            }
        });
        $progressBar->finish();
        $this->newLine(2);

        if (count($this->errorMessages)) {
            $this->warn('All done, but with some error messages:');
            foreach ($this->errorMessages as $resourceId => $message) {
                $this->warn("Resource id {$resourceId}: `{$message}`");
            }

            return 1;
        }
        $this->info('All done!');

        return 0;
    }

    /**
     * @param CResources_Repository $repository
     *
     * @return CModel_Query
     */
    protected function queryToRegenerate(CResources_Repository $repository) {
        $query = $repository->queryFor($this->filterArgument('modelType'));

        $startingFromId = (int) $this->option('starting-from-id');
        if ($startingFromId !== 0) {
            $keyName = $repository->getModel()->getKeyName();

            return $query->where($keyName, $this->option('exclude-starting-id') ? '>' : '>=', $startingFromId);
        }

        $ids = $this->resourceIds();
        if (count($ids) > 0) {
            return $query->whereIn($repository->getModel()->getKeyName(), $ids);
        }

        return $query;
    }

    /**
     * @return array
     */
    protected function resourceIds() {
        $ids = $this->option('ids');
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        if (count($ids) === 1 && cstr::contains((string) $ids[0], ',')) {
            $ids = explode(',', (string) $ids[0]);
        }

        return array_values(array_filter(array_map('trim', $ids), 'strlen'));
    }

    /**
     * A filter argument; missing or `*` means no filter.
     *
     * @param string $name
     *
     * @return null|string
     */
    protected function filterArgument($name) {
        $value = $this->argument($name);

        return is_string($value) && $value !== '' && $value !== '*' ? $value : null;
    }
}

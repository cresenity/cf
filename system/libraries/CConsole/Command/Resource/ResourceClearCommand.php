<?php

class CConsole_Command_Resource_ResourceClearCommand extends CConsole_Command {
    use CConsole_Trait_ConfirmableTrait;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'resource:clear {modelType? : Only resources attached to this model type}
                {collectionName? : Only resources in this collection}
                {--purge : Force delete the rows so their files are removed too (default is a soft delete)}
                {--dry-run : Count what would be deleted without deleting}
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
    protected static $defaultName = 'resource:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete every resource of a model type and/or collection';

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
        $query = $repository->queryFor($this->filterArgument('modelType'), $this->filterArgument('collectionName'));
        $total = $query->count();
        $purge = (bool) $this->option('purge') || !$repository->getModel()->usesSoftDelete();

        if ($this->option('dry-run')) {
            $this->info("{$total} resource(s) would be " . ($purge ? 'purged (rows and files)' : 'soft deleted') . '.');

            return 0;
        }

        $progressBar = $this->output->createProgressBar($total);
        $query->chunkById(200, function ($resources) use ($progressBar, $purge) {
            foreach ($resources as $resource) {
                $purge ? $resource->forceDelete() : $resource->delete();
                $progressBar->advance();
            }
        });
        $progressBar->finish();
        $this->newLine(2);
        $this->info("{$total} resource(s) " . ($purge ? 'purged.' : 'soft deleted; run with --purge to remove the files too.'));

        return 0;
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

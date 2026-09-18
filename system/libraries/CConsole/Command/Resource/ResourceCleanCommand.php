<?php

class CConsole_Command_Resource_ResourceCleanCommand extends CConsole_Command {
    use CConsole_Trait_ConfirmableTrait;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'resource:clean {modelType? : Only resources attached to this model type}
                {collectionName? : Only resources in this collection}
                {disk? : Only sweep this disk for orphaned directories}
                {--dry-run : List what would be removed without removing anything}
                {--delete-orphaned : Delete resources whose owning model row is gone}
                {--purge : Force delete orphaned resources so their files are removed too}
                {--skip-conversions : Do not remove files of conversions that are no longer registered}
                {--skip-directories : Do not sweep the disks for directories without a resource row}
                {--rate-limit= : Maximum operations per second}
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
    protected static $defaultName = 'resource:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove stale conversion files, orphaned resources and directories without a resource row';

    /**
     * @var CResources_Repository
     */
    protected $repository;

    /**
     * @var bool
     */
    protected $isDryRun = false;

    /**
     * @var int
     */
    protected $rateLimit = 0;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        if (!$this->confirmToProceed()) {
            return 1;
        }
        $this->repository = new CResources_Repository();
        $this->isDryRun = (bool) $this->option('dry-run');
        $this->rateLimit = (int) $this->option('rate-limit');

        if ($this->option('delete-orphaned')) {
            $this->deleteOrphanedResources();
        }
        if (!$this->option('skip-conversions')) {
            $this->deleteFilesOfUnregisteredConversions();
        }
        if (!$this->option('skip-directories')) {
            $this->deleteOrphanedDirectories();
        }
        $this->info($this->isDryRun ? 'Dry run done, nothing was removed.' : 'All done!');

        return 0;
    }

    /**
     * @return CModel_Query
     */
    protected function scopedQuery() {
        return $this->repository->queryFor($this->filterArgument('modelType'), $this->filterArgument('collectionName'));
    }

    /**
     * @return void
     */
    protected function deleteOrphanedResources() {
        $unresolvable = $this->repository->getUnresolvableModelTypes();
        if ($unresolvable->isNotEmpty()) {
            $this->warn('Skipped model types with no class to check against: ' . $unresolvable->implode(', '));
        }
        $purge = (bool) $this->option('purge') || !$this->repository->getModel()->usesSoftDelete();
        $query = $this->repository->orphansQuery();
        if ($collectionName = $this->filterArgument('collectionName')) {
            $query->where('collection_name', $collectionName);
        }
        $count = 0;
        $query->chunkById(200, function ($resources) use (&$count, $purge) {
            foreach ($resources as $resource) {
                $count++;
                if ($this->isDryRun) {
                    $this->line("Orphaned resource [id={$resource->getKey()}] {$resource->model_type}#{$resource->model_id} found");

                    continue;
                }
                $purge ? $resource->forceDelete() : $resource->delete();
                $this->throttle();
                $this->line("Orphaned resource [id={$resource->getKey()}] " . ($purge ? 'purged' : 'soft deleted'));
            }
        });
        $this->info("{$count} orphaned resource(s) " . ($this->isDryRun ? 'found.' : 'removed.'));
    }

    /**
     * @return void
     */
    protected function deleteFilesOfUnregisteredConversions() {
        $count = 0;
        $this->scopedQuery()->chunkById(200, function ($resources) use (&$count) {
            foreach ($resources as $resource) {
                try {
                    $count += $this->deleteUnregisteredConversionFiles($resource);
                    if ($resource->responsive_images) {
                        $this->deleteUnregisteredResponsiveImages($resource);
                    }
                } catch (Exception $exception) {
                    $this->warn("Resource id {$resource->getKey()} skipped: {$exception->getMessage()}");
                }
                $this->throttle(2);
            }
        });
        $this->info("{$count} stale conversion file(s) " . ($this->isDryRun ? 'found.' : 'removed.'));
    }

    /**
     * @param CModel_Resource_ResourceInterface|CModel $resource
     *
     * @return int
     */
    protected function deleteUnregisteredConversionFiles($resource) {
        $conversions = CResources_ConversionCollection::createForResource($resource);
        $registeredFiles = $conversions->getConversionsFiles($resource->collection_name);
        $registeredNames = $conversions->getConversions($resource->collection_name)->map->getName();
        $conversionsPath = CResources_PathGeneratorFactory::create($resource)->getPathForConversions($resource);
        $diskName = $this->conversionsDiskName($resource);
        $disk = CStorage::instance()->disk($diskName);

        $removed = 0;
        foreach ($disk->files($conversionsPath) as $filePath) {
            $basename = basename($filePath);
            if ($registeredFiles->contains($basename) || $basename === $resource->file_name) {
                continue;
            }
            $removed++;
            if (!$this->isDryRun) {
                $disk->delete($filePath);
            }
            $this->line("Stale conversion file `{$filePath}` on disk `{$diskName}` " . ($this->isDryRun ? 'found' : 'removed'));
        }
        if (!$this->isDryRun) {
            $this->unflagUnregisteredConversions($resource, $registeredNames);
        }

        return $removed;
    }

    /**
     * Flags of conversions that are no longer registered come off once their files are gone.
     *
     * @param CModel_Resource_ResourceInterface|CModel $resource
     * @param CCollection                              $registeredNames
     *
     * @return void
     */
    protected function unflagUnregisteredConversions($resource, CCollection $registeredNames) {
        $stale = $resource->getGeneratedConversions()->filter()->keys()->reject(function ($name) use ($registeredNames) {
            return $registeredNames->contains($name);
        });
        if ($stale->isEmpty()) {
            return;
        }
        foreach ($stale as $name) {
            $resource->markAsConversionNotGenerated($name);
        }
        $resource->save();
    }

    /**
     * @param CModel_Resource_ResourceInterface|CModel $resource
     *
     * @return void
     */
    protected function deleteUnregisteredResponsiveImages($resource) {
        $namesWithResponsiveImages = CResources_ConversionCollection::createForResource($resource)
            ->filter(function (CResources_Conversion $conversion) {
                return $conversion->shouldGenerateResponsiveImages();
            })
            ->map(function (CResources_Conversion $conversion) {
                return $conversion->getName();
            });
        if ($this->shouldGenerateResponsiveImagesForOriginal($resource)) {
            $namesWithResponsiveImages->push('resource_original');
        }

        foreach (array_keys($resource->responsive_images) as $generatedFor) {
            if ($namesWithResponsiveImages->contains($generatedFor)) {
                continue;
            }
            $this->line("Stale responsive images `{$generatedFor}` of resource id {$resource->getKey()} " . ($this->isDryRun ? 'found' : 'removed'));
            if (!$this->isDryRun) {
                $resource->responsiveImages($generatedFor)->delete();
            }
        }
    }

    /**
     * @param CModel_Resource_ResourceInterface|CModel $resource
     *
     * @return bool
     */
    protected function shouldGenerateResponsiveImagesForOriginal($resource) {
        $modelClass = CModel_Relation::getMorphedModel($resource->model_type) ?: $resource->model_type;
        if (!class_exists($modelClass)) {
            return true;
        }
        $collection = (new $modelClass())->getResourceCollection($resource->collection_name);

        return $collection ? (bool) $collection->generateResponsiveImages : false;
    }

    /**
     * Directories under `resources/` that no resource row points at, on every disk in use.
     *
     * @return void
     */
    protected function deleteOrphanedDirectories() {
        $diskNames = $this->diskNamesToSweep();
        foreach ($diskNames as $diskName) {
            if (CF::config("storage.disks.{$diskName}") === null) {
                throw CResources_Exception_FileCannotBeAdded_DiskDoesNotExist::create($diskName);
            }
        }
        $used = $this->usedDirectoriesByDisk();
        $count = 0;
        foreach ($diskNames as $diskName) {
            $disk = CStorage::instance()->disk($diskName);
            $usedOnDisk = $used[$diskName] ?? [];
            $this->sweepDirectory($disk, $diskName, 'resources', $usedOnDisk, $count);
        }
        $this->info("{$count} orphaned director" . ($count === 1 ? 'y' : 'ies') . ' ' . ($this->isDryRun ? 'found.' : 'removed.'));
    }

    /**
     * Walk `resources/[<appCode>/]<Ymd>/<modelType>/<id>`: a directory a row points at is kept, another
     * app's subtree or an unknown model type is left alone, and an `<id>` leaf nobody points at is orphaned.
     *
     * @param CStorage_Adapter $disk
     * @param string           $diskName
     * @param string           $directory
     * @param array            $usedOnDisk
     * @param int              $count
     *
     * @return void
     */
    protected function sweepDirectory($disk, $diskName, $directory, array $usedOnDisk, &$count) {
        foreach ($disk->directories($directory) as $subdirectory) {
            $subdirectory = rtrim($subdirectory, '/');
            if (isset($usedOnDisk[$subdirectory])) {
                continue;
            }
            $segments = explode('/', trim($subdirectory, '/'));
            array_shift($segments);
            if (isset($segments[0]) && !preg_match('/^\d{8}$/', $segments[0])) {
                if ($segments[0] !== CF::appCode()) {
                    continue;
                }
                array_shift($segments);
            }
            $depth = count($segments);
            if ($depth < 3) {
                $this->sweepDirectory($disk, $diskName, $subdirectory, $usedOnDisk, $count);

                continue;
            }
            if ($depth > 3 || !ctype_digit($segments[2]) || !class_exists(CModel_Relation::getMorphedModel($segments[1]) ?: $segments[1])) {
                continue;
            }
            $count++;
            if (!$this->isDryRun) {
                $disk->deleteDirectory($subdirectory);
                $this->throttle();
            }
            $this->line("Orphaned directory `{$subdirectory}` on disk `{$diskName}` " . ($this->isDryRun ? 'found' : 'removed'));
        }
    }

    /**
     * @return array<string, array<string, true>>
     */
    protected function usedDirectoriesByDisk() {
        $used = [];
        // a soft-deleted resource still owns its files, so its directories are kept
        $query = $this->repository->queryFor();
        if ($query->hasMacro('withTrashed')) {
            $query->withTrashed();
        }
        $query->chunkById(1000, function ($resources) use (&$used) {
            foreach ($resources as $resource) {
                $pathGenerator = CResources_PathGeneratorFactory::create($resource);
                $used[$resource->disk][rtrim($pathGenerator->getPath($resource), '/')] = true;
                $used[$this->conversionsDiskName($resource)][rtrim($pathGenerator->getPathForConversions($resource), '/')] = true;
                $used[$this->conversionsDiskName($resource)][rtrim($pathGenerator->getPathForResponsiveImages($resource), '/')] = true;
            }
        });

        return $used;
    }

    /**
     * @return array
     */
    protected function diskNamesToSweep() {
        $disk = $this->argument('disk');
        if (is_string($disk) && $disk !== '') {
            return [$disk];
        }

        return $this->repository->allDiskNames()
            ->push(CF::config('resource.disk'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param CModel_Resource_ResourceInterface|CModel $resource
     *
     * @return string
     */
    protected function conversionsDiskName($resource) {
        return $resource->conversions_disk ?: $resource->disk;
    }

    /**
     * @param int $weight
     *
     * @return void
     */
    protected function throttle($weight = 1) {
        if ($this->rateLimit) {
            usleep((int) ((1 / $this->rateLimit) * 1000000 * $weight));
        }
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

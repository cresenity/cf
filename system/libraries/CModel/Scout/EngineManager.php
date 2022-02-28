<?php
use TeamTNT\TNTSearch\TNTSearch;

class CModel_Scout_EngineManager extends CBase_ManagerAbstract {
    /**
     * Get a driver instance.
     *
     * @param null|string $name
     *
     * @return \CModel_Scout_EngineAbstract
     */
    public function engine($name = null) {
        return $this->driver($name);
    }

    public function createTntsearchEngine() {
        $tnt = new TNTSearch();

        $config = CF::config('model.scout.tntsearch');
        $storage = carr::get($config, 'storage');
        if (!is_dir($storage)) {
            CFile::makeDirectory($storage, 0755, true);
        }
        $databaseConfigName = CF::config('model.scout.tntsearch.database', 'default');
        $databaseConfig = CF::config('database.' . $databaseConfigName);
        $driver = carr::get($databaseConfig, 'connection.type');
        if ($driver == 'mysqli') {
            $driver = 'mysql';
        }
        $tntDbConfig = [
            'driver' => $driver,
            'host' => carr::get($databaseConfig, 'connection.host'),
            'database' => carr::get($databaseConfig, 'connection.database'),
            'username' => carr::get($databaseConfig, 'connection.user'),
            'password' => carr::get($databaseConfig, 'connection.pass'),
        ];

        $tnt->loadConfig($config + $tntDbConfig);

        $tnt->maxDocs = CF::config('model.scout.tntsearch.maxDocs', 500);

        $tnt->fuzziness = CF::config('model.scout.tntsearch.fuzziness', $tnt->fuzziness);
        $tnt->fuzzy_distance = CF::config('model.scout.tntsearch.fuzzy.distance', $tnt->fuzzy_distance);
        $tnt->fuzzy_prefix_length = CF::config('model.scout.tntsearch.fuzzy.prefix_length', $tnt->fuzzy_prefix_length);
        $tnt->fuzzy_max_expansions = CF::config('model.scout.tntsearch.fuzzy.max_expansions', $tnt->fuzzy_max_expansions);

        $tnt->asYouType = CF::config('model.scout.tntsearch.asYouType', $tnt->asYouType);

        return $tnt;
    }

    /**
     * Create a collection engine instance.
     *
     * @return \CModel_Scout_Engine_TNTSearchEngine
     */
    public function createTntsearchDriver() {
        $tnt = $this->createTntsearchEngine();

        return new CModel_Scout_Engine_TNTSearchEngine($tnt);
    }

    /**
     * Create a collection engine instance.
     *
     * @return \CModel_Scout_Engine_CollectionEngine
     */
    public function createCollectionDriver() {
        return new CModel_Scout_Engine_CollectionEngine();
    }

    /**
     * Create a null engine instance.
     *
     * @return \CModel_Scout_Engine_NullEngine
     */
    public function createNullDriver() {
        return new CModel_Scout_Engine_NullEngine();
    }

    /**
     * Forget all of the resolved engine instances.
     *
     * @return $this
     */
    public function forgetEngines() {
        $this->drivers = [];

        return $this;
    }

    /**
     * Get the default Scout driver name.
     *
     * @return string
     */
    public function getDefaultDriver() {
        if (is_null($driver = CF::config('model.scout.driver'))) {
            return 'null';
        }

        return $driver;
    }
}

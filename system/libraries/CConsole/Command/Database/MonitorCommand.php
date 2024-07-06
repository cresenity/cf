<?php


class CConsole_Command_Database_MonitorCommand extends CConsole_Command_Database_AbstractInspectionCommand {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:monitor
                {--databases= : The database connections to monitor}
                {--max= : The maximum number of connections that can be open before an event is dispatched}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor the number of connections on the specified database';

    /**
     * The connection resolver instance.
     *
     * @var \CDatabase_ConnectionResolver
     */
    protected $connection;

    /**
     * The events dispatcher instance.
     *
     * @var \CEvent_Dispatcher
     */
    protected $events;

    /**
     * Create a new command instance.
     */
    public function __construct() {
        parent::__construct();

        $this->connection = CDatabase::manager();
        $this->events = CEvent::dispatcher();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle() {
        $databases = $this->parseDatabases($this->option('databases'));

        $this->displayConnections($databases);

        if ($this->option('max')) {
            $this->dispatchEvents($databases);
        }
    }

    /**
     * Parse the database into an array of the connections.
     *
     * @param string $databases
     *
     * @return \CCollection
     */
    protected function parseDatabases($databases) {
        return c::collect(explode(',', $databases))->map(function ($database) {
            if (!$database) {
                $database = CF::config('database.default');
            }

            $maxConnections = $this->option('max');

            return [
                'database' => $database,
                'connections' => $connections = $this->getConnectionCount($this->connection->connection($database)),
                'status' => $maxConnections && $connections >= $maxConnections ? '<fg=yellow;options=bold>ALERT</>' : '<fg=green;options=bold>OK</>',
            ];
        });
    }

    /**
     * Display the databases and their connection counts in the console.
     *
     * @param \CCollection $databases
     *
     * @return void
     */
    protected function displayConnections($databases) {
        $this->newLine();

        $this->components->twoColumnDetail('<fg=gray>Database name</>', '<fg=gray>Connections</>');

        $databases->each(function ($database) {
            $status = '[' . $database['connections'] . '] ' . $database['status'];

            $this->components->twoColumnDetail($database['database'], $status);
        });

        $this->newLine();
    }

    /**
     * Dispatch the database monitoring events.
     *
     * @param \CCollection $databases
     *
     * @return void
     */
    protected function dispatchEvents($databases) {
        $databases->each(function ($database) {
            if ($database['status'] === '<fg=green;options=bold>OK</>') {
                return;
            }

            $this->events->dispatch(
                new CDatabase_Event_DatabaseBusy(
                    $database['database'],
                    $database['connections']
                )
            );
        });
    }
}

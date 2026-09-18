<?php

/**
 * Description of DaemonListCommand
 */
class CConsole_Command_Daemon_DaemonListCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daemon:list';

    public function handle() {
        CConsole::domainRequired($this);
        $daemonManager = CManager::daemon();
        $tableData = [];
        if ($daemonManager->haveGroup()) {
            $groupKeys = $daemonManager->getGroupsKey();
            $notGrouped = $daemonManager->daemons(false);
            if (count($notGrouped) > 0) {
                $tableData[] = $this->getTableData(false);
            }
            foreach ($groupKeys as $groupName) {
                $tableData[] = $this->getTableData($groupName);
            }
        } else {
            $tableData[] = $this->getTableData(false);
        }

        $i = 0;
        foreach ($tableData as $table) {
            if ($i > 0) {
                $this->line('');
            }
            $this->info(carr::get($table, 'group'));
            $this->table(carr::get($table, 'header'), carr::get($table, 'rows'));
            $i++;
        }
    }

    public static function getTableData($group = null) {
        $daemonManager = CManager::daemon();
        $listService = $daemonManager->daemons($group);
        $dataService = [];

        $rows = c::collect($listService)->map(function ($serviceName, $className) {
            $status = CManager::daemon()->isRunning($className) ? 'RUNNING' : 'STOPPED';
            return [
                'serviceName' => $serviceName,
                'className' => $className,
                'status' => $status,
            ];
        })->sortBy('serviceName')->all();

        return [
            'group' => $group ? $group : '[Ungrouped]',
            'header' => ['Service', 'Class', 'Status'],
            'rows' => $rows,
        ];
    }
}

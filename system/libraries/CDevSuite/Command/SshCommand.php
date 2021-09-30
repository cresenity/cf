<?php

/**
 * CDevSuite_Command_SshCommand
 *
 * @author Hery
 */
class CDevSuite_Command_SshCommand extends CDevSuite_CommandAbstract {
    public function run(CConsole_Command $cfCommand) {
        $collection = CDevSuite::ssh()->getTableData();

        CDevSuite::table(['Name', 'Host', 'Type', 'User', 'Password'], $collection->all());
    }
}

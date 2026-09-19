<?php

class CDatabase_Connection_Pdo_OdbcConnection extends CDatabase_Connection {
    protected function getDefaultQueryGrammar() {
        return isset($this->config['grammar']['query'])
            ? new $this->config['grammar']['query']()
            : new CDatabase_Query_Grammar_OdbcGrammar();
    }

    protected function getDefaultSchemaGrammar() {
        return isset($this->config['grammar']['schema'])
            ? new $this->config['grammar']['schema']()
            : new CDatabase_Schema_Grammar_OdbcGrammar();
    }
}

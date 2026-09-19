<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinLateralClause;

class CDatabase_Query_Grammar_MariaDBbGrammar extends CDatabase_Query_Grammar_MySqlGrammar {
    /**
     * Compile a "lateral join" clause.
     *
     * @param \CDatabase_Query_JoinLateralClause $join
     * @param string                             $expression
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    public function compileJoinLateral(CDatabase_Query_JoinLateralClause $join, $expression) {
        throw new RuntimeException('This database engine does not support lateral joins.');
    }

    /**
     * Determine whether to use a legacy group limit clause for MySQL < 8.0.
     *
     * @param \CDatabase_Query_Builder $query
     *
     * @return bool
     */
    public function useLegacyGroupLimit(CDatabase_Query_Builder $query) {
        return false;
    }
}

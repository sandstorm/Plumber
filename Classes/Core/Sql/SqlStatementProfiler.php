<?php
namespace Sandstorm\Plumber\Core\Sql;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.PhpProfiler". *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Neos\Flow\Persistence\Doctrine\Logging\SqlLogger;
use Neos\Flow\Annotations as Flow;
use Sandstorm\Plumber\Core\Profiler;

/**
 * PHP Profiler
 *
 * @Flow\Proxy(false)
 */
class SqlStatementProfiler extends SQLLogger {

    /**
     * Logs a SQL statement somewhere.
     *
     * @param string $sql The SQL to be executed.
     * @param array|null $params The SQL parameters.
     * @param array|null $types The SQL parameter types.
     *
     * @return void
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        $params = $params != null ? $params : [];
        $params['_sql'] = $sql;
        Profiler::getInstance()->getRun()->startTimer('SQL Query', $params);
        Profiler::getInstance()->getRun()->logSqlQuery($sql);
    }

    /**
     * Marks the last started query as stopped. This can be used for timing of queries.
     *
     * @return void
     */
    public function stopQuery()
    {
        Profiler::getInstance()->getRun()->stopTimer('SQL Query');
    }
}

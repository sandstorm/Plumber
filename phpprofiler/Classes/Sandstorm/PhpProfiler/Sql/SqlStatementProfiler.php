<?php
namespace Sandstorm\PhpProfiler\Sql;

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
use Psr\Log\NullLogger;
use Sandstorm\PhpProfiler\Aspect\SqlEntityManagerConfigurationAspect;
use Neos\Flow\Annotations as Flow;
use Sandstorm\PhpProfiler\Sql\Middleware\SqlProfilingMiddleware;

/**
 * WORKAROUND for Neos 8.X: MARKER CLASS -> if this class is configured as SQL logger,
 * the {@see SqlEntityManagerConfigurationAspect} kicks in and configures the {@see SqlProfilingMiddleware}.
 */
#[Flow\Proxy(false)]
class SqlStatementProfiler extends SQLLogger {

    public function __construct() {
        $this->logger = new NullLogger();
    }
}

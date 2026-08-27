<?php

namespace Sandstorm\Plumber\Core\Domain\Model;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.PhpProfiler". *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Neos\Flow\Annotations as Flow;

/**
 * Empty Profiling Run; provides method stubs which do not do anything.
 *
 * This is needed such that the user can do ...getRun()->startTimer() even
 * when profiling is disabled.
 *
 * @Flow\Proxy(false)
 */
class EmptyProfilingRun
{

    /**
     * Set an option.
     * @api
     */
    public function setOption(string $key, mixed $value): void
    {
    }

    /**
     * Returns all tags for this run.
     *
     * @api
     */
    public function getTags(): array
    {
        return [];
    }

    /**
     * Set tags for this run.
     *
     * @api
     */
    public function setTags(array $tags): void
    {
    }

    /**
     * Start a timer
     * @api
     */
    public function startTimer(string $name, array $data = []) {
    }

    /**
     * Stop a timer
     * @api
     */
    public function stopTimer(string $name): void
    {
    }

    public function manualTimer(string $name, array $data, float $startTimestamp, float $stopTimestamp): void
    {
    }

    /**
     * Record a timestamp
     */
    public function timestamp(string $name, array $data = []): void
    {
    }

    public function logSqlQuery($sql): void
    {
    }

    /**
     * From here on, save() writes nothing unless markAsRelevant() is called.
     *
     * @api
     */
    public function discardUnlessMarkedRelevant(): void
    {
    }

    /**
     * @api
     */
    public function markAsRelevant(): void
    {
    }
}

<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core\Domain\Model;

use Neos\Flow\Annotations as Flow;

/**
 * Empty Profiling Run; provides method stubs which do not do anything.
 *
 * This is needed such that the user can do ...getRun()->startTimer() even
 * when profiling is disabled.
 */
#[Flow\Proxy(false)]
class EmptyProfilingRun
{
    /**
     * Set an option.
     *
     * @param string $key
     * @param mixed $value
     * @api
     */
    public function setOption($key, $value): void {}

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
    public function setTags(array $tags): void {}

    /**
     * Start a timer
     *
     * @param string $name
     * @api
     */
    public function startTimer($name, array $data = []): void {}

    /**
     * Stop a timer
     *
     * @param string $name
     * @api
     */
    public function stopTimer($name): void {}

    /**
     * Record a timer whose start and stop time were measured elsewhere.
     *
     * @param string $name
     * @param array $data
     * @param float $startTimestamp
     * @param float $stopTimestamp
     * @api
     */
    public function manualTimer($name, array $data, $startTimestamp, $stopTimestamp): void {}

    /**
     * Record a timestamp
     *
     * @param string $name
     */
    public function timestamp($name, array $data = []): void {}

    public function logSqlQuery($sql): void {}

    /**
     * From here on, save() writes nothing unless markAsRelevant() is called.
     *
     * @api
     */
    public function discardUnlessMarkedRelevant(): void {}

    /**
     * @api
     */
    public function markAsRelevant(): void {}
}

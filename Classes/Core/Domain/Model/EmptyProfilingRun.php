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
     *
     * @param string $key
     * @param mixed $value
     * @return void
     * @api
     */
    public function setOption($key, $value)
    {
    }

    /**
     * Returns all tags for this run.
     *
     * @return array
     * @api
     */
    public function getTags()
    {
        return array();
    }

    /**
     * Set tags for this run.
     *
     * @param array $tags
     * @return void
     * @api
     */
    public function setTags(array $tags)
    {
    }

    /**
     * Start a timer
     *
     * @param string $name
     * @param array $data
     * @return void
     * @api
     */
    public function startTimer($name, array $data = array())
    {
    }

    /**
     * Stop a timer
     *
     * @param string $name
     * @return void
     * @api
     */
    public function stopTimer($name)
    {
    }

    /**
     * Record a timer whose start and stop time were measured elsewhere.
     *
     * @param string $name
     * @param array $data
     * @param float $startTimestamp
     * @param float $stopTimestamp
     * @return void
     * @api
     */
    public function manualTimer($name, array $data, $startTimestamp, $stopTimestamp)
    {
    }

    /**
     * Record a timestamp
     *
     * @param string $name
     * @param array $data
     * @return void
     */
    public function timestamp($name, array $data = array())
    {
    }

    public function logSqlQuery($sql)
    {
    }
}

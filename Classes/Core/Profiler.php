<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.PhpProfiler". *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Closure;
use Exception;
use Neos\Flow\Annotations as Flow;
use RuntimeException;
use Sandstorm\Plumber\Core\Domain\Model\EmptyProfilingRun;
use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;
use Sandstorm\Plumber\Core\Sql\Middleware\SqlProfilingStatement;

/**
 * PHP Profiler
 */
#[Flow\Proxy(false)]
final class Profiler
{
    private static ?Profiler $instance = null;

    private ?ProfilingRun $currentlyRunningProfilingRun = null;

    private ?Closure $configurationProvider = null;

    /**
     * An "empty" profiling run; which does not execute anything and
     * can be returned by getRun() if profiling is not currently running.
     */
    private EmptyProfilingRun $emptyProfilingRun;

    /**
     * Options which every run of this process gets, including one which is only started later on.
     *
     * @var array<string, string>
     */
    private array $runOptions = [];

    /**
     * What a run records, from Sandstorm.Plumber.record. Defaults to everything, because packages boot before
     * the settings are readable and the run started there is already recording by then.
     *
     * @var array<string, bool>
     */
    private array $recording = [
        'sqlQueries' => true,
        'xhprof' => true,
    ];

    /**
     * Set up an EmptyProfilingRun
     */
    private function __construct()
    {
        $this->emptyProfilingRun = new EmptyProfilingRun();
    }

    /**
     * @api
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set configuration options provider for the profiler.
     *
     * Must return an array with settings, the supported settings can be
     * seen in the Settings.yaml file.
     *
     * @api
     */
    public function setConfigurationProvider(Closure $configurationProvider): void
    {
        $this->configurationProvider = $configurationProvider;
    }

    /**
     * Start a profiling run and return the run instance.
     *
     * @throws RuntimeException
     * @api
     */
    public function start(): ProfilingRun
    {
        if ($this->currentlyRunningProfilingRun !== null) {
            throw new RuntimeException('Profiling already started', 1363337740);
        }
        $this->currentlyRunningProfilingRun = new ProfilingRun();
        $this->currentlyRunningProfilingRun->setRecordXhprof($this->recording['xhprof']);
        $this->currentlyRunningProfilingRun->start();
        foreach ($this->runOptions as $optionName => $optionValue) {
            $this->currentlyRunningProfilingRun->setOption($optionName, $optionValue);
        }
        return $this->currentlyRunningProfilingRun;
    }

    /**
     * Start a profiling run unless one is already recording, and return it.
     *
     * This is for integrations which want to profile one part of a process instead of all of it: with
     * Sandstorm.Plumber.enabled set to FALSE nothing is recording, and this starts a run at the point the
     * interesting work begins. It is written to disk by the shutdown function registered during boot.
     *
     * If profiling is switched off for the whole process (PLUMBER_ENABLED=0), the package never booted its
     * profiler and nothing would ever write such a run out - so the EmptyProfilingRun is returned instead.
     *
     * @api
     */
    public function startIfNotRunning(): EmptyProfilingRun
    {
        if ($this->currentlyRunningProfilingRun !== null) {
            return $this->currentlyRunningProfilingRun;
        }
        if ($this->configurationProvider === null) {
            return $this->emptyProfilingRun;
        }
        return $this->start();
    }

    /**
     * Set an option on the current run and on every run started later in this process.
     *
     * Context and controller are known long before a lazily started run exists, so they are remembered
     * here instead of being set on a single run object.
     *
     * @api
     */
    public function setRunOption(string $name, string $value): void
    {
        $this->runOptions[$name] = $value;
        if ($this->currentlyRunningProfilingRun !== null) {
            $this->currentlyRunningProfilingRun->setOption($name, $value);
        }
    }

    /**
     * Apply Sandstorm.Plumber.record to this process.
     *
     * Called once the settings are readable, which is after the run started during boot is already recording -
     * hence the run in flight is switched over as well as every run started later.
     *
     * @param array<string, mixed> $recording
     * @api
     */
    public function applyRecordingSettings(array $recording): void
    {
        $this->recording = array_merge($this->recording, array_filter($recording, 'is_bool'));
        if ($this->currentlyRunningProfilingRun !== null && !$this->recording['xhprof']) {
            $this->currentlyRunningProfilingRun->stopRecordingXhprof();
        }
    }

    /**
     * Whether every SQL query gets its own timer. The query count is recorded either way.
     *
     * Read by {@see SqlProfilingStatement}, which Doctrine instantiates outside the object manager and which
     * therefore cannot have the setting injected.
     *
     * @api
     */
    public function isRecordingSqlQueryTimers(): bool
    {
        return $this->recording['sqlQueries'];
    }

    /**
     * Get the current profiling run.
     */
    public function getRun(): EmptyProfilingRun
    {
        if ($this->currentlyRunningProfilingRun === null) {
            return $this->emptyProfilingRun;
        }
        return $this->currentlyRunningProfilingRun;
    }

    /**
     * Stop run and save it afterwards.
     *
     * @api
     */
    public function stopAndSave(): void
    {
        $run = $this->stop();
        if ($run !== null) {
            $this->save($run);
        }
    }

    /**
     * Stop a profiling run if one is running, and return it.
     */
    public function stop(): ?ProfilingRun
    {
        if ($this->currentlyRunningProfilingRun === null) {
            return null;
        }
        $this->currentlyRunningProfilingRun->stop();

        $run = $this->currentlyRunningProfilingRun;
        $this->currentlyRunningProfilingRun = null;
        return $run;
    }

    /**
     * Save a profiling run.
     *
     * @throws Exception
     */
    public function save(ProfilingRun $run): void
    {
        $configuration = $this->configurationProvider->__invoke();
        if (!isset($configuration['profilePath'])) {
            throw new Exception('Profiling path not set');
        }

        $run->save($configuration);
    }
}

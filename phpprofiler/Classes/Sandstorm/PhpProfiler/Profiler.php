<?php
namespace Sandstorm\PhpProfiler;

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
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Configuration\ConfigurationManager;

/**
 * PHP Profiler
 *
 * @Flow\Proxy(false)
 */
class Profiler
{

    /**
     * @var Profiler
     */
    protected static $instance;

    /**
     * @var Domain\Model\ProfilingRun
     */
    protected $currentlyRunningProfilingRun;

    /**
     * @var \Closure
     */
    protected $configurationProvider;

    /**
     * An "empty" profiling run; which does not execute anything and
     * can be returned by getRun() if profiling is not currently running.
     *
     * @var Domain\Model\ProfilingRun
     */
    protected $emptyProfilingRun;

    /**
     * Set up an EmptyProfilingRun
     */
    protected function __construct()
    {
        $this->emptyProfilingRun = new Domain\Model\EmptyProfilingRun();
    }

    /**
     * @return Profiler
     * @api
     */
    public static function getInstance()
    {
        if (self::$instance === NULL) {
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
     * @param \Closure $configurationProvider
     * @return void
     * @api
     */
    public function setConfigurationProvider($configurationProvider)
    {
        $this->configurationProvider = $configurationProvider;
    }

    /**
     * Start a profiling run and return the run instance.
     *
     * @throws \RuntimeException
     * @return Domain\Model\ProfilingRun
     * @api
     */
    public function start()
    {
        if ($this->currentlyRunningProfilingRun !== NULL) {
            throw new \RuntimeException('Profiling already started', 1363337740);
        }
        $this->currentlyRunningProfilingRun = new Domain\Model\ProfilingRun();
        $this->currentlyRunningProfilingRun->start();
        return $this->currentlyRunningProfilingRun;
    }

    /**
     * Get the current profiling run.
     *
     * @return Domain\Model\ProfilingRun
     */
    public function getRun()
    {
        if ($this->currentlyRunningProfilingRun === NULL) {
            return $this->emptyProfilingRun;
        }
        return $this->currentlyRunningProfilingRun;
    }

    /**
     * Stop run and save it afterwards.
     *
     * @return void
     * @api
     */
    public function stopAndSave()
    {
        $run = $this->stop();
        if ($run !== NULL) {
            $this->save($run);
        }
    }

    /**
     * Stop a profiling run if one is running, and return it.
     *
     * @return Domain\Model\ProfilingRun the profiling run or NULL if none is running
     */
    public function stop()
    {
        if (!$this->currentlyRunningProfilingRun) {
            return NULL;
        }
        $this->currentlyRunningProfilingRun->stop();

        $run = $this->currentlyRunningProfilingRun;
        $this->currentlyRunningProfilingRun = NULL;
        return $run;
    }

    /**
     * Save a profiling run.
     *
     * @param Domain\Model\ProfilingRun $run
     * @throws \Exception
     * @return void
     */
    public function save(Domain\Model\ProfilingRun $run)
    {
        $configuration = $this->configurationProvider->__invoke();
        if (!isset($configuration['plumber']['profilePath'])) {
            throw new \Exception('Profiling path not set');
        }

        $run->save($configuration);
    }
}

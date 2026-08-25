<?php

declare(strict_types=1);

namespace Sandstorm\Plumber;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Booting\Sequence;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Flow\SignalSlot\Dispatcher;
use Neos\Utility\Files;
use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;
use Sandstorm\Plumber\Core\Profiler;

class Package extends BasePackage
{
    /**
     * Sets up xhprof and some directories.
     *
     * @param Bootstrap $bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        define('XHPROF_ROOT', $this->getResourcesPath() . 'Private/PHP/xhprof-ui/');

        $environmentOverride = self::getIsPlumberEnabledFromEnvironment();
        if ($environmentOverride === false) {
            return;
        }

        if (($samplingRate = getenv('PHPPROFILER_SAMPLINGRATE')) !== false) {
            $currentSampleValue = mt_rand() / mt_getrandmax();
            if ($currentSampleValue > (float) $samplingRate) {
                return;
            }
        }

        $profiler = Profiler::getInstance();
        $profiler->setConfigurationProvider(function () use ($bootstrap) {
            $settings =
                $bootstrap
                    ->getEarlyInstance('Neos\Flow\Configuration\ConfigurationManager')
                    ->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Sandstorm.Plumber');
            if (!file_exists($settings['profilePath'])) {
                Files::createDirectoryRecursively($settings['profilePath']);
            }

            return $settings;
        });

        $run = $profiler->start();
        $run->setOption('Context', (string) $bootstrap->getContext());

        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $this->connectToSignals($dispatcher, $profiler, $run, $bootstrap);
        $this->connectToNeosSignals($dispatcher, $run);
        if ($environmentOverride === null) {
            $this->discardRunIfSettingsDisableProfiling($dispatcher, $profiler, $bootstrap);
        }

        // Flow emits finishedRuntimeRun at the end of Bootstrap::run(), and exit() skips it - which is how every
        // render worker of a content release ends (see Flowpack.DecoupledContentStore's
        // InterruptibleProcessRuntime). A shutdown function still runs in that case, and it also survives a fatal
        // error. On the normal path it saves nothing, because stop() returns NULL once the run has been stopped.
        register_shutdown_function(function () use ($profiler) {
            $profiler->stopAndSave();
        });
    }

    /**
     * PLUMBER_ENABLED decides on its own, so that a single run - one ./flow call, one prunner task - can be
     * profiled or skipped without changing the configuration and without rebuilding the settings.
     *
     * @return boolean|null TRUE or FALSE if the environment variable decides, NULL if it is not set
     */
    private static function getIsPlumberEnabledFromEnvironment(): ?bool
    {
        $value = getenv('PLUMBER_ENABLED');
        if ($value === false || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Throw the profiling run away again unless the settings ask for profiling.
     *
     * Packages are booted before the neos.flow:configuration step, so the settings cannot be read in boot() -
     * that is also why the configuration provider above is a closure. Hence the run is started first and
     * discarded as soon as the settings are available: stopping it disables the xhprof trace and makes
     * Profiler::getRun() return an EmptyProfilingRun, so every timer call afterwards does nothing and nothing is
     * ever written to disk.
     *
     * @param Dispatcher $dispatcher
     * @param Profiler $profiler
     * @param Bootstrap $bootstrap
     * @return void
     */
    private function discardRunIfSettingsDisableProfiling(
        Dispatcher $dispatcher,
        Profiler $profiler,
        Bootstrap $bootstrap,
    ): void {
        $dispatcher->connect(Sequence::class, 'afterInvokeStep', function ($step) use ($profiler, $bootstrap) {
            if ($step->getIdentifier() !== 'neos.flow:configuration') {
                return;
            }

            $settings = $bootstrap
                ->getEarlyInstance(ConfigurationManager::class)
                ->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Sandstorm.Plumber');
            if (($settings['enabled'] ?? false) !== true) {
                $profiler->stop();
            }
        });
    }

    /**
     * Wire signals to slots as needed.
     */
    private function connectToSignals(
        Dispatcher $dispatcher,
        Profiler $profiler,
        ProfilingRun $run,
        Bootstrap $bootstrap,
    ): void {
        $dispatcher->connect('Neos\Flow\Core\Booting\Sequence', 'beforeInvokeStep', function ($step) use ($run) {
            $run->startTimer('Boostrap Sequence: ' . $step->getIdentifier());
        });
        $dispatcher->connect('Neos\Flow\Core\Booting\Sequence', 'afterInvokeStep', function ($step) use ($run) {
            $run->stopTimer('Boostrap Sequence: ' . $step->getIdentifier());
        });

        $dispatcher->connect('Neos\Flow\Core\Bootstrap', 'finishedRuntimeRun', function () use ($profiler, $bootstrap) {
            $run = $profiler->stop();
            if ($run) {
                $profiler->save($run);
            }
        });

        $dispatcher->connect(
            'Neos\Flow\Core\Bootstrap',
            'finishedCompiletimeRun',
            function () use ($profiler, $bootstrap) {
                $run = $profiler->stop();
                if ($run) {
                    $run->setOption('Context', 'COMPILE');
                    $profiler->save($run);
                }
            },
        );

        $dispatcher->connect(
            'Neos\Flow\Mvc\Dispatcher',
            'beforeControllerInvocation',
            function ($request, $response, $controller) use ($run) {
                $run->setOption('Controller Name', get_class($controller));
                $data = [
                    'Controller' => get_class($controller),
                ];
                if ($request instanceof ActionRequest) {
                    $data['Action'] = $request->getControllerActionName();
                }

                $run->startTimer('MVC: Controller Invocation', $data);
            },
        );
        $dispatcher->connect('Neos\Flow\Mvc\Dispatcher', 'afterControllerInvocation', function () use ($run) {
            $run->stopTimer('MVC: Controller Invocation');
        });
    }

    /**
     * Wire signals to slots as needed in Neos.
     */
    private function connectToNeosSignals(
        Dispatcher $dispatcher,
        ProfilingRun $run,
    ): void {
        $dispatcher->connect('Neos\Fusion\Core\Runtime', 'beginEvaluation', function ($fusionPath) use ($run) {
            $run->startTimer('TypoScript Runtime: ' . $fusionPath);
        });
        $dispatcher->connect('Neos\Fusion\Core\Runtime', 'endEvaluation', function ($fusionPath) use ($run) {
            $run->stopTimer('TypoScript Runtime: ' . $fusionPath);
        });

        $dispatcher->connect('Neos\Neos\View\FusionView', 'beginRender', function () use ($run) {
            $run->startTimer('Neos TypoScript Rendering');
        });
        $dispatcher->connect('Neos\Neos\View\FusionView', 'endRender', function () use ($run) {
            $run->stopTimer('Neos TypoScript Rendering');
        });
    }

}

<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Utility\Files;
use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;
use Sandstorm\Plumber\Core\Profiler;

/**
 * Standard controller for the Sandstorm.Plumber package
 *
 * @Flow\Scope("singleton")
 */
abstract class AbstractController extends ActionController
{
    /**
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * Initializes the controller before invoking an action method.
     *
     * @return void
     */
    protected function initializeAction(): void
    {
        Profiler::getInstance()->stop();
    }

    /**
     * Returns a ProfilingRun instance that has been saved as $filename.
     *
     * @param string $filename
     * @return ProfilingRun
     */
    protected function getProfile(string $filename): ProfilingRun
    {
        $pathAndFilename = Files::concatenatePaths([$this->settings['profilePath'], $filename]);
        $profile = unserialize(file_get_contents($pathAndFilename));
        $profile->setPathAndFilename($pathAndFilename);
        return $profile;
    }

    /**
     * Returns an array of ProfilingRun instances that have been saved earlier.
     *
     * @return array<ProfilingRun>
     */
    public function getProfiles(): array
    {
        if (!file_exists($this->settings['profilePath'])) {
            return [];
        }

        $directoryIterator = new \DirectoryIterator($this->settings['profilePath']);

        $profiles = [];
        foreach ($directoryIterator as $element) {
            if (preg_match('/\.profile$/', $element->getFilename())) {
                $profile = unserialize(file_get_contents($element->getPathname()));
                if (!$profile instanceof ProfilingRun) {
                    continue;
                }
                $profile->setPathAndFilename($element->getPathname());
                $profiles[$element->getFilename()] = $profile;
            }
        }
        return $profiles;
    }
}


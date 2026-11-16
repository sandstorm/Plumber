<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Utility\Files;
use Sandstorm\Plumber\Core\Domain\Model\ProfileSummary;
use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;
use Sandstorm\Plumber\Core\Profiler;

/**
 * Abstract controller for the Sandstorm.Plumber package
 */
#[Flow\Scope("singleton")]
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
     * Yields the ProfilingRun instances that have been saved earlier, one at a time.
     *
     * A generator and not an array: a long batch job leaves thousands of profiles of ~10 MB behind, and holding
     * them all at once exhausts any memory limit. Callers must therefore not keep a reference to a run after
     * moving on to the next one. Where only the metadata is needed, use {@see getProfileSummaries()} instead,
     * which does not read the profiles at all.
     *
     * @return \Generator<string, ProfilingRun>
     */
    public function getProfiles(): \Generator
    {
        foreach ($this->getProfilePathsAndFilenames() as $filename => $pathAndFilename) {
            $profile = $this->loadProfile($pathAndFilename);
            if ($profile !== null) {
                yield $filename => $profile;
            }
        }
    }

    /**
     * Yields what the overview shows about each saved run, without reading the profiles themselves.
     *
     * A profile written before Plumber wrote sidecars has none, so it is read once here and gets one.
     *
     * @return \Generator<string, ProfileSummary>
     */
    public function getProfileSummaries(): \Generator
    {
        foreach ($this->getProfilePathsAndFilenames() as $filename => $pathAndFilename) {
            $summary = ProfileSummary::load($pathAndFilename);
            if ($summary === null) {
                $profile = $this->loadProfile($pathAndFilename);
                if ($profile === null) {
                    continue;
                }
                $summary = ProfileSummary::fromProfilingRun($pathAndFilename, $profile);
                $summary->save();
            }
            yield $filename => $summary;
        }
    }

    /**
     * @return array<string, string> profile filename => full path
     */
    protected function getProfilePathsAndFilenames(): array
    {
        if (!file_exists($this->settings['profilePath'])) {
            return [];
        }

        $pathsAndFilenames = [];
        foreach (new \DirectoryIterator($this->settings['profilePath']) as $element) {
            if (preg_match('/\.profile$/', $element->getFilename())) {
                $pathsAndFilenames[$element->getFilename()] = $element->getPathname();
            }
        }
        ksort($pathsAndFilenames);

        return $pathsAndFilenames;
    }

    protected function loadProfile(string $pathAndFilename): ?ProfilingRun
    {
        $profile = unserialize((string) file_get_contents($pathAndFilename));
        if (!$profile instanceof ProfilingRun) {
            return null;
        }
        $profile->setPathAndFilename($pathAndFilename);

        return $profile;
    }
}


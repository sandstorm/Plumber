<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Environment;
use Neos\Utility\Files;
use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;
use Sandstorm\Plumber\Exception;
use Sandstorm\Plumber\Export\ExportFormatRegistry;

/**
 * Hands the profiles to tools outside Plumber - see {@see ExportFormatRegistry}.
 *
 * @Flow\Scope("singleton")
 */
class ExportController extends AbstractController
{
    #[Flow\Inject]
    protected ExportFormatRegistry $exportFormatRegistry;

    #[Flow\Inject]
    protected Environment $environment;

    public function downloadAction(string $format, string $runIdentifier1): string
    {
        return $this->streamExport($format, [$runIdentifier1 => $this->getProfile($runIdentifier1)], $runIdentifier1);
    }

    /**
     * Exports every profile into one file, or every profile carrying $tag - which is how the profiles of the
     * render workers of a single content release end up in a single file.
     */
    public function downloadAllAction(string $format, string $tag = ''): string
    {
        $profiles = $this->getProfiles();
        if ($tag !== '') {
            $profiles = array_filter(
                $profiles,
                static fn(ProfilingRun $profile): bool => in_array($tag, $profile->getTags(), true),
            );
        }

        if ($profiles === []) {
            throw new Exception(
                $tag === '' ? 'There are no profiles to export.' : sprintf('No profile is tagged "%s".', $tag),
                1756200030,
            );
        }

        uasort(
            $profiles,
            static fn(ProfilingRun $a, ProfilingRun $b): int => $a->getStartTimeAsFloat() <=> $b->getStartTimeAsFloat(),
        );

        return $this->streamExport($format, $profiles, $tag === '' ? 'all-profiles' : $tag);
    }

    /**
     * @param array<string, ProfilingRun> $profiles
     */
    protected function streamExport(string $format, array $profiles, string $downloadName): string
    {
        $exportFormat = $this->exportFormatRegistry->getFormat($format);
        $filename = self::sanitizeFilename($downloadName) . $exportFormat->getFilenameSuffix();
        $targetPathAndFilename = Files::concatenatePaths([
            $this->environment->getPathToTemporaryDirectory(),
            uniqid('plumber-export-', true) . $exportFormat->getFilenameSuffix(),
        ]);

        try {
            $exportFormat->export($profiles, $targetPathAndFilename);
            $content = file_get_contents($targetPathAndFilename);
        } finally {
            if (file_exists($targetPathAndFilename)) {
                unlink($targetPathAndFilename);
            }
        }

        $this->response->setContentType($exportFormat->getContentType());
        $this->response->setHttpHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $content;
    }

    protected static function sanitizeFilename(string $name): string
    {
        return trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '-', $name), '-') ?: 'plumber-export';
    }
}

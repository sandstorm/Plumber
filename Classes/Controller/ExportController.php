<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Controller;

use Generator;
use GuzzleHttp\Psr7\Stream;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Environment;
use Neos\Utility\Files;
use Psr\Http\Message\StreamInterface;
use Sandstorm\Plumber\Core\Domain\Model\ProfileSummary;
use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;
use Sandstorm\Plumber\Exception;
use Sandstorm\Plumber\Export\ExportFormatRegistry;

/**
 * Hands the profiles to tools outside Plumber - see {@see ExportFormatRegistry}.
 *
 * Nothing here ever holds more than one profile at a time: the profiles carrying one tag can add up to more than
 * any memory limit, so they are selected by their sidecars, read one by one, and the result is streamed from disk
 * into the response instead of being read back into a string.
 */
#[Flow\Scope("singleton")]
class ExportController extends AbstractController
{
    #[Flow\Inject]
    protected ExportFormatRegistry $exportFormatRegistry;

    #[Flow\Inject]
    protected Environment $environment;

    public function downloadAction(string $format, string $runIdentifier1): StreamInterface
    {
        $pathAndFilename = Files::concatenatePaths([$this->settings['profilePath'], $runIdentifier1]);

        return $this->streamExport(
            $format,
            $this->loadProfiles([$runIdentifier1 => $pathAndFilename]),
            $runIdentifier1,
        );
    }

    /**
     * Exports every profile into one file, or every profile carrying $tag - which is how the profiles of all
     * worker processes of a single job end up in a single file.
     * @throws Exception
     */
    public function downloadAllAction(string $format, string $tag = ''): StreamInterface
    {
        $summaries = [];
        foreach ($this->getProfileSummaries() as $filename => $summary) {
            if ($tag === '' || in_array($tag, $summary->getTags(), true)) {
                $summaries[$filename] = $summary;
            }
        }

        if ($summaries === []) {
            throw new Exception(
                $tag === '' ? 'There are no profiles to export.' : sprintf('No profile is tagged "%s".', $tag),
                1756200030,
            );
        }

        uasort(
            $summaries,
            static fn(ProfileSummary $a, ProfileSummary $b): int => $a->getStartTime() <=> $b->getStartTime(),
        );

        $pathsAndFilenames = array_map(
            static fn(ProfileSummary $summary): string => $summary->getPathAndFilename(),
            $summaries,
        );

        return $this->streamExport(
            $format,
            $this->loadProfiles($pathsAndFilenames),
            $tag === '' ? 'all-profiles' : $tag,
        );
    }

    /**
     * @param array<string, string> $pathsAndFilenames profile filename => full path
     * @return Generator<string, ProfilingRun>
     */
    protected function loadProfiles(array $pathsAndFilenames): Generator
    {
        foreach ($pathsAndFilenames as $filename => $pathAndFilename) {
            $profile = $this->loadProfile($pathAndFilename);
            if ($profile === null) {
                continue;
            }
            yield (string) $filename => $profile;
        }
    }

    /**
     * @param iterable<string, ProfilingRun> $profiles
     * @throws Exception
     */
    private function streamExport(string $format, iterable $profiles, string $downloadName): StreamInterface
    {
        $exportFormat = $this->exportFormatRegistry->getFormat($format);
        $filename = self::sanitizeFilename($downloadName) . $exportFormat->getFilenameSuffix();
        $targetPathAndFilename = Files::concatenatePaths([
            $this->environment->getPathToTemporaryDirectory(),
            uniqid('plumber-export-', true) . $exportFormat->getFilenameSuffix(),
        ]);

        try {
            $exportFormat->export($profiles, $targetPathAndFilename);
            $handle = fopen($targetPathAndFilename, 'rb');
            if ($handle === false) {
                throw new Exception('The export could not be read back from ' . $targetPathAndFilename, 1756200031);
            }
        } finally {
            // The open handle keeps the data readable, so the file is gone from the temporary directory even if
            // the download is aborted halfway through.
            if (file_exists($targetPathAndFilename)) {
                unlink($targetPathAndFilename);
            }
        }

        $this->response->setContentType($exportFormat->getContentType());
        $this->response->setHttpHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return new Stream($handle);
    }

    private static function sanitizeFilename(string $name): string
    {
        return trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '-', $name), '-') ?: 'plumber-export';
    }
}

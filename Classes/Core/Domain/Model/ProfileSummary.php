<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core\Domain\Model;

use Neos\Flow\Annotations as Flow;

/**
 * Everything the overview shows about a profiling run, in a file small enough to read for all of them.
 *
 * A batch job writes one profile per worker restart - a long run measured at 1817 files and 17 GB - and the
 * overview needs nothing from them but their options, tags and the cached calculation results. Reading those from
 * a `<profile>.meta.json` sidecar keeps the overview's cost proportional to the number of profiles instead of
 * their size. {@see ProfilingRun::save()} writes the sidecar; a profile which predates it is read once and gets
 * one written.
 *
 * The calculation results live here rather than in the run, so that computing them writes a few kilobytes back
 * instead of rewriting a multi-megabyte profile.
 *
 * @Flow\Proxy(false)
 */
final class ProfileSummary
{
    private const FILENAME_SUFFIX = '.meta.json';

    private const FORMAT_VERSION = 1;

    /**
     * @param array<string, mixed> $options
     * @param list<string> $tags
     * @param array<string, array<string, mixed>> $calculations
     */
    public function __construct(
        private readonly string $pathAndFilename,
        private readonly array $options,
        private readonly array $tags,
        private readonly float $startTime,
        private readonly string $calculationHash,
        private readonly array $calculations,
    ) {
    }

    public static function fromProfilingRun(string $pathAndFilename, ProfilingRun $run): self
    {
        $calculationHash = (string)$run->getCalculationHash();
        $calculations = $calculationHash === '' ? null : $run->getCachedCalculationResults($calculationHash);

        if (!is_array($calculations) || $calculations === []) {
            // Only a run written by an older Plumber carries calculation results of its own; they live in the
            // sidecar now, and nothing puts them back into the run. Keeping what an existing sidecar already
            // holds is therefore what stops rewriting a profile - to change its tags, say - from throwing the
            // overview's computed results away and making it read the whole profile again.
            $existing = self::load($pathAndFilename);
            if ($existing !== null && $existing->calculations !== []) {
                $calculationHash = $existing->calculationHash;
                $calculations = $existing->calculations;
            }
        }

        return new self(
            $pathAndFilename,
            $run->getOptions(),
            array_values($run->getTags()),
            (float)$run->getStartTimeAsFloat(),
            $calculationHash,
            is_array($calculations) ? $calculations : [],
        );
    }

    /**
     * Read the sidecar of $pathAndFilename, or NULL when there is none - which is the signal to fall back to the
     * profile itself.
     */
    public static function load(string $pathAndFilename): ?self
    {
        $sidecar = $pathAndFilename . self::FILENAME_SUFFIX;
        if (!file_exists($sidecar)) {
            return null;
        }

        $data = json_decode((string)file_get_contents($sidecar), true);
        if (!is_array($data) || ($data['version'] ?? null) !== self::FORMAT_VERSION) {
            return null;
        }

        return new self(
            $pathAndFilename,
            is_array($data['options'] ?? null) ? $data['options'] : [],
            is_array($data['tags'] ?? null) ? array_values($data['tags']) : [],
            (float)($data['startTime'] ?? 0.0),
            (string)($data['calculationHash'] ?? ''),
            is_array($data['calculations'] ?? null) ? $data['calculations'] : [],
        );
    }

    public function save(): void
    {
        @file_put_contents(
            $this->pathAndFilename . self::FILENAME_SUFFIX,
            json_encode([
                'version' => self::FORMAT_VERSION,
                'startTime' => $this->startTime,
                'options' => $this->options,
                'tags' => $this->tags,
                'calculationHash' => $this->calculationHash,
                'calculations' => $this->calculations,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
        );
    }

    /**
     * Delete the profile this summary describes, with its XHProf sidecar and this summary.
     */
    public function remove(): void
    {
        self::removeProfile($this->pathAndFilename);
    }

    /**
     * The same, for a caller which only knows the path - deleting every profile has no reason to read any of
     * them first.
     */
    public static function removeProfile(string $pathAndFilename): void
    {
        foreach ([$pathAndFilename, $pathAndFilename . '.xhprof', $pathAndFilename . self::FILENAME_SUFFIX] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $calculations
     */
    public function withCalculations(string $calculationHash, array $calculations): self
    {
        return new self($this->pathAndFilename, $this->options, $this->tags, $this->startTime, $calculationHash, $calculations);
    }

    public function getFilename(): string
    {
        return basename($this->pathAndFilename);
    }

    public function getPathAndFilename(): string
    {
        return $this->pathAndFilename;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    /**
     * The results are only valid for the calculation configuration they were computed for, so a changed
     * configuration reads as "nothing cached yet".
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCalculations(string $calculationHash): array
    {
        return $this->calculationHash === $calculationHash ? $this->calculations : [];
    }
}

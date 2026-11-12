<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Export;

use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;

/**
 * Writes profiling runs into a file which a tool outside Plumber can read.
 *
 * Formats are registered at Sandstorm.Plumber.exports and instantiated by {@see ExportFormatRegistry}.
 *
 * Writing to a file rather than returning a string is what keeps the SQLite export straightforward, and it avoids
 * holding a large trace in memory twice.
 */
interface ExportFormatInterface
{
    public function getLabel(): string;

    /**
     * Appended to the download name, e.g. '.perfetto.json'.
     */
    public function getFilenameSuffix(): string;

    public function getContentType(): string;

    /**
     * The runs are iterable and not an array because the caller hands them over one at a time - the profiles of
     * one job do not fit into memory together. An implementation must therefore write each run out as it
     * arrives and must not collect them.
     *
     * @param iterable<string, ProfilingRun> $runs keyed by profile filename
     */
    public function export(iterable $runs, string $targetPathAndFilename): void;
}

<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Export;

use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;

/**
 * Writes the Chrome/Catapult JSON Trace Event Format, which https://ui.perfetto.dev ingests natively.
 *
 * Timestamps are absolute, not relative to the run, so that profiles written by several processes at the same
 * time - the render workers of one content release, for instance - line up on a single timeline.
 *
 * The XHProf trace is deliberately not exported: it is an aggregated caller-callee table without timestamps, so
 * there is no timeline to put it on. Plumber's own XHProf view stays the tool for that.
 */
final class PerfettoTraceExport implements ExportFormatInterface
{
    /**
     * This format has no options; the parameter exists because {@see ExportFormatRegistry} passes the configured
     * options to every format.
     *
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
    }

    public function getLabel(): string
    {
        return 'Perfetto trace';
    }

    public function getFilenameSuffix(): string
    {
        return '.perfetto.json';
    }

    public function getContentType(): string
    {
        return 'application/json';
    }

    public function export(array $runs, string $targetPathAndFilename): void
    {
        $traceEvents = [];
        $sortIndex = 0;
        foreach ($runs as $filename => $run) {
            $processId = self::processIdFor($filename);
            $traceEvents[] = self::metadataEvent($processId, 'process_name', 'name', self::processNameFor($filename, $run));
            $traceEvents[] = self::metadataEvent($processId, 'process_sort_index', 'sort_index', $sortIndex);
            $sortIndex++;

            foreach ($this->buildEventsForRun($processId, $run) as $event) {
                $traceEvents[] = $event;
            }
        }

        $json = json_encode(
            ['traceEvents' => $traceEvents, 'displayTimeUnit' => 'ms'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
        file_put_contents($targetPathAndFilename, $json);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildEventsForRun(int $processId, ProfilingRun $run): array
    {
        $runStartTime = (float)$run->getStartTimeAsFloat();
        $events = [];

        $timers = $run->getTimersAsDuration();
        usort($timers, static function (array $a, array $b): int {
            // parents first: same start means the longer slice encloses the shorter one
            return [$a['start'], -$a['time']] <=> [$b['start'], -$b['time']];
        });

        $lanes = [];
        foreach ($timers as $timer) {
            $laneIndex = self::assignLane($lanes, (float)$timer['start'], (float)$timer['stop']);
            $args = is_array($timer['data']) ? $timer['data'] : [];
            $args['dbQueryCount'] = $timer['dbQueryCount'] ?? 0;
            $events[] = [
                'ph' => 'X',
                'name' => $timer['name'],
                'cat' => 'timer',
                'pid' => $processId,
                'tid' => $laneIndex,
                'ts' => self::toMicroseconds($runStartTime + (float)$timer['start']),
                'dur' => max(0, self::toMicroseconds((float)$timer['time'])),
                'args' => $args,
            ];
        }

        foreach (array_keys($lanes) as $laneIndex) {
            $events[] = self::metadataEvent($processId, 'thread_name', 'name', 'lane ' . $laneIndex, $laneIndex);
        }

        foreach ($run->getTimestamps() as $timestamp) {
            $events[] = [
                'ph' => 'i',
                's' => 'p',
                'name' => $timestamp['name'],
                'cat' => 'timestamp',
                'pid' => $processId,
                'tid' => 0,
                'ts' => self::toMicroseconds($runStartTime + (float)$timestamp['time']),
                'args' => is_array($timestamp['data']) ? $timestamp['data'] : [],
            ];
        }

        foreach ($run->getMemory() as $memory) {
            $events[] = self::counterEvent($processId, 'Memory', 'bytes', $memory['mem'], $runStartTime + (float)$memory['time']);
        }

        foreach ($run->getDbQueryCount() as $dbQueryCount) {
            $events[] = self::counterEvent($processId, 'DB Queries', 'count', $dbQueryCount['dbQueryCount'], $runStartTime + (float)$dbQueryCount['time']);
        }

        return $events;
    }

    /**
     * Perfetto's JSON importer rejects slices which partially overlap on one track, and Plumber explicitly allows
     * several timers to be open at once without nesting. So every slice goes onto the lowest lane where it either
     * starts after everything else finished or nests completely inside the slice which is still open there.
     *
     * @param array<int, list<float>> $lanes end timestamps of the slices still open per lane, outermost first
     */
    private static function assignLane(array &$lanes, float $start, float $stop): int
    {
        $laneCount = count($lanes);
        for ($laneIndex = 0; $laneIndex < $laneCount; $laneIndex++) {
            while ($lanes[$laneIndex] !== [] && end($lanes[$laneIndex]) <= $start) {
                array_pop($lanes[$laneIndex]);
            }
            if ($lanes[$laneIndex] === [] || end($lanes[$laneIndex]) >= $stop) {
                $lanes[$laneIndex][] = $stop;
                return $laneIndex;
            }
        }

        $lanes[$laneCount] = [$stop];
        return $laneCount;
    }

    private static function toMicroseconds(float $seconds): int
    {
        return (int)round($seconds * 1000000);
    }

    /**
     * @return array<string, mixed>
     */
    private static function metadataEvent(int $processId, string $name, string $argName, mixed $argValue, int $threadId = 0): array
    {
        return [
            'ph' => 'M',
            'name' => $name,
            'pid' => $processId,
            'tid' => $threadId,
            'args' => [$argName => $argValue],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function counterEvent(int $processId, string $name, string $argName, mixed $argValue, float $absoluteTime): array
    {
        return [
            'ph' => 'C',
            'name' => $name,
            'pid' => $processId,
            'tid' => 0,
            'ts' => self::toMicroseconds($absoluteTime),
            'args' => [$argName => $argValue],
        ];
    }

    private static function processIdFor(string $filename): int
    {
        return (int)(crc32($filename) & 0x7fffffff);
    }

    private static function processNameFor(string $filename, ProfilingRun $run): string
    {
        $options = $run->getOptions();
        $parts = array_filter([
            $options['Controller Name'] ?? null,
            $options['Context'] ?? null,
            $filename,
        ], static fn($part): bool => is_string($part) && $part !== '');

        return implode(' | ', $parts);
    }
}

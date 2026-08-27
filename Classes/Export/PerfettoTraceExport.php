<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Export;

use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;

/**
 * Writes the Chrome/Catapult JSON Trace Event Format, which https://ui.perfetto.dev ingests natively.
 *
 * Timestamps are absolute, not relative to the run, so that profiles written by several processes at the same
 * time - the worker processes of one batch job, for instance - line up on a single timeline.
 *
 * The XHProf trace is deliberately not exported: it is an aggregated caller-callee table without timestamps, so
 * there is no timeline to put it on. Plumber's own XHProf view stays the tool for that.
 *
 * Options:
 *   withCounters - the memory and DB-query counter tracks, sampled at every timer event. They are the majority
 *                  of the events in a trace (79.000 of 99.000 in one measured example), so switch them off when
 *                  exporting many profiles at once.
 */
final class PerfettoTraceExport implements ExportFormatInterface
{
    /**
     * How many events are collected before they go to disk. Only bounds memory; any value works.
     */
    private const WRITE_CHUNK_SIZE = 2000;

    private readonly bool $withCounters;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->withCounters = (bool)($options['withCounters'] ?? true);
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

    public function export(iterable $runs, string $targetPathAndFilename): void
    {
        // Written event by event rather than json_encode()d in one go: the profiles of a whole job are far
        // larger than the memory limit, and the caller hands the runs over one at a time for the same reason.
        $handle = fopen($targetPathAndFilename, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not write the trace to ' . $targetPathAndFilename, 1756200040);
        }

        fwrite($handle, '{"displayTimeUnit":"ms","traceEvents":[');
        $buffer = [];
        $isFirstEvent = true;
        $sortIndex = 0;
        foreach ($runs as $filename => $run) {
            $processId = self::processIdFor((string)$filename);
            $events = [
                self::metadataEvent($processId, 'process_name', 'name', self::processNameFor((string)$filename, $run)),
                self::metadataEvent($processId, 'process_sort_index', 'sort_index', $sortIndex),
            ];
            $sortIndex++;

            foreach ($events as $event) {
                $buffer[] = ($isFirstEvent ? '' : ',') . self::encode($event);
                $isFirstEvent = false;
            }

            foreach ($this->buildEventsForRun($processId, $run) as $event) {
                $buffer[] = ($isFirstEvent ? '' : ',') . self::encode($event);
                $isFirstEvent = false;
                if (count($buffer) >= self::WRITE_CHUNK_SIZE) {
                    fwrite($handle, implode('', $buffer));
                    $buffer = [];
                }
            }

            fwrite($handle, implode('', $buffer));
            $buffer = [];
        }

        fwrite($handle, ']}');
        fclose($handle);
    }

    private static function encode(array $event): string
    {
        return (string)json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /**
     * @return \Generator<array<string, mixed>>
     */
    private function buildEventsForRun(int $processId, ProfilingRun $run): \Generator
    {
        $runStartTime = (float)$run->getStartTimeAsFloat();

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
            yield [
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
            yield self::metadataEvent($processId, 'thread_name', 'name', 'lane ' . $laneIndex, $laneIndex);
        }

        foreach ($run->getTimestamps() as $timestamp) {
            yield [
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

        if (!$this->withCounters) {
            return;
        }

        foreach ($run->getMemory() as $memory) {
            yield self::counterEvent($processId, 'Memory', 'bytes', $memory['mem'], $runStartTime + (float)$memory['time']);
        }

        foreach ($run->getDbQueryCount() as $dbQueryCount) {
            yield self::counterEvent($processId, 'DB Queries', 'count', $dbQueryCount['dbQueryCount'], $runStartTime + (float)$dbQueryCount['time']);
        }
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

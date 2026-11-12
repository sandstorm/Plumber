<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Export;

use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;

/**
 * Writes the profiling runs into a SQLite file, so that questions spanning many runs - "which group of items
 * costs the most time across this job" - become a GROUP BY instead of a click through the UI.
 *
 * Options:
 *   withXhprof - also write the aggregated caller-callee table. Off by default: a 40 MB profile is on the order
 *                of 100.000 rows, and the timers are what the timeline questions are about.
 */
final class SqliteExport implements ExportFormatInterface
{
    private const SCHEMA = [
        'CREATE TABLE runs (
            id INTEGER PRIMARY KEY,
            file TEXT NOT NULL,
            start_time REAL NOT NULL,
            context TEXT,
            controller TEXT,
            tags TEXT,
            duration_ms REAL,
            peak_memory_kb INTEGER,
            db_queries INTEGER
        )',
        'CREATE TABLE timers (
            id INTEGER PRIMARY KEY,
            run_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            parent TEXT,
            depth INTEGER NOT NULL,
            start_ms REAL NOT NULL,
            stop_ms REAL NOT NULL,
            duration_ms REAL NOT NULL,
            db_queries INTEGER,
            data_json TEXT
        )',
        'CREATE TABLE timestamps (
            id INTEGER PRIMARY KEY,
            run_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            time_ms REAL NOT NULL,
            data_json TEXT
        )',
        'CREATE TABLE xhprof_functions (
            run_id INTEGER NOT NULL,
            caller TEXT,
            callee TEXT NOT NULL,
            ct INTEGER,
            wt INTEGER,
            cpu INTEGER,
            mu INTEGER,
            pmu INTEGER
        )',
        'CREATE INDEX timers_run_id ON timers (run_id)',
        'CREATE INDEX timers_name ON timers (name)',
        'CREATE INDEX timers_duration_ms ON timers (duration_ms)',
        'CREATE INDEX timestamps_run_id ON timestamps (run_id)',
        'CREATE INDEX xhprof_functions_run_id ON xhprof_functions (run_id)',
    ];

    private readonly bool $withXhprof;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->withXhprof = (bool)($options['withXhprof'] ?? false);
    }

    public function getLabel(): string
    {
        return 'SQLite database';
    }

    public function getFilenameSuffix(): string
    {
        return '.sqlite';
    }

    public function getContentType(): string
    {
        return 'application/vnd.sqlite3';
    }

    public function export(iterable $runs, string $targetPathAndFilename): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new \RuntimeException(
                'The SQLite export needs the pdo_sqlite PHP extension, which is not loaded.',
                1756200020,
            );
        }

        if (file_exists($targetPathAndFilename)) {
            unlink($targetPathAndFilename);
        }

        $database = new \PDO('sqlite:' . $targetPathAndFilename);
        $database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        foreach (self::SCHEMA as $statement) {
            $database->exec($statement);
        }

        $insertRun = $database->prepare(
            'INSERT INTO runs (id, file, start_time, context, controller, tags, duration_ms, peak_memory_kb,'
            . ' db_queries) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insertTimer = $database->prepare(
            'INSERT INTO timers (run_id, name, parent, depth, start_ms, stop_ms, duration_ms, db_queries,'
            . ' data_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insertTimestamp = $database->prepare(
            'INSERT INTO timestamps (run_id, name, time_ms, data_json) VALUES (?, ?, ?, ?)',
        );
        $insertXhprof = $database->prepare(
            'INSERT INTO xhprof_functions (run_id, caller, callee, ct, wt, cpu, mu, pmu)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );

        $database->beginTransaction();
        $runId = 0;
        foreach ($runs as $filename => $run) {
            $runId++;
            $this->writeRun($insertRun, $runId, (string)$filename, $run);
            $this->writeTimers($insertTimer, $runId, $run);
            $this->writeTimestamps($insertTimestamp, $runId, $run);
            if ($this->withXhprof) {
                $this->writeXhprofFunctions($insertXhprof, $runId, $run);
            }
        }
        $database->commit();
    }

    private function writeRun(\PDOStatement $statement, int $runId, string $filename, ProfilingRun $run): void
    {
        $options = $run->getOptions();
        $statement->execute([
            $runId,
            $filename,
            $run->getStartTimeAsFloat(),
            is_string($options['Context'] ?? null) ? $options['Context'] : null,
            is_string($options['Controller Name'] ?? null) ? $options['Controller Name'] : null,
            json_encode($run->getTags()),
            self::totalDurationInMilliseconds($run),
            (int)round(self::maximumOf($run->getMemory(), 'mem') / 1024),
            (int)self::maximumOf($run->getDbQueryCount(), 'dbQueryCount'),
        ]);
    }

    private function writeTimers(\PDOStatement $statement, int $runId, ProfilingRun $run): void
    {
        $timers = $run->getTimersAsDuration();
        $parentOf = [];
        foreach ($timers as $timer) {
            $parentOf[$timer['name']] = $timer['parent'] ?? null;
        }

        foreach ($timers as $timer) {
            $statement->execute([
                $runId,
                $timer['name'],
                $timer['parent'] ?? null,
                self::depthOf($timer['name'], $parentOf),
                $timer['start'] * 1000,
                $timer['stop'] * 1000,
                $timer['time'] * 1000,
                $timer['dbQueryCount'] ?? 0,
                json_encode(is_array($timer['data']) ? $timer['data'] : []),
            ]);
        }
    }

    private function writeTimestamps(\PDOStatement $statement, int $runId, ProfilingRun $run): void
    {
        foreach ($run->getTimestamps() as $timestamp) {
            $statement->execute([
                $runId,
                $timestamp['name'],
                $timestamp['time'] * 1000,
                json_encode(is_array($timestamp['data']) ? $timestamp['data'] : []),
            ]);
        }
    }

    private function writeXhprofFunctions(\PDOStatement $statement, int $runId, ProfilingRun $run): void
    {
        foreach ($run->getXhprofTrace() as $key => $values) {
            $callerAndCallee = explode('==>', (string)$key);
            $callee = array_pop($callerAndCallee);
            $statement->execute([
                $runId,
                $callerAndCallee === [] ? null : implode('==>', $callerAndCallee),
                $callee,
                $values['ct'] ?? null,
                $values['wt'] ?? null,
                $values['cpu'] ?? null,
                $values['mu'] ?? null,
                $values['pmu'] ?? null,
            ]);
        }
    }

    /**
     * The nesting is only recorded as the name of the enclosing timer, so the depth has to be walked. Names repeat
     * across a run, which is why the walk is bounded rather than trusting the chain to end.
     *
     * @param array<string, string|null> $parentOf
     */
    private static function depthOf(string $name, array $parentOf): int
    {
        $depth = 0;
        $seen = [$name => true];
        $current = $parentOf[$name] ?? null;
        while (is_string($current) && !isset($seen[$current])) {
            $seen[$current] = true;
            $depth++;
            $current = $parentOf[$current] ?? null;
        }

        return $depth;
    }

    private static function totalDurationInMilliseconds(ProfilingRun $run): float
    {
        $total = 0.0;
        foreach ($run->getTimersAsDuration() as $timer) {
            if ($timer['name'] === 'Profiling Run') {
                $total += (float)$timer['time'] * 1000;
            }
        }

        return $total;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function maximumOf(array $rows, string $key): float
    {
        $maximum = 0.0;
        foreach ($rows as $row) {
            $maximum = max($maximum, (float)($row[$key] ?? 0));
        }

        return $maximum;
    }
}

<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Tests\Unit\Export;

use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;
use Sandstorm\Plumber\Export\SqliteExport;

/**
 * The export exists so that "which site costs the most rendering time in this content release" is a query rather
 * than a click through the UI, so that query is what is asserted.
 */
final class SqliteExportTest extends UnitTestCase
{
    private string $targetPathAndFilename;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The SQLite export needs the pdo_sqlite extension.');
        }
        $this->targetPathAndFilename = tempnam(sys_get_temp_dir(), 'plumber-sqlite-test-');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->targetPathAndFilename)) {
            unlink($this->targetPathAndFilename);
        }
        parent::tearDown();
    }

    public function testTimersOfSeveralProfilesCanBeGroupedByTheirParams(): void
    {
        $database = $this->export([
            'worker-1.profile' => $this->runWithDocuments(['louis' => 0.4, 'louis-cz' => 0.1]),
            'worker-2.profile' => $this->runWithDocuments(['louis' => 0.2]),
        ]);

        $rows = $database->query(
            "SELECT json_extract(data_json, '\$.site') AS site, count(*) AS docs, sum(duration_ms) AS ms"
            . " FROM timers WHERE name = 'Content Release: Render Document' GROUP BY 1 ORDER BY ms DESC",
        )->fetchAll(\PDO::FETCH_ASSOC);

        self::assertSame('louis', $rows[0]['site']);
        self::assertSame(2, (int)$rows[0]['docs']);
        self::assertEqualsWithDelta(600.0, (float)$rows[0]['ms'], 1.0);
        self::assertSame('louis-cz', $rows[1]['site']);
    }

    public function testOneRowPerProfileIsWrittenWithItsTags(): void
    {
        $run = $this->runWithDocuments(['louis' => 0.1]);
        $run->setTags(['contentRelease:1756123456']);
        $database = $this->export(['worker-1.profile' => $run]);

        $rows = $database->query('SELECT file, tags FROM runs')->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(1, $rows);
        self::assertSame('worker-1.profile', $rows[0]['file']);
        self::assertSame(['contentRelease:1756123456'], json_decode($rows[0]['tags'], true));
    }

    public function testTheXhprofTableStaysEmptyUnlessItWasAskedFor(): void
    {
        $database = $this->export(['worker-1.profile' => $this->runWithDocuments(['louis' => 0.1])]);

        self::assertSame(0, (int)$database->query('SELECT count(*) FROM xhprof_functions')->fetchColumn());
    }

    /**
     * @param array<string, float> $documents site => duration in seconds
     */
    private function runWithDocuments(array $documents): ProfilingRun
    {
        $run = new ProfilingRun();
        $run->start();
        $offset = $run->getStartTimeAsFloat();
        foreach ($documents as $site => $duration) {
            $run->manualTimer('Content Release: Render Document', ['site' => $site], $offset, $offset + $duration);
            $offset += $duration;
        }
        $run->stop();

        return $run;
    }

    /**
     * @param array<string, ProfilingRun> $runs
     */
    private function export(array $runs): \PDO
    {
        (new SqliteExport())->export($runs, $this->targetPathAndFilename);

        return new \PDO('sqlite:' . $this->targetPathAndFilename);
    }
}

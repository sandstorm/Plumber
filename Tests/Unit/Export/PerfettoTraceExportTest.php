<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Tests\Unit\Export;

use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;
use Sandstorm\Plumber\Export\PerfettoTraceExport;

/**
 * What matters about this export is that Perfetto accepts the file, and the two things it is strict about are
 * partially overlapping slices on one track and the microsecond timestamps.
 */
final class PerfettoTraceExportTest extends UnitTestCase
{
    private string $targetPathAndFilename;

    protected function setUp(): void
    {
        parent::setUp();
        $this->targetPathAndFilename = tempnam(sys_get_temp_dir(), 'plumber-perfetto-test-');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->targetPathAndFilename)) {
            unlink($this->targetPathAndFilename);
        }
        parent::tearDown();
    }

    public function testANestedTimerStaysOnTheSameLane(): void
    {
        $run = new ProfilingRun();
        $run->start();
        $startTime = $run->getStartTimeAsFloat();
        $run->manualTimer('outer', [], $startTime + 0.1, $startTime + 0.5);
        $run->manualTimer('inner', [], $startTime + 0.2, $startTime + 0.3);
        $run->stop();

        $slices = $this->slicesByName($run);
        self::assertSame(0, $slices['outer']['tid']);
        self::assertSame(0, $slices['inner']['tid']);
    }

    public function testAPartiallyOverlappingTimerGetsItsOwnLane(): void
    {
        $run = new ProfilingRun();
        $run->start();
        $startTime = $run->getStartTimeAsFloat();
        $run->manualTimer('outer', [], $startTime + 0.1, $startTime + 0.5);
        $run->manualTimer('overlapping', [], $startTime + 0.4, $startTime + 0.6);
        $run->stop();

        $slices = $this->slicesByName($run);
        self::assertSame(0, $slices['outer']['tid']);
        self::assertSame(1, $slices['overlapping']['tid']);
    }

    public function testTimestampsAreAbsoluteAndInMicroseconds(): void
    {
        $run = new ProfilingRun();
        $run->start();
        $startTime = $run->getStartTimeAsFloat();
        $run->manualTimer('outer', ['site' => 'louis'], $startTime + 0.25, $startTime + 0.75);
        $run->stop();

        $slices = $this->slicesByName($run);
        self::assertSame((int)round(($startTime + 0.25) * 1000000), $slices['outer']['ts']);
        self::assertSame(500000, $slices['outer']['dur']);
        self::assertSame('louis', $slices['outer']['args']['site']);
    }

    public function testEachProfileBecomesItsOwnNamedProcess(): void
    {
        $trace = $this->export(['first.profile' => $this->emptyRun(), 'second.profile' => $this->emptyRun()]);

        $processNames = [];
        foreach ($trace['traceEvents'] as $event) {
            if ($event['ph'] === 'M' && $event['name'] === 'process_name') {
                $processNames[$event['pid']] = $event['args']['name'];
            }
        }

        self::assertCount(2, $processNames);
        self::assertContains('first.profile', $processNames);
        self::assertContains('second.profile', $processNames);
    }

    private function emptyRun(): ProfilingRun
    {
        $run = new ProfilingRun();
        $run->start();
        $run->stop();

        return $run;
    }

    /**
     * @param array<string, ProfilingRun> $runs
     * @return array<string, mixed>
     */
    private function export(array $runs): array
    {
        (new PerfettoTraceExport())->export($runs, $this->targetPathAndFilename);

        return json_decode(file_get_contents($this->targetPathAndFilename), true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function slicesByName(ProfilingRun $run): array
    {
        $slices = [];
        foreach ($this->export(['test.profile' => $run])['traceEvents'] as $event) {
            if ($event['ph'] === 'X') {
                $slices[$event['name']] = $event;
            }
        }

        return $slices;
    }
}

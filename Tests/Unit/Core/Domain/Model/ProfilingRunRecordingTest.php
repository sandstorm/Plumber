<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Tests\Unit\Core\Domain\Model;

use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;
use Sandstorm\Plumber\Core\Profiler;

/**
 * What a run records and whether it is written at all - the two levers which decide how much disk a long batch
 * job costs.
 */
final class ProfilingRunRecordingTest extends UnitTestCase
{
    private string $profilePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profilePath = sys_get_temp_dir() . '/plumber-recording-test-' . bin2hex(random_bytes(6));
        mkdir($this->profilePath);
    }

    protected function tearDown(): void
    {
        $instance = new \ReflectionProperty(Profiler::class, 'instance');
        $instance->setAccessible(true);
        Profiler::getInstance()->stop();
        $instance->setValue(null, null);

        array_map('unlink', glob($this->profilePath . '/*') ?: []);
        rmdir($this->profilePath);
        parent::tearDown();
    }

    public function testARunWhichRequiresRelevanceIsNotWrittenUnlessItIsMarked(): void
    {
        $run = new ProfilingRun();
        $run->setRecordXhprof(false);
        $run->start();
        $run->discardUnlessMarkedRelevant();
        $run->stop();
        $run->save(['profilePath' => $this->profilePath]);

        self::assertSame([], glob($this->profilePath . '/*'));
    }

    public function testAMarkedRunIsWritten(): void
    {
        $run = new ProfilingRun();
        $run->setRecordXhprof(false);
        $run->start();
        $run->discardUnlessMarkedRelevant();
        $run->markAsRelevant();
        $run->stop();
        $run->save(['profilePath' => $this->profilePath]);

        self::assertCount(1, glob($this->profilePath . '/*.profile'));
    }

    public function testARunWithoutTheXhprofTraceWritesNoSidecar(): void
    {
        if (!function_exists('xhprof_enable') && !function_exists('tideways_xhprof_enable')) {
            self::markTestSkipped('No XHProf extension is loaded, so there would be no trace either way.');
        }

        $run = new ProfilingRun();
        $run->setRecordXhprof(false);
        $run->start();
        $run->stop();
        $run->save(['profilePath' => $this->profilePath]);

        self::assertCount(1, glob($this->profilePath . '/*.profile'));
        self::assertSame([], glob($this->profilePath . '/*.xhprof'));
    }

    public function testTheRecordSettingsReachARunStartedLater(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->setConfigurationProvider(fn(): array => ['profilePath' => $this->profilePath]);
        $profiler->applyRecordingSettings(['sqlQueries' => false, 'xhprof' => false]);

        self::assertFalse($profiler->isRecordingSqlQueryTimers());

        $profiler->startIfNotRunning();
        $profiler->stopAndSave();

        self::assertCount(1, glob($this->profilePath . '/*.profile'));
        self::assertSame([], glob($this->profilePath . '/*.xhprof'));
    }

    public function testUnknownRecordSettingsDoNotSwitchAnythingOff(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->applyRecordingSettings(['somethingElse' => 'yes']);

        self::assertTrue($profiler->isRecordingSqlQueryTimers());
    }
}

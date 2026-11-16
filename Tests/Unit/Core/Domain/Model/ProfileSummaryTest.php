<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Tests\Unit\Core\Domain\Model;

use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\Plumber\Core\Domain\Model\ProfileSummary;
use Sandstorm\Plumber\Core\Domain\Model\ProfilingRun;

/**
 * The sidecar exists so that the overview can list thousands of profiles without reading them, so what matters
 * here is that everything the overview shows survives the round trip and that writing a calculation result does
 * not touch the profile.
 */
final class ProfileSummaryTest extends UnitTestCase
{
    private string $profilePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profilePath = sys_get_temp_dir() . '/plumber-summary-test-' . bin2hex(random_bytes(6));
        mkdir($this->profilePath);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->profilePath . '/*') ?: []);
        rmdir($this->profilePath);
        parent::tearDown();
    }

    public function testSavingARunWritesASidecarNextToIt(): void
    {
        $run = $this->savedRun();

        self::assertCount(1, glob($this->profilePath . '/*.profile'));
        self::assertCount(1, glob($this->profilePath . '/*.meta.json'));
    }

    public function testTheSidecarCarriesWhatTheOverviewShows(): void
    {
        $run = $this->savedRun();
        $summary = ProfileSummary::load($this->profileFilename());

        self::assertInstanceOf(ProfileSummary::class, $summary);
        self::assertSame(['Renderer' => 'w7', 'Context' => 'Development/Docker'], $summary->getOptions());
        self::assertSame(['job:1787750713'], $summary->getTags());
        self::assertEqualsWithDelta($run->getStartTimeAsFloat(), $summary->getStartTime(), 0.0001);
    }

    public function testThereIsNoSidecarForAProfileWhichHasNone(): void
    {
        self::assertNull(ProfileSummary::load($this->profilePath . '/does-not-exist.profile'));
    }

    public function testCalculationResultsGoIntoTheSidecarInsteadOfTheProfile(): void
    {
        $this->savedRun();
        $profileFilename = $this->profileFilename();
        $profileSizeBefore = filesize($profileFilename);

        ProfileSummary::load($profileFilename)
            ->withCalculations('hash-1', ['totalRuntime' => ['value' => 42]])
            ->save();

        clearstatcache();
        self::assertSame($profileSizeBefore, filesize($profileFilename));
        self::assertSame(
            ['totalRuntime' => ['value' => 42]],
            ProfileSummary::load($profileFilename)->getCalculations('hash-1'),
        );
    }

    public function testCalculationResultsOfADifferentConfigurationAreIgnored(): void
    {
        $this->savedRun();
        ProfileSummary::load($this->profileFilename())
            ->withCalculations('hash-1', ['totalRuntime' => ['value' => 42]])
            ->save();

        self::assertSame([], ProfileSummary::load($this->profileFilename())->getCalculations('hash-2'));
    }

    public function testRemovingTakesTheProfileTheXhprofTraceAndTheSidecar(): void
    {
        $this->savedRun();
        file_put_contents($this->profileFilename() . '.xhprof', 'irrelevant');

        ProfileSummary::load($this->profileFilename())->remove();

        self::assertSame([], glob($this->profilePath . '/*'));
    }

    /**
     * A run written before Plumber wrote sidecars has to keep listing, so the overview builds one from it.
     */
    public function testASummaryCanBeBuiltFromAProfileWhichPredatesTheSidecar(): void
    {
        $this->savedRun();
        unlink($this->profileFilename() . '.meta.json');

        $run = unserialize((string)file_get_contents($this->profileFilename()));
        ProfileSummary::fromProfilingRun($this->profileFilename(), $run)->save();

        self::assertSame(['job:1787750713'], ProfileSummary::load($this->profileFilename())->getTags());
    }

    private function savedRun(): ProfilingRun
    {
        $run = new ProfilingRun();
        $run->setRecordXhprof(false);
        $run->start();
        $run->setOption('Renderer', 'w7');
        $run->setOption('Context', 'Development/Docker');
        $run->setTags(['job:1787750713']);
        $run->stop();
        $run->save(['profilePath' => $this->profilePath]);

        return $run;
    }

    private function profileFilename(): string
    {
        return (glob($this->profilePath . '/*.profile') ?: [''])[0];
    }
}

<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core\ContentRepository;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\Infrastructure\PerformanceTracing\PerformanceTracerInterface;
use Sandstorm\Plumber\Core\Profiler;

#[Flow\Proxy(false)]
class PlumberTracer implements PerformanceTracerInterface
{
    /**
     * @var CurrentSpanMeta[]
     */
    private array $openSpans = [];

    public function __construct(private readonly Profiler $profiler)
    {
    }

    public function openSpan(string $name, array $params = []): void
    {
        $this->profiler->getRun()->startTimer($name, $params);
        $this->openSpans[] = new CurrentSpanMeta($name);
    }

    public function closeSpan(): void
    {
        if (empty($this->openSpans)) {
            return;
        }

        $span = array_pop($this->openSpans);
        if ($span) {
            $this->profiler->getRun()->stopTimer($span->name);
        }
    }

    public function mark(string $name, array $params = []): void
    {
        $currentSpan = end($this->openSpans);
        if ($currentSpan === false) {
            // fallback if no span found so far
            $this->profiler->getRun()->timestamp($name, $params);
        } else {
            // add span since last mark() call (or beginning of span)
            $this->profiler->getRun()->manualTimer($name, $params, $currentSpan->advanceSpan(), microtime(TRUE));
        }
    }
}

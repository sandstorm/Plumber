<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core\ContentRepository;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\Infrastructure\Tracing\TracerInterface;
use Sandstorm\Plumber\Core\Profiler;

#[Flow\Proxy(false)]
class ContentRepositoryPlumberTracer implements TracerInterface
{
    /**
     * @var CurrentSpanMeta[]
     */
    private array $openSpans = [];

    public function __construct(private readonly Profiler $profiler)
    {
    }

    public function span(string $name, array $params, \Closure $fn)
    {
        $this->profiler->getRun()->startTimer($name, $params);
        $this->openSpans[] = new CurrentSpanMeta();
        try {
            return $fn();
        } finally {
            $this->profiler->getRun()->stopTimer($name);
            array_pop($this->openSpans);
        }
    }

    public function mark(string $name, ?array $params = null): void
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

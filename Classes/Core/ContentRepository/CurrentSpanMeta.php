<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core\ContentRepository;

use Neos\Flow\Annotations as Flow;

/**
 * @internal
 */
#[Flow\Proxy(false)]
final class CurrentSpanMeta
{

    private float $startTime;
    public function __construct(public readonly string $name)
    {
        $this->startTime = microtime(true);
    }

    /**
     * return the last span's start time, and advance the span (set start time to now)
     * @return float the last span's start time
     */
    public function advanceSpan(): float
    {
        $previousStartTime = $this->startTime;
        $this->startTime = microtime(true);
        return $previousStartTime;
    }
}

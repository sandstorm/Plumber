<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core\ContentRepository;

use Neos\ContentRepository\Core\Infrastructure\PerformanceTracing\PerformanceTracerInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\Factory\PerformanceTracer\PerformanceTracerFactoryInterface;
use Sandstorm\Plumber\Core\Profiler;

final readonly class PlumberTracerFactory implements PerformanceTracerFactoryInterface
{
    public function build(ContentRepositoryId $contentRepositoryId, array $options): PerformanceTracerInterface
    {
        $profiler = Profiler::getInstance();
        return new PlumberTracer($profiler);
    }
}

<?php

namespace Sandstorm\Plumber\Core\ContentRepository;

use Neos\ContentRepository\Core\Infrastructure\Tracing\TracerInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\Factory\Tracer\TracerFactoryInterface;
use Sandstorm\Plumber\Core\Profiler;

class ContentRepositoryPlumberTracerFactory implements TracerFactoryInterface
{
    public function __construct()
    {
    }

    public function build(ContentRepositoryId $contentRepositoryId, array $options): TracerInterface
    {
        $profiler = Profiler::getInstance();
        return new ContentRepositoryPlumberTracer($profiler);
    }
}

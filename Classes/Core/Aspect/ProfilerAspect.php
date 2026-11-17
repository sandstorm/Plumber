<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core\Aspect;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Phpprofiler". *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3          *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Sandstorm\Plumber\Core\Profiler;

#[Flow\Aspect]
final readonly class ProfilerAspect
{
    #[Flow\Around('methodAnnotatedWith(Sandstorm\Plumber\Core\Annotations\Profile)')]
    public function profileAround(JoinPointInterface $joinPoint): mixed
    {
        $run = Profiler::getInstance()->getRun();
        $tag = str_replace('\\', '_', $joinPoint->getClassName()) . ':' . $joinPoint->getMethodName();

        $run->startTimer($tag);
        $result = $joinPoint->getAdviceChain()->proceed($joinPoint);
        $run->stopTimer($tag);

        return $result;
    }
}

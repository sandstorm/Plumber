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

/**
 * Monitor how long the router::route method takes
 */
#[Flow\Scope('singleton')]
#[Flow\Aspect]
final readonly class RouterMonitoringAspect
{
    #[Flow\Around('method(Neos\Flow\Mvc\Routing\Router->route())')]
    public function profileRouteMethod(JoinPointInterface $joinPoint): mixed
    {
        Profiler::getInstance()->getRun()->startTimer('MVC: Build Request / Routing');
        $output = $joinPoint->getAdviceChain()->proceed($joinPoint);
        Profiler::getInstance()->getRun()->stopTimer('MVC: Build Request / Routing');
        return $output;
    }
}

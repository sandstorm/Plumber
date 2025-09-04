<?php
namespace Sandstorm\PhpProfiler\Aspect;

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

/**
 * Monitor how long the router::route method takes
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class RouterMonitoringAspect
{

    /**
     * Around advice
     *
     * @Flow\Around("method(Neos\Flow\Mvc\Routing\Router->route())")
     * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return array Result of the target method
     */
    public function profileRouteMethod(\Neos\Flow\Aop\JoinPointInterface $joinPoint)
    {
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('MVC: Build Request / Routing');
        $output = $joinPoint->getAdviceChain()->proceed($joinPoint);
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('MVC: Build Request / Routing');
        return $output;
    }

}

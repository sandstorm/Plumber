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
 * @Flow\Aspect
 */
class ProfilerAspect
{

    /**
     *
     * @Flow\Around("methodAnnotatedWith(Sandstorm\PhpProfiler\Annotations\Profile)")
     * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return array Result of the target method
     */
    public function profileAround(\Neos\Flow\Aop\JoinPointInterface $joinPoint)
    {
        $run = \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun();
        $tag = str_replace('\\', '_', $joinPoint->getClassName()) . ':' . $joinPoint->getMethodName();

        $run->startTimer($tag);
        $result = $joinPoint->getAdviceChain()->proceed($joinPoint);
        $run->stopTimer($tag);

        return $result;
    }
}

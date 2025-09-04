<?php
namespace Sandstorm\PhpProfiler\Aspect\Neos;

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
 * Monitor how long the node converter takes
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class NodeConverterMonitoringAspect
{

    /**
     * Around advice
     *
     * @Flow\Around("method(Neos\ContentRepository\TypeConverter\NodeConverter->convertFrom())")
     * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return array Result of the target method
     */
    public function profileConvertFromMethod(\Neos\Flow\Aop\JoinPointInterface $joinPoint)
    {
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('Property Mapping: Node Converter');
        $output = $joinPoint->getAdviceChain()->proceed($joinPoint);
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('Property Mapping: Node Converter');
        return $output;
    }

}

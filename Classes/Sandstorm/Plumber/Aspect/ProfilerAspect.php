<?php
namespace Sandstorm\Plumber\Aspect;

/*                                                                        *
 * This script belongs to the Flow framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * @Flow\Aspect
 */
class ProfilerAspect {
	/**
	 *
	 * @Flow\Around("methodAnnotatedWith(Sandstorm\Plumber\Annotations\Profile)")
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint The current join point
	 * @return array Result of the target method
	 */
	public function profileAround(\TYPO3\Flow\Aop\JoinPointInterface $joinPoint) {
		$run = \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun();
		$tag = $joinPoint->getClassName() . '::' . $joinPoint->getMethodName();

		$run->startTimer($tag);
		$result = $joinPoint->getAdviceChain()->proceed($joinPoint);
		$run->stopTimer($tag);

		return $result;
	}
}

?>
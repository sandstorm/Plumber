<?php
namespace Sandstorm\Plumber\Aspect;

/*                                                                        *
 * This script belongs to the FLOW3 package "Sandstorm.Plumber".          *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3          *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Http\Response;
use TYPO3\Flow\Error\Message;

/**
 * Monitor how long the router::route method takes
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class RouterMonitoringAspect {
	/**
	 * Around advice
	 *
	 * @Flow\Around("method(TYPO3\Flow\Mvc\Routing\Router->route())")
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint The current join point
	 * @return array Result of the target method
	 */
	public function cacheMatchingCall(\TYPO3\Flow\Aop\JoinPointInterface $joinPoint) {
		\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('MVC: Build Request / Routing');
		$output = $joinPoint->getAdviceChain()->proceed($joinPoint);
		\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('MVC: Build Request / Routing');
		return $output;
	}

}
?>
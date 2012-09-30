<?php
namespace SandstormMedia\Plumber\Aspect;

/*                                                                        *
 * This script belongs to the FLOW3 package "SandstormMedia.Plumber".     *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3          *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\FLOW3\Annotations as FLOW3;
use TYPO3\FLOW3\Http\Request;
use TYPO3\FLOW3\Http\Response;
use TYPO3\FLOW3\Error\Message;

/**
 * Monitor how long the router::route method takes
 *
 * @FLOW3\Scope("singleton")
 * @FLOW3\Aspect
 */
class RouterMonitoringAspect {
	/**
	 * Around advice
	 *
	 * @FLOW3\Around("method(TYPO3\FLOW3\Mvc\Routing\Router->route())")
	 * @param \TYPO3\FLOW3\Aop\JoinPointInterface $joinPoint The current join point
	 * @return array Result of the target method
	 */
	public function cacheMatchingCall(\TYPO3\FLOW3\Aop\JoinPointInterface $joinPoint) {
		\SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('MVC: Build Request / Routing');
		$output = $joinPoint->getAdviceChain()->proceed($joinPoint);
		\SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('MVC: Build Request / Routing');
		return $output;
	}

}
?>
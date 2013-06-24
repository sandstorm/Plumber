<?php
namespace Sandstorm\Plumber\Controller;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Plumber".     *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3          *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Standard controller for the Sandstorm.Plumber package
 *
 * @Flow\Scope("singleton")
 */
abstract class AbstractController extends \TYPO3\Flow\Mvc\Controller\ActionController {

	/**
	 * Initializes the controller before invoking an action method.
	 *
	 * @return void
	 */
	public function initializeAction() {
		\Sandstorm\PhpProfiler\Profiler::getInstance()->stop();
	}

	/**
	 * Returns a ProfilingRun instance that has been saved as $filename.
	 *
	 * @param string $filename
	 * @return \Sandstorm\PhpProfiler\Domain\Model\ProfilingRun
	 */
	protected function getProfile($filename) {
		$pathAndFilename = FLOW_PATH_DATA . 'Logs/Profiles/' . $filename;
		$profile = unserialize(file_get_contents($pathAndFilename));
		$profile->setPathAndFilename($pathAndFilename);
		return $profile;
	}

	/**
	 * Returns an array of ProfilingRun instances that have been saved earlier.
	 *
	 * @return array<\Sandstorm\PhpProfiler\Domain\Model\ProfilingRun>
	 */
	public function getProfiles() {
		$directoryIterator = new \DirectoryIterator(FLOW_PATH_DATA . 'Logs/Profiles');

		$profiles = array();
		foreach ($directoryIterator as $element) {
			if (preg_match('/\.profile$/', $element->getFilename())) {
				$profiles[$element->getFilename()] = unserialize(file_get_contents($element->getPathname()));
				$profiles[$element->getFilename()]->setPathAndFilename($element->getPathname());
			}

		}
		return $profiles;
	}
}
?>
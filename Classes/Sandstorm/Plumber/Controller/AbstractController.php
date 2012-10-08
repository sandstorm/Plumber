<?php
namespace Sandstorm\Plumber\Controller;

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

/**
 * Standard controller for the Sandstorm.Plumber package
 *
 * @Flow\Scope("singleton")
 */
abstract class AbstractController extends \TYPO3\Flow\Mvc\Controller\ActionController {

	public function initializeAction() {
		\Sandstorm\PhpProfiler\Profiler::getInstance()->stop();
	}

	protected function getProfile($file) {
		$file = FLOW_PATH_DATA . 'Logs/Profiles/' . $file;
		$profile = unserialize(file_get_contents($file));
		$profile->setFullPath($file);
		return $profile;
	}

	public function getProfiles() {
		$directoryIterator = new \DirectoryIterator(FLOW_PATH_DATA . 'Logs/Profiles');

		$profiles = array();
		foreach ($directoryIterator as $element) {
			if (preg_match('/\.profile$/', $element->getFilename())) {
				$profiles[$element->getFilename()] = unserialize(file_get_contents($element->getPathname()));
				$profiles[$element->getFilename()]->setFullPath($element->getPathname());
			}

		}
		return $profiles;
	}
}
?>
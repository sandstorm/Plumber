<?php
namespace SandstormMedia\Plumber\Controller;

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

/**
 * Standard controller for the SandstormMedia.Plumber package
 *
 * @FLOW3\Scope("singleton")
 */
abstract class AbstractController extends \TYPO3\FLOW3\Mvc\Controller\ActionController {

	public function initializeAction() {
		\SandstormMedia\PhpProfiler\Profiler::getInstance()->stop();
	}

	protected function getProfile($file) {
		$file = FLOW3_PATH_DATA . 'Logs/Profiles/' . $file;
		$profile = unserialize(file_get_contents($file));
		$profile->setFullPath($file);
		return $profile;
	}

	public function getProfiles() {
		$directoryIterator = new \DirectoryIterator(FLOW3_PATH_DATA . 'Logs/Profiles');

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
<?php
namespace Sandstorm\Plumber;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Plumber".     *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */


use TYPO3\Flow\Package\Package as BasePackage;
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Utility\Files;

/**
 * TYPO3 Flow package bootstrap
 */
class Package extends BasePackage {

	/**
	 * Sets up xhprof and some directories.
	 *
	 * @param Bootstrap $bootstrap
	 * @return void
	 */
	public function boot(Bootstrap $bootstrap) {
		define('XHPROF_ROOT', $this->getResourcesPath() . 'Private/PHP/xhprof-ui/');

		if (!file_exists(FLOW_PATH_DATA . 'Logs/Profiles')) {
			Files::createDirectoryRecursively(FLOW_PATH_DATA . 'Logs/Profiles');
		}
	}

}
?>
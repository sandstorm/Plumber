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
use TYPO3\Flow\Annotations as Flow;

/**
 * Standard controller for the SandstormMedia.Plumber package
 *
 * @Flow\Scope("singleton")
 */
class StandardController extends AbstractController {

	/**
	 * Index action
	 *
	 * @return void
	 */
	public function indexAction() {
		$this->redirect('index', 'Overview');
	}
}
?>
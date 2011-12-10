<?php
namespace SandstormMedia\Plumber\Controller;

/*                                                                        *
 * This script belongs to the FLOW3 package "SandstormMedia.Plumber".*
 *                                                                        *
 *                                                                        */

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * Standard controller for the SandstormMedia.Plumber package
 *
 * @FLOW3\Scope("singleton")
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
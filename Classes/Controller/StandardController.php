<?php
namespace SandstormMedia\PhpProfilerConnector\Controller;

/*                                                                        *
 * This script belongs to the FLOW3 package "SandstormMedia.PhpProfilerConnector".*
 *                                                                        *
 *                                                                        */

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * Standard controller for the SandstormMedia.PhpProfilerConnector package
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
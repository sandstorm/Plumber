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
class OverviewController extends AbstractController {

	/**
	 * Index action
	 *
	 * @return void
	 */
	public function indexAction() {
		$profiles = $this->getProfiles();

		$options = array();
		foreach ($profiles as $profile) {
			foreach ($profile->getOptions() as $optionName => $optionValue) {
				$options[$optionName] = $optionName;
			}
		}

		$this->view->assign('profiles', $profiles);
		$this->view->assign('options', $options);
	}

	public function removeAllAction() {
		$profiles = $this->getProfiles();

		foreach ($profiles as $profile) {
			$profile->remove();
		}
		$this->redirect('index');
	}

	public function removeAllUntaggedAction() {
		$profiles = $this->getProfiles();

		foreach ($profiles as $profile) {
			if (count($profile->getTags()) === 0) {
				$profile->remove();
			}
		}
		$this->redirect('index');
	}

	/**
	 * @param string $file
	 * @param string $value
	 */
	public function updateTagsAction($file, $value) {
		$profile = $this->getProfile($file);
		$tags = \TYPO3\FLOW3\Utility\Arrays::trimExplode(',', $value);
		$profile->setTags($tags);
		$profile->save();
		$this->view->assign('tags', $tags);
	}

	/**
	 *
	 * @param string $run
	 */
	public function removeAction($run) {
		$profile = $this->getProfile($run);
		$profile->remove();
		$this->redirect('index');
	}
}
?>
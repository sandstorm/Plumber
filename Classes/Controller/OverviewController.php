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
	 * @FLOW3\Inject
	 * @var \SandstormMedia\Plumber\Service\CalculationService
	 */
	protected $calculationService;

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

	public function crossfilterAction() {
		$profiles = $this->getProfiles();

		$profileData = array();
		$options = array();

		$calculations = $this->settings['calculations'];
		if (!isset($calculations['startTime'])) {
			$calculations['startTime'] = array(
				'label' => 'Start Time',
				'type' => 'startTime',
				'crossfilter' => array(
					'numberOfBars' => 20
				)
			);
		}

		$currentCalculationHash = sha1(serialize($calculations));

		$calculationMinMax = array();
		foreach ($calculations as $calculationName => $calculationOptions) {
			$calculationMinMax[$calculationName] = array('min' => INF, 'max' => -INF);
		}



		foreach ($profiles as $profileId => $profile) {
			foreach ($profile->getOptions() as $optionName => $optionValue) {
				$options[$optionName] = $optionName;
			}
			$currentProfileData = $profile->getOptions();
			$currentProfileData['id'] = $profileId;

			$cachedCalculationResults = $profile->getCachedCalculationResults($currentCalculationHash);

			$shouldUpdateCalculationCache = FALSE;
			foreach ($calculations as $calculationName => $calculationOptions) {
				if (isset($cachedCalculationResults[$calculationName])) {
					$calculationResult = $cachedCalculationResults[$calculationName];
				} else {
					$calculationResult = $this->calculationService->calculate($profile, $calculationOptions);
					$cachedCalculationResults[$calculationName] = $calculationResult;
					$shouldUpdateCalculationCache = TRUE;
				}

				$currentProfileData[$calculationName] = $calculationResult;
				if ($calculationResult < $calculationMinMax[$calculationName]['min']) {
					$calculationMinMax[$calculationName]['min'] = $calculationResult;
				}

				if ($calculationResult > $calculationMinMax[$calculationName]['max']) {
					$calculationMinMax[$calculationName]['max'] = $calculationResult;
				}

			}
			$profileData[] = $currentProfileData;

			if ($shouldUpdateCalculationCache) {
				$profile->setCachedCalculationResults($currentCalculationHash, $cachedCalculationResults);
				$profile->save();
			}
		}

		foreach ($calculations as $calculationName => &$calculationOptions) {
			if (!isset($calculationOptions['crossfilter']['min'])) {
				$calculationOptions['crossfilter']['min'] = $calculationMinMax[$calculationName]['min'];
			}

			if (!isset($calculationOptions['crossfilter']['max'])) {
				$calculationOptions['crossfilter']['max'] = $calculationMinMax[$calculationName]['max'];
			}
		}

		$this->view->assign('profileData', json_encode($profileData));
		$this->view->assign('calculationsJson', json_encode($calculations));
		$this->view->assign('calculations', $calculations);
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
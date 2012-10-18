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
class OverviewController extends AbstractController {

	/**
	 * @Flow\Inject
	 * @var \Sandstorm\Plumber\Service\CalculationService
	 */
	protected $calculationService;

	/**
	 * @Flow\Inject
	 * @var \Sandstorm\Plumber\Service\RenderTagsService
	 */
	protected $renderTagsService;

	public function indexAction() {
		$profiles = $this->getProfiles();

		$profileData = array();
		$options = array();

		$calculations = $this->settings['calculations'];

		$currentCalculationHash = sha1(serialize($calculations));

		$calculationMinMax = array();
		foreach ($calculations as $calculationName => $calculationOptions) {
			$calculationMinMax[$calculationName] = array('min' => INF, 'max' => - INF);
		}

		foreach ($profiles as $profileId => $profile) {
			$currentProfileData = array();
			$currentProfileData['id'] = $profileId;
			$currentProfileData['tagsAsHtml'] = $this->renderTagsService->render($profile->getTags());
			foreach ($profile->getOptions() as $optionName => $optionValue) {
				if (!is_string($optionValue)) {
					continue;
				}
				if (!isset($options[$optionName])) {
					$options[$optionName] = array();
				}
				$options[$optionName][$optionValue] = $optionValue;
				$currentProfileData[$optionName] = $optionValue;
			}

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
				if ($calculationResult['value'] < $calculationMinMax[$calculationName]['min']) {
					$calculationMinMax[$calculationName]['min'] = $calculationResult['value'];
				}

				if ($calculationResult['value'] > $calculationMinMax[$calculationName]['max']) {
					$calculationMinMax[$calculationName]['max'] = $calculationResult['value'];
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
		$this->view->assign('options', $options);
		$this->view->assign('optionsJson', json_encode($options));
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
		$tags = \TYPO3\Flow\Utility\Arrays::trimExplode(',', $value);
		$profile->setTags($tags);
		$profile->save();
		return $this->renderTagsService->render($tags);
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
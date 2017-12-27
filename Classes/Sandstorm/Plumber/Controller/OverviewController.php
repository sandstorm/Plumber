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

use Neos\Flow\Annotations as Flow;

/**
 * Standard controller for the Sandstorm.Plumber package
 *
 * @Flow\Scope("singleton")
 */
class OverviewController extends AbstractController
{

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

    /**
     * Show an overview of all existing profiles.
     *
     * @return void
     */
    public function indexAction()
    {
        $profiles = $this->getProfiles();

        $profileData = array();
        $options = array();

        $calculations = $this->settings['calculations'];

        $currentCalculationHash = sha1(serialize($calculations));

        $calculationMinMax = array();
        foreach ($calculations as $calculationName => $calculationOptions) {
            $calculationMinMax[$calculationName] = array('min' => PHP_INT_MAX, 'max' => -PHP_INT_MAX);
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

    /**
     * Updates the profile given in $profileFilename with the tags given in
     * $tagList (comma-separated tags) and return the tags rendered as HTML.
     *
     * @param string $profileFilename
     * @param string $tagList
     * @return string
     */
    public function updateTagsAction($profileFilename, $tagList)
    {
        $profile = $this->getProfile($profileFilename);
        $tags = \Neos\Utility\Arrays::trimExplode(',', $tagList);
        $profile->setTags($tags);
        $profile->save();
        return $this->renderTagsService->render($tags);
    }

    /**
     * Removes all profiles.
     *
     * @return void
     */
    public function removeAllAction()
    {
        $profiles = $this->getProfiles();

        foreach ($profiles as $profile) {
            $profile->remove();
        }
        $this->redirect('index');
    }

    /**
     * Removes all untagged profiles.
     *
     * @return void
     */
    public function removeAllUntaggedAction()
    {
        $profiles = $this->getProfiles();

        foreach ($profiles as $profile) {
            if (count($profile->getTags()) === 0) {
                $profile->remove();
            }
        }
        $this->redirect('index');
    }

    /**
     * Removes the given profile.
     *
     * @param string $profileFilename
     * @return void
     */
    public function removeAction($profileFilename)
    {
        $profile = $this->getProfile($profileFilename);
        $profile->remove();
        $this->redirect('index');
    }
}

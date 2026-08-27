<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Sandstorm\Plumber\Core\Domain\Model\ProfileSummary;
use Sandstorm\Plumber\Export\ExportFormatRegistry;
use Sandstorm\Plumber\Service\CalculationService;
use Sandstorm\Plumber\Service\RenderTagsService;

/**
 * Overview controller for the Sandstorm.Plumber package
 */
#[Flow\Scope("singleton")]
class OverviewController extends AbstractController
{
    #[Flow\Inject]
    protected CalculationService $calculationService;

    #[Flow\Inject]
    protected RenderTagsService $renderTagsService;

    #[Flow\Inject]
    protected ExportFormatRegistry $exportFormatRegistry;

    /**
     * Show an overview of all existing profiles.
     */
    public function indexAction(): void
    {
        $profileData = [];
        $options = [];

        $calculations = $this->settings['calculations'];

        $currentCalculationHash = sha1(serialize($calculations));

        $calculationMinMax = [];
        foreach ($calculations as $calculationName => $calculationOptions) {
            $calculationMinMax[$calculationName] = ['min' => PHP_INT_MAX, 'max' => -PHP_INT_MAX];
        }

        // Summaries, not profiles: everything below reads metadata only, and a long batch job leaves thousands
        // of profiles of ~10 MB behind. A profile is read - once, and released again straight away - only when a
        // calculation is missing for it, and the result then goes back into the sidecar instead of rewriting the
        // profile.
        foreach ($this->getProfileSummaries() as $profileId => $summary) {
            $currentProfileData = [];
            $currentProfileData['id'] = $profileId;
            $currentProfileData['tagsAsHtml'] = $this->renderTagsService->render($summary->getTags());
            foreach ($summary->getOptions() as $optionName => $optionValue) {
                if (!is_string($optionValue)) {
                    continue;
                }
                if (!isset($options[$optionName])) {
                    $options[$optionName] = [];
                }
                $options[$optionName][$optionValue] = $optionValue;
                $currentProfileData[$optionName] = $optionValue;
            }

            $cachedCalculationResults = $summary->getCalculations($currentCalculationHash);

            $missingCalculations = array_diff_key($calculations, $cachedCalculationResults);
            if ($missingCalculations !== []) {
                $profile = $this->loadProfile($summary->getPathAndFilename());
                if ($profile === null) {
                    continue;
                }
                foreach ($missingCalculations as $calculationName => $calculationOptions) {
                    $cachedCalculationResults[$calculationName] =
                        $this->calculationService->calculate($profile, $calculationOptions);
                }
                unset($profile);
                $summary->withCalculations($currentCalculationHash, $cachedCalculationResults)->save();
            }

            foreach ($calculations as $calculationName => $calculationOptions) {
                $calculationResult = $cachedCalculationResults[$calculationName];

                $currentProfileData[$calculationName] = $calculationResult;
                if ($calculationResult['value'] < $calculationMinMax[$calculationName]['min']) {
                    $calculationMinMax[$calculationName]['min'] = $calculationResult['value'];
                }

                if ($calculationResult['value'] > $calculationMinMax[$calculationName]['max']) {
                    $calculationMinMax[$calculationName]['max'] = $calculationResult['value'];
                }
            }
            $profileData[] = $currentProfileData;
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
        $exportFormats = $this->exportFormatRegistry->getLabels();
        $this->view->assign('exportFormats', $exportFormats);
        $this->view->assign('exportFormatsJson', json_encode($exportFormats));
    }

    /**
     * Updates the profile given in $profileFilename with the tags given in
     * $tagList (comma-separated tags) and return the tags rendered as HTML.
     */
    public function updateTagsAction(string $profileFilename, string $tagList): string
    {
        $profile = $this->getProfile($profileFilename);
        $tags = Arrays::trimExplode(',', $tagList);
        $profile->setTags($tags);
        $profile->save();
        return $this->renderTagsService->render($tags);
    }

    /**
     * Removes all profiles.
     */
    public function removeAllAction(): void
    {
        // By path, not by summary: deleting everything has no reason to read anything first.
        foreach ($this->getProfilePathsAndFilenames() as $pathAndFilename) {
            ProfileSummary::removeProfile($pathAndFilename);
        }
        $this->redirect('index');
    }

    /**
     * Removes all untagged profiles.
     */
    public function removeAllUntaggedAction(): void
    {
        foreach ($this->getProfileSummaries() as $summary) {
            if (count($summary->getTags()) === 0) {
                $summary->remove();
            }
        }
        $this->redirect('index');
    }

    /**
     * Removes the given profile.
     */
    public function removeAction(string $profileFilename): void
    {
        $profile = $this->getProfile($profileFilename);
        $profile->remove();
        $this->redirect('index');
    }
}

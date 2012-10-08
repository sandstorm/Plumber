<?php
namespace Sandstorm\Plumber\ViewHelpers;

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
 */
class CalculateViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * @Flow\Inject
	 * @var \Sandstorm\Plumber\Service\CalculationService
	 */
	protected $calculationService;

	/**
	 *
	 * @param \Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 * @return type
	 */
	public function render(\Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		return $this->calculationService->calculate($profile, $calculationOptions, TRUE);
	}
}
?>
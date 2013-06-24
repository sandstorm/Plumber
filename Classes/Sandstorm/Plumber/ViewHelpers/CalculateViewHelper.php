<?php
namespace Sandstorm\Plumber\ViewHelpers;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Plumber".     *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3          *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Run calculations for the given profile.
 */
class CalculateViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * @Flow\Inject
	 * @var \Sandstorm\Plumber\Service\CalculationService
	 */
	protected $calculationService;

	/**
	 * Run calculations for the given profile.
	 *
	 * @param \Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 * @return array
	 */
	public function render(\Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		return $this->calculationService->calculate($profile, $calculationOptions);
	}
}
?>
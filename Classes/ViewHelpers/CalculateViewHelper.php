<?php
namespace SandstormMedia\Plumber\ViewHelpers;

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 */
class CalculateViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * @FLOW3\Inject
	 * @var \SandstormMedia\Plumber\Service\CalculationService
	 */
	protected $calculationService;

	/**
	 *
	 * @param \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 * @return type
	 */
	public function render(\SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		return $this->calculationService->calculate($profile, $calculationOptions, TRUE);
	}
}
?>
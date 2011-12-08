<?php
namespace SandstormMedia\PhpProfilerConnector\ViewHelpers;

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 */
class CalculateViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 *
	 * @param \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 * @return type
	 */
	public function render(\SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		$type = 'calculate' . ucfirst($calculationOptions['type']);
		return $this->$type($profile, $calculationOptions);
	}

	/**
	 *
	 * @param \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 */
	protected function calculateRegexSum(\SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		if (!isset($calculationOptions['regex'])) throw new \Exception('TODO: Regex not set');
		$result = 0;
		foreach ($profile->getXhprofTrace() as $id => $data) {
			if (preg_match($calculationOptions['regex'], $id)) {
				$result += $data['ct'];
			}
		}
		return $result;
	}
}
?>
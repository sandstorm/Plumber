<?php
namespace SandstormMedia\Plumber\Service;

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * @FLOW3\Scope("singleton")
 */
class CalculationService {

	/**
	 *
	 * @param \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 * @return type
	 */
	public function calculate(\SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions, $asHtml = FALSE) {
		$type = 'calculate' . ucfirst($calculationOptions['type']);
		return $this->$type($profile, $calculationOptions, $asHtml);
	}

	/**
	 * @param \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 * @param type $asHtml
	 */
	protected function calculateStartTime(\SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions, $asHtml) {
		return $profile->getStartTimeAsFloat();
	}

	/**
	 *
	 * @param \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 */
	protected function calculateRegexSum(\SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions, $asHtml) {
		if (!isset($calculationOptions['regex'])) throw new \Exception('TODO: Regex not set');
		$result = 0;

		$detailedResult = array();
		foreach ($profile->getXhprofTrace() as $id => $data) {
			$matches = NULL;
			if (preg_match($calculationOptions['regex'], $id, $matches)) {
				$result += $data['ct'];

				if (isset($matches[1])) {
					$className = $matches[1];
					if (!isset($detailedResult[$className])) {
						$detailedResult[$className] = 0;
					}
					$detailedResult[$className] += $data['ct'];
				}
			}
		}
		if (!$asHtml) {
			return $result;
		}
		arsort($detailedResult);

		$detailedResultHtml = '<table class="condensed-table" style="font-size:60%">';
		$i = 0;
		foreach ($detailedResult as $className => $count) {
			if ($i > 10) break;
			$i++;

			$detailedResultHtml .= sprintf('<tr><td>%s</td><td>%s</td></tr>', $className, $count);
		}
		$detailedResultHtml .= '</table>';

		$aTag = new \TYPO3\Fluid\Core\ViewHelper\TagBuilder('a');
		$aTag->addAttribute('rel', 'popover');
		$aTag->addAttribute('title', 'Top 10');
		$aTag->addAttribute('data-content', $detailedResultHtml);
		$aTag->setContent($result);
		return $aTag->render();
	}

	/**
	 * @param \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 */
	protected function calculateTimerSum(\SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		if (!isset($calculationOptions['timerName'])) throw new \Exception('TODO: timerName not set');

		$sum = 0;

		foreach ($profile->getTimersAsDuration() as $duration) {
			if ($duration['name'] === $calculationOptions['timerName']) {
				$sum += $duration['stop']*1000 - $duration['start']*1000;
			}
		}
		return round($sum);
	}

	/**
	 * @param \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 */
	protected function calculateMaxMemory(\SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		$memory = $profile->getMemory();
		$lastSamplingPoint = array_pop($memory);
		return round($lastSamplingPoint['mem'] / 1024);
	}
}
?>
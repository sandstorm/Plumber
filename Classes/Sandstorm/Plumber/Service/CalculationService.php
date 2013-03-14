<?php
namespace Sandstorm\Plumber\Service;

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
 * @Flow\Scope("singleton")
 */
class CalculationService {

	/**
	 *
	 * Calculate a value in $profile; returning an array with the following data:
	 *
	 * - value (required): Numerical value which is the main aggregation result
	 * - tableCellHtml (optional, string): if given, is used in the results table
	 *   for display. Helpful f.e. for more verbose output
	 *
	 * @param \Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 * @return array
	 */
	public function calculate(\Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		$type = 'calculate' . ucfirst($calculationOptions['type']);
		return $this->$type($profile, $calculationOptions);
	}

	/**
	 * @param \Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 * @param type $asHtml
	 */
	protected function calculateStartTime(\Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		return array(
			'value' => $profile->getStartTimeAsFloat()
		);
	}

	/**
	 *
	 * @param \Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 */
	protected function calculateRegexSum(\Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		if (!isset($calculationOptions['regex'])) {
			throw new \Exception('TODO: Regex not set');
		}

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

		arsort($detailedResult);

		$detailedResultHtml = '<table class="condensed-table" style="font-size:60%">';
		$i = 0;
		foreach ($detailedResult as $className => $count) {
			if ($i > 10) {
				break;
			}

			$i++;

			$detailedResultHtml .= sprintf('<tr><td>%s</td><td>%s</td></tr>', $className, $count);
		}
		$detailedResultHtml .= '</table>';

		$aTag = new \TYPO3\Fluid\Core\ViewHelper\TagBuilder('a');
		$aTag->addAttribute('rel', 'popover');
		$aTag->addAttribute('title', 'Top 10');
		$aTag->addAttribute('data-content', $detailedResultHtml);
		$aTag->setContent($result);

		return array('value' => $result, 'tableCellHtml' => $aTag->render());
	}

	/**
	 *
	 * @param \Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 */
	protected function calculateRegex(\Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		if (!isset($calculationOptions['regex'])) {
			throw new \Exception('TODO: Regex not set');
		}

		$metrics = array(
			'time' => 'wt',
			'calls' => 'ct',
			'memory' => 'mu'
		);
		$metric = isset($calculationOptions['metric']) ? $metrics[$calculationOptions['metric']] : 'ct';

		$results = array();
		$detailedResult = array();
		foreach ($profile->getXhprofTrace() as $id => $data) {
			$matches = NULL;
			if (preg_match($calculationOptions['regex'], $id, $matches)) {
				$results[] = $data[$metric];

				if (isset($matches[1])) {
					$className = $matches[1];
					if (!isset($detailedResult[$className])) {
						$detailedResult[$className] = array();
					}
					$detailedResult[$className][] = $data[$metric];
				}
			}
		}

		$subtype = isset($calculationOptions['subtype']) ? $calculationOptions['subtype'] : 'sum';
		$result = $this->calculateSubtype($results, $subtype);

		arsort($detailedResult);

		$detailedResultHtml = '<table class="condensed-table" style="font-size:60%">';
		$i = 0;
		foreach ($detailedResult as $className => $counts) {
			$result = $this->calculateSubtype($counts, $subtype);
			if ($i > 10) {
				break;
			}

			$i++;

			$detailedResultHtml .= sprintf('<tr><td>%s</td><td>%s</td></tr>', $className, $result);
		}
		$detailedResultHtml .= '</table>';

		$aTag = new \TYPO3\Fluid\Core\ViewHelper\TagBuilder('a');
		$aTag->addAttribute('rel', 'popover');
		$aTag->addAttribute('title', 'Top 10');
		$aTag->addAttribute('data-content', $detailedResultHtml);
		$aTag->setContent($result);

		return array('value' => $result, 'tableCellHtml' => $aTag->render());
	}

	/**
	 * @param  array $data
	 * @param  string $subtype
	 * @return integer
	 */
	public function calculateSubtype($data, $subtype) {
		$result = 0;
		switch ($subtype) {
			case 'average':
				if (count($data) > 0) {
					$result = array_sum($data) / count($data);
				}
				break;

			case 'sum':
				$result = array_sum($data);
				break;

			default:
				break;
		}
		return $result;
	}

	/**
	 * @param \Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 */
	protected function calculateTimerSum(\Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		if (!isset($calculationOptions['timerName'])) {
			throw new \Exception('TODO: timerName not set');
		}

		$sum = 0;

		foreach ($profile->getTimersAsDuration() as $duration) {
			if ($duration['name'] === $calculationOptions['timerName']) {
				$sum += $duration['stop'] * 1000 - $duration['start'] * 1000;
			}
		}
		return array('value' => round($sum));
	}

	/**
	 * @param \Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile
	 * @param array $calculationOptions
	 */
	protected function calculateMaxMemory(\Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile, array $calculationOptions) {
		$memory = $profile->getMemory();
		$lastSamplingPoint = array_pop($memory);
		return array('value' => round($lastSamplingPoint['mem'] / 1024));
	}
}
?>
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
 * Uses the given $path to fetch a property on the subject (children).
 */
class ObjectAccessViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * Uses the given $path to fetch a property on the subject (children).
	 *
	 * @param string $path
	 * @return mixed
	 */
	public function render($path) {
		return \TYPO3\Flow\Reflection\ObjectAccess::getPropertyPath($this->renderChildren(), $path);
	}
}
?>
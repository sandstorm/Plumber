<?php
namespace SandstormMedia\PhpProfilerConnector\ViewHelpers;

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 */
class ObjectAccessViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * @param string $path
	 */
	public function render($path) {
		return \TYPO3\FLOW3\Reflection\ObjectAccess::getPropertyPath($this->renderChildren(), $path);
	}
}
?>
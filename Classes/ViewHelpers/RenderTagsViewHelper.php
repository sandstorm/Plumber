<?php
namespace SandstormMedia\PhpProfilerConnector\ViewHelpers;

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 */
class RenderTagsViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * @param array $tags
	 */
	public function render($tags) {
		$output = '';
		foreach ($tags as $tag) {
			$color = substr(md5(strtolower($tag)), 0, 6);
			$output .= sprintf('<span class="label" style="background-color:#%s">%s</span>', $color, $tag);
		}
		return $output;
	}
}
?>
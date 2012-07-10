<?php
namespace SandstormMedia\Plumber\Service;

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * @FLOW3\Scope("singleton")
 */
class RenderTagsService {

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
<?php
namespace SandstormMedia\Plumber\Service;

/*                                                                        *
 * This script belongs to the FLOW3 package "SandstormMedia.Plumber".     *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3          *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

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
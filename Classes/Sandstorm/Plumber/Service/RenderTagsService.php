<?php
namespace Sandstorm\Plumber\Service;

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
 * @Flow\Scope("singleton")
 */
class RenderTagsService {

	/**
	 * Renders the given tags as HTML output.
	 *
	 * @param array $tags
	 * @return string
	 */
	public function render(array $tags) {
		$output = '';
		foreach ($tags as $tag) {
			$color = substr(md5(strtolower($tag)), 0, 6);
			$output .= sprintf('<span class="label" style="background-color:#%s">%s</span>', $color, $tag);
		}
		return $output;
	}

}
?>
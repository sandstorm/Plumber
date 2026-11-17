<?php

declare(strict_types=1);

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

use Neos\Flow\Annotations as Flow;

#[Flow\Scope('singleton')]
final readonly class RenderTagsService
{
    /**
     * Renders the given tags as HTML output.
     *
     * @param array<string> $tags
     */
    public function render(array $tags): string
    {
        $output = '';
        foreach ($tags as $tag) {
            $color = substr(md5(strtolower($tag)), 0, 6);
            $output .= sprintf('<span class="label" style="background-color:#%s">%s</span>', $color, $tag);
        }
        return $output;
    }
}

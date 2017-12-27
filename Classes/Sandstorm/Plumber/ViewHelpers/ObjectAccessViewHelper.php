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

use Neos\Flow\Annotations as Flow;

/**
 * Uses the given $path to fetch a property on the subject (children).
 */
class ObjectAccessViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper
{


    /**
     * NOTE: This property has been introduced via code migration to ensure backwards-compatibility.
     * @see AbstractViewHelper::isOutputEscapingEnabled()
     * @var boolean
     */
    protected $escapeOutput = FALSE;

    /**
     * Uses the given $path to fetch a property on the subject (children).
     *
     * @param string $path
     * @return mixed
     */
    public function render($path)
    {
        return \Neos\Utility\ObjectAccess::getPropertyPath($this->renderChildren(), $path);
    }
}

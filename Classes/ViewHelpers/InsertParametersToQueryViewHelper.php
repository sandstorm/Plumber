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
 * Run calculations for the given profile.
 */
class InsertParametersToQueryViewHelper extends \Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper
{


    /**
     * NOTE: This property has been introduced via code migration to ensure backwards-compatibility.
     * @see AbstractViewHelper::isOutputEscapingEnabled()
     * @var boolean
     */
    protected $escapeOutput = FALSE;

    /**
     * @Flow\Inject
     * @var \Sandstorm\Plumber\Service\CalculationService
     */
    protected $calculationService;


    public function initializeArguments()
    {
        $this->registerArgument('sqlQuery', 'string', '', true);
        $this->registerArgument('params', 'array', '', false, null);
    }

    public function render()
    {
        $sqlQuery = $this->arguments['sqlQuery'];
        $params = $this->arguments['params'];
        if (!isset($params)) {
            return $sqlQuery;
        }
        foreach ($params as $key => $value) {
            // we love SQL injections ^^
            if (is_array($value) && count($value) > 0) {
                // support for multi-valued string properties; used in "IN" queries
                $value = implode("', '", array_map('strval', $value));
            }
            if (is_string($value)) {
                $sqlQuery = str_replace(':' . $key, "'" . $value . "'", $sqlQuery);
            } elseif (is_int($value)) {
                $sqlQuery = str_replace(':' . $key, $value, $sqlQuery);
            }
        }
        return $sqlQuery;
    }
}

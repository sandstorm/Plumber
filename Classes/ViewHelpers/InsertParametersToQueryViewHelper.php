<?php

declare(strict_types=1);

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

use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;

/**
 * Inserts the bound parameters into an SQL statement, for display only.
 */
final class InsertParametersToQueryViewHelper extends AbstractViewHelper
{
    /**
     * NOTE: This property has been introduced via code migration to ensure backwards-compatibility.
     * @see AbstractViewHelper::isOutputEscapingEnabled()
     * @var boolean
     */
    protected $escapeOutput = FALSE;

    public function initializeArguments(): void
    {
        $this->registerArgument('sqlQuery', 'string', '', true);
        $this->registerArgument('params', 'array', '', false, null);
    }

    public function render(): string
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
                $sqlQuery = str_replace(':' . $key, (string) $value, $sqlQuery);
            }
        }
        return $sqlQuery;
    }
}

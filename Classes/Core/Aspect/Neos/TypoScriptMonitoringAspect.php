<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core\Aspect\Neos;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Phpprofiler". *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3          *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Sandstorm\Plumber\Core\Profiler;

/**
 * Monitor TypoScript execution times
 */
#[Flow\Scope('singleton')]
#[Flow\Aspect]
final readonly class TypoScriptMonitoringAspect
{
    #[Flow\Around('method(Neos\Neos\View\FusionView->render())')]
    public function profileRenderMethod(JoinPointInterface $joinPoint): mixed
    {
        Profiler::getInstance()->getRun()->startTimer('Neos.Neos: TypoScript View');
        $output = $joinPoint->getAdviceChain()->proceed($joinPoint);
        Profiler::getInstance()->getRun()->stopTimer('Neos.Neos: TypoScript View');
        return $output;
    }

    #[Flow\Around('method(Neos\Fusion\FusionObjects\TemplateImplementation->evaluate())')]
    public function profileTemplateImplementationEvaluate(JoinPointInterface $joinPoint): mixed
    {
        Profiler::getInstance()->getRun()->startTimer('Neos.Neos: TypoScript Template Rendering');
        $output = $joinPoint->getAdviceChain()->proceed($joinPoint);
        Profiler::getInstance()->getRun()->stopTimer('Neos.Neos: TypoScript Template Rendering');
        return $output;
    }
}

<?php
namespace Sandstorm\PhpProfiler\Aspect\Neos;

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

/**
 * Monitor TypoScript execution times
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class TypoScriptMonitoringAspect
{

    /**
     * Around advice
     *
     * @Flow\Around("method(Neos\Neos\View\FusionView->render())")
     * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return array Result of the target method
     */
    public function profileRenderMethod(\Neos\Flow\Aop\JoinPointInterface $joinPoint)
    {
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('Neos.Neos: TypoScript View');
        $output = $joinPoint->getAdviceChain()->proceed($joinPoint);
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('Neos.Neos: TypoScript View');
        return $output;
    }

    /**
     * Around advice
     *
     * @Flow\Around("method(Neos\Neos\Domain\Service\FusionService->createRuntime())")
     * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return array Result of the target method
     */
    public function profileTypoScriptCompilation(\Neos\Flow\Aop\JoinPointInterface $joinPoint)
    {
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('Neos.Neos: TypoScript Compilation');
        $output = $joinPoint->getAdviceChain()->proceed($joinPoint);
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('Neos.Neos: TypoScript Compilation');
        return $output;
    }

    /**
     * Around advice
     *
     * @Flow\Around("method(Neos\Fusion\FusionObjects\TemplateImplementation->evaluate())")
     * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return array Result of the target method
     */
    public function profileTemplateImplementationEvaluate(\Neos\Flow\Aop\JoinPointInterface $joinPoint)
    {
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('Neos.Neos: TypoScript Template Rendering');
        $output = $joinPoint->getAdviceChain()->proceed($joinPoint);
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('Neos.Neos: TypoScript Template Rendering');
        return $output;
    }

    /**
     * Around advice
     *
     * @Flow\Around("method(Neos\Neos\Fusion\AbstractMenuImplementation->evaluate())")
     * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return array Result of the target method
     */
    public function profileMenuRendering(\Neos\Flow\Aop\JoinPointInterface $joinPoint)
    {
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('Neos.Neos: TypoScript Menu Rendering');
        $output = $joinPoint->getAdviceChain()->proceed($joinPoint);
        \Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('Neos.Neos: TypoScript Menu Rendering');
        return $output;
    }

}

<?php
namespace SandstormMedia\PhpProfilerConnector;

use \TYPO3\FLOW3\Package\Package as BasePackage;
use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * Package base class of the SandstormMedia.PhpProfilerConnector package.
 *
 * @FLOW3\Scope("singleton")
 */
class Package extends BasePackage {

	protected function connectToSignals(\TYPO3\FLOW3\SignalSlot\Dispatcher $dispatcher, \SandstormMedia\PhpProfiler\Profiler $profiler, \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $run) {
		$dispatcher->connect('TYPO3\FLOW3\Core\Booting\Sequence', 'beforeInvokeStep', function($step) use($run) {
			$run->startTimer('Boostrap Sequence: ' . $step->getIdentifier());
		});
		$dispatcher->connect('TYPO3\FLOW3\Core\Booting\Sequence', 'afterInvokeStep', function($step) use($run) {
			$run->stopTimer('Boostrap Sequence: ' . $step->getIdentifier());
		});

		$dispatcher->connect('TYPO3\FLOW3\Core\Bootstrap', 'finishedRuntimeRun', function() use($profiler) {
			$run = $profiler->stop();
			if ($run) {
				$profiler->save($run);
			}
		});
		$dispatcher->connect('TYPO3\FLOW3\MVC\Dispatcher', 'beforeControllerInvocation', function($request, $controller) use($run) {
			$run->setOption('Controller Name', get_class($controller));
			$data = array(
				'Controller' => get_class($controller)
			);
			if ($request instanceof \TYPO3\FLOW3\MVC\Web\Request) {
				$data['Action'] = $request->getControllerActionName();
			}

			$run->startTimer('MVC: Controller Invocation', $data);
		});
		$dispatcher->connect('TYPO3\FLOW3\MVC\Dispatcher', 'afterControllerInvocation', function() use($run) {
			$run->stopTimer('MVC: Controller Invocation');
		});

		$dispatcher->connect('TYPO3\FLOW3\MVC\Web\RequestBuilder', 'beforeBuild', function() use($run) {
			$run->startTimer('MVC: Build Request');
		});
		$dispatcher->connect('TYPO3\FLOW3\MVC\Web\RequestBuilder', 'afterBuild', function() use($run) {
			$run->stopTimer('MVC: Build Request');
		});

	}

	protected function connectToSignalsWithOldBootstrap(\TYPO3\FLOW3\SignalSlot\Dispatcher $dispatcher, \SandstormMedia\PhpProfiler\Profiler $profiler, \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $run) {

		$dispatcher->connect('TYPO3\FLOW3\Core\Bootstrap', 'finishedRuntimeRun', function() use($profiler) {
			$run = $profiler->stop();
			if ($run) {
				$profiler->save($run);
			}
		});



		$dispatcher->connect('TYPO3\Fluid\View\AbstractTemplateView', 'beforeRender', function() use($run) {
			$run->startTimer('Fluid: Rendering');
		});
		$dispatcher->connect('TYPO3\Fluid\View\AbstractTemplateView', 'afterRender', function() use($run) {
			$run->stopTimer('Fluid: Rendering');
		});

		$dispatcher->connect('TYPO3\Fluid\View\AbstractTemplateView', 'beforeRenderSection', function($sectionName) use($run) {
			$run->startTimer('Fluid: Rendering Section', array('Section' => $sectionName));
		});
		$dispatcher->connect('TYPO3\Fluid\View\AbstractTemplateView', 'afterRenderSection', function() use($run) {
			$run->stopTimer('Fluid: Rendering Section');
		});

		$dispatcher->connect('TYPO3\Fluid\View\AbstractTemplateView', 'beforeRenderPartial', function($partialName, $sectionName) use($run) {
			$run->startTimer('Fluid: Rendering Partial', array('Partial' => $partialName, 'Section' => $sectionName));
		});
		$dispatcher->connect('TYPO3\Fluid\View\AbstractTemplateView', 'afterRenderPartial', function() use($run) {
			$run->stopTimer('Fluid: Rendering Partial');
		});
	}

	public function boot(\TYPO3\FLOW3\Core\Bootstrap $bootstrap) {

		if (!file_exists(FLOW3_PATH_DATA . 'Logs/Profiles')) {
			mkdir(FLOW3_PATH_DATA . 'Logs/Profiles');
		}

		$profiler = \SandstormMedia\PhpProfiler\Profiler::getInstance();
		$profiler->setOption('profilePath', FLOW3_PATH_DATA . 'Logs/Profiles');

		$run = $profiler->start();
		$dispatcher = $bootstrap->getSignalSlotDispatcher();
		$run->setOption('Context', $bootstrap->getContext());
		$this->connectToSignals($dispatcher, $profiler, $run);
		//$this->connectToSignalsWithOldBootstrap($dispatcher, $profiler, $run);
	}
}
?>
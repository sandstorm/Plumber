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
	public function boot(\TYPO3\FLOW3\Core\Bootstrap $bootstrap) {

		if (!file_exists(FLOW3_PATH_DATA . 'Logs/Profiles')) {
			mkdir(FLOW3_PATH_DATA . 'Logs/Profiles');
		}

		$profiler = \SandstormMedia\PhpProfiler\Profiler::getInstance();
		$profiler->setOption('profilePath', FLOW3_PATH_DATA . 'Logs/Profiles');

		$run = $profiler->start();

		$dispatcher = $bootstrap->getSignalSlotDispatcher();

		$dispatcher->connect('TYPO3\FLOW3\Core\Bootstrap', 'finishedRuntimeRun', function() use($profiler) {
			$run = $profiler->stop();
			if ($run) {
				$profiler->save($run);
			}
		});

		$dispatcher->connect('TYPO3\FLOW3\MVC\Dispatcher', 'beforeControllerInvocation', function($request, $controller) use($run) {
			$run->setOption('controllerName', get_class($controller));
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
		/*
		$dispatcher->connect('TYPO3\FLOW3\Core\Bootstrap', 'dispatchedCommandLineSlaveRequest', 'TYPO3\FLOW3\Persistence\PersistenceManagerInterface', 'persistAll');
		$dispatcher->connect('TYPO3\FLOW3\Core\Bootstrap', 'bootstrapShuttingDown', 'TYPO3\FLOW3\Configuration\ConfigurationManager', 'shutdown');
		$dispatcher->connect('TYPO3\FLOW3\Core\Bootstrap', 'bootstrapShuttingDown', 'TYPO3\FLOW3\Object\ObjectManagerInterface', 'shutdown');
		$dispatcher->connect('TYPO3\FLOW3\Core\Bootstrap', 'bootstrapShuttingDown', 'TYPO3\FLOW3\Reflection\ReflectionService', 'saveToCache');

		$dispatcher->connect('TYPO3\FLOW3\Command\CoreCommandController', 'finishedCompilationRun', 'TYPO3\FLOW3\Security\Policy\PolicyService', 'savePolicyCache');

		$dispatcher->connect('TYPO3\FLOW3\Security\Authentication\AuthenticationProviderManager', 'authenticatedToken', 'TYPO3\FLOW3\Session\SessionInterface', 'renewId');
		$dispatcher->connect('TYPO3\FLOW3\Security\Authentication\AuthenticationProviderManager', 'loggedOut', 'TYPO3\FLOW3\Session\SessionInterface', 'destroy');*/
	}
}
?>
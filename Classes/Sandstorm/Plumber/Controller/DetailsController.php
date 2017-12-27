<?php
namespace Sandstorm\Plumber\Controller;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Plumber".     *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3          *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Sandstorm\PhpProfiler\Domain\Model\ProfilingRun;
use Sandstorm\Plumber\Exception;
use Neos\Flow\Annotations as Flow;

/**
 * Standard controller for the Sandstorm.Plumber package
 *
 * @Flow\Scope("singleton")
 */
class DetailsController extends AbstractController
{

    /**
     *
     * @param string $runIdentifier1
     * @param string $runIdentifier2
     * @return void
     */
    public function timelineAction($runIdentifier1, $runIdentifier2 = NULL)
    {
        $profile = $this->getProfile($runIdentifier1);
        $this->view->assign('numberOfProfiles', $runIdentifier2 === NULL ? 1 : 2);
        $this->view->assign('profile', $profile);
        $this->view->assign('runIdentifier1', $runIdentifier1);

        $js = $this->buildJavaScriptForProfile($profile, 0);

        if ($runIdentifier2 !== NULL) {
            $profile2 = $this->getProfile($runIdentifier2);
            $this->view->assign('profile2', $profile2);
            $this->view->assign('runIdentifier2', $runIdentifier2);
            $js .= $this->buildJavaScriptForProfile($profile2, 1);
        }
        $this->view->assign('js', $js);
    }

    /**
     * Show the XHProf view for the given $runIdentifier
     *
     * Note: the argument must be named "run" because XHProf_UI fetches its "run" directly
     * from the request variables…
     *
     * @param string $run
     * @return void
     * @throws \Sandstorm\Plumber\Exception
     */
    public function xhprofAction($run)
    {
        require_once(XHPROF_ROOT . '/classes/xhprof_ui.php');
        require_once(XHPROF_ROOT . '/classes/xhprof_ui/config.php');
        require_once(XHPROF_ROOT . '/classes/xhprof_ui/compute.php');
        require_once(XHPROF_ROOT . '/classes/xhprof_ui/utils.php');
        require_once(XHPROF_ROOT . '/classes/xhprof_ui/run.php');
        require_once(XHPROF_ROOT . '/classes/xhprof_ui/report/driver.php');
        require_once(XHPROF_ROOT . '/classes/xhprof_ui/report/single.php');
        $oldErrorReportingLevel = error_reporting(0);

        $xhprofConfiguration = new \XHProf_UI\Config();

        $xhprofUi = new \XHProf_UI(
            array(
                'run' => array(\XHProf_UI\Utils::STRING_PARAM, ''),
                'compare' => array(\XHProf_UI\Utils::STRING_PARAM, ''),
                'wts' => array(\XHProf_UI\Utils::STRING_PARAM, ''),
                'fn' => array(\XHProf_UI\Utils::STRING_PARAM, ''),
                'sort' => array(\XHProf_UI\Utils::STRING_PARAM, 'wt'),
                'run1' => array(\XHProf_UI\Utils::STRING_PARAM, ''),
                'run2' => array(\XHProf_UI\Utils::STRING_PARAM, ''),
                'namespace' => array(\XHProf_UI\Utils::STRING_PARAM, 'xhprof'),
                'all' => array(\XHProf_UI\Utils::UINT_PARAM, 0),
            ),
            $xhprofConfiguration,
            FLOW_PATH_DATA . 'Logs/Profiles'
        );
        $report = $xhprofUi->generate_report();

        if ($report === FALSE) {
            $message = 'A report could not be generated.';
            $xhprofPathAndFilename = sprintf('%sLogs/Profiles/%s.xhprof', FLOW_PATH_DATA, $run);
            if (!file_exists($xhprofPathAndFilename)) {
                $message .= sprintf(' The required profile file "%s" does not exist.', $xhprofPathAndFilename);
            }
            if (!extension_loaded('xhprof')) {
                $message .= ' Hint: the required PHP extension "xhprof" is not loaded which might be the reason.';
            }
            error_reporting($oldErrorReportingLevel);
            throw new Exception($message, 1360937314);
        }

        ob_start();
        $report->render();

        $contents = ob_get_contents();
        $contents = str_replace('<tbody', '<tbody class="list"', $contents);
        ob_end_clean();
        $this->view->assign('contents', $contents);

        $this->view->assign('run', $run);
    }

    /**
     * Fetches the profile attached to the profiling run and var_dumps it.
     *
     * @param string $runIdentifier
     * @return string
     */
    public function xhprofDebugAction($runIdentifier)
    {
        $profile = $this->getProfile($runIdentifier);
        \Neos\Flow\var_dump($profile->getXhprofTrace());
        return '';
    }

    /**
     * Build timeline JS code for the given profile.
     *
     * @param \Sandstorm\PhpProfiler\Domain\Model\ProfilingRun $profile
     * @param integer $eventSourceIndex
     * @return string
     */
    protected function buildJavaScriptForProfile(ProfilingRun $profile, $eventSourceIndex)
    {
        $javaScript = array();
        foreach ($profile->getTimersAsDuration() as $event) {

            $data = $event['data'];
            if (isset($event['dbQueryCount'])) {
                $data['dbQueryCount'] = $event['dbQueryCount'];
            }
            $javaScript[] = sprintf('timelineRunner.addEvent(%s, new Timeline.DefaultEventSource.Event({
				start: new Date(%s),
				end:  new Date(%s),
				durationEvent: true,
				caption: %s,
				description: %s,
				color: "#%s"
			}));', $eventSourceIndex, (int)($event['start'] * 1000), (int)($event['stop'] * 1000), json_encode($event['name']), json_encode($data),
                $this->getColorForEventName($event['name']));
        }

        foreach ($profile->getTimestamps() as $event) {
            $javaScript[] = sprintf('timelineRunner.addEvent(%s, new Timeline.DefaultEventSource.Event({
				start: new Date(%s),
				durationEvent: false,
				text: %s,
				caption: %s,
				description: %s,
				color: "#%s"
			}));', $eventSourceIndex, (int)($event['time'] * 1000), json_encode($event['name']), json_encode($event['name']),
                json_encode($event['data']), $this->getColorForEventName($event['name']));
        }

        $memory = $profile->getMemory();
        foreach ($memory as &$record) {
            $record['time'] = (int)($record['time'] * 1000);
        }
        $javaScript[] = sprintf('timelineRunner.setMemory(%s, %s);', $eventSourceIndex, json_encode($memory));

        $dbQueryCount = $profile->getDbQueryCount();
        foreach ($dbQueryCount as &$record) {
            $record['time'] = (int)($record['time'] * 1000);
        }
        $javaScript[] = sprintf('timelineRunner.setDbQueryCount(%s, %s);', $eventSourceIndex, json_encode($dbQueryCount));

        return implode("\n", $javaScript);
    }

    /**
     * If given an event name without a group (i.e. like "Routing"), this
     * method will deterministically calculate a color value from the string.
     *
     * If given an event name with a group (i.e. like "MVC: Routing" or "MVC: Controller"),
     * we want to make sure that the group is *roughly* having the same color. That's why
     * we take the group title ("MVC"), calculate a base color from it, and then
     * darken or lighten this color using the remaining string.
     *
     * @param string $name
     * @return string
     */
    protected function getColorForEventName($name)
    {
        $parts = explode(':', $name);
        if (count($parts) > 1) {
            $firstElementHash = sha1(array_shift($parts));
            $restHash = substr(sha1(implode(':', $parts)), 0, 6);
            $steps = (hexdec($restHash) % 256) - 128;

            $rHex = $firstElementHash[0] . $firstElementHash[1];
            $gHex = $firstElementHash[2] . $firstElementHash[3];
            $bHex = $firstElementHash[4] . $firstElementHash[5];

            $r = hexdec($rHex);
            $g = hexdec($gHex);
            $b = hexdec($bHex);

            $r = max(0, min(255, $r + $steps));
            $g = max(0, min(255, $g + $steps));
            $b = max(0, min(255, $b + $steps));

            return str_pad(dechex($r), 2, '0') . str_pad(dechex($g), 2, '0') . str_pad(dechex($b), 2, '0');
        } else {
            return substr(sha1($name), 0, 6);
        }
    }

}

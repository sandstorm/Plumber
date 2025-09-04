<?php
namespace Sandstorm\PhpProfiler\Domain\Model;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.PhpProfiler". *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Neos\Flow\Annotations as Flow;

/**
 * Profiling run Domain Model
 *
 * @Flow\Proxy(false)
 */
class ProfilingRun extends EmptyProfilingRun
{

    /**
     * Start time of the profiling run in seconds (microtime(true))
     *
     * @var float
     */
    protected $startTime;

    /**
     * @var int
     */
    protected $numberOfDatabaseQueries = 0;

    /**
     * Name of the currently active Timer
     *
     * @var string
     */
    protected $activeTimer = NULL;

    /**
     * Collected timers. Is an associative array:
     * key: Timer Name
     * value: array of the "events" for the current timer, an event can be either "start" or "stop".
     *
     *    'time' => (float) Current time in seconds, with microtime precision; relative to $this->startTime
     *    'data' => (array) Data payload, as specified in startTimer(). Only used if start=TRUE.
     *    'start' => (boolean) If TRUE, is a "start" event of the timer, if FALSE, is a stop event.
     *    'mem' => (int) Memory consumption in bytes at the current time.
     *
     * @var array
     */
    protected $timers;

    /**
     * Collected timestamps. Is an array, where each array element looks as follows:
     *
     *    'name' => (string) name of the timestamp.
     *    'time' => (float) Current time in seconds, with microtime precision; relative to $this->startTime
     *    'data' => (array) Data payload, as specified in timestamp().
     *    'mem' => (int) Memory consumption in bytes at the current time.
     *
     * @var array
     */
    protected $timestamps;

    /**
     * If it is an array, it is an XHProf trace array. If it is a string,
     * it is a fully qualified file name pointing to a serialized XHProf
     * Trace array
     *
     * @var array|string
     */
    protected $xhprofTrace;

    /**
     * Associative Array of Options. Options are just global meta information
     * for the current profiling run, which can be shown in the overview
     * pages.
     *
     * @var array
     */
    protected $options = array();

    /**
     * Tags of the current profiling run
     *
     * @var array
     */
    protected $tags = array();

    /**
     * Full path to the serialized profiling run file. Not always set,
     * purely internal.
     *
     * @var string
     */
    protected $pathAndFilename;

    /**
     * @var string
     */
    protected $currentCalculationHash;

    /**
     * @var array
     */
    protected $cachedCalculationResults;

    /**
     * Set an option.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     * @api
     */
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
    }

    /**
     * Returns all options.
     *
     * @return array
     * @api
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Returns all tags for this run.
     *
     * @return array
     * @api
     */
    public function getTags()
    {
        if (!is_array($this->tags)) {
            return array();
        }

        return $this->tags;
    }

    /**
     * Set tags for this run.
     *
     * @param array $tags
     * @return void
     * @api
     */
    public function setTags(array $tags)
    {
        $this->tags = $tags;
    }

    /**
     * Start to record this profiling run
     *
     * @return void
     */
    public function start()
    {
        $this->timers = array();
        $this->timestamps = array();
        $this->startTime = microtime(TRUE);
        if (function_exists('tideways_xhprof_enable')) {
            tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY);
        }
        $this->startTimer('Profiling Run');
    }

    /**
     * Stop this profiling run recording
     *
     * @return void
     */
    public function stop()
    {
        $this->stopTimer('Profiling Run');
        if (function_exists('tideways_xhprof_disable')) {
            $this->xhprofTrace = tideways_xhprof_disable();
        }

        $this->convertTimersRelativeToStartTime();
    }

    /**
     * Helper which converts the timer values relative to the start time.
     * Is called automatically on stop().
     *
     * @return void
     */
    protected function convertTimersRelativeToStartTime()
    {
        foreach ($this->timers as &$t) {
            foreach ($t as &$v) {
                $v['time'] -= $this->startTime;
            }
        }

        foreach ($this->timestamps as &$t) {
            $t['time'] -= $this->startTime;
        }
    }

    /**
     * Save this profiling run to disk
     *
     * @param array $settings
     * @return void
     */
    public function save(array $settings = array())
    {
        if ($settings !== array() && is_array($this->xhprofTrace)) {
            if (FLOW_SAPITYPE === 'CLI') {
                $_SERVER['HTTP_HOST'] = 'localhost';
                $_SERVER['REQUEST_URI'] = 'CLI run';
                $_SERVER['REQUEST_METHOD'] = 'CLI';
            }

            if ($settings['xhprof.io']['enable']) {
                $this->saveToXhprofio($settings);
            }

            if ($settings['xhgui']['enable']) {
                $this->saveToXhgui($settings);
            }
        }

        $filename = NULL;
        if ($this->pathAndFilename !== NULL) {
            $filename = $this->pathAndFilename;
        } elseif ($settings !== array() && file_exists($settings['plumber']['profilePath'])) {
            $filename = $settings['plumber']['profilePath'] . '/' . microtime(TRUE) . '.profile';
        }

        if ($filename !== NULL) {
            if (is_array($this->xhprofTrace)) {
                @file_put_contents($filename . '.xhprof', serialize($this->xhprofTrace));
                $this->xhprofTrace = $filename . '.xhprof';
            }

            @file_put_contents($filename, serialize($this));
        }
    }

    /**
     * xhprof.io data storage
     *
     * @param array $settings
     * @return void
     */
    protected function saveToXhprofio(array $settings)
    {
        require_once(__DIR__ . '/../../../../../Resources/Private/Xhprof.io/data.php');
        $pdo = new \PDO($settings['xhprof.io']['dsn'], $settings['xhprof.io']['username'], $settings['xhprof.io']['password']);
        $xhprofData = new \ay\xhprof\Data($pdo);
        $xhprofData->save($this->xhprofTrace);
    }

    /**
     * xhgui data storage
     *
     * @param array $settings
     * @return void
     */
    protected function saveToXhgui(array $settings)
    {
        require_once(__DIR__ . '/../../../../../Resources/Private/Xhgui/Db.php');
        require_once(__DIR__ . '/../../../../../Resources/Private/Xhgui/Db/Mapper.php');
        require_once(__DIR__ . '/../../../../../Resources/Private/Xhgui/Profile.php');
        require_once(__DIR__ . '/../../../../../Resources/Private/Xhgui/Profiles.php');
        $data = array(
            'profile' => $this->xhprofTrace,
            'meta' => array(
                'url' => $_SERVER['REQUEST_URI'],
                'SERVER' => $_SERVER,
                'get' => $_GET,
                'env' => $_ENV,
                'simple_url' => preg_replace('/\=\d+/', '', $_SERVER['REQUEST_URI']),
                'request_ts' => new \MongoDate($_SERVER['REQUEST_TIME']),
                'request_date' => date('Y-m-d', $_SERVER['REQUEST_TIME'])
            )
        );
        $db = \Xhgui_Db::connect($settings['xhgui']['host'], $settings['xhgui']['dbname']);
        $profiles = new \Xhgui_Profiles($db->results);
        $profiles->insert($data);
    }

    /**
     * Set calculation result cache.
     *
     * @param string $currentCalculationHash
     * @param array $cachedCalculationResults
     * @return void
     */
    public function setCachedCalculationResults($currentCalculationHash, array $cachedCalculationResults)
    {
        $this->currentCalculationHash = $currentCalculationHash;
        $this->cachedCalculationResults = $cachedCalculationResults;
    }

    /**
     * Fetches the cached calculation results, if the hash equals the stored one.
     *
     * @param string $calculationHash
     * @return array
     */
    public function getCachedCalculationResults($calculationHash)
    {
        if ($calculationHash === $this->currentCalculationHash) {
            return $this->cachedCalculationResults;
        }
        return array();
    }

    /**
     * @param string $fullPath
     * @return void
     */
    public function setPathAndFilename($fullPath)
    {
        $this->pathAndFilename = $fullPath;
        $this->xhprofTrace = $fullPath . '.xhprof';
    }

    /**
     * Remove this profiling run.
     *
     * @return void
     * @api
     */
    public function remove()
    {
        if ($this->pathAndFilename !== NULL) {
            unlink($this->pathAndFilename);
            if (file_exists($this->pathAndFilename . '.xhprof')) {
                unlink($this->pathAndFilename . '.xhprof');
            }
        }
    }

    /**
     * Start a timer
     *
     * @param string $name
     * @param array $data
     * @return void
     * @api
     */
    public function startTimer($name, array $data = array())
    {
        if (!isset($this->timers[$name])) {
            $this->timers[$name] = array();
        }
        $this->timers[$name][] = array(
            'time' => microtime(TRUE),
            'data' => $data,
            'start' => TRUE,
            'mem' => memory_get_peak_usage(TRUE),
            'parent' => $this->activeTimer,
            'dbQueryCount' => $this->numberOfDatabaseQueries
        );
        $this->activeTimer = $name;
    }

    /**
     * Stop a timer
     *
     * @param string $name
     * @return void
     * @api
     */
    public function stopTimer($name)
    {
        if (!isset($this->timers[$name])) {
            $this->timers[$name] = array();
        }

        $lastTimer = end($this->timers[$name]);
        if (isset($lastTimer['parent'])) {
            $this->activeTimer = $lastTimer['parent'];
        }

        $this->timers[$name][] = array(
            'time' => microtime(TRUE),
            'start' => FALSE,
            'mem' => memory_get_peak_usage(TRUE),
            'dbQueryCount' => $this->numberOfDatabaseQueries
        );
    }

    /**
     * Record a timestamp
     *
     * @param string $name
     * @param array $data
     * @return void
     */
    public function timestamp($name, array $data = array())
    {
        $this->timestamps[] = array(
            'name' => $name,
            'time' => microtime(TRUE),
            'data' => $data,
            'mem' => memory_get_peak_usage(TRUE),
            'dbQueryCount' => $this->numberOfDatabaseQueries
        );
    }

    /**
     * Returns the start time of this run as a DateTime.
     *
     * @return \DateTime the start time
     */
    public function getStartTime()
    {
        return \DateTime::createFromFormat('U', (int)$this->startTime);
    }

    /**
     * Returns the start time of this run as a float
     *
     * @return float
     */
    public function getStartTimeAsFloat()
    {
        return $this->startTime;
    }

    /**
     * Get memory consumption. Returned is a sorted-by-time array
     * where each array element is again an array with the following structure:
     *
     * 'time' => (float) Current time in seconds, with microtime precision; relative to $this->startTime
     * 'mem'  => (int) Current memory consumption in bytes.
     *
     * @return array
     */
    public function getMemory()
    {
        $output = array();
        foreach ($this->timestamps as $t) {
            $output[] = array(
                'time' => $t['time'],
                'mem' => $t['mem']
            );
        }
        foreach ($this->timers as $tmp) {
            foreach ($tmp as $t) {
                $output[] = array(
                    'time' => $t['time'],
                    'mem' => $t['mem']
                );
            }
        }

        // now, sort events by start time
        usort($output, function ($a, $b) {
            return (int)(1000 * $a['time'] - 1000 * $b['time']);
        });
        return $output;
    }

    /**
     * Get DB Queries. Returned is a sorted-by-time array
     * where each array element is again an array with the following structure:
     *
     * 'time' => (float) Current time in seconds, with microtime precision; relative to $this->startTime
     * 'dbQueryCount'  => (int) Number of DB queries done so far.
     *
     * @return array
     */
    public function getDbQueryCount()
    {
        $output = array();
        foreach ($this->timestamps as $t) {
            $output[] = array(
                'time' => $t['time'],
                'dbQueryCount' => $t['dbQueryCount']
            );
        }
        foreach ($this->timers as $tmp) {
            foreach ($tmp as $t) {
                $output[] = array(
                    'time' => $t['time'],
                    'dbQueryCount' => $t['dbQueryCount']
                );
            }
        }

        // now, sort events by start time
        usort($output, function ($a, $b) {
            return (int)(1000 * $a['time'] - 1000 * $b['time']);
        });
        return $output;
    }

    /**
     * Get the full XHProf Trace array
     *
     * @return array
     */
    public function getXhprofTrace()
    {
        if (is_string($this->xhprofTrace) && file_exists($this->xhprofTrace)) {
            $this->xhprofTrace = unserialize(file_get_contents($this->xhprofTrace));
        }
        if (!is_array($this->xhprofTrace)) {
            return array();
        }

        return $this->xhprofTrace;
    }

    /**
     * Get all timestamps; see $this->timestamps for the format
     * description.
     *
     * @return array
     */
    public function getTimestamps()
    {
        return $this->timestamps;
    }

    /**
     * Get all timers as "duration" with a start and end time.
     * Returned is a (sorted by time) array of events
     * where each array element has the following structure:
     *
     * 'start' => (float) start time in seconds, with microtime precision; relative to $this->startTime
     * 'stop'  => (float) stop time in seconds, with microtime precision; relative to $this->startTime
     * 'name'  => (string) Name of the timer
     * 'data'  => (array) additional payload which has been specified in $this->startTimer()
     * 'dbQueryCount' => (int) number of DB queries between start and end
     *
     * @param boolean $asTree Set this to true to get the timers as an Tree
     * @return array|NULL
     */
    public function getTimersAsDuration($asTree = FALSE)
    {
        $events = array();
        $currentlyOpenTimers = array();

        foreach ($this->timers as $timerName => $timerValues) {
            $currentlyOpenTimers[$timerName] = array();
            foreach ($timerValues as $timerValue) {
                if ($timerValue['start'] === TRUE) {
                    $currentlyOpenTimers[$timerName][] = $timerValue;
                } else {
                    $startTime = array_pop($currentlyOpenTimers[$timerName]);
                    if (is_array($startTime)) {
                        $stopTime = $timerValue['time'];
                        $events[] = array(
                            'start' => $startTime['time'],
                            'stop' => $stopTime,
                            'time' => $stopTime - $startTime['time'],
                            'name' => $timerName,
                            'data' => $startTime['data'],
                            'parent' => $startTime['parent'],
                            'dbQueryCount' => (isset($timerValue['dbQueryCount']) ? ($timerValue['dbQueryCount'] - $startTime['dbQueryCount']) : 0)
                            // very conservatively programmed, to be able to read older traces
                        );
                    }
                }
            }
        }

        // now, sort events by start time
        usort($events, function ($a, $b) {
            return (int)(1000 * $a['start'] - 1000 * $b['start']);
        });

        if ($asTree === TRUE) {
            $events = $this->convertToTree($events, $this->activeTimer);
        }

        return $events;
    }

    /**
     * Converts the given $events array into a tree structure.
     *
     * @param array $events
     * @param mixed $root
     * @return array|NULL
     */
    protected function convertToTree(array $events, $root = NULL)
    {
        $returnArray = array();
        foreach ($events as $child => $event) {
            if (isset($event['parent']) && $event['parent'] === $root) {
                unset($events[$child]);
                $event['children'] = $this->convertToTree($events, $event['name']);
                $returnArray[] = $event;
            }
        }
        return empty($returnArray) ? NULL : $returnArray;
    }

    public function logSqlQuery($sql)
    {
        $this->numberOfDatabaseQueries++;
    }
}

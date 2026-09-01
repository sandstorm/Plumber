<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Core\Domain\Model;

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
    protected $options = [];

    /**
     * Tags of the current profiling run
     *
     * @var array
     */
    protected $tags = [];

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
     * Whether start() turns the XHProf trace on. See Sandstorm.Plumber.record.xhprof.
     */
    private bool $recordXhprof = true;

    /**
     * Whether save() writes anything at all unless markAsRelevant() was called. Armed by integrations which
     * only want the runs in which something interesting happened - see discardUnlessMarkedRelevant().
     */
    private bool $requiresRelevance = false;

    private bool $isRelevant = false;

    /**
     * Set an option.
     *
     * @param string $key
     * @param mixed $value
     * @api
     */
    public function setOption($key, $value): void
    {
        $this->options[$key] = $value;
    }

    /**
     * Returns all options.
     * @api
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Returns all tags for this run.
     * @api
     */
    public function getTags(): array
    {
        if (!is_array($this->tags)) {
            return array();
        }

        return $this->tags;
    }

    /**
     * Set tags for this run.
     * @api
     */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * Start to record this profiling run
     */
    public function start(): void
    {
        $this->timers = array();
        $this->timestamps = array();
        $this->startTime = microtime(TRUE);
        if ($this->recordXhprof) {
            if (function_exists('tideways_xhprof_enable')) {
                tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY);
            } elseif (function_exists('xhprof_enable')) {
                // The xhprof extension produces the same trace format as tideways_xhprof and is the
                // only one of the two with builds for PHP versions beyond 8.4.
                xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
            }
        }
        $this->startTimer('Profiling Run');
    }

    /**
     * Decide whether start() turns the XHProf trace on. Has to be called before start().
     * @api
     */
    public function setRecordXhprof(bool $recordXhprof): void
    {
        $this->recordXhprof = $recordXhprof;
    }

    /**
     * Turn the XHProf trace off again and throw away what it collected so far.
     *
     * Packages boot before the settings are readable, so a run which the settings did not want traced has the
     * trace running already by the time that is known.
     * @api
     */
    public function stopRecordingXhprof(): void
    {
        if (!$this->recordXhprof) {
            return;
        }
        $this->recordXhprof = false;
        if (function_exists('tideways_xhprof_disable')) {
            tideways_xhprof_disable();
        } elseif (function_exists('xhprof_disable')) {
            xhprof_disable();
        }
        $this->xhprofTrace = null;
    }

    /**
     * From here on, save() writes nothing unless markAsRelevant() is called.
     *
     * A worker of a batch job profiles a fixed number of items and then restarts, so a long run produces
     * thousands of profiles of which only a handful are worth looking at. An integration which knows what "worth
     * looking at" means arms this at the start and marks the run when it happens.
     *
     * @api
     */
    public function discardUnlessMarkedRelevant(): void
    {
        $this->requiresRelevance = true;
    }

    /**
     * @api
     */
    public function markAsRelevant(): void
    {
        $this->isRelevant = true;
    }

    /**
     * Stop this profiling run recording
     */
    public function stop(): void
    {
        $this->stopTimer('Profiling Run');
        if ($this->recordXhprof) {
            if (function_exists('tideways_xhprof_disable')) {
                $this->xhprofTrace = tideways_xhprof_disable();
            } elseif (function_exists('xhprof_disable')) {
                $this->xhprofTrace = xhprof_disable();
            }
        }

        $this->convertTimersRelativeToStartTime();
    }

    /**
     * Helper which converts the timer values relative to the start time.
     * Is called automatically on stop().
     */
    private function convertTimersRelativeToStartTime(): void
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
     */
    public function save(array $settings = []): void
    {
        if ($this->requiresRelevance && !$this->isRelevant) {
            return;
        }

        if ($settings !== [] && is_array($this->xhprofTrace)) {
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
        } elseif ($settings !== array() && file_exists($settings['profilePath'])) {
            // microtime() resolves to ~0.1 ms only (float precision), so runs finishing in
            // parallel collide on the filename and overwrite each other mid-write. The process id
            // does not separate them either: threaded SAPIs such as FrankenPHP serve every request
            // from the same process, and a rendering pipeline forks many at once.
            $filename = $settings['profilePath'] . '/' . microtime(TRUE) . '-' . bin2hex(random_bytes(4)) . '.profile';
        }

        if ($filename !== NULL) {
            if (is_array($this->xhprofTrace)) {
                @file_put_contents($filename . '.xhprof', serialize($this->xhprofTrace));
                $this->xhprofTrace = $filename . '.xhprof';
            }

            @file_put_contents($filename, serialize($this));
            ProfileSummary::fromProfilingRun($filename, $this)->save();
        }
    }

    /**
     * xhprof.io data storage
     *
     */
    private function saveToXhprofio(array $settings): void
    {
        require_once(__DIR__ . '/../../../../Resources/Private/Xhprof.io/data.php');
        $pdo = new \PDO($settings['xhprof.io']['dsn'], $settings['xhprof.io']['username'], $settings['xhprof.io']['password']);
        $xhprofData = new \ay\xhprof\Data($pdo);
        $xhprofData->save($this->xhprofTrace);
    }

    /**
     * xhgui data storage
     *
     * @param array $settings
     */
    private function saveToXhgui(array $settings): void
    {
        require_once(__DIR__ . '/../../../../Resources/Private/Xhgui/Db.php');
        require_once(__DIR__ . '/../../../../Resources/Private/Xhgui/Db/Mapper.php');
        require_once(__DIR__ . '/../../../../Resources/Private/Xhgui/Profile.php');
        require_once(__DIR__ . '/../../../../Resources/Private/Xhgui/Profiles.php');
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
     */
    public function setCachedCalculationResults($currentCalculationHash, array $cachedCalculationResults): void
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
     * The calculation configuration the cached results belong to, so that {@see ProfileSummary} can carry both
     * over into the sidecar.
     */
    public function getCalculationHash(): ?string
    {
        return $this->currentCalculationHash;
    }

    /**
     * @param string $fullPath
     */
    public function setPathAndFilename($fullPath): void
    {
        $this->pathAndFilename = $fullPath;
        $this->xhprofTrace = $fullPath . '.xhprof';
    }

    /**
     * Remove this profiling run.
     * @api
     */
    public function remove(): void
    {
        if ($this->pathAndFilename !== NULL) {
            ProfileSummary::removeProfile($this->pathAndFilename);
        }
    }

    /**
     * Start a timer
     *
     * @param string $name
     * @api
     */
    public function startTimer($name, array $data = []): void
    {
        $this->startTimerInternal($name, $data, microtime(true));
    }

    /**
     * Stop a timer
     *
     * @param string $name
     * @api
     */
    public function stopTimer($name): void
    {
        $this->stopTimerInternal($name, microtime(true));
    }

    /**
     * Record a timer whose start and stop time were measured elsewhere.
     *
     * Callers which time an operation themselves (e.g. a tracer collecting spans in a
     * subprocess) cannot use startTimer()/stopTimer(), because those stamp the current
     * time. The two events are appended in one go, so the timer never appears as open.
     *
     * @param string $name
     * @param float $startTimestamp seconds, microtime(TRUE) scale
     * @param float $stopTimestamp seconds, microtime(TRUE) scale
     * @api
     */
    public function manualTimer($name, array $data, $startTimestamp, $stopTimestamp): void
    {
        $this->startTimerInternal($name, $data, $startTimestamp);
        $this->stopTimerInternal($name, $stopTimestamp);
    }

    /**
     * @param string $name
     * @param float $startTimestamp
     */
    private function startTimerInternal($name, array $data, $startTimestamp): void
    {
        if (!isset($this->timers[$name])) {
            $this->timers[$name] = array();
        }
        $this->timers[$name][] = array(
            'time' => $startTimestamp,
            'data' => $data,
            'start' => TRUE,
            'mem' => memory_get_peak_usage(TRUE),
            'parent' => $this->activeTimer,
            'dbQueryCount' => $this->numberOfDatabaseQueries
        );
        $this->activeTimer = $name;
    }

    /**
     * @param string $name
     * @param float $stopTimestamp
     */
    private function stopTimerInternal($name, $stopTimestamp): void
    {
        if (!isset($this->timers[$name])) {
            $this->timers[$name] = [];
        }

        $lastTimer = end($this->timers[$name]);
        if (isset($lastTimer['parent'])) {
            $this->activeTimer = $lastTimer['parent'];
        }

        $this->timers[$name][] = [
            'time' => $stopTimestamp,
            'start' => false,
            'mem' => memory_get_peak_usage(true),
            'dbQueryCount' => $this->numberOfDatabaseQueries
        ];
    }

    /**
     * Record a timestamp
     *
     * @param string $name
     */
    public function timestamp($name, array $data = []): void
    {
        $this->timestamps[] = [
            'name' => $name,
            'time' => microtime(true),
            'data' => $data,
            'mem' => memory_get_peak_usage(true),
            'dbQueryCount' => $this->numberOfDatabaseQueries
        ];
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
     */
    public function getMemory(): array
    {
        $output = [];
        foreach ($this->timestamps as $t) {
            $output[] = [
                'time' => $t['time'],
                'mem' => $t['mem']
            ];
        }
        foreach ($this->timers as $tmp) {
            foreach ($tmp as $t) {
                $output[] = [
                    'time' => $t['time'],
                    'mem' => $t['mem']
                ];
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

    public function logSqlQuery($sql): void
    {
        $this->numberOfDatabaseQueries++;
    }
}

# Plumber - Profiling Neos Flow

## Versioning Scheme

| Package Version | Neos / Flow Version | Released? | Supported                 | Remarks                                            |
|-----------------|---------------------|-----------|---------------------------|----------------------------------------------------|
| 1.x-3.x         |                     | ☑️        | ⛔️ not maintained anymore | was still having Plumber and PhpProfiler separated |
| 4.0.x           | 8.3                 | ☑️        | ☑️                        | Use this for Neos or Flow Projects up to Neos 8.3  |
| 4.1.x           | 8.4                 | ☑️        | ☑️                        | Neos 8.4                                           |

-- Measuring the flow of your application --

Plumber is a profiling and tracing GUI with the following features:

* **list** all profiling runs in an overview
* show a **graphical timeline** for a single profiling run
* **filter** the graphical timeline
* show the **xhprof** analyzer for a single profiling run
* **compare** two profiling runs with the timeline
* **tag** your profiling runs
* show **aggregated statistics** in the overview

It relies on PhpProfiler for gathering the needed information.

## Installation

Warning: Do not install Plumber on production websites. If you do, make sure to disallow access to the profiler URLs.

To install, just use composer:

```bash
composer require --dev sandstorm/plumber
```

The system will automatically install PhpProfiler and use XHProf if it is installed.

### Installing a trace extension (tideways_xhprof or xhprof)

Timers, runtime, memory and the DB query count are measured by Plumber itself and need no PHP extension. The
*function-level trace* does: it comes from a profiler extension, and without one
`ProfilingRun::getXhprofTrace()` stays empty, no `.xhprof` files are written next to the profiles, and every
`regexSum` / `regex` calculation in the overview reads 0 - with the default configuration that is the "No. of Method
Calls" and "No. of Object Creations" columns.

Plumber uses whichever of these two extensions is loaded, preferring the first:

| Extension         | Provides              | Availability                                                                                                                             |
|-------------------|-----------------------|------------------------------------------------------------------------------------------------------------------------------------------|
| `tideways_xhprof` | `tideways_xhprof_*()` | [tideways/php-xhprof-extension](https://github.com/tideways/php-xhprof-extension), last release v5.0.4 (Dec 2020); no builds for PHP 8.5 |
| `xhprof`          | `xhprof_*()`          | PECL, builds up to PHP 8.5                                                                                                               |

Both write the same trace format, so the timeline and the xhprof analyzer behave identically either way. On PHP 8.5 and
newer, `xhprof` is the only one of the two that can be built.

Note that `tideways` (the commercial APM extension, `tideways_*()` without the `_xhprof`) is a different extension and
is not used by Plumber.

In docker images that ship
[install-php-extensions](https://github.com/mlocati/docker-php-extension-installer) - the official
`php` and `frankenphp` images among them - install it with:

```bash
install-php-extensions xhprof
```

On mac:

```bash
# tideways_xhprof, for PHP 8.1
brew install kabel/pecl/php@8.1-tideways-xhprof

# xhprof
pecl install xhprof

# for older versions
brew install  tideways/homebrew-profiler/php71-tideways --env=std
echo "tideways.auto_prepend_library=0" >> /usr/local/etc/php/7.1/conf.d/ext-tideways.ini
```

Once the extension is loaded, tracing runs for *every* request and every CLI run, which costs noticeable time and writes
an `.xhprof` file per profiling run. Use the
`PHPPROFILER_SAMPLINGRATE` environment variable (see below) to profile only a fraction of them.

# PhpProfiler -- Profiling Neos Flow Applications

-- Measuring the flow of your application --

PhpProfiler is a profiling and tracing tool that measures time spent in various parts of
your application flow and can leverage XHProf to profile applications.

It stores data in a format understood by Plumber and can also store to the databases used by XHGui
(https://github.com/preinheimer/xhgui).

## Installation

To install, just use composer:

```bash
composer require --dev sandstorm/phpprofiler ^3.0.0
```

The system will automatically install PhpProfiler and use XHProf if it is installed.

## Configuration

This is the default configuration:

```
Sandstorm:
  Plumber:
    # off unless asked for - see "Switching profiling on and off"
    enabled: false

    profilePath: '%FLOW_PATH_DATA%Logs/Profiles'

    # what a run collects - see "Keeping profiles small"
    record:
      sqlQueries: true
      xhprof: true

    # formats offered for download in the overview - see "Exporting profiles"
    exports:
      perfetto:
        className: Sandstorm\Plumber\Export\PerfettoTraceExport
        options:
          withCounters: true
      sqlite:
        className: Sandstorm\Plumber\Export\SqliteExport
        options:
          withXhprof: false

    # xhprof.io settings (see http://xhprof.io/)
    'xhprof.io':
      enable: false
      dsn: 'mysql:dbname=xhprofio;host=localhost;charset=utf8'
      username: ''
      password: ''

    # preinheimer-xhgui settings (see https://github.com/preinheimer/xhgui)
    'xhgui':
      enable: false
      host: 'mongodb://localhost:27017'
      dbname: 'xhprof'
```

To enable the XHProf.io and XHGui backends adjust the configuration as needed, but keep in
mind that any needed setup (e.g. databasae creation) needs to be done as described in the
respective documentation.

### Switching profiling on and off

Profiling costs time in every request and every CLI run and writes a file per run, so `enabled` is `false` in the
package defaults. Switch it on for a context, typically in `Configuration/Development/Settings.yaml`:

```yaml
Sandstorm:
  Plumber:
    enabled: true
```

For a single run, the environment variable `PLUMBER_ENABLED` decides on its own and overrules the setting in both
directions - handy to profile one CLI command, or to keep one out of the profiles:

```bash
PLUMBER_ENABLED=1 ./flow some:command
PLUMBER_ENABLED=0 ./flow some:command
```

The `/plumber` UI works either way: it only reads the profiles which are already on disk.

Leaving it off is also what makes the profile list readable when an integration profiles one part of a process
instead of all of it. `Profiler::startIfNotRunning()` starts a run at the point the interesting work begins and
returns the run to record into:

```php
$run = Profiler::getInstance()->startIfNotRunning();
$run->manualTimer('Item: ' . $identifier, [], $start, $stop);
```

Everything wired to the boot and Neos signals - SQL queries, Fusion evaluation, controller invocation - records
into that run from then on, and the shutdown function writes it out. So with `enabled: false`, the only processes
which leave a profile behind are the ones doing the work you asked about; `PLUMBER_ENABLED=0` switches off even
those, because then the package never boots its profiler and nothing would write the run out.

The setting cannot be read while the package boots - Flow boots its packages before the configuration is
available, which is also why `Profiler::setConfigurationProvider()` takes a closure. The run is therefore started
as usual and discarded again as soon as the settings can be read, in a slot on the boot sequence's
`afterInvokeStep` signal. What a disabled run costs is one object, two `microtime()` calls and the timers of the
first two boot steps, all thrown away. `PLUMBER_ENABLED=0` is cheaper still: it returns from `boot()` before
anything is started at all.

### Keeping profiles small

A batch job whose workers restart every so often writes one profile per restart, and a long run can easily leave
a four-digit number of them of ~10 MB each behind - more than any memory limit can list at once. Two settings
decide most of that size.

`record.sqlQueries` gives every SQL query its own timer, carrying the statement and its bound parameters. That is
how a query shows up on the timeline and in the SQLite export, and in a query-heavy job it is easily **99% of all
events**. Switching it off keeps the query *count*, so the "Number of DB queries" column and the DB counter track
are unaffected - only the per-query timers go.

`record.xhprof` writes the `<profile>.xhprof` sidecar of a few megabytes. Without it the XHProf page and the
"No. of Method Calls" / "No. of Object Creations" columns have nothing to show, and the rest works unchanged.

Both are applied as soon as the settings can be read, which is after the run started during boot is already
recording - so a trace that the settings did not want is stopped and thrown away rather than never started.

For a long-running process that starts its own run (see above), a third lever is the run itself:

```php
$run = Profiler::getInstance()->startIfNotRunning();
$run->discardUnlessMarkedRelevant();   // save() writes nothing from here on ...
// ... unless something worth looking at happened:
$run->markAsRelevant();
```

An integration which knows what "worth looking at" means - a job that only cares about the batches containing an
item slower than some threshold - arms the run when the work starts and marks it when such an item turns up.
Batches in which nothing did are never written.

### The `.meta.json` sidecar

Next to every profile, `save()` writes a small JSON file with the run's options, tags and cached calculation
results. The overview lists profiles from those sidecars alone and only reads a profile when a calculation is
missing for it, which is what keeps the page openable with thousands of profiles on disk. Calculation results are
written back into the sidecar, not into the profile.

A profile written by an older Plumber has no sidecar; it is read once when the overview first lists it and gets
one. Deleting a profile in the UI removes the profile, its XHProf trace and its sidecar together - when deleting
by hand, take all three.

### Profiles are written even when the process calls `exit()`

Plumber saves a run when Flow emits `finishedRuntimeRun` / `finishedCompiletimeRun` at the end of
`Bootstrap::run()`. A process which ends with `exit()` never gets there - which is how a worker process that
restarts itself after a fixed number of items usually terminates. A shutdown function therefore saves the run as
well; it also survives a fatal error. On the normal path it writes nothing, because the run has already been
stopped by then.

### Limiting Profiling Run Probability

Using the environment variable ``PHPPROFILER_SAMPLINGRATE`` the probability of runs being
profiled can be changed. If the variable is not set, every run will be profiled. If a float
between 0 and 1 is given, that represents a probability between 0% and 100% for every run
to trigger profiling.

If limiting the probability to a low enough value, it is feasible to leave PhpProfiler running
on production instances.

## Profiling Custom Code

PhpProfiler collects regular XHProf data and some data specific to TYPO3 Flow, Neos and CMS.

To collect profiling information on critical parts of a custom application, various options exist.

### Profiling method calls using an Aspect (NEW!)

You can use the `Sandstorm\Plumber\Core\Annotations\Profile` annotation on a method in order
to profile it:

```php
class MyClass {

	/**
	 * @Sandstorm\Plumber\Core\Annotations\Profile
	 */
	public function myMethod() {
	}
}
```

### Adding custom timers

When hunting for performance bottlenecks, it often makes sense to add custom
timers throughout your application. Doing so is quite easy, as the following
example demonstrates:

```php
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->startTimer('My Timer');
// run some code
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->stopTimer('My Timer');
```

If the timer name contains a colon (`:`), related timers are grouped together in the User Interface:

```php
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->startTimer('Security: Authentication');
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->stopTimer('Security: Authentication');

\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->startTimer('Security: Authorization');
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->stopTimer('Security: Authorization');
```

It's not a problem if multiple timers are active at the same time; even the same timer can
be active multiple times at the same time. The following example is perfectly valid:

```php
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->startTimer('t1');
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->startTimer('t1');
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->stopTimer('t1');
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->stopTimer('t1');
```

Furthermore, the `startTimer` allows a second `array` argument containing additional information
which is shown in the UI.

If you measured the time yourself - a tracer collecting spans, a duration read back from somewhere else - use
`manualTimer()`, which takes the two timestamps instead of stamping the current time:

```php
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()
    ->manualTimer('My Timer', ['url' => $url], $startTimestamp, $stopTimestamp);
```

Both timestamps have to be on the `microtime(true)` scale, because the run rebases every timer against its own
start time when it stops. Recording a timer this way is what makes a *duration threshold* possible: you cannot
decide whether a span is worth keeping before you know how long it took. The price is that such a timer has no
children - its start and stop event are appended in one go.

### Setting Options

Furthermore, you can set meta-information on the current run (which is called `options` currently):

```php
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->setOption('context', 'DEV');
```

## Viewing the results

For the Plumber UI install the Plumber package as described in it's manual.

For XHProf.ui and XHGui follow the instructions given on the project websites.

## Exporting profiles

The overview page offers every format registered at `Sandstorm.Plumber.exports` as a download, per profile and -
via the tag field next to the buttons - for all profiles carrying one tag at once. Two ship with the package.

**Perfetto trace** (`.perfetto.json`) is the Chrome/Catapult JSON Trace Event Format, to be dropped onto
<https://ui.perfetto.dev>. Its timestamps are absolute, so profiles written by several processes at the same time
line up on one timeline. Every profile becomes its own process; timers become slices, and because Plumber allows
several timers to be open at once without nesting, slices are packed onto as many lanes as it takes for them to
nest cleanly - Perfetto's importer rejects partially overlapping slices on one track. Timestamps become instant
events, memory and query counters become counter tracks. The XHProf trace is deliberately left out: it is an
aggregated caller-callee table with no timestamps, so there is no timeline to put it on. Plumber's own XHProf page
stays the tool for that.

The counter tracks are sampled at every timer event and are typically the majority of the events in a trace - in
one measured example 79.000 of 99.000 - so set `withCounters: false` when exporting many profiles at once.

**SQLite database** (`.sqlite`) writes `runs`, `timers`, `timestamps` and - only with `withXhprof: true` -
`xhprof_functions`, so that questions spanning many profiles become a query:

```sql
-- group a timer recorded once per item by something its params carry
SELECT json_extract(data_json, '$.group') AS "group", count(*) AS items, sum(duration_ms) AS ms
FROM timers WHERE name = 'Process Item'
GROUP BY 1 ORDER BY ms DESC;

-- the slowest individual items, where the timer name carries the item
SELECT name, duration_ms FROM timers
WHERE name LIKE 'Item: %'
ORDER BY duration_ms DESC LIMIT 20;
```

It needs the `pdo_sqlite` extension. `withXhprof` is off by default because a 40 MB profile is on the order of
100.000 rows there, none of which carries timing information.

To add a format, implement `Sandstorm\Plumber\Export\ExportFormatInterface` and register the class name under
`Sandstorm.Plumber.exports`.

### Recipe: finding the slow item in a parallel batch job

1. Have the job record a timer per item into a run of its own, and give every worker's run the same tag so that
   the profiles belonging to one job can be found together:

   ```php
   $run = Profiler::getInstance()->startIfNotRunning();
   $run->setTags(['job:' . $jobId]);
   $run->manualTimer('Item: ' . $identifier, ['group' => $group], $start, $stop);
   ```

   Leave `Sandstorm.Plumber.enabled` at `false`, so that the workers are the only processes writing a profile at
   all and the list is not buried under ordinary requests.
2. Run the job. Every worker process writes one profile, tagged `job:<jobId>`.
3. On `/plumber`, type that tag into the field next to the download buttons and pick a format: *SQLite* to run the
   two queries above, *Perfetto trace* to see all workers side by side on one timeline.

For a long job, arm `discardUnlessMarkedRelevant()` so that only the batches containing a slow item are kept, and
consider `record.sqlQueries: false` and `withCounters: false` - otherwise a big run leaves tens of gigabytes
behind and its Perfetto trace is larger than <https://ui.perfetto.dev> will load.

## Credits

Originally developed by Sebastian Kurfürst, Sandstorm Media UG (haftungsbeschränkt)

Code from the XHProf.io and XHGui projects is included for storing the data.

## License

All the code is licensed under the GPL license.

## Configuration

Some settings are available in Plumber and PhpProfiler as well as the TYPO3 CMS
extension, none of which are needed for basic operation. Feel free to investigate
them if you feel like it.

## Usage

Just use your web application as normal. To browse profiling reports, go to `http://yourhost/profiler/`.

For each run, the profiler collects the following data:

* meta-information for the current run (like: the context the request was invoked in, the controller being used)
* timers which can be started and stopped, measuring the details of the application flow.
* the full XHProf profile, containing the (almost) complete call-graph of the run. This is only enabled if XHProf is
  installed.

### Overview Page

![Overview](http://sandstorm.github.io/Plumber/Documentation/OverviewPage.jpg)

The overview page is the main entry point to the profiler. It shows the different
profiling runs. For each profiling run, it can display overview information
like the number of created objects or the memory consumption. Each of the
columns of the table is called a *dimension*.

On top, the bar charts show how the values in a given dimension are distributed,
and allow you to filter the different dimensions to the wanted values.

You can easily create your own dimensions; how to do that is explained later.

### Timeline Page

The timeline page gives a visual overview of a request, showing the timers
of the request, and how memory consumption changed.

![Timeline](http://sandstorm.github.io/Plumber/Documentation/TimelinePage.png)

### XHProf Page

You can also drill down to the XHProf page, showing the detailed statistics
of the run.

## Configuring Custom Dimensions

The available dimensions are configured inside the `Settings.yaml` and that's
also how you can add new dimensions.

Let's check how the default dimensions work:

```yaml
Sandstorm:
  Plumber:
    calculations:
      methodCallsOnObject:
        label: 'No. of Method Calls'
        type: regexSum
        regex: '#==>(.*)::.*#'
      totalRuntime:
        label: 'Runtime (ms)'
        type: timerSum
        timerName: 'Profiling Run'
      totalMemory:
        label: 'Memory (kb)'
        type: maxMemory
```

It defines three dimensions, and gives each of them a label. Each dimension has
a `type` which specifies how the data inside this dimension is aggregated.

We support the following types:

### maxMemory

**Parameters:** None

Output the maximum memory which has been used in kilobytes.

### totalRuntime

**Parameters:** `timerName`

This one sums up the total runtime in milliseconds of a timer specified by `timerName`.

### regexSum

**Parameters:** `regex`

This is the most versatile counter. **It needs XHProf to be installed**, else it
does not work.

It counts the number of method invocations in an XHProf trace. To know how the `regex`
parameter works, we need to check how an XHProf trace is built:

An XHProf trace is a big array with elements like the following:

```php
	'Sandstorm\Plumber\Core\Domain\Model\ProfilingRun::startTimer==>microtime' (76) => array(2)
	   'ct' (2) => integer 10
	   'wt' (2) => integer 9
```

This means: "From inside the method `startTime` in `ProfilingRun` the function `microtime` has
been called 10 times. All these calls to microtime together needed 9 milliseconds."

I'm currently not sure about the time scale, whether it's micro- or milliseconds...

Now, the `regexSum` loops over such a trace, and if the regex matches the array key,
it counts the number of calls together.

As an example, let's demonstrate that with some regexes:

```text
#==>.*__construct#              Matches all constructor invocations
#==>.*TextNode::__construct#    Matches all constructor invocations of classes which end with TextNode

#.*#                            Matches all method calls
#.*==>Doctrine\\Common.*::__construct#'
                                Matches all object creations inside the Doctrine\Common package
```

Furthermore, the regex might contain exactly one submatch pattern. In this case, a popover is displayed
with the top 10 invocations grouped by the regex. Example:

```text
#==>(.*)::__construct#                Matches all constructor invocations, displaying a Top 10 list of constructor invocations
#==>TYPO3\\Fluid\\(.*)::__construct#  Matches constructor invocations in Fluid, displaying a Top 10 list of constructor invocations inside the fluid package
```

### regex

**Paramters:**

* `regex`: '...' (see `regexSum`)
* `metric`: `time|calls|memory`
* `subtype`: `sum|average`

### Your custom type

Custom types are currently not possible.

The calculation happens inside `Sandstorm\Plumber\Service\CalculationService`,
if you want to extend it. Make sure to submit a pull request then :-).

## Profiling Custom Code

The PhpProfiler documentation has instructions on how to profile custom code.

## Credits

Developed by Sebastian Kurfürst, Sandstorm Media GmbH. Pull
requests by various authors.

## License

All the code is licensed under the GPL license.


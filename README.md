# Plumber - Profiling Neos Flow

## Versioning Scheme

| Package Version | Neos / Flow Version | Released? | Supported                 | Remarks                                            |
|-----------------|---------------------|-----------|---------------------------|----------------------------------------------------|
| 1.x-3.x         |                     | ☑️        | ⛔️ not maintained anymore | was still having Plumber and PhpProfiler separated |
| 4.0.x           | 8.3                 | ☑️        | ☑️                        | Use this for Neos or Flow Projects up to Neos 8.3  |
| 4.1.x           | 8.4                 | ☑️        | ☑️                        | Neos 8.4                                           |
| 5.0.x           | 9.0                 | ☑️        | ☑️                        | Neos 9.0 and up                                    |

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

### Installing XHProf / Tideways on mac

XHProf is not supported anymore, but the Tideways data format is still 100%
compatible - and the Tideways PHP Extension is still 100% open source

```bash
# for PHP 8.1
brew install kabel/pecl/php@8.1-tideways-xhprof




# for older versions
brew install  tideways/homebrew-profiler/php71-tideways --env=std
echo "tideways.auto_prepend_library=0" >> /usr/local/etc/php/7.1/conf.d/ext-tideways.ini
```

# PhpProfiler -- Profiling Neos Flow Applications

-- Measuring the flow of your application --

PhpProfiler is a profiling and tracing tool that measures time spent in various parts of
your application flow and can leverage XHProf to profile applications.

It stores data in a format understood by Plumber and can also store to the databases used
by XHProf.io (http://xhprof.io/) and XHGui (https://github.com/preinheimer/xhgui).

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
    profilePath: '%FLOW_PATH_DATA%Logs/Profiles'

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

### Setting Options

Furthermore, you can set meta-information on the current run (which is called `options` currently):

```php
\Sandstorm\Plumber\Core\Profiler::getInstance()->getRun()->setOption('context', 'DEV');
```

## Viewing the results

For the Plumber UI install the Plumber package as described in it's manual.

For XHProf.ui and XHGui follow the instructions given on the project websites.

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


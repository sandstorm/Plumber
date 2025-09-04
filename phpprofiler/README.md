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

```Sandstorm:
  PhpProfiler:
    plumber:
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

You can use the `Sandstorm\PhpProfiler\Annotations\Profile` annotation on a method in order
to profile it:

```php
class MyClass {

	/**
	 * @Sandstorm\PhpProfiler\Annotations\Profile
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
\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('My Timer');
// run some code
\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('My Timer');
```

If the timer name contains a colon (`:`), related timers are grouped together in the User Interface:

```php
\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('Security: Authentication');
\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('Security: Authentication');

\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('Security: Authorization');
\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('Security: Authorization');
```

It's not a problem if multiple timers are active at the same time; even the same timer can
be active multiple times at the same time. The following example is perfectly valid:

```php
\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('t1');
\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('t1');
\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('t1');
\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('t1');
```

Furthermore, the `startTimer` allows a second `array` argument containing additional information
which is shown in the UI.

### Setting Options

Furthermore, you can set meta-information on the current run (which is called `options` currently):

```php
\Sandstorm\PhpProfiler\Profiler::getInstance()->getRun()->setOption('context', 'DEV');
```

## Viewing the results

For the Plumber UI install the Plumber package as described in it's manual.

For XHProf.ui and XHGui follow the instructions given on the project websites.

## Credits

Originally developed by Sebastian Kurfürst, Sandstorm Media UG (haftungsbeschränkt)

Code from the XHProf.io and XHGui projects is included for storing the data.

## License

All the code is licensed under the GPL license.

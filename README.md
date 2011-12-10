Plumber -- FLOW3 Profiling and Debugging Tools
==============================================
-- Measuring the flow of your application --

(c) Sebastian Kurfürst, Sandstorm Media UG (haftungsbeschränkt)

Installation
------------

```
cd <YourFlow3Root>/Packages/Application
git clone --recursive git://github.com/sandstorm/Plumber.git SandstormMedia.Plumber
git clone --recursive git://github.com/sandstorm/PhpProfiler.git SandstormMedia.PhpProfiler
cd ../../
./flow3 package:activate SandstormMedia.PhpProfiler
./flow3 package:activate SandstormMedia.Plumber
```

The system will automatically use XHProf if it is installed.

Usage
-----

Just use your website as normal. To browse profiling reports, go to `http://yourhost/SandstormMedia.Plumber/`

There, you can:

* **list** all profiling runs in an overview
* show a **graphical timeline** for a single profiling run
* **filter** the graphical timeline
* show the **xhprof** analyzer for a single profiling run
* **compare** two profiling runs with the timeline
* **tag** your profiling runs
* show **aggregated statistics** in the overview

Showing aggregated statistics
-----------------------------

If you have xhprof installed, then we can use the Xhprof run reports to show some detailed
statistics in the overview page. This is configured through some settings. Example:

```
SandstormMedia:
  Plumber:
    calculations:
      objectCreations:
        label: 'No. of Object Creations'
        type: regexSum
        regex: '#==>.*__construct#'
```

Inside `SandstormMedia:Plumber:calculations` follow some additional table columns which are shown in the overview page.

The `label` is the table column header. The `type` is the type of aggregation to perform. In the above example, we count constructor calls.

Type: regexSum
--------------

Example:
```
SandstormMedia:
  Plumber:
    calculations:
      objectCreations:
        label: 'No. of Object Creations'
        type: regexSum
        regex: '#==>(.*)::__construct#'
```


This one can be used to count function invocation counters. Examples:

```
#==>.*__construct#              Matches all constructor invocations
#==>.*TextNode::__construct#    Matches all constructor invocations of classes which end with TextNode
```

**Top 10**: When the RegEx contains a submatch, a popover is displayed with the top 10 invocations grouped by the regex. Examples:

```
#==>(.*)::__construct#              Matches all constructor invocations, displaying a Top 10 list of constructor invocations
#==>TYPO3\Fluid\(.*)::__construct#  Matches constructor invocations in Fluid, displaying a Top 10 list of constructor invocations inside the fluid package
```

Type: totalRuntime
------------------

Example:
```
SandstormMedia:
  Plumber:
    calculations:
      totalRuntime:
        label: 'Runtime (ms)'
        type: timerSum
        timerName: 'Profiling Run'
```

This one sums up the total runtime of a timer specified by `timerName`.

Type: maxMemory
---------------

Example:
```
SandstormMedia:
  Plumber:
    calculations:
      mem:
        label: 'Maximum Memory (kB)'
        type: maxMemory
```

Output the maximum memory consumption. Does not have any configuration options.

Type: [custom]
--------------

The aggregation types still need to be extended. They have to be implemented in the CalculationViewHelper.

License
-------

All the code is licensed under the GPL license.
Profiling Custom Code
---------------------

Adding custom timers
~~~~~~~~~~~~~~~~~~~~

When hunting for performance bottlenecks, it often makes sense to add custom
timers throughout your application. Doing so is quite easy, as the following
example demonstrates::

	\SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('My Timer');
	// run some code
	\SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('My Timer');

If the timer name contains a colon (``:``), related timers are grouped together in the User Interface::

	\SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('Security: Authentication');
	\SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('Security: Authentication');

	\SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('Security: Authorization');
	\SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('Security: Authorization');

.. note:: It's not a problem if multiple timers are active at the same time; even the same timer can be active
   multiple times at the same time. The following example is perfectly valid::

      \SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('t1');
      \SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->startTimer('t1');
      \SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('t1');
      \SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->stopTimer('t1');

Furthermore, the ``startTimer`` allows a second ``array`` argument containing additional information
which is shown in the UI.

Setting Options
~~~~~~~~~~~~~~~

Furthermore, you can set meta-information on the current run (which is called ``options`` currently)::

	\SandstormMedia\PhpProfiler\Profiler::getInstance()->getRun()->setOption('context', 'DEV');
Installation
============

.. warning:: Do not install Plumber on production websites. If you do, make sure to disallow access
   to the ``/profiler`` URL using htaccess or the like..


To install, just clone the packages from github:

.. code-block:: bash

	cd <YourFlow3Root>/Packages/Application
	git clone --recursive git://github.com/sandstorm/Plumber.git Sandstorm.Plumber
	git clone --recursive git://github.com/sandstorm/PhpProfiler.git Sandstorm.PhpProfiler
	cd ../../
	./flow3 package:activate Sandstorm.PhpProfiler
	./flow3 package:activate Sandstorm.Plumber

The system will automatically use XHProf if it is installed.

Installation
============

.. warning:: Do not install Plumber on production websites. If you do, make sure to disallow access
   to the ``/profiler`` URL using htaccess or the like.

To install, just require the package in your project:

.. code-block:: bash

	cd <YourProjectRoot>
	composer require sandstorm/plumber \*

The system will automatically use XHProf if it is installed.

Now include the Plumber routes in your global routes configuration:

.. code-block:: yaml

	-
	  name: 'Plumber'
	  uriPattern: 'profiler/<PlumberSubroutes>'
	  defaults:
		'@format': 'html'
	  subRoutes:
		PlumberSubroutes:
		  package: Sandstorm.Plumber

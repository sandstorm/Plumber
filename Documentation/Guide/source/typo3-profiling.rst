Profiling TYPO3 v4 using Plumber
--------------------------------

You can also profile TYPO3 v4 using Plumber. For that, you need to install
https://github.com/sandstorm/typo3v4ext-plumber:

.. code-block:: bash

	cd typo3conf/ext; git clone https://github.com/sandstorm/typo3v4ext-plumber sandstormmedia_plumber

Furthermore, you need a running FLOW3 installation which is used to show the
profiling data.

After installing the extension in TYPO3 v4, you need to specify the base path
to the FLOW3 installation inside the extension configuration.

Then, flush your caches and you should see a profiling run appear in Plumber
for every page request in TYPO3 v4.
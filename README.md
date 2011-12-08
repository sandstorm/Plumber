FLOW3 Profiling Tools
=====================
(c) Sebastian Kurfürst, Sandstorm Media UG (haftungsbeschränkt)

Installation
------------

```
cd <YourFlow3Root>/Packages/Application
git clone --recursive git://github.com/sandstorm/PhpProfilerConnector.git SandstormMedia.PhpProfilerConnector
git clone --recursive git://github.com/sandstorm/PhpProfiler.git SandstormMedia.PhpProfiler
cd ../../
./flow3 package:activate SandstormMedia.PhpProfiler
./flow3 package:activate SandstormMedia.PhpProfilerConnector
```

Furthermore, you need https://review.typo3.org/#change,7158 and https://review.typo3.org/#q,topic:32333,n,z applied.

The system will automatically run XHProf if it is installed.

Usage
-----

Just use your website as normal. To browse profiling reports, go to `http://yourhost/SandstormMedia.PhpProfilerConnector/`

License
-------

All the code is licensed under the GPL license.
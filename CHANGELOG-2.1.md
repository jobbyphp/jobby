# Changes in jobby 2.1

## jobby 2.1.0

* PHP 5.4 is required.
* Updated external libraries, in special [SuperClosure](https://github.com/jeremeamia/super_closure)
from [1.0.1](https://github.com/jeremeamia/super_closure/releases/tag/1.0.1) to 
[2.1.0](https://github.com/jeremeamia/super_closure/releases/tag/2.1.0). SuperClosure is used within jobby for executing
[Closures](http://php.net/manual/de/class.closure.php) as cron-tasks. As SuperClosure itself has
backward incompatible changes from 1.x to 2.x 
(see [PHP SuperClosure v2.0-alpha1](https://github.com/jeremeamia/super_closure/releases/tag/2.0-alpha1)), 
jobby inherits this breaking changes. 
See [UPGRADE-2.1](https://github.com/hellogerard/jobby/blob/master/UPGRADE-2.1.md) for upgrade-hints.
See [Pull request #31](https://github.com/hellogerard/jobby/pull/31) for details.
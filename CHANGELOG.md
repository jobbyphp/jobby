## 3.2.1

* Fix default output (to null device) on Windows (#65)

## 3.2.0

* Cast cron expression to string before passing to CronExpression (which allows
  us to use, e.g., https://github.com/garethellis36/crontab-schedule-generator) (#63)
* Remove job schedule check from jobby jobs (keepin' it DRY!) (#62)

## 3.1.0

* Determine if job should run in main process, not jobby jobs (#45)
* Get tests passing on Windows (#61)

## 3.0.2

* Fix bug where if an error happens during the command's execution, it will be silent (#53)

## 3.0.1

* Support Symfony 3 components (#49)
* Support `phar`-based jobs (#48)
* Update how closure jobs are serialized (#46)
* `BackgroundJob` class can now be overridden with `jobClass` config option (#44)
* Project updates (#43)
  * PSR-4 autoloading
  * PSR-2 Code styling, short array syntax, and single quotes
  * Composer
    * Updated dependencies to allow minor releases
    * Removed composer.lock (It's not needed for libraries and general practice
      is to ignore it.)
  * Travis
    * Updated to builtin composer and caching vendor dirs (should be faster now)
    * Added PHP 7.0 and HHVM. (looks like the bug with 7.0 is fixed in the dev
      branch of superclosure, once they tag a release, we can make not allowed
      to fail)
  * Simplified PHPUnit config
* Support for spaces in log file (#39)
* Adds support for running background processes on the same version of PHP
  that's jobby is currently running (prevents errors in cases where there is
  more than one installed version of php or is running jobby with a different
  version of the php default version installed) (#37)

## 2.2.0

* Support PDO-based jobby jobs (#34)

## 2.1.0

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


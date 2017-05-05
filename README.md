# Jobby, a PHP cron job manager #
[![Total Downloads](https://img.shields.io/packagist/dt/hellogerard/jobby.svg)](https://packagist.org/packages/hellogerard/jobby)
[![Latest Version](https://img.shields.io/packagist/v/hellogerard/jobby.svg)](https://packagist.org/packages/hellogerard/jobby)
[![Build Status](https://img.shields.io/travis/jobbyphp/jobby.svg)](https://travis-ci.org/jobbyphp/jobby)
[![MIT License](https://img.shields.io/packagist/l/hellogerard/jobby.svg)](https://github.com/jobbyphp/jobby/blob/master/LICENSE)

Install the master jobby cron job, and it will manage all your offline tasks. Add jobs without modifying crontab.
Jobby can handle logging, locking, error emails and more.

**NEW REPO:** We have moved `jobby` to a Github org. Please update your remotes to `https://github.com/jobbyphp/jobby.git`.

## Features ##

- Maintain one master crontab job.
- Jobs run via PHP, so you can run them under any programmatic conditions.
- Use ordinary crontab schedule syntax (powered by the excellent [`cron-expression`](<https://github.com/mtdowling/cron-expression>)).
- Run only one copy of a job at a given time.
- Send email whenever a job exits with an error status. 
- Run job as another user, if crontab user has `sudo` privileges.
- Run only on certain hostnames (handy in webfarms).
- Theoretical Windows support (but not ever tested)

## Getting Started ##

### Installation ###

The recommended way to install Jobby is through [Composer](http://getcomposer.org):
```
$ composer require hellogerard/jobby
```

Then add the following line to your (or whomever's) crontab:
```
* * * * * cd /path/to/project && php jobby.php 1>> /dev/null 2>&1
```

After Jobby installs, you can copy an example file to the project root.
```
$ cp vendor/hellogerard/jobby/resources/jobby.php .
```

### Running a job ###

```php
<?php 

// Ensure you have included composer's autoloader  
require_once __DIR__ . '/vendor/autoload.php';

// Create a new instance of Jobby
$jobby = new Jobby\Jobby();

// Every job has a name
$jobby->add('CommandExample', [

    // Run a shell command
    'command'  => 'ls',

    // Ordinary crontab schedule format is supported.
    // This schedule runs every hour.
    'schedule' => '0 * * * *',

]);

$jobby->run();
```

## Examples ##

### Logging ###

```php
<?php

/* ... */

$jobby->add('LoggingExample', [
    
    'command'  => 'ls',
    'schedule' => '0 * * * *',
    
    // Stdout and stderr is sent to the specified file
    'output'   => 'logs/command.log',

]);

/* ... */
```

### Disabling a command ###

```php
<?php

/* ... */

$jobby->add('DisabledExample', [
    
    'command'  => 'ls',
    'schedule' => '0 * * * *',
    
    // You can turn off a job by setting 'enabled' to false
    'enabled'  => false,

]);

/* ... */
```

### Running closures ###

```php
<?php

/* ... */

$jobby->add('ClosureCommandExample', [
    
     // Use the 'closure' key
     // instead of 'command'
    'closure'  => function() {
        echo "I'm a function!\n";
        return true;
    },
    
    'schedule' => '0 * * * *',

]);

/* ... */
```

### Using a DateTime ###

```php
<?php

/* ... */

$jobby->add('DateTimeExample', [
    
    'command'  => 'ls',
    
    // Use a DateTime string in
    // the format Y-m-d H:i:s
    'schedule' => '2017-05-03 17:15:00',

]);

/* ... */
```

### Using a Custom Scheduler ###

```php
<?php

/* ... */

$jobby->add('Example', [
    
    'command'  => 'ls',
    
    // Use any callable that returns
    // a boolean stating whether
    // to run the job or not
    'schedule' => function() {
        // Run on even minutes
        return date('i') % 2 === 0;
    },

]);

/* ... */
```

## Supported Options ##

Each job requires these:

Key       | Type    | Description
:-------- | :------ | :---------------------------------------------------------------------------------------------------------
schedule  | string  | Crontab schedule format (`man -s 5 crontab`) or DateTime format (`Y-m-d H:i:s`) or callable (`function(): Bool { /* ... */ }`)
command   | string  | The shell command to run (exclusive-or with `closure`)
closure   | Closure | The anonymous PHP function to run (exclusive-or with `command`)


The options listed below can be applied to an individual job or globally through the `Jobby` constructor. 
Global options will be used as default values, and individual jobs can override them.

Option         | Type      | Default                             | Description
:------------- | :-------- | :---------------------------------- | :-------------------------------------------------------- 
runAs          | string    | null                                | Run as this user, if crontab user has `sudo` privileges
debug          | boolean   | false                               | Send `jobby` internal messages to 'debug.log'
_**Filtering**_|           |                                     | _**Options to determine whether the job should run or not**_ 
environment    | string    | null or `getenv('APPLICATION_ENV')` | Development environment for this job
runOnHost      | string    | `gethostname()`                     | Run jobs only on this hostname
maxRuntime     | integer   | null                                | Maximum execution time for this job (in seconds)
enabled        | boolean   | true                                | Run this job at scheduled times
haltDir        | string    | null                                | A job will not run if this directory contains a file bearing the job's name 
_**Logging**_  |           |                                     | _**Options for logging**_
output         | string    | /dev/null                           | Redirect `stdout` and `stderr` to this file
dateFormat     | string    | Y-m-d H:i:s                         | Format for dates on `jobby` log messages
_**Mailing**_  |           |                                     | _**Options for emailing errors**_
recipients     | string    | null                                | Comma-separated string of email addresses
mailer         | string    | sendmail                            | Email method: _sendmail_ or _smtp_ or _mail_
smtpHost       | string    | null                                | SMTP host, if `mailer` is smtp
smtpPort       | integer   | 25                                  | SMTP port, if `mailer` is smtp
smtpUsername   | string    | null                                | SMTP user, if `mailer` is smtp
smtpPassword   | string    | null                                | SMTP password, if `mailer` is smtp
smtpSecurity   | string    | null                                | SMTP security option: _ssl_ or _tls_, if `mailer` is smtp
smtpSender     | string    | jobby@&lt;hostname&gt;              | The sender and from addresses used in SMTP notices
smtpSenderName | string    | Jobby                               | The name used in the from field for SMTP messages

## Credits ##

Developed before, but since inspired by [whenever](<https://github.com/javan/whenever>).

[Support this project](https://cash.me/$hellogerard)

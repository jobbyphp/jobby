[![Build Status](https://secure.travis-ci.org/michaelcontento/jobby.png)](http://travis-ci.org/michaelcontento/jobby)

`Jobby` is a PHP cron job manager. Install the master jobby cron job, and it will
manage all your offline tasks. Add jobs without modifying crontab. Jobby can
handle logging, locking, error emails and more.

## Install ##

1. Install [`composer`](<http://getcomposer.org>).
2. Add `jobby` to your `composer.json`.

  `'hellogerard/jobby': 'dev-master'`

3. Run `composer install`.
4. Add the following line to your (or whomever's) crontab:

  `* * * * * cd /path/to/project && php jobby.php 1>> /dev/null 2>&1`

After `jobby` installs, you can copy an example jobby file to the project root.

  `% cp vendor/hellogerard/jobby/resources/jobby.php .`

## Usage ##

### Features ###

- Maintain one master crontab job.
- Jobs run via PHP, so you can run them under any programmatic conditions.
- Use ordinary crontab schedule syntax (powered by the excellent [`cron-expression`](<https://github.com/mtdowling/cron-expression>)).
- Run only one copy of a job at a given time.
- Send email whenever a job exits with an error status. 
- Run job as another user, if crontab user has `sudo` privileges.
- Run only on certain hostnames (handy in webfarms).
- Theoretical Windows support (but not ever tested)

### Currently Supported Options ###

Global options can be given to the `Jobby` object constructor. These will be
used as a default for all subsequent jobs. Individual jobs can override a
particular option when the job is `added`.

<pre>
Option         | Default                             | Required | Description
===============+=====================================+==========+============
               |                                     |          |
recipients     | null                                | No       | Comma-separated string of email addresses
mailer         | sendmail                            | No       | Email method: sendmail or smtp or mail
smtpHost       | null                                | No       | SMTP host, if `mailer` is smtp
smtpPort       | 25                                  | No       | SMTP port, if `mailer` is smtp
smtpUsername   | null                                | No       | SMTP user, if `mailer` is smtp
smtpPassword   | null                                | No       | SMTP password, if `mailer` is smtp
smtpSecurity   | null                                | No       | SMTP security option (ssl|tls), if `mailer` is smtp
smtpSender     | jobby@&lt;hostname&gt;                    | No       | The sender and from addresses used in SMTP notices
smtpSenderName | Jobby                               | No       | The name used in the from field for SMTP messages
runAs          | null                                | No       | Run as this user, if crontab user has `sudo` privileges
environment    | null or `getenv('APPLICATION_ENV')` | No       | Development environment for this job
runOnHost      | `gethostname()`                     | No       | Run jobs only on this hostname
maxRuntime     | null                                | No       | Maximum execution time for this job (in seconds)
output         | /dev/null                           | No       | Redirect `stdout` and `stderr` to this file
dateFormat     | 'Y-m-d H:i:s'                       | No       | Format for dates on `jobby` log messages
enabled        | true                                | No       | Run this job at scheduled times
haltDir        | null                                | No       | A job will not run if this directory contains a file bearing the job's name
debug          | false                               | No       | Send `jobby` internal messages to 'debug.log'
command        | none                                | Yes      | The job to run (either a shell command or anonymous PHP function)
schedule       | none                                | Yes      | Crontab schedule format (`man -s 5 crontab`) or Datetime format
</pre>

### Example `jobby.php` File ###

```php
<?php 

require(__DIR__ . '/vendor/autoload.php');

$jobby = new \Jobby\Jobby();

// Every job has a name
$jobby->add('CommandExample', array(
    // Commands are either shell commands or anonymous functions
    'command' => 'ls',

    // Ordinary crontab schedule format is supported.
    // This schedule runs every hour.
    // You could also insert Datetime string.
    'schedule' => '0 * * * *',

    // Stdout and stderr is sent to the specified file
    'output' => 'logs/command.log',

    // You can turn off a job by setting 'enabled' to false
    'enabled' => true,
));

$jobby->add('ClosureExample', array(
    // Commands can be PHP closures
    'command' => function() {
        echo "I'm a function!\n";
        return true;
    },

    // This function will run every other hour
    'schedule' => '0 */2 * * *',

    'output' => 'logs/closure.log',
    'enabled' => true,
));

$jobby->run();
```

### Paid Support

[![Support and Consulting Services](https://s3-us-west-2.amazonaws.com/supportedsourceassets/buttons/supportandservices1.png)](http://supportedsource.org/consulting-services-and-support/jobby)

### Credits ###

Developed before, but since inspired by [`whenever`](<https://github.com/javan/whenever>).

[Support this project](https://cash.me/$hellogerard)

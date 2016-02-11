<?php

//
// This script demonstrates how to use jobby with a PDO-backend, which is used to
// save the jobby-cronjob/jobbies configuration.
//
// Adapt this file to your needs, copy it to your project-root,
// and add this line to your crontab file:
//
// * * * * * cd /path/to/project && php jobby-pdo.php 1>> /dev/null 2>&1
//

require_once __DIR__ . '/../vendor/autoload.php';

// The table, which shall contain the cronjob-configuration(s).
$dbhJobbiesTableName = 'jobbies';

/*
 * For demo-purposes, an in-memory SQLite database is used.
 *
 * !!! REPLACE WITH YOUR OWN DATASOURCE!!!
 */
$dbh = new PDO('sqlite::memory:');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/*
 * Setup a test-fixture, having two jobs, first one is a system-cmd (date), second one is a Closure
 * (which is saved to pdo-database).
 */

$dbh->exec("
CREATE TABLE IF NOT EXISTS `$dbhJobbiesTableName`
(`name` VARCHAR(255) NOT NULL ,
 `command` TEXT NOT NULL ,
 `schedule` VARCHAR(255) NOT NULL ,
 `mailer` VARCHAR(255) NULL DEFAULT 'sendmail' ,
 `maxRuntime` INT UNSIGNED NULL ,
 `smtpHost` VARCHAR(255) NULL ,
 `smtpPort` SMALLINT UNSIGNED NULL ,
 `smtpUsername` VARCHAR(255) NULL ,
 `smtpPassword` VARCHAR(255) NULL ,
 `smtpSender` VARCHAR(255) NULL DEFAULT 'jobby@localhost' ,
 `smtpSenderName` VARCHAR(255) NULL DEFAULT 'Jobby' ,
 `smtpSecurity` VARCHAR(20) NULL ,
 `runAs` VARCHAR(255) NULL ,
 `environment` TEXT NULL ,
 `runOnHost` VARCHAR(255) NULL ,
 `output` VARCHAR(255) NULL ,
 `dateFormat` VARCHAR(100) NULL DEFAULT 'Y-m-d H:i:s' ,
 `enabled` BOOLEAN NULL DEFAULT TRUE ,
 `haltDir` VARCHAR(255) NULL , `debug` BOOLEAN NULL DEFAULT FALSE ,
 PRIMARY KEY (`name`)
)
");

$insertCronJobConfiguration = $dbh->prepare("
INSERT INTO `$dbhJobbiesTableName`
 (`name`,`command`,`schedule`,`output`)
 VALUES
 (:name,:command,:schedule,:output)
");
// First demo-job - print "date" to logs/command-pdo.log.
$insertCronJobConfiguration->execute(
    ['CommandExample', 'date', '* * * * *', 'logs/command-pdo.log']
);
// Second demo-job - a Closure which does some php::echo(). The Closure is saved to PDO-backend, too.
$secondJobFn = function() {
    echo "I'm a function (" . date('Y-m-d H:i:s') . ')!' . PHP_EOL;
    return true;
};
$serializer = new SuperClosure\Serializer();

$secondJobFnSerialized = $serializer->serialize($secondJobFn);
$insertCronJobConfiguration->execute(
    ['ClosureExample', $secondJobFnSerialized, '* * * * *', 'logs/closure-pdo.log']
);

/*
 * Examples are now set up, and saved to PDO-backend.
 *
 * Now, fetch all jobbies from PDO-backend and run them.
 */

$jobbiesStmt = $dbh->query("SELECT * FROM `$dbhJobbiesTableName`");
$jobbies = $jobbiesStmt->fetchAll(PDO::FETCH_ASSOC);

$jobby = new \Jobby\Jobby();

foreach ($jobbies as $job) {
    // Filter out each value, which is not set (for example, "maxRuntime" is not defined in the job).
    $job = array_filter($job);

    try {
        $job['closure'] = $serializer->unserialize($job['command']);
        unset($job['command']);
    } catch (SuperClosure\Exception\ClosureUnserializationException $e) {
    }

    $jobName = $job['name'];
    unset($job['name']);
    $jobby->add($jobName, $job);
}

$jobby->run();

<?php

//
// Add this line to your crontab file:
//
// * * * * * cd /path/to/project && php jobby.php 1>> /dev/null 2>&1
//

require_once __DIR__ . '/../vendor/autoload.php';

$jobby = new \Jobby\Jobby();

$jobby->add('CommandExample', array(
    'command' => 'ls',
    'schedule' => '* * * * *',
    'output' => 'logs/command.log',
    'enabled' => true,
));

$jobby->add('ClosureExample', array(
    'command' => function() {
        echo "I'm a function!\n";
        return true;
    },
    'schedule' => '* * * * *',
    'output' => 'logs/closure.log',
    'enabled' => true,
));

$jobby->run();

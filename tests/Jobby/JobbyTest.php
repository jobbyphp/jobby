<?php

namespace Jobby\Tests;

use Jobby\Jobby;

/**
 *
 */
class JobbyTest extends \PHPUnit_Framework_TestCase
{
    /**
     *
     */
    public function setUp()
    {
        @unlink('helloworld.log');
    }

    /**
     *
     */
    public function tearDown()
    {
        @unlink('helloworld.log');
    }

    /**
     *
     */
    public function testShell()
    {
        $jobby = new Jobby();
        $jobby->add('HelloWorldShell', array(
            'command' => 'php ' . __DIR__ . '/_files/helloworld.php',
            'schedule' => '* * * * *',
            'output' => 'helloworld.log'
        ));
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertEquals('Hello World!', file_get_contents('helloworld.log'));
    }

    /**
     *
     */
    public function testClosure()
    {
        $jobby = new Jobby();
        $jobby->add('HelloWorldClosure', array(
            'command' => function() {
                echo "A function!";
            },
            'schedule' => '* * * * *',
            'output' => 'helloworld.log',
        ));
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertEquals('A function!', file_get_contents('helloworld.log'));
    }
}

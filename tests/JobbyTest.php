<?php

/**
 *
 */
class JobbyTest extends PHPUnit_Framework_TestCase
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
        $jobby = new \Jobby();
        $jobby->add('HelloWorld', array(
            'command' => 'php ' . __DIR__ . '/helloworld.php',
            'schedule' => '* * * * *',
            'output' => 'helloworld.log',
        ));
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(2);

        $this->assertEquals('Hello World!', file_get_contents('helloworld.log'));
    }

    /**
     *
     */
    public function testClosure()
    {
        $jobby = new \Jobby();
        $jobby->add('HelloWorld', array(
            'command' => function() {
                echo "A function!";
            },
            'schedule' => '* * * * *',
            'output' => 'helloworld.log',
        ));
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(2);

        $this->assertEquals('A function!', file_get_contents('helloworld.log'));
    }
}

<?php

namespace Jobby\Tests;

use Jobby\Jobby;

/**
 *
 */
class JobbyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $logFile;

    /**
     * @return string
     */
    private function getLogContent()
    {
        return file_get_contents($this->logFile);
    }

    /**
     *
     */
    public function setUp()
    {
        $this->logFile = __DIR__ . "/_files/JobbyTest.log";
        @unlink($this->logFile);
    }

    /**
     *
     */
    public function tearDown()
    {
        @unlink($this->logFile);
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
            'output' => $this->logFile
        ));
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertEquals('Hello World!', $this->getLogContent());
    }

    /**
     *
     */
    public function testShellInvalidCommand()
    {
        $jobby = new Jobby();
        $jobby->add('HelloWorldShell', array(
            'command' => 'invalid-command',
            'schedule' => '* * * * *',
            'output' => $this->logFile
        ));
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertContains("invalid-command", $this->getLogContent());
        $this->assertContains("not found", $this->getLogContent());
        $this->assertContains(
            "ERROR: Job exited with status '127'",
            $this->getLogContent()
        );
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
                return true;
            },
            'schedule' => '* * * * *',
            'output' => $this->logFile
        ));
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertEquals('A function!', $this->getLogContent());
    }

    /**
     *
     */
    public function testShouldRunAllJobsAdded()
    {
        $jobby = new Jobby(array(
            'output' => $this->logFile
        ));
        $jobby->add('job-1', array(
            'schedule' => '* * * * *',
            'command' => function() { echo "job-1"; return true; }
        ));
        $jobby->add('job-2', array(
            'schedule' => '* * * * *',
            'command' => function() { echo "job-2"; return true; }
        ));
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertContains('job-1', $this->getLogContent());
        $this->assertContains('job-2', $this->getLogContent());
    }

    /**
     * This is the same test as testClosure but (!) we use the default
     * options to set the output file.
     */
    public function testDefaultOptionsShouldBeMerged()
    {
        $jobby = new Jobby(array('output' => $this->logFile));
        $jobby->add('HelloWorldClosure', array(
            'command' => function() {
                echo "A function!";
                return true;
            },
            'schedule' => '* * * * *'
        ));
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertEquals('A function!', $this->getLogContent());
    }

    /**
     *
     */
    public function testDefaultOptions()
    {
        $jobby = new Jobby();
        $opts = $jobby->getDefaultConfig();

        $this->assertNull($opts["recipients"]);
        $this->assertEquals("sendmail", $opts["mailer"]);
        $this->assertNull($opts["runAs"]);
        $this->assertNUll($opts["output"]);
        $this->assertEquals("Y-m-d H:i:s", $opts["dateFormat"]);
        $this->assertTrue($opts["enabled"]);
        $this->assertFalse($opts["debug"]);
    }

    /**
     *
     */
    public function testSetConfig()
    {
        $jobby = new Jobby();
        $oldCfg = $jobby->getConfig();

        $jobby->setConfig(array("dateFormat" => "foo bar"));
        $newCfg = $jobby->getConfig();

        $this->assertEquals(count($oldCfg), count($newCfg));
        $this->assertEquals("foo bar", $newCfg["dateFormat"]);
    }

    /**
     *
     */
    public function testExceptionOnMissingJobOptionCommand()
    {
        $jobby = new Jobby();

        $this->setExpectedException("Jobby\Exception");
        $jobby->add('should fail', array(
            'schedule' => '* * * * *'
        ));
    }

    /**
     *
     */
    public function testExceptionOnMissingJobOptionSchedule()
    {
        $jobby = new Jobby();

        $this->setExpectedException("Jobby\Exception");
        $jobby->add('should fail', array(
            'command' => function() {}
        ));
    }

    /**
     *
     */
    public function testShouldRunJobsAsync()
    {
        $jobby = new Jobby();
        $jobby->add('HelloWorldClosure', array(
            'command' => function () {
                sleep(2);
                return true;
            },
            'schedule' => '* * * * *'
        ));

        $timeStart = microtime();
        $jobby->run();
        $duration = microtime() - $timeStart;

        $this->assertLessThan(0.5, $duration);
    }
}

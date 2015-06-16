<?php

namespace Jobby\Tests;

use Jobby\Jobby;
use SuperClosure\SerializableClosure;

/**
 * @covers Jobby\Jobby
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
    protected function setUp()
    {
        $this->logFile = __DIR__ . '/_files/JobbyTest.log';
        !file_exists($this->logFile) || unlink($this->logFile);
    }

    /**
     *
     */
    protected function tearDown()
    {
        !file_exists($this->logFile) || unlink($this->logFile);
    }

    /**
     * @covers Jobby\Jobby::add
     * @covers Jobby\Jobby::run
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
     * @covers Jobby\Jobby::add
     * @covers Jobby\Jobby::run
     */
    public function testSuperClosure()
    {
        $fn = static function() {
            echo "Another function!";
            return true;
        };

        $jobby = new Jobby();
        $jobby->add('HelloWorldClosure', array(
            'command' => new SerializableClosure($fn),
            'schedule' => '* * * * *',
            'output' => $this->logFile
        ));
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertEquals('Another function!', $this->getLogContent());
    }

    /**
     * @covers Jobby\Jobby::add
     * @covers Jobby\Jobby::run
     */
    public function testClosure()
    {
        $jobby = new Jobby();
        $jobby->add('HelloWorldClosure', array(
            'command' => static function() {
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
     * @covers Jobby\Jobby::add
     * @covers Jobby\Jobby::run
     */
    public function testShouldRunAllJobsAdded()
    {
        $jobby = new Jobby(array(
            'output' => $this->logFile
        ));
        $jobby->add('job-1', array(
            'schedule' => '* * * * *',
            'command' => static function() { echo "job-1"; return true; }
        ));
        $jobby->add('job-2', array(
            'schedule' => '* * * * *',
            'command' => static function() { echo "job-2"; return true; }
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
            'command' => static function() {
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
     * @covers Jobby\Jobby::getDefaultConfig
     */
    public function testDefaultOptions()
    {
        $jobby = new Jobby();
        $opts = $jobby->getDefaultConfig();

        $this->assertNull($opts["recipients"]);
        $this->assertEquals("sendmail", $opts["mailer"]);
        $this->assertNull($opts["runAs"]);
        $this->assertNull($opts["output"]);
        $this->assertEquals("Y-m-d H:i:s", $opts["dateFormat"]);
        $this->assertTrue($opts["enabled"]);
        $this->assertFalse($opts["debug"]);
    }

    /**
     * @covers Jobby\Jobby::setConfig
     * @covers Jobby\Jobby::getConfig
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
     * @covers Jobby\Jobby::add
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
     * @covers Jobby\Jobby::add
     */
    public function testExceptionOnMissingJobOptionSchedule()
    {
        $jobby = new Jobby();

        $this->setExpectedException("Jobby\Exception");
        $jobby->add('should fail', array(
            'command' => static function() {}
        ));
    }

    /**
     * @covers Jobby\Jobby::run
     * @covers Jobby\Jobby::runWindows
     * @covers Jobby\Jobby::runUnix
     */
    public function testShouldRunJobsAsync()
    {
        $jobby = new Jobby();
        $jobby->add('HelloWorldClosure', array(
            'command' => function () {
                return true;
            },
            'schedule' => '* * * * *'
        ));

        $timeStart = microtime();
        $jobby->run();
        $duration = microtime() - $timeStart;

        $this->assertLessThan(0.5, $duration);
    }

    /**
     *
     */
    public function testShouldFailIfMaxRuntimeExceeded()
    {
        $jobby = new Jobby();
        $jobby->add('slow job', array(
            'command' => 'sleep 4',
            'schedule' => '* * * * *',
            'maxRuntime' => 1,
            'output' => $this->logFile
        ));

        $jobby->run();
        sleep(2);
        $jobby->run();
        sleep(1);

        $this->assertContains(
            "ERROR: MaxRuntime of 1 secs exceeded!",
            $this->getLogContent()
        );
    }
}

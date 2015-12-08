<?php

namespace Jobby\Tests;

use Jobby\Jobby;
use SuperClosure\SerializableClosure;

/**
 * @coversDefaultClass Jobby\Jobby
 */
class JobbyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $logFile;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->logFile = __DIR__ . '/_files/JobbyTest.log';
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    /**
     * @covers ::add
     * @covers ::run
     */
    public function testShell()
    {
        $jobby = new Jobby();
        $jobby->add(
            'HelloWorldShell',
            [
                'command'  => 'php ' . __DIR__ . '/_files/helloworld.php',
                'schedule' => '* * * * *',
                'output'   => $this->logFile,
            ]
        );
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertEquals('Hello World!', $this->getLogContent());
    }

    /**
     * @covers ::add
     * @covers ::run
     */
    public function testSuperClosure()
    {
        $fn = static function () {
            echo 'Another function!';

            return true;
        };

        $jobby = new Jobby();
        $jobby->add(
            'HelloWorldClosure',
            [
                'command'  => new SerializableClosure($fn),
                'schedule' => '* * * * *',
                'output'   => $this->logFile,
            ]
        );
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertEquals('Another function!', $this->getLogContent());
    }

    /**
     * @covers ::add
     * @covers ::run
     */
    public function testClosure()
    {
        $jobby = new Jobby();
        $jobby->add(
            'HelloWorldClosure',
            [
                'command'  => static function () {
                    echo 'A function!';

                    return true;
                },
                'schedule' => '* * * * *',
                'output'   => $this->logFile,
            ]
        );
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertEquals('A function!', $this->getLogContent());
    }

    /**
     * @covers ::add
     * @covers ::run
     */
    public function testShouldRunAllJobsAdded()
    {
        $jobby = new Jobby(['output' => $this->logFile]);
        $jobby->add(
            'job-1',
            [
                'schedule' => '* * * * *',
                'command'  => static function () {
                    echo 'job-1';

                    return true;
                },
            ]
        );
        $jobby->add(
            'job-2',
            [
                'schedule' => '* * * * *',
                'command'  => static function () {
                    echo 'job-2';

                    return true;
                },
            ]
        );
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
        $jobby = new Jobby(['output' => $this->logFile]);
        $jobby->add(
            'HelloWorldClosure',
            [
                'command'  => static function () {
                    echo "A function!";

                    return true;
                },
                'schedule' => '* * * * *',
            ]
        );
        $jobby->run();

        // Job runs asynchronously, so wait a bit
        sleep(1);

        $this->assertEquals('A function!', $this->getLogContent());
    }

    /**
     * @covers ::getDefaultConfig
     */
    public function testDefaultConfig()
    {
        $jobby = new Jobby();
        $config = $jobby->getDefaultConfig();

        $this->assertNull($config['recipients']);
        $this->assertEquals('sendmail', $config['mailer']);
        $this->assertNull($config['runAs']);
        $this->assertNull($config['output']);
        $this->assertEquals('Y-m-d H:i:s', $config['dateFormat']);
        $this->assertTrue($config['enabled']);
        $this->assertFalse($config['debug']);
    }

    /**
     * @covers ::setConfig
     * @covers ::getConfig
     */
    public function testSetConfig()
    {
        $jobby = new Jobby();
        $oldCfg = $jobby->getConfig();

        $jobby->setConfig(['dateFormat' => 'foo bar']);
        $newCfg = $jobby->getConfig();

        $this->assertEquals(count($oldCfg), count($newCfg));
        $this->assertEquals('foo bar', $newCfg['dateFormat']);
    }

    /**
     * @covers ::add
     * @expectedException \Jobby\Exception
     */
    public function testExceptionOnMissingJobOptionCommand()
    {
        $jobby = new Jobby();

        $jobby->add(
            'should fail',
            [
                'schedule' => '* * * * *',
            ]
        );
    }

    /**
     * @covers ::add
     * @expectedException \Jobby\Exception
     */
    public function testExceptionOnMissingJobOptionSchedule()
    {
        $jobby = new Jobby();

        $jobby->add(
            'should fail',
            [
                'command' => static function () {
                },
            ]
        );
    }

    /**
     * @covers ::run
     * @covers ::runWindows
     * @covers ::runUnix
     */
    public function testShouldRunJobsAsync()
    {
        $jobby = new Jobby();
        $jobby->add(
            'HelloWorldClosure',
            [
                'command'  => function () {
                    return true;
                },
                'schedule' => '* * * * *',
            ]
        );

        $timeStart = microtime();
        $jobby->run();
        $duration = microtime() - $timeStart;

        $this->assertLessThan(0.5, $duration);
    }

    public function testShouldFailIfMaxRuntimeExceeded()
    {
        $jobby = new Jobby();
        $jobby->add(
            'slow job',
            [
                'command'    => 'sleep 4',
                'schedule'   => '* * * * *',
                'maxRuntime' => 1,
                'output'     => $this->logFile,
            ]
        );

        $jobby->run();
        sleep(2);
        $jobby->run();
        sleep(2);

        $this->assertContains('ERROR: MaxRuntime of 1 secs exceeded!', $this->getLogContent());
    }

    /**
     * @return string
     */
    private function getLogContent()
    {
        return file_get_contents($this->logFile);
    }
}

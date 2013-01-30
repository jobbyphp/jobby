<?php

namespace Jobby\Tests;

use Jobby\BackgroundJob;

/**
 *
 */
class BackgroundJobTest extends \PHPUnit_Framework_TestCase
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
        return @file_get_contents($this->logFile);
    }

    /**
     *
     */
    public function setUp()
    {
        $this->logFile = __DIR__ . "/_files/BackgroundJobTest.log";
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
    public function testShouldNotRunIfNotEnabled()
    {
        $job = new BackgroundJob("name", array(
            "enabled" => false,
            "output" => $this->logFile
        ));
        $job->run();

        $this->assertEquals("", $this->getLogContent());
    }
}

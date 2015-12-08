<?php

namespace Jobby\Tests;

use Jobby\BackgroundJob;
use Jobby\Helper;
use Jobby\SerializerTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @coversDefaultClass Jobby\BackgroundJob
 */
class BackgroundJobTest extends \PHPUnit_Framework_TestCase
{
    use SerializerTrait;

    const JOB_NAME = 'name';

    /**
     * @var string
     */
    private $logFile;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->logFile = __DIR__ . '/_files/BackgroundJobTest.log';
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

    public function runProvider()
    {
        $echo = function () {
            echo 'test';

            return true;
        };
        $uid = function () {
            echo getmyuid();

            return true;
        };
        $job = ['closure' => $echo];

        return [
            'diabled, not run'       => [$job + ['enabled' => false], ''],
            'cron schedule, not run' => [$job + ['schedule' => '0 0 1 1 *'], ''],
            'date time, not run'     => [$job + ['schedule' => date('Y-m-d H:i:s', strtotime('tomorrow'))], ''],
            'date time, run'         => [$job + ['schedule' => date('Y-m-d H:i:s')], 'test'],
            'wrong host, not run'    => [$job + ['runOnHost' => 'something that does not match'], ''],
            'current user, run,'     => [['closure' => $uid], getmyuid()],
        ];
    }

    /**
     * @dataProvider runProvider
     *
     * @covers ::run
     *
     * @param array  $config
     * @param string $expectedOutput
     */
    public function testRun($config, $expectedOutput)
    {
        $this->runJob($config);

        $this->assertEquals($expectedOutput, $this->getLogContent());
    }

    /**
     * @covers ::runFile
     */
    public function testInvalidCommand()
    {
        $this->runJob(['command' => 'invalid-command']);

        $this->assertContains('invalid-command', $this->getLogContent());
        $this->assertContains('not found', $this->getLogContent());
        $this->assertContains(
            "ERROR: Job exited with status '127'",
            $this->getLogContent()
        );
    }

    /**
     * @covers ::runFunction
     */
    public function testClosureNotReturnTrue()
    {
        $this->runJob(
            [
                'closure' => function () {
                    return false;
                },
            ]
        );

        $this->assertContains(
            'ERROR: Closure did not return true! Returned:',
            $this->getLogContent()
        );
    }

    /**
     * @covers ::getLogFile
     */
    public function testHideStdOutByDefault()
    {
        ob_start();
        $this->runJob(
            [
                'closure' => function () {
                    echo 'foo bar';
                },
                'output'  => null,
            ]
        );
        $content = ob_get_contents();
        ob_end_clean();

        $this->assertEmpty($content);
    }

    /**
     * @covers ::getLogFile
     */
    public function testShouldCreateLogFolder()
    {
        $logfile = dirname($this->logFile) . '/foo/bar.log';
        $this->runJob(
            [
                'closure' => function () {
                    echo 'foo bar';
                },
                'output'  => $logfile,
            ]
        );

        $dirExists = file_exists(dirname($logfile));
        $isDir = is_dir(dirname($logfile));

        unlink($logfile);
        rmdir(dirname($logfile));

        $this->assertTrue($dirExists);
        $this->assertTrue($isDir);
    }

    /**
     * @covers ::mail
     */
    public function testNotSendMailOnMissingRecipients()
    {
        $helper = $this->getMock('Jobby\Helper', ['sendMail']);
        $helper->expects($this->never())
            ->method('sendMail')
        ;

        $this->runJob(
            [
                'closure'    => function () {
                    return false;
                },
                'recipients' => '',
            ],
            $helper
        );
    }

    /**
     * @covers ::mail
     */
    public function testMailShouldTriggerHelper()
    {
        $helper = $this->getMock('Jobby\Helper', ['sendMail']);
        $helper->expects($this->once())
            ->method('sendMail')
        ;

        $this->runJob(
            [
                'closure'    => function () {
                    return false;
                },
                'recipients' => 'test@example.com',
            ],
            $helper
        );
    }

    /**
     * @covers ::checkMaxRuntime
     */
    public function testCheckMaxRuntime()
    {
        $helper = $this->getMock('Jobby\Helper', ['getLockLifetime']);
        $helper->expects($this->once())
            ->method('getLockLifetime')
            ->will($this->returnValue(0))
        ;

        $this->runJob(
            [
                'command'    => 'true',
                'maxRuntime' => 1,
            ],
            $helper
        );

        $this->assertEmpty($this->getLogContent());
    }

    /**
     * @covers ::checkMaxRuntime
     */
    public function testCheckMaxRuntimeShouldFailIsExceeded()
    {
        $helper = $this->getMock('Jobby\Helper', ['getLockLifetime']);
        $helper->expects($this->once())
            ->method('getLockLifetime')
            ->will($this->returnValue(2))
        ;

        $this->runJob(
            [
                'command'    => 'true',
                'maxRuntime' => 1,
            ],
            $helper
        );

        $this->assertContains(
            'MaxRuntime of 1 secs exceeded! Current runtime: 2 secs',
            $this->getLogContent()
        );
    }

    /**
     * @dataProvider haltDirProvider
     * @covers       ::shouldRun
     *
     * @param bool $createFile
     * @param bool $jobRuns
     */
    public function testHaltDir($createFile, $jobRuns)
    {
        $dir = __DIR__ . '/_files';
        $file = $dir . '/' . static::JOB_NAME;

        $fs = new Filesystem();

        if ($createFile) {
            $fs->touch($file);
        }

        $this->runJob(
            [
                'haltDir' => $dir,
                'closure' => function () {
                    echo 'test';

                    return true;
                },
            ]
        );

        if ($createFile) {
            $fs->remove($file);
        }

        $content = $this->getLogContent();
        $this->assertEquals($jobRuns, is_string($content) && !empty($content));
    }

    public function haltDirProvider()
    {
        return [
            [true, false],
            [false, true],
        ];
    }

    /**
     * @param array  $config
     * @param Helper $helper
     */
    private function runJob(array $config, Helper $helper = null)
    {
        $config = $this->getJobConfig($config);

        $job = new BackgroundJob(self::JOB_NAME, $config, $helper);
        $job->run();
    }

    /**
     * @param array $config
     *
     * @return array
     */
    private function getJobConfig(array $config)
    {
        $helper = new Helper();

        if (isset($config['closure'])) {
            $config['closure'] = $this->getSerializer()->serialize($config['closure']);
        }

        return array_merge(
            [
                'enabled'    => 1,
                'haltDir'    => null,
                'runOnHost'  => $helper->getHost(),
                'dateFormat' => 'Y-m-d H:i:s',
                'schedule'   => '* * * * *',
                'output'     => $this->logFile,
                'maxRuntime' => null,
                'runAs'      => null,
            ],
            $config
        );
    }

    /**
     * @return string
     */
    private function getLogContent()
    {
        return @file_get_contents($this->logFile);
    }
}

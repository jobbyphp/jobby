<?php

namespace Jobby\Tests;

use Jobby\Helper;
use Jobby\Jobby;

/**
 * @coversDefaultClass Jobby\Helper
 */
class HelperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var string
     */
    private $tmpDir;

    /**
     * @var string
     */
    private $lockFile;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->helper = new Helper();
        $this->tmpDir = $this->helper->getTempDir();
        $this->lockFile = $this->tmpDir . '/test.lock';
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        unset($_SERVER['APPLICATION_ENV']);
    }

    /**
     * @param string $input
     * @param string $expected
     *
     * @dataProvider dataProviderTestEscape
     */
    public function testEscape($input, $expected)
    {
        $actual = $this->helper->escape($input);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public function dataProviderTestEscape()
    {
        return [
            ['lower', 'lower'],
            ['UPPER', 'upper'],
            ['0123456789', '0123456789'],
            ['with    spaces', 'with_spaces'],
            ['invalid!@#$%^&*()chars', 'invalidchars'],
            ['._-', '._-'],
        ];
    }

    /**
     * @covers ::getPlatform
     */
    public function testGetPlatform()
    {
        $actual = $this->helper->getPlatform();
        $this->assertContains($actual, [Helper::UNIX, Helper::WINDOWS]);
    }

    /**
     * @covers ::getPlatform
     */
    public function testPlatformConstants()
    {
        $this->assertNotEquals(Helper::UNIX, Helper::WINDOWS);
    }

    /**
     * @covers ::acquireLock
     * @covers ::releaseLock
     */
    public function testAquireAndReleaseLock()
    {
        $this->helper->acquireLock($this->lockFile);
        $this->helper->releaseLock($this->lockFile);
        $this->helper->acquireLock($this->lockFile);
        $this->helper->releaseLock($this->lockFile);
    }

    /**
     * @covers ::acquireLock
     * @covers ::releaseLock
     */
    public function testLockFileShouldContainCurrentPid()
    {
        $this->helper->acquireLock($this->lockFile);
        $this->assertEquals(getmypid(), file_get_contents($this->lockFile));

        $this->helper->releaseLock($this->lockFile);
        $this->assertEmpty(file_get_contents($this->lockFile));
    }

    /**
     * @covers ::getLockLifetime
     */
    public function testLockLifetimeShouldBeZeroIfFileDoesNotExists()
    {
        unlink($this->lockFile);
        $this->assertFalse(file_exists($this->lockFile));
        $this->assertEquals(0, $this->helper->getLockLifetime($this->lockFile));
    }

    /**
     * @covers ::getLockLifetime
     */
    public function testLockLifetimeShouldBeZeroIfFileIsEmpty()
    {
        file_put_contents($this->lockFile, '');
        $this->assertEquals(0, $this->helper->getLockLifetime($this->lockFile));
    }

    /**
     * @covers ::getLockLifetime
     */
    public function testLockLifetimeShouldBeZeroIfItContainsAInvalidPid()
    {
        file_put_contents($this->lockFile, 'invalid-pid');
        $this->assertEquals(0, $this->helper->getLockLifetime($this->lockFile));
    }

    /**
     * @covers ::getLockLifetime
     */
    public function testGetLocklifetime()
    {
        $this->helper->acquireLock($this->lockFile);

        $this->assertEquals(0, $this->helper->getLockLifetime($this->lockFile));
        sleep(1);
        $this->assertEquals(1, $this->helper->getLockLifetime($this->lockFile));
        sleep(1);
        $this->assertEquals(2, $this->helper->getLockLifetime($this->lockFile));

        $this->helper->releaseLock($this->lockFile);
    }

    /**
     * @covers ::releaseLock
     * @expectedException \Jobby\Exception
     */
    public function testReleaseNonExistin()
    {
        $this->helper->releaseLock($this->lockFile);
    }

    /**
     * @covers ::acquireLock
     * @expectedException \Jobby\InfoException
     */
    public function testExceptionIfAquireFails()
    {
        $fh = fopen($this->lockFile, 'r+');
        $this->assertTrue(is_resource($fh));

        $res = flock($fh, LOCK_EX | LOCK_NB);
        $this->assertTrue($res);

        $this->helper->acquireLock($this->lockFile);
    }

    /**
     * @covers ::acquireLock
     * @expectedException \Jobby\Exception
     */
    public function testAquireLockShouldFailOnSecondTry()
    {
        $this->helper->acquireLock($this->lockFile);
        $this->helper->acquireLock($this->lockFile);
    }

    /**
     * @covers ::getTempDir
     */
    public function testGetTempDir()
    {
        $valid = [sys_get_temp_dir(), getcwd()];
        foreach (['TMP', 'TEMP', 'TMPDIR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $valid[] = $_SERVER[$key];
            }
        }

        $actual = $this->helper->getTempDir();
        $this->assertContains($actual, $valid);
    }

    /**
     * @covers ::getApplicationEnv
     */
    public function testGetApplicationEnv()
    {
        $_SERVER['APPLICATION_ENV'] = 'foo';

        $actual = $this->helper->getApplicationEnv();
        $this->assertEquals('foo', $actual);
    }

    /**
     * @covers ::getApplicationEnv
     */
    public function testGetApplicationEnvShouldBeNullIfUndefined()
    {
        $actual = $this->helper->getApplicationEnv();
        $this->assertNull($actual);
    }

    /**
     * @covers ::getHost
     */
    public function testGetHostname()
    {
        $actual = $this->helper->getHost();
        $this->assertContains($actual, [gethostname(), php_uname('n')]);
    }

    /**
     * @covers ::sendMail
     * @covers ::getCurrentMailer
     */
    public function testSendMail()
    {
        $mailer = $this->getSwiftMailerMock();
        $mailer->expects($this->once())
            ->method('send')
        ;

        $jobby = new Jobby();
        $config = $jobby->getDefaultConfig();
        $config['output'] = 'output message';
        $config['recipients'] = 'a@a.com,b@b.com';

        $helper = new Helper($mailer);
        $mail = $helper->sendMail('job', $config, 'message');

        $host = $helper->getHost();
        $email = "jobby@$host";
        $this->assertContains('job', $mail->getSubject());
        $this->assertContains("[$host]", $mail->getSubject());
        $this->assertEquals(1, count($mail->getFrom()));
        $this->assertEquals('jobby', current($mail->getFrom()));
        $this->assertEquals($email, current(array_keys($mail->getFrom())));
        $this->assertEquals($email, current(array_keys($mail->getSender())));
        $this->assertContains($config['output'], $mail->getBody());
        $this->assertContains('message', $mail->getBody());
    }

    /**
     * @return \Swift_Mailer
     */
    private function getSwiftMailerMock()
    {
        return $this->getMock('Swift_Mailer', [], [\Swift_NullTransport::newInstance()]);
    }
}

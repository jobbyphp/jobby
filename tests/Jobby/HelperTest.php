<?php

namespace Jobby\Tests;

use Jobby\Helper;
use Jobby\Jobby;

/**
 * @covers Jobby\Helper
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
     *
     */
    protected function setUp()
    {
        $this->helper = new Helper();
        $this->tmpDir = $this->helper->getTempDir();
    }

    /**
     *
     */
    protected function tearDown()
    {
        unset($_SERVER["APPLICATION_ENV"]);
    }

    /**
     * @return \Swift_Mailer
     */
    private function getSwiftMailerMock()
    {
        return $this->getMock(
            "Swift_Mailer",
            array(),
            array(\Swift_NullTransport::newInstance())
        );
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
        return array(
            array("lower", "lower"),
            array("UPPER", "upper"),
            array("0123456789", "0123456789"),
            array("with    spaces", "with_spaces"),
            array("invalid!@#$%^&*()chars", "invalidchars"),
            array("._-", "._-")
        );
    }

    /**
     * @covers Jobby\Helper::closureToString
     */
    public function testClosureToString()
    {
        $closure = function ($args) { return $args . "bar"; };

        $serialized = $this->helper->closureToString($closure);

        /** @var \Closure $actual */
        $actual = @unserialize($serialized);
        $actual = $actual("foo");

        $expected = $closure("foo");

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Jobby\Helper::getPlatform
     */
    public function testGetPlatform()
    {
        $actual = $this->helper->getPlatform();
        $this->assertContains($actual, array(Helper::UNIX, Helper::WINDOWS));
    }

    /**
     * @covers Jobby\Helper::getPlatform
     */
    public function testPlatformConstants()
    {
        $this->assertNotEquals(Helper::UNIX, Helper::WINDOWS);
    }

    /**
     * @covers Jobby\Helper::acquireLock
     * @covers Jobby\Helper::releaseLock
     */
    public function testAquireAndReleaseLock()
    {
        $lockFile = $this->tmpDir . "/test.lock";

        $this->helper->acquireLock($lockFile);
        $this->helper->releaseLock($lockFile);
        $this->helper->acquireLock($lockFile);
        $this->helper->releaseLock($lockFile);
    }

    /**
     * @covers Jobby\Helper::acquireLock
     * @covers Jobby\Helper::releaseLock
     */
    public function testLockFileShouldContainCurrentPid()
    {
        $lockFile = $this->tmpDir . "/test.lock";

        $this->helper->acquireLock($lockFile);
        $this->assertEquals(getmypid(), file_get_contents($lockFile));

        $this->helper->releaseLock($lockFile);
        $this->assertEquals("", file_get_contents($lockFile));
    }

    /**
     * @covers Jobby\Helper::getLockLifetime
     */
    public function testLockLifetimeShouldBeZeroIfFileDoesNotExists()
    {
        $lockFile = $this->tmpDir . "/test.lock";
        unlink($lockFile);
        $this->assertFalse(file_exists($lockFile));
        $this->assertEquals(0, $this->helper->getLockLifetime($lockFile));
    }

    /**
     * @covers Jobby\Helper::getLockLifetime
     */
    public function testLockLifetimeShouldBeZeroIfFileIsEmpty()
    {
        $lockFile = $this->tmpDir . "/test.lock";
        file_put_contents($lockFile, "");
        $this->assertEquals(0, $this->helper->getLockLifetime($lockFile));
    }

    /**
     * @covers Jobby\Helper::getLockLifetime
     */
    public function testLockLifetimeShouldBeZeroIfItContainsAInvalidPid()
    {
        $lockFile = $this->tmpDir . "/test.lock";
        file_put_contents($lockFile, "invalid-pid");
        $this->assertEquals(0, $this->helper->getLockLifetime($lockFile));
    }

    /**
     * @covers Jobby\Helper::getLockLifetime
     */
    public function testGetLocklifetime()
    {
        $lockFile = $this->tmpDir . "/test.lock";

        $this->helper->acquireLock($lockFile);

        $this->assertEquals(0, $this->helper->getLockLifetime($lockFile));
        sleep(1);
        $this->assertEquals(1, $this->helper->getLockLifetime($lockFile));
        sleep(1);
        $this->assertEquals(2, $this->helper->getLockLifetime($lockFile));

        $this->helper->releaseLock($lockFile);
    }

    /**
     * @covers Jobby\Helper::releaseLock
     */
    public function testReleaseNonExistin()
    {
        $lockFile = $this->tmpDir . "/test.lock";

        $this->setExpectedException("Jobby\\Exception");
        $this->helper->releaseLock($lockFile);
    }

    /**
     * @covers Jobby\Helper::acquireLock
     */
    public function testExceptionIfAquireFails()
    {
        $lockFile = $this->tmpDir . "/test.lock";

        $fh = fopen($lockFile, "r+");
        $this->assertTrue(is_resource($fh));

        $res = flock($fh, LOCK_EX | LOCK_NB);
        $this->assertTrue($res);

        $this->setExpectedException("Jobby\\InfoException");
        $this->helper->acquireLock($lockFile);
    }

    /**
     * @covers Jobby\Helper::acquireLock
     */
    public function testAquireLockShouldFailOnSecondTry()
    {
        $lockFile = $this->tmpDir . "/test.lock";
        $this->helper->acquireLock($lockFile);

        $this->setExpectedException("Jobby\\Exception");
        $this->helper->acquireLock($lockFile);
    }

    /**
     * @covers Jobby\Helper::getTempDir
     */
    public function testGetTempDir()
    {
        $valid = array(sys_get_temp_dir(), getcwd());
        foreach (array("TMP", "TEMP", "TMPDIR") as $key) {
            if (!empty($_SERVER[$key])) {
                $valid[] = $_SERVER[$key];
            }
        }

        $actual = $this->helper->getTempDir();
        $this->assertContains($actual, $valid);
    }

    /**
     * @covers Jobby\Helper::getApplicationEnv
     */
    public function testGetApplicationEnv()
    {
        $_SERVER["APPLICATION_ENV"] = "foo";

        $actual = $this->helper->getApplicationEnv();
        $this->assertEquals("foo", $actual);
    }

    /**
     * @covers Jobby\Helper::getApplicationEnv
     */
    public function testGetApplicationEnvShouldBeNullIfUndefined()
    {
        $actual = $this->helper->getApplicationEnv();
        $this->assertNull($actual);
    }

    /**
     * @covers Jobby\Helper::getHost
     */
    public function testGetHostname()
    {
        $actual = $this->helper->getHost();
        $this->assertContains($actual, array(gethostname(), php_uname("n")));
    }

    /**
     * @covers Jobby\Helper::sendMail
     * @covers Jobby\Helper::getCurrentMailer
     */
    public function testSendMail()
    {
        $mailer = $this->getSwiftMailerMock();
        $mailer->expects($this->once())
            ->method("send");

        $jobby  = new Jobby();
        $config = $jobby->getDefaultConfig();
        $config["output"]     = "output message";
        $config["recipients"] = "a@a.com,b@b.com";

        $helper = new Helper($mailer);
        $mail = $helper->sendMail("job", $config, "message");

        $host = $helper->getHost();
        $email = "jobby@$host";
        $this->assertContains("job", $mail->getSubject());
        $this->assertContains("[$host]", $mail->getSubject());
        $this->assertEquals(1, count($mail->getFrom()));
        $this->assertEquals("jobby", current($mail->getFrom()));
        $this->assertEquals($email, current(array_keys($mail->getFrom())));
        $this->assertEquals($email, current(array_keys($mail->getSender())));
        $this->assertContains($config["output"], $mail->getBody());
        $this->assertContains("message", $mail->getBody());
    }
}

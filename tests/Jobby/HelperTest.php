<?php

namespace Jobby\Tests;

use Jobby\Helper;

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
    public function setUp()
    {
        $this->helper = new Helper();
        $this->tmpDir = $this->helper->getTempDir();
    }

    /**
     *
     */
    public function tearDown()
    {
        unset($_SERVER["APPLICATION_ENV"]);
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
        $actual = $this->helper->closureToString(
            function ($args) { return "bar"; }
        );

        $expected = 'function ($args) { return "bar"; }';
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
     * @covers Jobby\Helper::aquireLock
     * @covers Jobby\Helper::releaseLock
     */
    public function testAquireAndReleaseLock()
    {
        $lockFile = $this->tmpDir . "/test.lock";

        $this->helper->aquireLock($lockFile);
        $this->helper->releaseLock($lockFile);
        $this->helper->aquireLock($lockFile);
        $this->helper->releaseLock($lockFile);
    }

    /**
     * @covers Jobby\Helper::releaseLock
     */
    public function testReleaseNonExistin()
    {
        $lockFile = $this->tmpDir . "/test.lock";

        $this->setExpectedException("Jobby\Exception");
        $this->helper->releaseLock($lockFile);
    }

    /**
     * @covers Jobby\Helper::aquireLock
     */
    public function testExceptionIfAquireFails()
    {
        $lockFile = $this->tmpDir . "/test.lock";

        $fh = fopen($lockFile, "r+");
        $this->assertTrue(is_resource($fh));

        $res = flock($fh, LOCK_EX | LOCK_NB);
        $this->assertTrue($res);

        $this->setExpectedException("Jobby\Exception");
        $this->helper->aquireLock($lockFile);
    }

    /**
     * @covers Jobby\Helper::aquireLock
     */
    public function testAquireLockShouldFailOnSecondTry()
    {
        $lockFile = $this->tmpDir . "/test.lock";
        $this->helper->aquireLock($lockFile);

        $this->setExpectedException("Jobby\Exception");
        $this->helper->aquireLock($lockFile);
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
}

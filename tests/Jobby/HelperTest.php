<?php

namespace Jobby\Tests;

use Jobby\Helper;

/**
 *
 */
class HelperTest extends \PHPUnit_Framework_TestCase
{
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
        $helper = new Helper();
        $actual = $helper->escape($input);
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
     *
     */
    public function testClosureToString()
    {
        $helper = new Helper();
        $actual = $helper->closureToString(function ($args) { return "bar"; });

        $expected = 'function ($args) { return "bar"; }';
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     */
    public function testGetPlatform()
    {
        $helper = new Helper();
        $actual = $helper->getPlatform();

        $this->assertContains($actual, array(Helper::UNIX, Helper::WINDOWS));
    }

    /**
     *
     */
    public function testPlatformConstants()
    {
        $this->assertNotEquals(Helper::UNIX, Helper::WINDOWS);
    }

    /**
     *
     */
    public function testGetTempDir()
    {
        $valid = array(sys_get_temp_dir(), getcwd());
        foreach (array("TMP", "TEMP", "TMPDIR") as $key) {
            if (!empty($_SERVER[$key])) {
                $valid[] = $_SERVER[$key];
            }
        }

        $helper = new Helper();
        $actual = $helper->getTempDir();

        $this->assertContains($actual, $valid);
    }

    /**
     *
     */
    public function testGetApplicationEnv()
    {
        $_SERVER["APPLICATION_ENV"] = "foo";

        $helper = new Helper();
        $actual = $helper->getApplicationEnv();

        $this->assertEquals("foo", $actual);
    }

    /**
     *
     */
    public function testGetApplicationEnvShouldBeNullIfUndefined()
    {
        $helper = new Helper();
        $actual = $helper->getApplicationEnv();

        $this->assertNull($actual);
    }

    /**
     *
     */
    public function testGetHostname()
    {
        $helper = new Helper();
        $actual = $helper->getHost();

        $this->assertContains($actual, array(gethostname(), php_uname("n")));
    }
}

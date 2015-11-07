<?php

namespace Jobby\Tests;

use Jobby\Exception;

/**
 * @covers Jobby\Exception
 */
class ExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testInheritsBaseException()
    {
        $e = new Exception();
        $this->assertTrue($e instanceof \Exception);
    }
}

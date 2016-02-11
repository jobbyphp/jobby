<?php

namespace Jobby\Tests;

class SerializerTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSerializer()
    {
        $mock = $this->getObjectForTrait('Jobby\SerializerTrait');
        $method = new \ReflectionMethod($mock, 'getSerializer');
        $method->setAccessible(true);

        $serializer = $method->invoke($mock);
        $this->assertInstanceOf('\SuperClosure\Serializer', $serializer);

        $serializer2 = $method->invoke($mock);
        $this->assertSame($serializer, $serializer2);
    }
}

<?php

namespace Jobby;

use SuperClosure\Serializer;

trait SerializerTrait
{
    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @return Serializer
     */
    protected function getSerializer()
    {
        if ($this->serializer === null) {
            $this->serializer = new Serializer();
        }

        return $this->serializer;
    }
}

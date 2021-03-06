<?php

namespace Netgen\Bundle\RemoteMediaBundle\Tests\Exception;

use Netgen\Bundle\RemoteMediaBundle\Exception\MimeCategoryParseException;
use PHPUnit\Framework\TestCase;

class MimeCategoryParseExceptionTest extends TestCase
{
    /**
     * @expectedException \Netgen\Bundle\RemoteMediaBundle\Exception\MimeCategoryParseException
     * @expectedExceptionMessage Could not parse mime category for given mime type: mimetype.
     */
    public function testException()
    {
        throw new MimeCategoryParseException('mimetype');
    }
}

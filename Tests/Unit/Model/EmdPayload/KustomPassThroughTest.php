<?php

namespace Fatchip\FcKustom\Tests\Unit\Model\EmdPayload;


use Fatchip\FcKustom\Model\EmdPayload\KustomPassThrough;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

/**
 * Class KustomPassThroughTest
 * @package Fatchip\FcKustom\Tests\Unit\Models\EmdPayload
 * @covers \Fatchip\FcKustom\Model\EmdPayload\KustomPassThrough
 */
class KustomPassThroughTest extends ModuleUnitTestCase
{

    public function testGetPassThroughField()
    {
        $passThrough = new KustomPassThrough();

        $this->assertEquals('To be implemented by the merchant.', $passThrough->getPassThroughField());
    }
}

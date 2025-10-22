<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller\Admin;


use Fatchip\FcKustom\Controller\Admin\KustomPaymentMain;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomPaymentMainTest extends ModuleUnitTestCase
{

    /**
     * @dataProvider renderDataProvider
     */
    public function testRender($id, $expected)
    {
        $stub = $this->getMockBuilder(KustomPaymentMain::class)->setMethods(['getEditObjectid'])->getMock();
        $stub->expects($this->any())->method('getEditObjectid')->willReturn($id);
        $stub->render();
        $result = $stub->getViewData()['isKustomPayment'];
        $this->assertEquals($expected, $result);


    }

    public function renderDataProvider()
    {
        return [
            ['kustom_checkout', true],
            ['test', false],
        ];

    }
}

<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller\Admin;

use OxidEsales\Eshop\Core\Module\Module;
use Fatchip\FcKustom\Controller\Admin\KustomStart;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;
use OxidEsales\Eshop\Core\UtilsObject;

class KustomStartTest extends ModuleUnitTestCase
{

    public function testRender()
    {
        $start = oxNew(KustomStart::class);
        $result = $start->render();
        $this->assertEquals('@fckustom/admin/fckustom_start', $result);

    }

    public function testGetKustomModuleInfo()
    {
        $module = $this->getMockBuilder(Module::class)->setMethods(['getInfo'])->getMock();
        $module->expects($this->once())
            ->method('getInfo')
            ->willReturn('1');

        UtilsObject::setClassInstance(Module::class, $module);
        $start = oxNew(KustomStart::class);
        $result = $start->getKustomModuleInfo();

        $this->assertEquals(' VERSION 1', $result);
    }
}

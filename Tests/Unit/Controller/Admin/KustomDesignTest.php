<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller\Admin;


use OxidEsales\Eshop\Application\Model\Actions;
use OxidEsales\Eshop\Core\Registry;
use Fatchip\FcKustom\Controller\Admin\KustomDesign;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomDesignTest extends ModuleUnitTestCase
{

    public function testRender()
    {
        $obj    = new KustomDesign();
        $result = $obj->render();

        $viewData = $obj->getViewData();
        $this->assertEquals('@fckustom/admin/fckustom_design', $result);
        $this->assertEquals('de_de', $viewData['locale']);

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        putenv("HTTP_X_REQUESTED_WITH=xmlhttprequest");
        $obj    = $this->getMockBuilder(KustomDesign::class)->setMethods(['getMultiLangData'])->getMock();
        $obj->expects($this->once())->method('getMultiLangData')->willReturn('test');
        $result = $obj->render();
        $this->assertEquals('"test"', $result);

    }
}

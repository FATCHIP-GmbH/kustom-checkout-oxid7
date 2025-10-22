<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller\Admin;


use Fatchip\FcKustom\Controller\Admin\KustomGeneral;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomGeneralTest extends ModuleUnitTestCase
{

    public function testRender()
    {
        $general = new KustomGeneral();
        $result = $general->render();
        $this->assertEquals("@fckustom/admin/fckustom_general", $result);

        $expected = ['test' => 'test'];
        $notSet = ['notSet' => 'test'];
        $this->setProtectedClassProperty($general, '_aKustomCountryCreds', $expected);
        $this->setProtectedClassProperty($general, '_aKustomCountries', $notSet);

        $general->render();

        $viewData = $general->getViewData();

        $this->assertEquals(json_encode($notSet), $viewData['fckustom_countryList']);
        $this->assertEquals($expected, $viewData['fckustom_countryCreds']);
        $this->assertEquals($notSet, $viewData['fckustom_notSetUpCountries']);

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        putenv("HTTP_X_REQUESTED_WITH=xmlhttprequest");
        $general = $this->getMockBuilder(KustomGeneral::class)->setMethods(['getMultiLangData'])->getMock();
        $general->expects($this->once())->method('getMultiLangData')->willReturn('test');
        $result = $general->render();
        $this->assertEquals('"test"', $result);
    }

    public function testConvertNestedParams()
    {
        $notSet = ['DE' => 'test'];
        $expected = [
            'aKustomCreds_test' => ['key' => 'test'],
        ];
        $methodReflection = new \ReflectionMethod(KustomGeneral::class, 'convertNestedParams');
        $methodReflection->setAccessible(true);

        $general = $this->getMockBuilder(KustomGeneral::class)->setMethods(['removeConfigKeys'])->getMock();
        $general->expects($this->any())->method('removeConfigKeys')->willReturn(null);
        $this->setProtectedClassProperty($general, '_aKustomCountries', $notSet);
        $result = $methodReflection->invokeArgs($general, ['nestedArray' => $expected]);

        $this->assertEquals(['aKustomCreds_test' => 'key => test'], $result);

        $result = $methodReflection->invokeArgs($general, ['nestedArray' => 'invalid']);
        $this->assertEquals('invalid', $result);
    }
}

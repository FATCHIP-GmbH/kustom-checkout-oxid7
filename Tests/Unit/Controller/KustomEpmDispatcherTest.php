<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller;


use OxidEsales\Eshop\Core\ViewConfig;
use Fatchip\FcKustom\Controller\KustomEpmDispatcher;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomEpmDispatcherTest extends ModuleUnitTestCase
{

    public function testAmazonLogin()
    {
        $view = $this->getMockBuilder(ViewConfig::class)->setMethods(['getAmazonProperty', 'getAmazonConfigValue', 'getModuleUrl'])->getMock();
        $view->expects($this->once())->method('getAmazonProperty')->willReturn('https://widgetUrl');
        $view->expects($this->once())->method('getAmazonConfigValue')->willReturn('test');
        $view->expects($this->once())->method('getModuleUrl')->willReturn('https://moduleUrl');
        $epmDispatcher = $this->getMockBuilder(KustomEpmDispatcher::class)->setMethods(['init', 'getViewConfig'])->getMock();
        $epmDispatcher->expects($this->once())->method('getViewConfig')->willReturn($view);
        $epmDispatcher->amazonLogin();
        $result = $this->getProtectedClassProperty($epmDispatcher, '_aViewData');
        $expected = [
            'sAmazonWidgetUrl' => 'https://widgetUrl',
            'sAmazonSellerId' => 'test',
            'sModuleUrl' => 'https://moduleUrl'
        ];
        $this->assertEquals($expected, $result);
    }
}

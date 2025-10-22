<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller;


use OxidEsales\Eshop\Core\Registry;
use Fatchip\FcKustom\Controller\KustomBasketController;
use Fatchip\FcKustom\Model\KustomBasket;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomBasketControllerTest extends ModuleUnitTestCase
{

    public function testRender()
    {
        $basket = $this->getMockBuilder(KustomBasket::class)->setMethods(['getPaymentId'])->getMock();
        $basket->expects($this->once())->method('getPaymentId')->willReturn('kustom_checkout');
        $session = Registry::getSession();
        $session->setBasket($basket);
        $this->setRequestParameter('openAmazonLogin', true);
        $this->setRequestParameter('kustomInvalid', true);
        $basketController = $this->getMockBuilder(KustomBasketController::class)->setMethods(['displayKustomValidationErrors'])->getMock();
        $basketController->expects($this->once())->method('displayKustomValidationErrors')->willReturn(true);
        $result = $basketController->render();
        $this->assertEquals('page/checkout/basket', $result);
    }
}

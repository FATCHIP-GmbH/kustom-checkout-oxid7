<?php

namespace Fatchip\FcKustom\Testes\Unit\Controllers;


use OxidEsales\Eshop\Application\Controller\ThankYouController;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\BasketItem;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use Fatchip\FcKustom\Controller\KustomThankYouController;
use Fatchip\FcKustom\Core\KustomCheckoutClient;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use Fatchip\FcKustom\Model\KustomInstantBasket;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

/**
 * Class KustomThankYouControllerTest
 * @covers \Fatchip\FcKustom\Controller\KustomThankYouController
 * @package Fatchip\FcKustom\Testes\Unit\Controllers
 */
class KustomThankYouControllerTest extends ModuleUnitTestCase
{
    public function testSimpleRender()
    {
        $oBasketItem = oxNew(BasketItem::class);
        $this->setProtectedClassProperty($oBasketItem,'_sProductId', '_testArt');
        $oBasket = $this->getMockBuilder(Basket::class)->setMethods(['getContents', 'getProductsCount', 'getOrderId'])->getMock();
        $oBasket->expects($this->once())->method('getContents')->will($this->returnValue(array($oBasketItem)));
        $oBasket->expects($this->once())->method('getProductsCount')->will($this->returnValue(1));
        $oBasket->expects($this->once())->method('getOrderId')->will($this->returnValue(1));

        $controller = $this->getMockBuilder(KustomThankYouController::class)->
        setMethods(['getNewKustomInstantBasket'])->getMock();

        $this->setProtectedClassProperty($controller, '_oBasket', $oBasket);

        $result = $controller->render();

        $expected = $this->getProtectedClassProperty($controller, '_sThisTemplate');

        $this->assertSame($expected, $result);
    }
}

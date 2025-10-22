<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller;


use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\UtilsObject;
use OxidEsales\Eshop\Core\ViewConfig;
use OxidEsales\Eshop\Application\Model\User;
use Fatchip\FcKustom\Controller\KustomAjaxController;
use Fatchip\FcKustom\Core\KustomCheckoutClient;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Model\KustomBasket;
use Fatchip\FcKustom\Model\KustomUser;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomAjaxControllerTest extends ModuleUnitTestCase {

    public function testInit() {
        $ajaxController = $this->getMockBuilder(KustomAjaxController::class)->setMethods(['getKustomCheckoutClient'])->getMock();
        $ajaxController->init();
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        putenv("HTTP_X_REQUESTED_WITH=xmlhttprequest");
        $ajaxController = $this->getMockBuilder(KustomAjaxController::class)->setMethods(['getKustomCheckoutClient'])->getMock();
        $ajaxController->init();
        $this->assertEquals('Invalid payment ID', \oxUtilsHelper::$response);
        $oBasket = $this->getMockBuilder(KustomBasket::class)->setMethods(['getPaymentId'])->getMock();
        $oBasket->expects($this->any())->method('getPaymentId')->willReturn('kustom_checkout');
        $session = Registry::getSession();
        $session->setBasket($oBasket);


        $client = $this->getMockBuilder(KustomCheckoutClient::class)->setMethods(['getOrder'])->getMock();
        $client->expects($this->any())->method('getOrder')->willThrowException(new KustomClientException('test', 404));
        $ajaxController = $this->getMockBuilder(KustomAjaxController::class)->setMethods(['getKustomCheckoutClient'])->getMock();
        $ajaxController->expects($this->once())->method('getKustomCheckoutClient')->willReturn($client);
        $result = $ajaxController->init();
        $expected = '{"action":"init","status":"restart needed","data":null}';
        $this->assertEquals($expected, $result);
        $this->assertNull($this->getProtectedClassProperty($ajaxController, '_aOrderData'));


        $oOrder = ['test1', 'test2'];
        $client = $this->getMockBuilder(KustomCheckoutClient::class)->setMethods(['getOrder'])->getMock();
        $client->expects($this->once())->method('getOrder')->willReturn($oOrder);
        $ajaxController = $this->getMockBuilder(KustomAjaxController::class)->setMethods(['getKustomCheckoutClient'])->getMock();
        $ajaxController->expects($this->once())->method('getKustomCheckoutClient')->willReturn($client);
        \oxUtilsHelper::$response = '';
        $ajaxController->init();
        $result = $this->getProtectedClassProperty($ajaxController, '_aOrderData');
        $this->assertEquals($oOrder, $result);
        $this->assertEquals('', \oxUtilsHelper::$response);


        $oOrder = ['test1', 'test2', 'status' => 'checkout_complete'];
        $client = $this->getMockBuilder(KustomCheckoutClient::class)->setMethods(['getOrder'])->getMock();
        $client->expects($this->once())->method('getOrder')->willReturn($oOrder);
        $ajaxController = $this->getMockBuilder(KustomAjaxController::class)->setMethods(['getKustomCheckoutClient'])->getMock();
        $ajaxController->expects($this->once())->method('getKustomCheckoutClient')->willReturn($client);
        $ajaxController->init();
        $this->assertEquals('{"action":"ajax","status":"read_only","data":null}', \oxUtilsHelper::$response);

    }

    public function testGetKustomCheckoutClient() {
        $ajaxController = new KustomAjaxController();
        $result = $ajaxController->getKustomCheckoutClient();
        $this->assertInstanceOf(KustomCheckoutClient::class, $result);
    }

    /**
     * @dataProvider vouchersdataProvider
     * @param $method
     * @throws \oxSystemComponentException
     */
    public function testVouchers($method) {
        $this->setRequestParameter('voucherNr', '1');
        $this->setRequestParameter('voucherId', '1');
        $ajaxController = new KustomAjaxController();
        $ajaxController->$method();
        $result = $ajaxController->getViewData()['aIncludes'];
        $expected = [
            'vouchers' => "@fckustom/checkout/inc/fckustom_checkout_voucher_data",
            'error'    => "@fckustom/checkout/inc/fckustom_checkout_voucher_data",
        ];

        $this->assertEquals($expected, $result);

    }

    public function vouchersdataProvider() {
        return [
            ['addVoucher'],
            ['removeVoucher'],
        ];
    }

    public function testSetKustomDeliveryAddress() {
        $this->setRequestParameter('kustom_address_id', '1');
        $ajaxController = new KustomAjaxController();
        $ajaxController->setKustomDeliveryAddress();

        $deladrid = $this->getSessionParam('deladrid');
        $this->assertEquals($deladrid, '1');
        $blshowshipaddress = $this->getSessionParam('blshowshipaddress');
        $this->assertEquals($blshowshipaddress, 1);

        $orderId = $this->getSessionParam('kustom_checkout_order_id');
        $this->assertNull($orderId);
    }

    public function test_initUser()
    {
        $user = oxNew(\OxidEsales\Eshop\Application\Model\User::class);
        // make username unique (avoid the UNIQUE (OXSHOPID, OXUSERNAME) collision)
        $user->oxuser__oxusername = new \OxidEsales\Eshop\Core\Field(
            'unit-' . bin2hex(random_bytes(6)) . '@local',
            \OxidEsales\Eshop\Core\Field::T_RAW
        );
        // (optional but harmless) ensure shop id explicitly
        $user->oxuser__oxshopid = new \OxidEsales\Eshop\Core\Field('1', \OxidEsales\Eshop\Core\Field::T_RAW);

        $viewConfig = $this->getMockBuilder(\OxidEsales\Eshop\Application\Component\Widget\WidgetController::class) // or your concrete ViewConfig class
        ->setMethods(['isUserLoggedIn'])
            ->getMock();
        $viewConfig->expects($this->once())->method('isUserLoggedIn')->willReturn(false);

        $ajaxController = $this->getMockBuilder(\Fatchip\FcKustom\Controller\KustomAjaxController::class)
            ->setMethods(['getUser', 'getViewConfig'])
            ->getMock();
        $ajaxController->expects($this->once())->method('getUser')->willReturn($user);
        $ajaxController->expects($this->once())->method('getViewConfig')->willReturn($viewConfig);

        $ajaxController->initUser();
        $result = $this->getProtectedClassProperty($user, '_type');
        $this->assertEquals(2, $result);

        // second branch
        $viewConfig = $this->getMockBuilder(\OxidEsales\Eshop\Application\Component\Widget\WidgetController::class)
            ->setMethods(['isUserLoggedIn'])
            ->getMock();
        $viewConfig->expects($this->once())->method('isUserLoggedIn')->willReturn(true);

        $ajaxController = $this->getMockBuilder(\Fatchip\FcKustom\Controller\KustomAjaxController::class)
            ->setMethods(['getUser', 'getViewConfig'])
            ->getMock();
        $ajaxController->expects($this->once())->method('getUser')->willReturn($user);
        $ajaxController->expects($this->once())->method('getViewConfig')->willReturn($viewConfig);

        $ajaxController->initUser();
        $result = $this->getProtectedClassProperty($user, '_type');
        $this->assertEquals(3, $result);
    }
}

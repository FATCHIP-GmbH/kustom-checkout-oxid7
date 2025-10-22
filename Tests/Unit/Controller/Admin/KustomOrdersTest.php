<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller\Admin;


use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\DisplayError;
use OxidEsales\Eshop\Core\Exception\ExceptionToDisplay;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use Fatchip\FcKustom\Controller\Admin\KustomOrders;
use Fatchip\FcKustom\Core\KustomOrderManagementClient;
use Fatchip\FcKustom\Core\Exception\KustomCaptureNotAllowedException;
use Fatchip\FcKustom\Core\Exception\KustomOrderNotFoundException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use Fatchip\FcKustom\Model\KustomOrder;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;
use OxidEsales\Eshop\Core\UtilsObject;

class KustomOrdersTest extends ModuleUnitTestCase {
    /**
     * @param int $oxstorno
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function setOrder($oxstorno = 0) {
        $order = $this->getMockBuilder(
            Order::class)->setMethods(
            ['load', 'save', 'getTotalOrderSum', 'getNewOrderLinesAndTotals', 'updateKustomOrder', 'captureKustomOrder']
        )->getMock();
        $order->expects($this->any())->method('load')->willReturn(true);
        $order->expects($this->any())->method('save')->willReturn(true);
        $order->expects($this->any())->method('getTotalOrderSum')->willReturn(100);
        $order->expects($this->any())->method('getNewOrderLinesAndTotals')->willReturn(['order_lines' => true]);
        $order->expects($this->any())->method('updateKustomOrder')->willReturn('test');
        $order->expects($this->any())->method('captureKustomOrder')->willReturn(true);
        $order->oxorder__oxstorno = new Field($oxstorno, Field::T_RAW);
        $order->oxorder__oxpaymenttype = new Field('kustom_checkout', Field::T_RAW);
        $order->oxorder__fckustom_merchantid = new Field('smid', Field::T_RAW);
        $order->oxorder_oxbillcountryid = new Field('a7c40f631fc920687.20179984', Field::T_RAW);
        UtilsObject::setClassInstance(Order::class, $order);

        return $order;
    }

    /**
     * @dataProvider exceptionDataProvider
     * @param $exception
     * @param $expected
     */
    public function testRenderExceptions($exception, $expected) {
        $this->setOrder();
        $controller = $this->getMockBuilder(
            KustomOrders::class)->setMethods(
            ['authorize', 'getEditObjectId', 'retrieveKustomOrder', 'isCredentialsValid']
        )->getMock();
        $controller->expects($this->any())->method('authorize')->willReturn(true);
        $controller->expects($this->any())->method('isCredentialsValid')->willReturn(true);
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->any())->method('retrieveKustomOrder')->will($this->throwException($exception));

        $controller->render();
        $result = $controller->getViewData()['unauthorizedRequest'];


        if ($expected == 'test') {
            $result = unserialize($this->getSessionParam('Errors')['default'][0]);
            $this->assertInstanceOf(ExceptionToDisplay::class, $result);

            $result = $result->getOxMessage();
        }

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function exceptionDataProvider() {
        return [
            [
                new KustomWrongCredentialsException(),
                'KUSTOM_UNAUTHORIZED_REQUEST',
            ],
            [
                new KustomOrderNotFoundException(),
                'KUSTOM_ORDER_NOT_FOUND',
            ],
            [
                new KustomCaptureNotAllowedException(),
                'Diese Bestellung konnte bei Kustom im System nicht gefunden werden. Änderungen an den Bestelldaten werden daher nicht an Kustom übertragen.',
            ],
            [new StandardException('test'), 'test'],
        ];
    }

    /**
     *
     */
    public function testRender() {
        $this->setOrder();
        $orderMain = $this->getMockBuilder(KustomOrders::class)->setMethods(['isKustomOrder', 'getEditObjectId'])->getMock();
        $orderMain->expects($this->once())->method('isKustomOrder')->willReturn(false);
        $orderMain->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $orderMain->render();
        $warningMessage = $orderMain->getViewData()['sMessage'];
        $this->assertEquals('Dieser Reiter betrifft nur Kustom Bestellungen', $warningMessage);

        $orderMain = $this->getMockBuilder(KustomOrders::class)->setMethods(['isKustomOrder', 'getEditObjectId'])->getMock();
        $orderMain->expects($this->once())->method('isKustomOrder')->willReturn(true);
        $orderMain->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $result = $orderMain->render();
        $this->assertEquals('@fckustom/admin/fckustom_orders', $result);
        $warningMessage = $orderMain->getViewData()['wrongCredentials'];
        $this->assertEquals('<strong>Wrong credentials!</strong> This order has been placed using <strong>smid</strong> merchant id. Currently configured merchant id for <strong></strong> is <strong></strong>.',
            $warningMessage
        );

        $order = $this->setOrder(1);
        $orderData = [
            'order_amount' => 10000,
            'status'       => 'CANCELLED',
            'refunds'      => 'refunds',
            'captures'     => 'test',
        ];
        $orderMain = $this->getMockBuilder(KustomOrders::class)->setMethods(['isKustomOrder', 'getEditObjectId', 'isCredentialsValid', 'retrieveKustomOrder'])->getMock();
        $orderMain->expects($this->once())->method('isKustomOrder')->willReturn(true);
        $orderMain->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $orderMain->expects($this->once())->method('isCredentialsValid')->willReturn(true);
        $orderMain->expects($this->once())->method('retrieveKustomOrder')->willReturn($orderData);
        $orderMain->render();
        $viewData = $orderMain->getViewData();
        $this->assertTrue($viewData['cancelled']);
        $this->assertTrue($viewData['inSync']);
        $this->assertEquals(1, $order->oxorder__fckustom_sync->value);
        $this->assertEquals($viewData['aRefunds'], 'refunds');
        $this->assertEquals($viewData['sKustomRef'], ' - ');
        $this->assertEmpty($viewData['aCaptures']);

        $order = $this->setOrder();
        $orderMain = $this->getMockBuilder(KustomOrders::class)->setMethods(['isKustomOrder', 'getEditObjectId', 'isCredentialsValid', 'retrieveKustomOrder'])->getMock();
        $orderMain->expects($this->once())->method('isKustomOrder')->willReturn(true);
        $orderMain->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $orderMain->expects($this->once())->method('isCredentialsValid')->willReturn(true);
        $orderMain->expects($this->once())->method('retrieveKustomOrder')->willReturn(['status' => 'CANCELLED']);
        $orderMain->render();
        $this->assertEquals(0, $order->oxorder__fckustom_sync->value);
    }

    /**
     *
     */
    public function testGetKustomPortalLink() {
        $order = $this->setOrder();
        $order->oxorder__fckustom_servermode = new Field('playground', Field::T_RAW);
        $order->oxorder__fckustom_orderid = new Field('id', Field::T_RAW);
        $expected = sprintf(KustomOrders::KUSTOM_PORTAL_PLAYGROUND_URL, 'id');
        $controller = $this->getMockBuilder(KustomOrders::class)->setMethods(['getEditObjectId'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $result = $controller->getKustomPortalLink();
        $this->assertEquals($expected, $result);
        $order->oxorder__fckustom_servermode = new Field('test', Field::T_RAW);
        $expected = sprintf(KustomOrders::KUSTOM_PORTAL_LIVE_URL, 'id');
        $result = $controller->getKustomPortalLink();
        $this->assertEquals($expected, $result);
    }

    /**
     *
     */
    public function testFormatPrice() {
        $order = $this->setOrder();
        $order->oxorder__oxcurrency = new Field('€', Field::T_RAW);
        $controller = $this->getMockBuilder(KustomOrders::class)->setMethods(['getEditObjectId'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $result = $controller->formatPrice(100);
        $this->assertEquals("1,00 €", $result);

    }

    /**
     *
     */
    public function testRetrieveKustomOrder() {
        $this->setOrder();
        $controller = $this->getMockBuilder(KustomOrders::class)->setMethods(['getEditObjectId'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $this->expectException(KustomWrongCredentialsException::class);
        $this->expectExceptionMessage('');
        $controller->retrieveKustomOrder();
    }

    /**
     *
     */
    public function testFormatCaptures() {
        $controller = $this->getMockBuilder(KustomOrders::class)->setMethods(['getEditObjectId'])->getMock();
        $result = $controller->formatCaptures([['captured_at' => '2018']]);

        $this->assertArrayHasKey('captured_at', $result[0]);
    }

    /**
     *
     */
    public function testRefundFullOrder() {
        $this->setOrder();

        $client = $this->getMockBuilder(KustomOrderManagementClient::class)->setMethods(['createOrderRefund'])->getMock();
        $client->expects($this->any())->method('createOrderRefund')->willReturn('test');
        $controller = $this->getMockBuilder(KustomOrders::class)->setMethods(['getEditObjectId', 'getKustomMgmtClient'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->any())->method('getKustomMgmtClient')->willReturn($client);
        $this->setProtectedClassProperty($controller, 'client', $client);
        $controller->refundFullOrder();
        $result = $this->getSession()->getVariable('testorderRefund');
        $this->assertEquals('test', $result);

        $client = $this->getMockBuilder(KustomOrderManagementClient::class)->setMethods(['createOrderRefund'])->getMock();
        $client->expects($this->any())->method('createOrderRefund')->will($this->throwException(new \Exception('testException')));
        $controller = $this->getMockBuilder(KustomOrders::class)->setMethods(['getEditObjectId', 'getKustomMgmtClient'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->any())->method('getKustomMgmtClient')->willReturn($client);
        $this->setProtectedClassProperty($controller, 'client', $client);
        $controller->refundFullOrder();
        $result = $this->getSession()->getVariable('testorderRefund');

        $this->assertNull($result);
    }

    /**
     *
     */
    public function testCaptureFullOrder() {
        $order = $this->setOrder();
        $order->oxorder__fckustom_orderid = new Field('id', Field::T_RAW);
        $controller = $this->getMockBuilder(KustomOrders::class)->setMethods(['getEditObjectId'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->captureFullOrder();
        $this->assertEquals(new Field(1), $order->oxorder__fckustom_sync);

        $order->expects($this->any())->method('captureKustomOrder')->willThrowException(new StandardException('test'));
        $controller = $this->getMockBuilder(KustomOrders::class)->setMethods(['getEditObjectId'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->captureFullOrder();
        $result = unserialize($this->getSessionParam('Errors')['default'][0]);
        $this->assertInstanceOf(DisplayError::class, $result);
        $result = $result->getOxMessage();
        $this->assertEquals('test', $result);
    }

    /**
     * @dataProvider cancelOrderDataProvider
     */
    public function testCancelOrder($data, $expectedResult) {
        $cancelKustomOrder = $data['cancelKustomOrder'];
        $methods = [
            'getId'         => 'test',
            'isLoaded'      => $data['isLoaded'],
            'isKustomOrder' => $data['isKustomOrder'],
            'getFieldData'  => $data['getFieldData'],
            'save'          => true,
        ];
        $setMethods = array_keys($methods);
        $setMethods[] = 'cancelKustomOrder';
        $oOrder = $this->getMockBuilder(KustomOrder::class)->setMethods($setMethods)->getMock();
        foreach ($methods as $method => $return) {
            $oOrder->expects($this->any())->method($method)->willReturn($return);
        }
        if ($cancelKustomOrder === 'test' || $cancelKustomOrder === 'Order is canceled.') {
            $oOrder->expects($this->any())->method('cancelKustomOrder')->willThrowException(new StandardException($cancelKustomOrder));
        } else {
            $oOrder->expects($this->any())->method('cancelKustomOrder')->willReturn($cancelKustomOrder);
        }
        $controller = $this->getMockBuilder(KustomOrders::class)->setMethods(['getEditObject', 'resetCache'])->getMock();
        $controller->expects($this->once())->method('getEditObject')->willReturn($oOrder);
//        $controller->expects($this->once())->method('resetCache')->willReturn(true);
        $controller->cancelOrder();
        $result = $this->getSession()->getVariable($oOrder->getId() . 'orderCancel');
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function cancelOrderDataProvider() {
        return [
            [
                ['isLoaded' => true, 'isKustomOrder' => true, 'getFieldData' => false, 'cancelKustomOrder' => true],
                true,
            ],
            [
                ['isLoaded' => false, 'isKustomOrder' => true, 'getFieldData' => false, 'cancelKustomOrder' => true],
                false,
            ],
            [
                ['isLoaded' => true, 'isKustomOrder' => true, 'getFieldData' => false, 'cancelKustomOrder' => 'test'],
                false,
            ],
            [
                ['isLoaded' => true, 'isKustomOrder' => true, 'getFieldData' => false, 'cancelKustomOrder' => 'Order is canceled.'],
                true,
            ],
        ];
    }

    /**
     * @dataProvider isOrderCancellationInSyncDataProvider
     * @param $oxstorno
     * @param $expectedResult
     */
    public function testIsOrderCancellationInSync($oxstorno, $expectedResult) {
        $Order = $this->getMockBuilder(KustomOrder::class)->setMethods(['getFieldData'])->getMock();
        $Order->expects($this->once())->method('getFieldData')->willReturn($oxstorno);
        $controller = $this->getMockBuilder(KustomOrders::class)->setMethods(['getEditObject', 'getViewDataElement'])->getMock();
        $controller->expects($this->once())->method('getEditObject')->willReturn($Order);
        $controller->expects($this->once())->method('getViewDataElement')->willReturn('asdf');
        $result = $controller->isOrderCancellationInSync();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function isOrderCancellationInSyncDataProvider() {
        return [
            [1, false],
            [0, true],
        ];
    }
}

<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller\Admin;


use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use Fatchip\FcKustom\Core\KustomOrderManagementClient;
use Fatchip\FcKustom\Core\Exception\KustomOrderNotFoundException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;
use OxidEsales\Eshop\Core\UtilsObject;
use OxidEsales\Eshop\Application\Controller\Admin\OrderMain;

class KustomOrderMainTest extends ModuleUnitTestCase {

    public function testInit() {
        $order = $this->setOrder();

        $controller = $this->getMockBuilder(OrderMain::class)
            ->setMethods(['authorize', 'getEditObjectId', 'isCredentialsValid'])->getMock();
        $controller->expects($this->once())->method('authorize')->willReturn(true);
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->once())->method('isCredentialsValid')->willReturn(false);

        $controller->init();
        $this->assertEquals(new Field(0), $order->oxorder__fckustom_sync);

        $controller = $this->getMockBuilder(OrderMain::class)
            ->setMethods(['authorize', 'getEditObjectId', 'isCredentialsValid', 'retrieveKustomOrder'])->getMock();
        $controller->expects($this->once())->method('authorize')->willReturn(true);
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->once())->method('isCredentialsValid')->willReturn(true);
        $controller->expects($this->once())->method('retrieveKustomOrder')->willReturn(['status' => 'CANCEL']);
        $controller->init();
        $this->assertEquals(new Field(0), $order->oxorder__fckustom_sync);

        $orderData = [
            'order_amount'                => 10000,
            'remaining_authorized_amount' => 10000,
            'status'                      => 'AUTHORIZED',
        ];
        $controller = $this->getMockBuilder(OrderMain::class)
            ->setMethods(['authorize', 'getEditObjectId', 'isCredentialsValid', 'retrieveKustomOrder'])->getMock();
        $controller->expects($this->once())->method('authorize')->willReturn(true);
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->once())->method('isCredentialsValid')->willReturn(true);
        $controller->expects($this->once())->method('retrieveKustomOrder')->willReturn($orderData);

        $controller->init();

        $this->assertEquals(new Field(1), $order->oxorder__fckustom_sync);

    }

    /**
     * @dataProvider exceptionDataProvider
     * @param $exception
     * @param $expected
     */
    public function testInitExceptions($exception, $expected) {
        $this->setOrder();
        $controller = $this->getMockBuilder(OrderMain::class)
            ->setMethods(['authorize', 'getEditObjectId', 'retrieveKustomOrder', 'isCredentialsValid'])->getMock();
        $controller->expects($this->any())->method('authorize')->willReturn(true);
        $controller->expects($this->any())->method('isCredentialsValid')->willReturn(true);
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->any())->method('retrieveKustomOrder')->will($this->throwException($exception));

        $controller->init();
        $result = $controller->getViewDataElement('sErrorMessage');
        $this->assertEquals($expected, $result);
    }

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
            [new StandardException('test'), 'test'],

        ];
    }

    protected function setOrder($oxstorno = 0) {
        $order = $this->getMockBuilder(Order::class)
            ->setMethods(['load', 'save', 'getTotalOrderSum', 'getNewOrderLinesAndTotals', 'updateKustomOrder', 'captureKustomOrder'])
            ->getMock();
        $order->expects($this->any())->method('load')->willReturn(true);
        $order->expects($this->any())->method('save')->willReturn(true);
        $order->expects($this->any())->method('getTotalOrderSum')->willReturn(100);
        $order->expects($this->any())->method('getNewOrderLinesAndTotals')->willReturn(true);
        $order->expects($this->any())->method('updateKustomOrder')->willReturn('test');
        $order->expects($this->any())->method('captureKustomOrder')->willReturn(true);
        $order->oxorder__oxstorno = new Field($oxstorno, Field::T_RAW);
        $order->oxorder__oxpaymenttype = new Field('kustom_checkout', Field::T_RAW);
        $order->oxorder__fckustom_merchantid = new Field('smid', Field::T_RAW);
        $order->oxorder_oxbillcountryid = new Field('a7c40f631fc920687.20179984', Field::T_RAW);
        UtilsObject::setClassInstance(Order::class, $order);

        return $order;
    }

    public function testRender() {
        $order = $this->setOrder();
        $orderMain = $this->getMockBuilder(OrderMain::class)->setMethods(['isKustomOrder'])->getMock();
        $orderMain->expects($this->once())->method('isKustomOrder')->willReturn(true);
        $result = $orderMain->render();
        $this->assertEquals('order_main', $result);
        $warningMessage = $orderMain->getViewDataElement('sWarningMessage');
        $this->assertEquals($warningMessage, '<strong>Wrong credentials!</strong> This order has been placed using <strong>smid</strong> merchant id. Currently configured merchant id for <strong></strong> is <strong></strong>.');

        $this->setRequestParameter('fnc', false);
        $orderMain = $this->getMockBuilder(OrderMain::class)->setMethods(['isKustomOrder', 'isCredentialsValid'])->getMock();
        $orderMain->expects($this->once())->method('isKustomOrder')->willReturn(true);
        $orderMain->expects($this->once())->method('isCredentialsValid')->willReturn(true);
        $this->setProtectedClassProperty($orderMain, 'kustomOrderData', ['status' => 'CANCELLED']);
        $orderMain->render();
        $warningMessage = $orderMain->getViewDataElement('sWarningMessage');
        $this->assertEquals("Die Bestellung wurde storniert. Ihre Änderungen an dieser Bestellung werden nicht an Kustom übertragen.", $warningMessage);
        $this->assertEquals(new Field(0), $order->oxorder__fckustom_sync);

        $orderMain = $this->getMockBuilder(OrderMain::class)->setMethods(['isKustomOrder', 'isCredentialsValid'])->getMock();
        $orderMain->expects($this->once())->method('isKustomOrder')->willReturn(true);
        $orderMain->expects($this->once())->method('isCredentialsValid')->willReturn(true);
        $this->setProtectedClassProperty($orderMain, 'kustomOrderData', ['order_amount' => 1]);
        $orderMain->render();
        $warningMessage = $orderMain->getViewDataElement('sWarningMessage');
        $this->assertEquals(
            '<strong>Achtung!</strong> Die Daten dieser Bestellung weichen von den bei Kustom gespeicherten Daten ab. Ihre Änderungen an dieser Bestellung werden nicht an Kustom übertragen.',
            $warningMessage
        );
        $this->assertEquals(new Field(0), $order->oxorder__fckustom_sync);
        $orderMain = $this->getMockBuilder(OrderMain::class)->setMethods(['isKustomOrder', 'isCredentialsValid', 'isCaptureInSync'])->getMock();
        $orderMain->expects($this->once())->method('isKustomOrder')->willReturn(true);
        $orderMain->expects($this->once())->method('isCredentialsValid')->willReturn(true);
        $orderMain->expects($this->once())->method('isCaptureInSync')->willReturn(true);
        $this->setProtectedClassProperty($orderMain, 'kustomOrderData', ['order_amount' => 10000]);
        $orderMain->render();
        $this->assertEquals(new Field(1), $order->oxorder__fckustom_sync);

    }

    /**
     * @dataProvider exceptionDataProvider
     * @param $exception
     * @param $expected
     */
    public function testRenderExceptions($exception, $expected) {
        $this->setOrder();
        $this->setRequestParameter('fnc', 'test');
        $controller = $this->getMockBuilder(OrderMain::class)
            ->setMethods(['isKustomOrder', 'isCredentialsValid', 'retrieveKustomOrder'])->getMock();
        $controller->expects($this->any())->method('isKustomOrder')->willReturn(true);
        $controller->expects($this->any())->method('isCredentialsValid')->willReturn(true);
        $controller->expects($this->any())->method('retrieveKustomOrder')->will($this->throwException($exception));
        $controller->render();
        $result = $controller->getViewDataElement('sErrorMessage');
        $this->assertEquals($expected, $result);
    }


    public function testSendorder() {
        $this->setOrder(1);
        $controller = $this->getMockBuilder(OrderMain::class)->setMethods(['getEditObjectId'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->sendOrder();
        $result = $controller->getViewDataElement('sErrorMessage');
        $this->assertEquals(' Die Bestellung konnte nicht abgebucht werden, da sie bereits storniert wurde.', $result);


        $order = $this->setOrder(0);
        $order->oxorder__fckustom_sync = new Field(1, Field::T_RAW);
        $controller = $this->getMockBuilder(OrderMain::class)->setMethods(['getEditObjectId', 'retrieveKustomOrder'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->any())->method('retrieveKustomOrder')->willReturn('test');
        $this->setProtectedClassProperty($controller, 'kustomOrderData', ['remaining_authorized_amount' => 1]);
        $controller->sendorder();
        $kustomOrder = $this->getProtectedClassProperty($controller, 'kustomOrderData');
        $result = $controller->getViewDataElement('sMessage');
        $error = $controller->getViewDataElement('sErrorMessage');
        $this->assertEmpty($error);
        $this->assertEquals('test', $kustomOrder);
        $this->assertEquals('Der Betrag wurde erfolgreich abgebucht.', $result);


        $order = $this->setOrder();
        $order->oxorder__fckustom_sync = new Field(1, Field::T_RAW);
        $order->expects($this->once())->method('captureKustomOrder')->will($this->throwException(new StandardException('test')));
        $controller = $this->getMockBuilder(OrderMain::class)->setMethods(['getEditObjectId', 'retrieveKustomOrder'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->any())->method('retrieveKustomOrder')->willReturn('test');
        $this->setProtectedClassProperty($controller, 'kustomOrderData', ['remaining_authorized_amount' => 1]);
        $controller->sendOrder();

        $error = $controller->getViewDataElement('sErrorMessage');
        $this->assertEquals('test', $error);
    }

    /**
     * @dataProvider captureInSyncDataProvider
     * @param $kustomOrderData
     * @param $expected
     * @param bool $withOrder
     */
    public function testIsCaptureInSync($kustomOrderData, $expected, $withOrder = false) {

        $controller = $this->getMockBuilder(OrderMain::class)->setMethods(['getEditObjectId'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        if ($withOrder) {
            $order = $this->setOrder();
            $order->oxorder__oxsenddate = new Field('-', Field::T_RAW);
            UtilsObject::setClassInstance(Order::class, $order);
        }

        $result = $controller->isCaptureInSync($kustomOrderData);
        $this->assertEquals($expected, $result);
    }

    public function captureInSyncDataProvider() {
        $kustomOrderData1['status'] = 'TEST';
        $kustomOrderData2['status'] = 'PART_CAPTURED';
        $kustomOrderData3['status'] = 'AUTHORIZED';

        return [
            [$kustomOrderData1, true],
            [$kustomOrderData2, true],
            [$kustomOrderData2, false, true],
            [$kustomOrderData3, true],

        ];
    }

    public function testSave() {
        $order = $this->setOrder();
        $order->oxorder__oxdiscount = new Field(10, Field::T_RAW);
        $order->oxorder__oxordernr = new Field(1, Field::T_RAW);
        $order->oxorder__fckustom_sync = new Field(1, Field::T_RAW);
        $order->oxorder__fckustom_orderid = new Field('1', Field::T_RAW);

        $client = $this->getMockBuilder(KustomOrderManagementClient::class)->setMethods(['sendOxidOrderNr'])->getMock();
        $client->expects($this->any())->method('sendOxidOrderNr')->willReturn(true);
        $this->setRequestParameter('editval', 11);
        $controller = $this->getMockBuilder(OrderMain::class)->setMethods(['getEditObjectId', 'isKustomOrder', 'getKustomMgmtClient'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->any())->method('isKustomOrder')->willReturn(true);
        $controller->expects($this->any())->method('getKustomMgmtClient')->willReturn($client);
        $this->setProtectedClassProperty($controller, 'kustomOrderData', ['captured_amount' => 1]);
        $controller->save();
        $result = $controller->getViewDataElement('sErrorMessage');
        $this->assertEquals('Achtung! Aufgrund des aktuellen Bestellstatus kann diese Anpassung nicht an Kustom übermittelt werden. Führen Sie die entsprechende Anpassung ggf. direkt im Kustom Merchant Portal von Hand durch.', $result);


        $this->setProtectedClassProperty($controller, 'kustomOrderData', ['captured_amount' => 0]);
        $this->setProtectedClassProperty($controller, 'client', $client);
        $controller->save();
        $result = $controller->getViewDataElement('sErrorMessage');
        $this->assertEquals('test', $result);


        $controller = $this->getMockBuilder(OrderMain::class)->setMethods(['getEditObjectId', 'isKustomOrder', 'discountChanged'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->any())->method('isKustomOrder')->willReturn(true);
        $controller->expects($this->any())->method('discountChanged')->willReturn(false);
        $client = $this->getMockBuilder(KustomOrderManagementClient::class)->setMethods(['sendOxidOrderNr', 'addShippingToCapture'])->getMock();
        $client->expects($this->any())->method('sendOxidOrderNr')->willReturn(true);
        $client->expects($this->any())->method('sendOxidOrderNr')->willReturn(true);
        $client->expects($this->any())->method('addShippingToCapture')->will(
            $this->throwException(new StandardException())
        );
        $this->setProtectedClassProperty($controller, 'client', $client);
        $this->setProtectedClassProperty($controller, 'kustomOrderData', ['captured_amount' => 1]);
        $this->setRequestParameter('editval', ['oxorder__oxtrackcode' => 1]);
        $controller->save();
        $result = $controller->getViewDataElement('sErrorMessage');
        $this->assertEquals('Achtung! Aufgrund des aktuellen Bestellstatus kann diese Anpassung nicht an Kustom übermittelt werden. Führen Sie die entsprechende Anpassung ggf. direkt im Kustom Merchant Portal von Hand durch.', $result);
    }

    public function testRetrieveKustomOrder() {
        $this->setOrder();
        $controller = $this->getMockBuilder(OrderMain::class)->setMethods(['getEditObjectId'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $this->expectException(KustomWrongCredentialsException::class);
        $this->expectExceptionMessage('');
        $controller->retrieveKustomOrder();
    }
}
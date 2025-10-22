<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller\Admin;


use OxidEsales\Eshop\Application\Controller\Admin\OrderList;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\ExceptionToDisplay;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;
use OxidEsales\Eshop\Core\UtilsObject;

class KustomOrderListTest extends ModuleUnitTestCase {
    protected function setOrder($exception = null) {
        $order = $this->getMockBuilder(Order::class)->setMethods(['isLoaded', 'cancelKustomOrder', 'save', 'isKustomOrder', 'isDerived', 'delete'])->getMock();
        $order->expects($this->any())->method('isLoaded')->willReturn(true);
        $order->expects($this->any())->method('save')->willReturn(true);
        $order->expects($this->any())->method('isKustomOrder')->willReturn(true);
        $order->expects($this->any())->method('isDerived')->willReturn(true);
        $order->expects($this->any())->method('delete')->willReturn(true);

        if ($exception) {
            $order->expects($this->any())->method('cancelKustomOrder')->willThrowException($exception);
        } else {
            $order->expects($this->any())->method('cancelKustomOrder')->willReturn(true);
        }
        $order->oxorder_oxbillcountryid = new Field('a7c40f631fc920687.20179984', Field::T_RAW);
        $order->oxorder__fckustom_orderid = new Field('1', Field::T_RAW);
        UtilsObject::setClassInstance(Order::class, $order);

        return $order;
    }

    /**
     * @dataProvider stornoAndDeleteDataProvider
     * @param $method
     */
    public function testStornoAndDelete($method) {
        $order = $this->setOrder();
        $controller = $this->getMockBuilder(OrderList::class)->setMethods(['getEditObjectId', 'cancelOrder', 'init'])->getMock();
        $controller->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $controller->expects($this->any())->method('cancelOrder')->willReturn(true);
        $controller->expects($this->once())->method('init')->willReturn(true);

        $this->assertNull($order->oxorder__fckustom_sync);
        $controller->$method();
        $this->assertEquals(new Field(1), $order->oxorder__fckustom_sync);

        $mockException = $this->getMockBuilder(StandardException::class)
            ->setConstructorArgs(['test'])
            ->getMock();
        $this->setOrder($mockException);
        $controller->$method();

        $result = unserialize($this->getSessionParam('Errors')['default'][0]);
        $this->assertInstanceOf(ExceptionToDisplay::class, $result);
        $this->assertEquals('test', $result->getOxMessage());

    }

    public function stornoAndDeleteDataProvider() {
        return [
            ['deleteEntry'],
        ];

    }
}

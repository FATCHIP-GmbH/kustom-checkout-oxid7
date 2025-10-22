<?php

namespace Fatchip\FcKustom\Tests\Unit\Component;

use OxidEsales\Eshop\Application\Component\BasketComponent;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Registry;
use Fatchip\FcKustom\Component\KustomBasketComponent;
use Fatchip\FcKustom\Core\KustomCheckoutClient;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;
use OxidEsales\Eshop\Application\Component\BasketComponent as BaseBasketComponent;

/**
 * Class KustomBasketComponentTest
 * @package Fatchip\FcKustom\Tests\Unit\Components
 */
class KustomBasketComponentTest extends ModuleUnitTestCase {

    protected function setUp(): void {
        parent::setUp();
    }

    /** returns basket component ready to call 'tobasket' on it
     * @param array $aStubMethods skip internally included array('_getItems', '_setLastCallFnc', '_addItems', 'getConfig')
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function getBasketComponentMock($aStubMethods = array()) {
        $aProducts = array(
            'sProductId' => array(
                'am'           => 10,
                'sel'          => null,
                'persparam'    => null,
                'override'     => 0,
                'basketitemid' => ''
            )
        );

        /** @var \oxBasketItem|\PHPUnit_Framework_MockObject_MockObject $oBItem */
        $oBItem = $this->getMockBuilder(\OxidEsales\Eshop\Application\Model\BasketItem::class)
            ->setMethods(array('getTitle', 'getProductId', 'getAmount', 'getdBundledAmount'))
            ->getMock();
        $oBItem->expects($this->once())->method('getTitle')->will($this->returnValue('ret:getTitle'));
        $oBItem->expects($this->once())->method('getProductId')->will($this->returnValue('ret:getProductId'));
        $oBItem->expects($this->once())->method('getAmount')->will($this->returnValue('ret:getAmount'));
        $oBItem->expects($this->once())->method('getdBundledAmount')->will($this->returnValue('ret:getdBundledAmount'));

        /** @var \oxConfig|\PHPUnit_Framework_MockObject_MockObject $oConfig */
        $oConfig = $this->getMockBuilder(\OxidEsales\Eshop\Core\Config::class)
            ->setMethods(array('getConfigParam'))
            ->getMock();
        $oConfig->expects($this->at(0))->method('getConfigParam')->with($this->equalTo('iNewBasketItemMessage'))->will($this->returnValue('2'));
        $oConfig->expects($this->at(1))->method('getConfigParam')->with($this->equalTo('iNewBasketItemMessage'))->will($this->returnValue('2'));

        /** @var \oxcmp_basket|\PHPUnit_Framework_MockObject_MockObject $o */
        $stubList = array_merge($aStubMethods, array('_getItems', '_setLastCallFnc', '_addItems', 'getConfig'));
        $o = $this->getMockBuilder(\OxidEsales\Eshop\Application\Component\BasketComponent::class)
            ->setMethods($stubList)
            ->getMock();
        $o->expects($this->once())->method('_getItems')->will($this->returnValue($aProducts));
        $o->expects($this->once())->method('_setLastCallFnc')->with($this->equalTo('tobasket'))->will($this->returnValue(null));
        $o->expects($this->once())->method('_addItems')->with($this->equalTo($aProducts))->will($this->returnValue($oBItem));
        $o->expects($this->exactly(2))->method('getConfig')->will($this->returnValue($oConfig));

        return $o;
    }

    public function testChangebasket_kcoModeOn()
    {
        $klSessionId = 'fakeSessionId';
        $this->setSessionParam('kustom_checkout_order_id', $klSessionId);

        $this->assertTrue(
            Registry::getSession()->hasVariable('kustom_checkout_order_id'),
            'Expected session key not set'
        );

        $resolved = \oxNew(BasketComponent::class);
        $this->assertSame(
            \Fatchip\FcKustom\Component\KustomBasketComponent::class,
            get_class($resolved),
            'Module not in class chain — activate the module first'
        );

        // Let OXID resolve the extended class (builds the _parent alias)
        $resolved = \oxNew(BaseBasketComponent::class);      // e.g. returns KustomBasketComponent
        $resolvedClass = get_class($resolved);

        // Partial mock the *resolved* class and intercept the protected call
        $cmpBasket = $this->getMockBuilder($resolvedClass)
            ->setMethods(['updateKustomOrder'])   // keep your older PHPUnit style
            ->getMock();

        $cmpBasket->expects($this->once())
            ->method('updateKustomOrder');

        $cmpBasket->changebasket('abc', 11, 'sel', 'persparam', 'override');
    }

    public function testChangebasket_kcoModeOn_exception() {
        $klMode = 'KCO';
        $klSessionId = 'fakeSessionId';
        $this->getConfig()->saveShopConfVar('str', 'sKustomActiveMode', $klMode, $shopId = $this->getShopId(), $module = 'module:fckustom');
        $this->setSessionParam('kustom_checkout_order_id', $klSessionId);

        $cmpBasket = $this->getMockBuilder(BasketComponent::class)->setMethods(['updateKustomOrder'])->getMock();
        $cmpBasket->expects($this->once())->method('updateKustomOrder')->will($this->throwException(new StandardException('Test')));

        $cmpBasket->changebasket('abc', 11, 'sel', 'persparam', 'override');

        $this->assertLoggedException(StandardException::class, 'Test');
        $this->assertEquals(null, $this->getSessionParam('kustom_checkout_order_id'));
    }

    public function testUpdateKustomOrder() {
        $basket = $this->getMockBuilder(Basket::class)->setMethods(['getKustomOrderLines'])->getMock();
        $basket->expects($this->once())->method('getKustomOrderLines')->willReturn(['test']);
        Registry::getSession()->setBasket($basket);

        $client = $this->getMockBuilder(KustomCheckoutClient::class)->setMethods(['createOrUpdateOrder'])->getMock();
        $client->expects($this->once())->method('createOrUpdateOrder')->willReturn(['testResult']);

        $basketComponent = $this->getMockBuilder(KustomBasketComponent::class)->setMethods(['getKustomCheckoutClient'])->getMock();
        $basketComponent->expects($this->once())->method('getKustomCheckoutClient')->willReturn($client);

        $class = new \ReflectionClass(KustomBasketComponent::class);
        $method = $class->getMethod('updateKustomOrder');
        $method->setAccessible(true);

        $result = $method->invoke($basketComponent);

        $this->assertEquals(['testResult'], $result);
    }

}

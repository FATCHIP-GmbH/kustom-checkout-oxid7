<?php

namespace Fatchip\FcKustom\Tests\Unit\Component;


use OxidEsales\Eshop\Application\Component\UserComponent;
use OxidEsales\Eshop\Core\Controller\BaseController;
use OxidEsales\Eshop\Core\ViewConfig;
use ReflectionClass;
use Fatchip\FcKustom\Component\KustomUserComponent;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;
use OxidEsales\Eshop\Core\UtilsObject;

/**
 * Class KustomUserComponentTest
 * @package Fatchip\FcKustom\Tests\Unit\Components
 * @covers \Fatchip\FcKustom\Component\KustomUserComponent
 */
class KustomUserComponentTest extends ModuleUnitTestCase
{
    public function loginDataProvider()
    {
        $redirectUrl = $this->removeQueryString($this->getConfig()->getShopSecureHomeUrl()) . 'cl=KustomExpress';

        return [
            ['KCO', true, false, null],
            ['KCO', true, true, $redirectUrl],
        ];
    }

    /**
     * @dataProvider loginDataProvider
     * @param $klMode
     * @param $isEnabledPrivateSales
     * @param $isKustomController
     * @param $redirectUrl
     */
    public function testLogin_noredirect($klMode, $isEnabledPrivateSales, $isKustomController, $redirectUrl)
    {
        $this->setRequestParameter('lgn_usr', 'xxx');
        $this->setRequestParameter('lgn_pwd', 'xxx');

        $this->getConfig()->saveShopConfVar('str', 'sKustomActiveMode', $klMode, $shopId = $this->getShopId(), $module = 'module:fckustom');

        $cmpUser = $this->getMockBuilder(UserComponent::class)->setMethods(['kustomRedirect'])->getMock();
        $cmpUser->expects($this->any())->method('kustomRedirect')->willReturn($isKustomController);

        $oParent = $this->getMockBuilder(\stdClass::class)->setMethods(array('isEnabledPrivateSales'))->getMock();
        $oParent->expects($this->any())->method('isEnabledPrivateSales')->willReturn($isEnabledPrivateSales);
        $cmpUser->setParent($oParent);

        $cmpUser->login_noredirect();

        $this->assertEquals($redirectUrl, \oxUtilsHelper::$sRedirectUrl);
    }

    public function stateDataProvider()
    {
        return [
            ['KCO', 1, 1, 1, null],
            ['KCO', 1, 1, 1, 'fake_id'],
            ['KCO', 0, 1, null, null],
        ];
    }

    /**
     * @dataProvider stateDataProvider
     * @param $klMode
     * @param $showShippingAddress
     * @param $resetResult
     * @param $showShippingAddressResult
     * @param $addressIdResult
     */
    public function testChangeuser_testvalues($klMode, $showShippingAddress, $resetResult, $showShippingAddressResult, $addressIdResult)
    {
        $this->getConfig()->saveShopConfVar('str', 'sKustomActiveMode', $klMode, $shopId = $this->getShopId(), $module = 'module:fckustom');
        $this->setRequestParameter('blshowshipaddress', $showShippingAddress);
        $this->setRequestParameter('oxaddressid', $addressIdResult);

        $cmpUser = $this->getMockBuilder(UserComponent::class)->setMethods(['changeUserWithoutRedirect'])->getMock();
        $cmpUser->expects($this->once())->method('changeUserWithoutRedirect')->willReturn(true);

        $cmpUser->changeuser_testvalues();
        $this->assertEquals($resetResult, $this->getSessionParam('resetKustomSession'));
        $this->assertEquals($showShippingAddressResult, $this->getSessionParam('blshowshipaddress'));
        $this->assertEquals($addressIdResult, $this->getSessionParam('deladrid'));
    }

    /**
     * @dataProvider getLogoutLinkDataProvider
     * @param $isKustomCheckoutEnabled
     * @param $isKustomRedirect
     * @throws \ReflectionException
     */
    public function testgetLogoutLink($isKustomCheckoutEnabled, $isKustomRedirect, $expectedResult)
    {
        $oViewConfig = $this->getMockBuilder(ViewConfig::class)->setMethods(['isKustomCheckoutEnabled'])->getMock();
        $oViewConfig->expects($this->any())
            ->method('isKustomCheckoutEnabled')->willReturn($isKustomCheckoutEnabled);
        UtilsObject::setClassInstance(ViewConfig::class, $oViewConfig);

        $baseController = $this->getMockBuilder(BaseController::class)->setMethods(['getDynUrlParams'])->getMock();
        $userComponent  = $this->getMockBuilder(UserComponent::class)->setMethods(['kustomRedirect', 'getDynUrlParams', 'getParent'])->getMock();
        $userComponent->expects($this->any())->method('getParent')->willReturn($baseController);
        $userComponent->expects($this->any())->method('getDynUrlParams')->willReturn('dyna');
        $userComponent->expects($this->any())->method('kustomRedirect')->willReturn($isKustomRedirect);

        $class = new ReflectionClass(get_class($userComponent));
        $sut   = $class->getMethod('getLogoutLink');
        $sut->setAccessible(true);

        $result = $sut->invokeArgs($userComponent, []);

        $this->assertEquals($expectedResult, $result);
    }

    public function getLogoutLinkDataProvider()
    {
        $res1 = $this->getConfig()->getShopUrl() . 'index.php?cl=basket&amp;fnc=logout';

        return [
            [true, true, $res1],
        ];
    }

    public function testGetKustomRedirect()
    {
        $this->setRequestParameter('cl', 'test');
        $userComp = oxNew(KustomUserComponent::class);

        $class  = new \ReflectionClass(KustomUserComponent::class);
        $method = $class->getMethod('kustomRedirect');
        $method->setAccessible(true);

        $this->setProtectedClassProperty($userComp, '_aClasses', ['test']);

        $result = $method->invoke($userComp);

        $this->assertTrue($result);
    }
}

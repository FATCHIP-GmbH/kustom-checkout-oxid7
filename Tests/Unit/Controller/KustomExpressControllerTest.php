<?php

namespace Fatchip\FcKustom\Testes\Unit\Controllers;


use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\UtilsObject;
use OxidEsales\Eshop\Core\UtilsView;
use Fatchip\FcKustom\Controller\KustomExpressController;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use Fatchip\FcKustom\Core\KustomCheckoutClient;
use Fatchip\FcKustom\Core\Exception\KustomBasketTooLargeException;
use Fatchip\FcKustom\Core\Exception\KustomConfigException;
use Fatchip\FcKustom\Model\KustomUser;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

/**
 * Class KustomExpressControllerTest
 * @package Fatchip\FcKustom\Testes\Unit\Controllers
 * @covers \Fatchip\FcKustom\Controller\KustomExpressController
 */
class KustomExpressControllerTest extends ModuleUnitTestCase {
    /**
     * @dataProvider getBreadCrumbDataProvider
     * @param $iLang
     * @param $expectedResult
     */
    public function testGetBreadCrumb($iLang, $expectedResult) {
        $this->setLanguage($iLang);
        $expressController = new KustomExpressController();
        $result = $expressController->getBreadCrumb();

        $this->assertEquals($result[0]['title'], $expectedResult['title']);
    }

    public function getBreadCrumbDataProvider() {
        return [
            [0, ['title' => 'Kasse']],
            [1, ['title' => 'Checkout']],
        ];
    }

    public function testGetKustomModalFlagCountries() {
        $countryList = ['DE', 'AT', 'CH'];
        $expressController = oxNew(KustomExpressController::class);
        $result = $expressController->getKustomModalFlagCountries();

        $this->assertEquals(3, count($result));
        foreach ($result as $index => $country) {
            if (in_array($country->oxcountry__oxisoalpha2->rawValue, $countryList)) {
                unset($result[$index]);
            }
        }
        $this->assertEquals(0, count($result));
    }

    public function testSetKustomDeliveryAddress() {
        $this->setRequestParameter('kustom_address_id', 'delAddressId');
        $kcoController = new KustomExpressController();
        $kcoController->init();
        $kcoController->setKustomDeliveryAddress();
        $this->assertEquals('delAddressId', $this->getSessionParam('deladrid'));
        $this->assertEquals(1, $this->getSessionParam('blshowshipaddress'));
        $this->assertTrue($this->getSessionParam('kustom_checkout_order_id') === null);
    }

    public function testGetKustomModalOtherCountries() {
        $kcoController = new KustomExpressController();
        $result = $kcoController->getKustomModalOtherCountries();

        $this->assertEquals(3, count($result));
    }

    public function testGetActiveShopCountries() {
        $kcoController = new KustomExpressController();
        $result = $kcoController->getActiveShopCountries();

        $this->assertEquals(6, count($result));

        $active = ['DE', 'AT', 'CH', 'US', 'GB'];
        foreach ($result as $country) {
            $index = array_search($country->oxcountry__oxisoalpha2->value, $active);
            if ($index !== null) {
                unset($active[$index]);
            }
        }
        $this->assertEquals(0, count($active));
    }

    public function initPopupDataProvider() {
        $oUser = oxNew(User::class);
        $oUser->oxuser__oxcountryid = new Field('a7c40f6320aeb2ec2.72885259');
        $baseUrl = $this->getConfig()->getSSLShopURL() . 'index.php?cl=KustomExpress';
        $nonKCOUrl = $this->getConfig()->getSSLShopURL() . 'index.php?cl=user&non_kco_global_country=AF';

        return [
            ['AT', null, $baseUrl],
            ['AT', $oUser, $baseUrl],
            ['DE', $oUser, $baseUrl],
            ['AF', $oUser, $nonKCOUrl],
        ];
    }

    /**
     * @dataProvider initPopupDataProvider
     * @param $selectedCountry
     * @param $oUser
     * @param $expectedKustomSessionId
     */
    public function testInit_popupSelection($selectedCountry, $oUser, $redirectUrl) {
        $this->setSessionParam('kustom_checkout_order_id', 'fake-value');
        $this->setRequestParameter('selected-country', $selectedCountry);
        $this->setSessionParam('blshowshipaddress', 1);
        $kcoController = $this->getMockBuilder(KustomExpressController::class)->setMethods(['getUser'])->getMock();
        $kcoController->expects($this->any())
            ->method('getUser')->willReturn($oUser);
        $kcoController->init();
        $this->assertEquals(0, $this->getSessionParam('blshowshipaddress'));
        $this->assertEquals($selectedCountry, $this->getSessionParam('sCountryISO'));
        if ($oUser) {
            $oCountry = oxNew(Country::class);
            $oCountry->load($oUser->oxuser__oxcountryid);
            $this->assertEquals($selectedCountry, $oCountry->oxcountry__oxisoalpha2);
        }

        $this->assertEquals($redirectUrl, \oxUtilsHelper::$sRedirectUrl);
    }


    /**
     * @param $sslredirect
     * @param $getCurrentShopURL
     *
     * @param $expectedResult
     * @dataProvider checkSslDataProvider
     */
    public function testCheckSsl($sslredirect, $expectedResult) {
        $this->setConfigParam('sShopURL', 'http://test.de');
        $this->setConfigParam('sSSLShopURL', 'https://test.de');
        $oRequest = $this->getMockBuilder(Request::class)->setMethods(['getRequestEscapedParameter'])->getMock();
        $oRequest->expects($this->once())->method('getRequestEscapedParameter')->willReturn($sslredirect);
        $kcoController = oxNew(KustomExpressController::class);
        $kcoController->checkSsl($oRequest);
        $this->doAssertContains((string)$expectedResult, (string)\oxUtilsHelper::$sRedirectUrl);
    }

    public function checkSslDataProvider() {
        $forceSslUrlSuffix = 'index.php?sslredirect=forced&cl=KustomExpress';

        return [
            ['forced', null],
            ['forced', null],
            ['asdf', null],
            ['asdf', $forceSslUrlSuffix]
        ];
    }

    public function renderDataProvider() {
        $ssl_url = $this->getConfig()->getSSLShopURL();
        $oUser = oxNew(User::class);
        $oUser->setType(KustomUser::LOGGED_IN);
        $email = 'info@topconcepts.de';
        $apiCreds = [];

        return [
            [$ssl_url, $oUser, null, false, $apiCreds],
            [$ssl_url, null, $email, true],
            [$ssl_url, null, null, true],
        ];
    }

    /**
     * @dataProvider renderDataProvider
     * @param $currentUrl
     * @param $oUser User
     * @param $email
     * @param $expectedShowPopUp
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function testRender_noShippingSet($currentUrl, $oUser, $email, $expectedShowPopUp) {
        $this->expectException(\OxidEsales\Eshop\Core\Exception\NoArticleException::class);
        $oBasket = $this->prepareBasketWithProduct();
        $oBasket = $this->prepareBasketWithProduct();
        $this->getSession()->setBasket($oBasket);
        $this->setSessionParam('sShipSet', '1b842e732a23255b1.91207751');
        $this->setSessionParam('kustom_checkout_user_email', $email);
        $oConfig = $this->getMockBuilder(Config::class)->setMethods(['getCurrentShopURL'])->getMock();
        $oConfig->expects($this->any())->method('getCurrentShopURL')->willReturn($currentUrl);

        $methodReflection = new \ReflectionProperty(KustomExpressController::class, 'blShowPopup');
        $methodReflection->setAccessible(true);
        $kcoController = $this->getMockBuilder(KustomExpressController::class)->setMethods(['getConfig', 'getUser', 'rebuildFakeUser'])->getMock();
        $kcoController->expects($this->any())
            ->method('rebuildFakeUser')->willReturn(true);
        $kcoController->expects($this->atLeastOnce())
            ->method('getConfig')->willReturn($oConfig);
        $kcoController->expects($this->any())
            ->method('getUser')->willReturn($oUser);
        \oxTestModules::addFunction('oxutilsview', 'addErrorToDisplay', '{$this->selectArgs = $aA[0]; return $aA[0];}');
        $this->setLanguage(1);

        $kcoController->init();
        $kcoController->render();

        $oException = Registry::get(UtilsView::class)->selectArgs;

        $this->assertTrue($oException instanceof KustomConfigException);
        if ($kcoController->getUser() && $email) {
            $this->assertEquals($email, $kcoController->getUser()->oxuser__oxemail->rawValue, "User email mismatch.");
        }
        $this->assertEquals($expectedShowPopUp, $methodReflection->getValue($kcoController), "Show popup mismatch.");
    }

    public function testRenderBlockIframeRender() {
        $this->setRequestParameter('sslredirect', 'forced');
        $keController = $this->getMockBuilder(KustomExpressController::class)->setMethods(['getKustomOrder'])->getMock();
        $this->setProtectedClassProperty($keController, 'blockIframeRender', true);
        $keController->expects($this->never())->method('getKustomOrder');
        $keController->init();
        $result = $keController->render();
        $this->assertEquals('@fckustom/checkout/fckustom_checkout', $result);
    }

    public function testRenderException() {
        $this->setRequestParameter('sslredirect', 'forced');
        $keController = $this->getMockBuilder(KustomExpressController::class)->setMethods(['getKustomOrder'])->getMock();
        $keController->expects($this->any())->method('getKustomOrder')->will($this->throwException(new KustomBasketTooLargeException()));
        $keController->init();
        $result = $keController->render();
        $this->assertEquals(Registry::getConfig()->getShopSecureHomeUrl() . 'cl=basket', \oxUtilsHelper::$sRedirectUrl);
        $this->assertEquals('@fckustom/checkout/fckustom_checkout', $result);
    }

    public function testGetKustomClient() {
        $keController = $this->getMockBuilder(KustomExpressController::class)->setMethods(['init'])->getMock();
        $result = $keController->getKustomClient('DE');
        $this->assertInstanceOf(KustomCheckoutClient::class, $result);
    }

    public function testShowCountryPopup() {
        $this->setSessionParam('sCountryISO', 'test');
        $methodReflection = new \ReflectionMethod(KustomExpressController::class, 'showCountryPopup');
        $methodReflection->setAccessible(true);
        $keController = oxNew(KustomExpressController::class);
        $keController->init();
        $result = $methodReflection->invoke($keController);
        $this->assertFalse($result);

        $this->setSessionParam('sCountryISO', false);
        $keController = oxNew(KustomExpressController::class);
        $keController->init();
        $result = $methodReflection->invoke($keController);
        $this->assertTrue($result);

        $this->setRequestParameter('reset_kustom_country', true);
        $keController = oxNew(KustomExpressController::class);
        $keController->init();
        $result = $methodReflection->invoke($keController);
        $this->assertTrue($result);
    }

    public function testRenderWrongMerchantUrls() {
        $this->setRequestParameter('sslredirect', 'forced');
        $this->setSessionParam('wrong_merchant_urls', 'sds');
        $keController = oxNew(KustomExpressController::class);
        $keController->init();
        $result = $keController->render();
        $viewData = $this->getProtectedClassProperty($keController, '_aViewData');
        $this->assertTrue($viewData['confError']);
        $this->assertEquals('@fckustom/checkout/fckustom_checkout', $result);

    }

    public function testRenderKustomClient() {
        $this->setRequestParameter('sslredirect', 'forced');
        $checkoutClient = $this->getMockBuilder(KustomCheckoutClient::class)
            ->setMethods(['createOrUpdateOrder'])->getMock();
        $checkoutClient->expects($this->any())->method('createOrUpdateOrder')
            ->will($this->throwException(new KustomWrongCredentialsException()));
        $keController = $this->getMockBuilder(KustomExpressController::class)
            ->setMethods(['getKustomClient', 'rebuildFakeUser'])->getMock();
        $keController->expects($this->any())->method('getKustomClient')->willReturn($checkoutClient);
        $keController->expects($this->any())->method('rebuildFakeUser')->willReturn(true);
        $keController->init();
        $template = $keController->render();
        $this->assertSame('@fckustom/checkout/fckustom_checkout', $template);
    }

    /**
     * @dataProvider lastResortRenderRedirectDataProvider
     * @param $sCountryISO
     * @param $expectedResult
     */
    public function testLastResortRenderRedirect($sCountryISO, $expectedResult) {
        $mockObj = $this->getMockBuilder(\stdClass::class)->setMethods(['createOrUpdateOrder'])->getMock();
        $mockObj->expects($this->any())->method('createOrUpdateOrder')->willReturn(true);

        $oKustomOrder = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['getOrderData', 'initOrder', 'getHtmlSnippet'])->getMock();
        $oKustomOrder->expects($this->once())->method('getOrderData')->willReturn(['purchase_country' => $sCountryISO]);
        $oKustomOrder->expects($this->any())->method('initOrder')->willReturn($mockObj);
        $oKustomOrder->expects($this->any())->method('getHtmlSnippet')->willReturn(true);
        $controller = $this->getMockBuilder(KustomExpressController::class)
            ->setMethods(['getKustomOrder', 'checkSsl', 'showCountryPopup', 'getKustomClient'])->getMock();
        $controller->expects($this->once())->method('getKustomOrder')->willReturn($oKustomOrder);
        $controller->expects($this->once())->method('checkSsl')->willReturn(null);
        $controller->expects($this->once())->method('showCountryPopup')->willReturn(true);
        $controller->expects($this->any())->method('getKustomClient')->willReturn($oKustomOrder);
        $controller->render();
        $this->assertEquals($expectedResult, \oxUtilsHelper::$sRedirectUrl);
    }

    public function lastResortRenderRedirectDataProvider() {
        return [
            ['AF', Registry::getConfig()->getShopUrl() . 'index.php?cl=user'],
            ['DE', null],
        ];
    }

    /**
     * @dataProvider handleLoggedInUserWithNonKustomCountryDataProvider
     * @param $resetCountry
     * @param $expectedResult
     */
    public function testHandleLoggedInUserWithNonKustomCountry($resetCountry, $expectedResult) {
        $oUser = $this->getMockBuilder(User::class)->setMethods(['getUserCountryISO2'])->getMock();
        $oUser->expects($this->any())->method('getUserCountryISO2')->willReturn('AF');
        $oRequest = $this->getMockBuilder(Request::class)
            ->setMethods(['getRequestEscapedParameter'])->getMock();
        $oRequest->expects($this->at(0))->method('getRequestEscapedParameter')->will($this->returnValue(null));
        $oRequest->expects($this->at(1))->method('getRequestEscapedParameter')->will($this->returnValue($resetCountry));
        $controller = $controller = $this->getMockBuilder(KustomExpressController::class)->setMethods(['getUser'])->getMock();
        $controller->expects($this->any())->method('getUser')->willReturn($oUser);
        $controller->determineUserControllerAccess($oRequest);
        if ($expectedResult) {
            $this->assertStringEndsWith($expectedResult, \oxUtilsHelper::$sRedirectUrl);
        } else {
            $this->assertEquals($expectedResult, \oxUtilsHelper::$sRedirectUrl);
        }
    }

    /**
     * @return array
     */
    public function handleLoggedInUserWithNonKustomCountryDataProvider() {
        return [
            [1, null],
            [null, 'cl=user&non_kco_global_country=AF'],
        ];
    }

    /**
     *
     */
    public function testResolveFakeUserRegistered() {
        $mockUser = $this->getMockBuilder(User::class)->setMethods(['checkUserType'])->getMock();
        $mockUser->oxuser__oxpassword = new Field('testPass');
        $mockUser->expects($this->once())->method('checkUserType');
        $this->setSessionParam('kustom_checkout_user_email', 'test@email');
        $controller = $this->getMockBuilder(KustomExpressController::class)->setMethods(['getUser'])->getMock();
        $controller->expects($this->any())->method('getUser')->willReturn($mockUser);
        $result = $controller->resolveUser();
        $this->assertInstanceOf(User::class, $result);
    }

    public function testResolveFakeUserNew() {
        $this->setSessionParam('kustom_checkout_user_email', 'test@email');
        $controller = $this->getMockBuilder(KustomExpressController::class)->setMethods(['getUser'])->getMock();
        $controller->expects($this->any())->method('getUser')->willReturn(false);
        $result = $controller->resolveUser();
        $this->assertInstanceOf(User::class, $result);
    }

    /**
     *
     */
    public function testRebuildFakeUser() {
        $orderId = 'testId';
        $email = 'test@mail.com';
        $oUser = oxNew(User::class);
        $oUser->oxuser__oxpassword = new Field('');
        $oBasket = new \stdClass();
        $aOrderData = [
            'order_id'        => $orderId,
            'billing_address' => ['email' => $email]
        ];
        $oClient = $this->getMockBuilder(KustomCheckoutClient::class)->setMethods(['getOrder'])->getMock();
        $oClient->expects($this->any())->method('getOrder')->willReturn($aOrderData);
        $controller = $this->getMockBuilder(KustomExpressController::class)->setMethods(['getUser', 'getKustomCheckoutClient'])->getMock();
        $controller->expects($this->any())->method('getUser')->willReturn($oUser);
        $controller->expects($this->any())->method('getKustomCheckoutClient')->willReturn($oClient);
        $controller->rebuildFakeUser($oBasket);

        // assert we rebuild user context
        $this->assertEquals($orderId, $this->getSessionParam('kustom_checkout_order_id'));
        $this->assertEquals($email, $this->getSessionParam('kustom_checkout_user_email'));
        $this->assertEquals($oBasket, $this->getSession()->getBasket());
        $this->getSession()->setBasket(null); // clean up

        // exception
        $oClient = $this->getMockBuilder(KustomCheckoutClient::class)->setMethods(['getOrder'])->getMock();
        $oClient->expects($this->once())->method('getOrder')->willThrowException(new KustomClientException('Test'));
        $oUser = $this->getMockBuilder(User::class)->setMethods(['logout'])->getMock();
        $oUser->expects($this->once())->method('logout');
        $oUser->oxuser__oxpassword = new Field('');
        $controller = $this->getMockBuilder(KustomExpressController::class)->setMethods(['getUser', 'getKustomCheckoutClient'])->getMock();
        $controller->expects($this->any())->method('getUser')->willReturn($oUser);
        $controller->expects($this->any())->method('getKustomCheckoutClient')->willReturn($oClient);
        $controller->rebuildFakeUser($oBasket);
    }
}

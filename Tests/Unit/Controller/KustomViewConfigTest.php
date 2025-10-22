<?php

namespace Fatchip\FcKustom\Testes\Unit\Controllers;


use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\CountryList;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Price;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\ViewConfig;
use Fatchip\FcKustom\Controller\KustomViewConfig;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

/**
 * Class KustomViewConfigTest
 * @package Fatchip\FcKustom\Testes\Unit\Controllers
 * @covers \Fatchip\FcKustom\Controller\KustomViewConfig
 */
class KustomViewConfigTest extends ModuleUnitTestCase
{
    public function isKarnaExternalPaymentDataProvider()
    {
        return [
            ['oxidcashondel', 'DE', true],
            ['oxidcashondel', 'AF', false],
            ['oxidpayadvance', 'DE',  false]
        ];
    }

    public function isATDataProvider()
    {
        return [
            ['AT', 'a7c40f6320aeb2ec2.72885259', true],
            ['DE', 'a7c40f631fc920687.20179984', false]
        ];
    }

    /**
     * @dataProvider isATDataProvider
     * @param $iso
     * @param $oxCountryId
     * @param $result
     */
    public function testGetIsAustria($iso, $oxCountryId, $result)
    {
        $user = $this->getMockBuilder(User::class)->setMethods(['getFieldData'])->getMock();
        $user->expects($this->any())->method('getFieldData')->with('oxcountryid')->willReturn($oxCountryId);
        $oViewConfig = $this->getMockBuilder(ViewConfig::class)->setMethods(['getUser'])->getMock();
        $oViewConfig->expects($this->once())->method('getUser')->willReturn($user);
        $this->assertEquals($result, $oViewConfig->getIsAustria());

    }

    public function testGetIsAustria_noUser_defaultCountry()
    {
        $oViewConfig = $this->getMockBuilder(ViewConfig::class)->setMethods(['getUser'])->getMock();
        $oViewConfig->expects($this->once())->method('getUser')->willReturn(null);
        $this->assertFalse( $oViewConfig->getIsAustria());
    }

    public function isDEDataProvider()
    {
        return [
            ['a7c40f6320aeb2ec2.72885259', false],
            ['a7c40f631fc920687.20179984', true],
        ];
    }

    /**
     * @dataProvider isDEDataProvider
     * @param $mode
     * @param $oxCountryId
     * @param $expectedResult
     */
    public function testGetIsGermany($oxCountryId, $expectedResult)
    {
        $user = $this->getMockBuilder(User::class)->setMethods(['getFieldData'])->getMock();
        $user->expects($this->any())->method('getFieldData')->with('oxcountryid')->willReturn($oxCountryId);
        $oViewConfig = $this->getMockBuilder(ViewConfig::class)->setMethods(['getUser'])->getMock();
        $oViewConfig->expects($this->once())->method('getUser')->willReturn($user);
        $this->assertEquals($expectedResult, $oViewConfig->getIsGermany());
    }

    public function testGetIsGermany_noUser_defaultCountry()
    {
        $oViewConfig = $this->getMockBuilder(ViewConfig::class)->setMethods(['getUser'])->getMock();
        $oViewConfig->expects($this->once())->method('getUser')->willReturn(null);
        $this->assertTrue( $oViewConfig->getIsGermany());
    }


    public function testGetKustomFooterContent_nonKustomSetAsDefault()
    {
        $this->setModuleConfVar('sKustomDefaultCountry', 'AF');
        $oViewConfig = oxNew(ViewConfig::class);
        $result = $oViewConfig->getKustomFooterContent();
        $this->assertFalse($result);
        $this->setModuleConfVar('sKustomDefaultCountry', 'DE');
    }

    public function getCountryListDataProvider()
    {
        $oCountryList = oxNew(CountryList::class);
        $oCountryList->loadActiveCountries();
        $parentCountryListCount = $oCountryList->count();
        $oCountryList->loadActiveNonKustomCheckoutCountries();
        $nonKustomCountriesCount = $oCountryList->count();

        return [
            [false, true, 'some_cc', $nonKustomCountriesCount ],
            [false, false, 'some_cc', $parentCountryListCount ],
            [false, true, 'account_user', $parentCountryListCount ],
            [true, false, 'some_cc', $parentCountryListCount ]
        ];
    }

    /**
     * @dataProvider getCountryListDataProvider
     * @param $blShipping
     * @param $isCheckoutNonKustomCountry
     * @param $activeClassName
     * @param $expectedResult
     */
    public function testGetCountryList($blShipping, $isCheckoutNonKustomCountry, $activeClassName, $expectedResult)
    {

        $viewConfig = $this->getMockBuilder(ViewConfig::class)->setMethods(['isCheckoutNonKustomCountry', 'getActiveClassName'])->getMock();
        $viewConfig->expects($this->any())->method('isCheckoutNonKustomCountry')->willReturn($isCheckoutNonKustomCountry);
        $viewConfig->expects($this->any())->method('getActiveClassName')->willReturn($activeClassName);

        $result = $viewConfig->getCountryList($blShipping);

        $this->assertEquals($expectedResult, $result->count());
    }

    public function isCheckoutNonKustomCountryDataProvider()
    {
        return [
            ['DE', false],
            ['AT', false],
            ['AF', true]
        ];
    }

    /**
     * @dataProvider isCheckoutNonKustomCountryDataProvider
     * @param $iso
     * @param $expectedResult
     */
    public function testIsCheckoutNonKustomCountry($iso, $expectedResult)
    {
        $this->setSessionParam('sCountryISO', $iso);
        $oViewConfig = oxNew(ViewConfig::class);
        $result = $oViewConfig->isCheckoutNonKustomCountry();

        $this->assertEquals($expectedResult, $result);

    }

    public function isUserLoggedInDataProvider()
    {
        $userId = 'fake_id';
        $user = new \stdClass();
        $user->oxuser__oxid = new Field($userId, Field::T_RAW);

        return [
            [$userId, $user, true],
            [null, null, false],
        ];
    }

    /**
     * @dataProvider isUserLoggedInDataProvider
     * @param $userId
     * @param $usrSession
     * @param $user
     * @param $expectedResult
     */
    public function testIsUserLoggedIn($usrSession, $user, $expectedResult)
    {
        $this->setSessionParam('usr', $usrSession);

        $viewConfig = $this->getMockBuilder(ViewConfig::class)->setMethods(['getUser'])->getMock();
        $viewConfig->expects($this->once())->method('getUser')->willReturn($user);

        $this->assertEquals($expectedResult, $viewConfig->isUserLoggedIn());
    }
}

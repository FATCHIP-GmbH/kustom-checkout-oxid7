<?php

namespace Fatchip\FcKustom\Tests\Unit\Core;

use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Field;
use Fatchip\FcKustom\Core\KustomFormatter;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomFormatterTest extends ModuleUnitTestCase
{

    /**
     * @dataProvider oxidtokustomDataProvider
     * @param $object
     * @param $expectedResult
     * @throws \Exception
     */
    public function testOxidToKustomAddress($object, $expectedResult)
    {
        if ($object == null) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage($expectedResult);
            KustomFormatter::oxidToKustomAddress('invalid');
        } else {
            $result = KustomFormatter::oxidToKustomAddress($object);
            $this->assertEquals($expectedResult, $result);
        }
    }

    public function oxidtokustomDataProvider()
    {
        $addressMock = oxNew(Address::class);
        $userMock = oxNew(User::class);
        $userMock->oxuser__oxcountryid = new Field('a7c40f632a0804ab5.18804076', Field::T_RAW);
        $userMock->oxuser__oxstreet = new Field('street', Field::T_RAW);
        $userMock->oxuser__oxstreetnr = new Field('streetnr', Field::T_RAW);
        $userMock->oxuser__oxsal = new Field('Mr', Field::T_RAW);
        $userMock->oxuser__oxmobfon = new Field('000', Field::T_RAW);
        $userMock->oxuser__oxfon = new Field('111', Field::T_RAW);

        $expectedResultUser = [
            'street_address' => "street streetnr",
            'phone' => "000",
            'title' => "Mr",
            'country' => "gb",
        ];
        $expectedResultUser1 = $expectedResultUser;
        $expectedResultUser1['phone'] = '111';
        $expectedResultAddress = [];

        $expectedExceptionMessage = 'Argument must be instance of User|Address.';

        $userMockMobEmpty = clone $userMock;
        $userMockMobEmpty->oxuser__oxmobfon = new Field('', Field::T_RAW);

        $userMockFonEmpty = clone $userMock;
        $userMockFonEmpty->oxuser__oxfon = new Field('', Field::T_RAW);


        return [
            [$userMock, $expectedResultUser],
            [$userMockMobEmpty, $expectedResultUser1],
            [$userMockFonEmpty, $expectedResultUser],
            [$addressMock, $expectedResultAddress],
            [null, $expectedExceptionMessage],
        ];
    }

    /**
     * @dataProvider kustomToOxidAddressDataprovider
     */
    public function testKustomToOxidAddress($sKey, $addressData, $expected)
    {

        $result = KustomFormatter::kustomToOxidAddress($addressData, $sKey);
        $sKey === null
            ? $this->assertNull($result)
            : $this->doAssertArraySubset($expected, $result);
    }

    public function kustomToOxidAddressDataprovider()
    {

        $addressDataBilling['billing_address'] = [
            'street_address' => '01 test',
        ];

        $expectedBilling = [
            'oxuser__oxstreet' => "test",
            'oxuser__oxstreetnr' => "01",
            'oxuser__oxcountryid' => "2db455824e4a19cc7.14731328",
        ];

        $addressDataShipping['shipping_address'] = [
            'date_of_birth' => '01 test',
        ];

        $expectedShipping = [
            'oxaddress__oxcountryid' => "2db455824e4a19cc7.14731328",
            'oxaddress__oxbirthdate' => '01 test'
        ];

        $addressDataShippingWithTitle['shipping_address'] = [
            'title' => 'Mr',
        ];

        $expectedShippingWithTitle = [
            'oxaddress__oxcountryid' => "2db455824e4a19cc7.14731328",
            'oxaddress__oxsal' => "Mr",
        ];

        return [
            [null, null, null],
            ['billing_address', $addressDataBilling, $expectedBilling],
            ['shipping_address', $addressDataShipping, $expectedShipping],
            ['shipping_address', $addressDataShippingWithTitle, $expectedShippingWithTitle],
        ];
    }

    /**
     * @dataProvider formatSalutationDataProvider
     * @param $title
     * @param $country
     * @param $expected
     */
    public function testFormatSalutation($title, $country, $expected)
    {
        $result = KustomFormatter::formatSalutation($title, $country);
        $this->assertEquals($result, $expected);

    }

    public function formatSalutationDataProvider()
    {
        return [
            [null, null, false],
            ['Miss', null, 'Ms'],
            ['Frau', 'de', 'Frau'],
        ];

    }

    protected function doAssertArraySubset($needle, $haystack)
    {
        if (method_exists($this, 'assertArraySubsetOxid')) {
            parent::assertArraySubsetOxid($needle, $haystack);
        } else {
            parent::assertArraySubset($needle, $haystack);
        }
    }
}
<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller\Admin;


use OxidEsales\Eshop\Core\Database\Adapter\Doctrine\ResultSet;
use Fatchip\FcKustom\Controller\Admin\KustomBaseConfig;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

/**
 * Class KustomBaseConfigTest
 * @package Fatchip\FcKustom\Tests\Unit\Controller\Admin
 */
class KustomBaseConfigTest extends ModuleUnitTestCase {
    public function testGetAllActiveOxPaymentIds() {
        $stub = $this->getMockBuilder(KustomBaseConfig::class)->setMethods(['authorize'])->getMock();
        $stub->expects($this->any())->method('authorize')->willReturn(true);
        $result = $stub->getAllActiveOxPaymentIds();
        $this->assertInstanceOf(ResultSet::class, $result);
    }

    public function testRender() {
        $stub = $this->getMockBuilder(KustomBaseConfig::class)->setMethods(['authorize', 'getEditObjectId', 'getViewDataElement'])->getMock();
        $stub->expects($this->once())->method('authorize')->willReturn(true);
        $stub->expects($this->any())->method('getEditObjectId')->willReturn('test');
        $stub->expects($this->any())->method('getViewDataElement')->willReturn(['aKustomDesign' =>
                                                                                     'color_button =&gt; #D5FF4D
            color_button_text =&gt; #40FF53
            color_checkbox =&gt; #FF40DF
            color_checkbox_checkmark =&gt; #FFC387
            color_header =&gt; #FF7AC6
            color_link =&gt; #FFA200
            radius_border =&gt; 4px',
        ]);
        $expectedResult = array (
            'aKustomDesign' =>
                array (
                ),
            'aarrKustomCreds' =>
                array (
                ),
            'aarrKustomAnonymizedProductTitle' =>
                array (
                    'sKustomAnonymizedProductTitle_EN' => 'Product name',
                    'sKustomAnonymizedProductTitle_DE' => 'Produktname',
                ),
            'aarrKustomTermsConditionsURI' =>
                array (
                    'sKustomTermsConditionsURI_DE' => '',
                    'sKustomTermsConditionsURI_EN' => '',
                ),
            'aarrKustomCancellationRightsURI' =>
                array (
                    'sKustomCancellationRightsURI_DE' => '',
                    'sKustomCancellationRightsURI_EN' => '',
                ),
            'aarrKustomShippingDetails' =>
                array (
                    'sKustomShippingDetails_DE' => '',
                    'sKustomShippingDetails_EN' => '',
                ),
            'aarrKustomISButtonStyle' =>
                array (
                    'variation' => 'Kustom',
                    'tagline' => 'light',
                    'type' => 'pay',
                ),
            'aarrKustomISButtonSettings' =>
                array (
                    'allow_separate_shipping_address' => 0,
                    'date_of_birth_mandatory' => 0,
                    'national_identification_number_mandatory' => 0,
                    'phone_mandatory' => 0,
                ),
            'aarrKustomShippingMap' =>
                array (
                ),
            'aKustomDesignKP' =>
                array (
                ),
        );


        $stub->init();
        $stub->render();
        $result = $this->getProtectedClassProperty($stub, '_aViewData')['confaarrs'];

        $this->assertEquals($expectedResult, $result);
    }

    public function testSave() {
        $stub = $this->getMockBuilder(KustomBaseConfig::class)->setMethods(['authorize'])->getMock();
        $stub->expects($this->once())->method('authorize')->willReturn(true);
        $stub->init();
        $stub->save();
        $this->assertNull($stub->getParameter('confaarrs'));

        $stub = $this->getMockBuilder(KustomBaseConfig::class)->setMethods(['authorize', 'getParameter', '_aConfParams'])->getMock();
        $stub->expects($this->once())->method('authorize')->willReturn(true);
        $stub->expects($this->any())->method('getParameter')->willReturn(['test' => 'test']);
        $this->setProtectedClassProperty($stub, '_aConfParams', ['test' => 'test']);
        $stub->init();
        $stub->save();

        $this->assertEquals(['test' => 'test'], $stub->getParameter('confaarrs'));
        $stub = $this->getMockBuilder(KustomBaseConfig::class)->setMethods(['authorize', 'getParameter', '_aConfParams'])->getMock();
        $stub->expects($this->once())->method('authorize')->willReturn(true);
        $stub->expects($this->any())->method('getParameter')->willReturn(['test' => 'test']);
        $this->setProtectedClassProperty($stub, '_aConfParams', ['test' => 'test']);
        $stub->init();
        $stub->save();
    }

    public function testGetFlippedLangArray() {
        $stub = $this->getMockBuilder(KustomBaseConfig::class)->setMethods(['init'])->getMock();
        $result = $stub->getFlippedLangArray();
        $de = $result['de'];
        $en = $result['en'];

        $deExpected = $this->getLangExpected();
        $deExpected->selected = $de->selected;
        $this->assertEquals($deExpected, $de);

        $enExpected = $this->getLangExpected('en');
        $enExpected->selected = $en->selected;
        $this->assertEquals($enExpected, $en);

    }

    public function testSetParameter() {
        $stub = $this->getMockBuilder(KustomBaseConfig::class)->setMethods(['init'])->getMock();
        $stub->setParameter('test', 'test');
        $this->assertEquals($stub->getParameter('test'), 'test');
    }

    public function testInit() {
        $stub = $this->getMockBuilder(KustomBaseConfig::class)->setMethods(['authorize'])->getMock();
        $stub->expects($this->once())->method('authorize')->willReturn(true);
        $this->assertNull($this->getProtectedClassProperty($stub, '_oRequest'));
        $stub->init();
        $this->assertNotEmpty($this->getProtectedClassProperty($stub, '_oRequest'));
    }

    public function testGetLangs() {
        $stub = $this->getMockBuilder(KustomBaseConfig::class)->setMethods(['init'])->getMock();
        $result = json_decode(html_entity_decode($stub->getLangs()));
        $de = $result[0];
        $en = $result[1];

        $deExpected = $this->getLangExpected();
        $deExpected->selected = $de->selected;
        $this->assertEquals($de, $deExpected);

        $enExpected = $this->getLangExpected('en');
        $enExpected->selected = $en->selected;
        $this->assertEquals($enExpected, $en);

    }

    protected function getLangExpected($lang = 'de') {
        if ($lang == 'de') {
            return (object)[
                'id'     => 0,
                'oxid'   => "de",
                'abbr'   => "de",
                'name'   => "Deutsch",
                'active' => "1",
                'sort'   => "1",
            ];

        } else {
            return (object)[
                'id'       => 1,
                'oxid'     => "en",
                'abbr'     => "en",
                'name'     => "English",
                'active'   => "1",
                'sort'     => "2",
                'selected' => 0,
            ];
        }
    }

    public function testGetMultiLangData() {
        $confstrs = [
            'iKustomActiveCheckbox'            => '3',
            'iKustomValidation'                => '2',
            'sKustomActiveMode'                => 'KCO',
            'sKustomAnonymizedProductTitle'    => 'anonymized product',
            'sKustomAnonymizedProductTitle_DE' => 'Produktname',
            'sKustomAnonymizedProductTitle_EN' => 'Product name',
            'sKustomCancellationRightsURI_DE'  => 'https://www.example.com/cancel_deutsch.pdf',
            'sKustomCancellationRightsURI_EN'  => 'https://www.example.com/cancel_english.pdf',
            'sKustomDefaultCountry'            => 'DE',
            'sKustomFooterDisplay'             => '1',
            'sKustomFooterValue'               => 'longBlack',
            'sKustomMerchantId'                => 'K501664_9c5b3285c29f',
            'sKustomPassword'                  => '7NvBzZ5irjFqXcbA',
            'sKustomTermsConditionsURI_DE'     => 'https://www.example.com/tc_deutsch.pdf',
            'sKustomTermsConditionsURI_EN'     => 'https://www.example.com/tc_english.pdf',
            'sKustomShippingDetails_DE'        => '',
            'sKustomShippingDetails_EN'        => '',
            'sVersion'                         => null,
        ];
        $expectedResult = [
            'confstrs[sKustomAnonymizedProductTitle_DE]' => 'Produktname',
            'confstrs[sKustomAnonymizedProductTitle_EN]' => 'Product name',
        ];

        $controller = $this->getMockBuilder(KustomBaseConfig::class)->setMethods(['getViewDataElement'])->getMock();
        $controller->expects($this->once())->method('getViewDataElement')->willReturn($confstrs);
        $this->setProtectedClassProperty($controller, 'MLVars', ['sKustomAnonymizedProductTitle_']);
        $methodReflection = new \ReflectionMethod(KustomBaseConfig::class, 'getMultiLangData');
        $methodReflection->setAccessible(true);

        $result = $methodReflection->invoke($controller);
        $this->assertEquals($expectedResult, $result);
    }

}

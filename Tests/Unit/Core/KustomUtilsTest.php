<?php

namespace Fatchip\FcKustom\Tests\Unit\Core;

use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\BasketItem;
use OxidEsales\Eshop\Application\Model\Category;
use OxidEsales\Eshop\Application\Model\CountryList;
use OxidEsales\Eshop\Core\Price;
use OxidEsales\Eshop\Core\Registry;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomUtilsTest extends ModuleUnitTestCase
{

    public function testCalculateOrderAmountsPricesAndTaxes() {
        $expected = [
            0,
            0,
            0,
            0,
            10000,
            0,
            "pcs",
        ];

        $price = $this->getMockBuilder(Price::class)->setMethods(['getVat'])->getMock();
        $price->expects($this->once())->method('getVat')->willReturn(100);

        $item = $this->getMockBuilder(BasketItem::class)->setMethods(['isBundle', 'getUnitPrice'])->getMock();
        $item->expects($this->exactly(2))->method('isBundle')->willReturn(true);
        $item->expects($this->once())->method('getUnitPrice')->willReturn($price);
        $result = KustomUtils::calculateOrderAmountsPricesAndTaxes($item, false);
        $this->assertEquals($expected, $result);
    }

    public function testCalculateOrderAmountsPricesAndTaxes_1()
    {
        $price = $this->getMockBuilder(Price::class)->setMethods(['getVat', 'getBruttoPrice'])->getMock();
        $price->expects($this->any())->method('getBruttoPrice')->willReturn(20);
        $price->expects($this->any())->method('getVat')->willReturn(2);
        $priceUnit = $this->getMockBuilder(Price::class)->setMethods(['getBruttoPrice'])->getMock();
        $priceUnit->expects($this->any())->method('getBruttoPrice')->willReturn(10);
        $article = $this->getMockBuilder(Article::class)->setMethods(['getUnitPrice'])->getMock();
        $article->expects($this->once())->method('getUnitPrice')->willReturn($price);
        $item = $this->getMockBuilder(BasketItem::class)
            ->setMethods(['isBundle', 'getUnitPrice', 'getArticle', 'getRegularUnitPrice'])
            ->getMock();
        $item->expects($this->exactly(2))->method('isBundle')->willReturn(false);
        $item->expects($this->once())->method('getUnitPrice')->willReturn($price);
        $item->expects($this->exactly(2))->method('getArticle')->willReturn($article);
        $item->expects($this->once())->method('getRegularUnitPrice')->willReturn($priceUnit);
        $result = KustomUtils::calculateOrderAmountsPricesAndTaxes($item, true);
        $expected = [
            0,
            1000,
            0,
            0,
            200,
            0,
            "pcs",
        ];
        $this->assertEquals($expected, $result);
    }

    public function testCalculateOrderAmountsPricesAndTaxes_2()
    {
        $item = $this->getMockBuilder(BasketItem::class)
            ->setMethods(['isBundle', 'getUnitPrice', 'getRegularUnitPrice'])
            ->getMock();
        $item->expects($this->exactly(2))->method('isBundle')->willReturn(false);
        $price = $this->getMockBuilder(Price::class)->setMethods(['getVat', 'getBruttoPrice'])->getMock();
        $price->expects($this->once())->method('getBruttoPrice')->willReturn(20);
        $price->expects($this->any())->method('getVat')->willReturn(7);
        $priceUnit = $this->getMockBuilder(Price::class)->setMethods(['getBruttoPrice'])->getMock();
        $priceUnit->expects($this->any())->method('getBruttoPrice')->willReturn(10);
        $item->expects($this->any())->method('getUnitPrice')->willReturn($price);
        $item->expects($this->once())->method('getRegularUnitPrice')->willReturn($priceUnit);

        $result = KustomUtils::calculateOrderAmountsPricesAndTaxes($item, false);
        $expected = [
            0,
            1000,
            0,
            0,
            700,
            0,
            "pcs",
        ];
        $this->assertEquals($expected, $result);
    }

    public function testIsNonKustomCountryActive()
    {
        $list = $this->getMockBuilder(CountryList::class)->setMethods(['loadActiveNonKustomCheckoutCountries'])->getMock();
        $list->expects($this->any())->method('loadActiveNonKustomCheckoutCountries')->willReturn([null]);
        Registry::set(CountryList::class, $list);
        $result = KustomUtils::isNonKustomCountryActive();
        $this->assertFalse($result);

        $this->setProtectedClassProperty($list, '_aArray', ['test1', 'test2']);
        $result = KustomUtils::isNonKustomCountryActive();
        $this->assertTrue($result);

    }

    public function testGetSubCategoriesArray()
    {
        $categoryParent = $this->getMockBuilder(Category::class)
            ->setMethods(['getTitle', 'getParentCategory'])->getMock();
        $category = clone $categoryParent;
        $categoryParent->expects($this->once())->method('getTitle')->willReturn('parentTitle');
        $category->expects($this->once())->method('getTitle')->willReturn('category');
        $category->expects($this->once())->method('getParentCategory')->willReturn($categoryParent);

        $result = KustomUtils::getSubCategoriesArray($category, ['test' => 'test']);
        $expected = [
            'test' => 'test',
            'category',
            'parentTitle',
        ];
        $this->assertEquals($expected, $result);

    }

    public function testIsCountryActiveInKustomCheckout()
    {
        $list = $this->getMockBuilder(CountryList::class)->setMethods(['loadActiveKustomCheckoutCountries'])->getMock();
        $list->expects($this->once())->method('loadActiveKustomCheckoutCountries')->willReturn([null]);
        $this->setProtectedClassProperty($list, '_aArray', []);
        Registry::set(CountryList::class, $list);
        $result = KustomUtils::isCountryActiveInKustomCheckout('invalid');
        $this->assertFalse($result);
    }
}

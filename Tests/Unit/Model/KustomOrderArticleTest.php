<?php
/**
 * Created by PhpStorm.
 * User: arekk
 * Date: 09.04.2018
 * Time: 17:16
 */

namespace Fatchip\FcKustom\Testes\Unit\Models;

use OxidEsales\Eshop\Application\Model\OrderArticle;
use OxidEsales\Eshop\Core\Field;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomOrderArticleTest extends ModuleUnitTestCase
{

    public function testfckustom_setArtNum()
    {
        $testVal = 'string-value';
        $expectedResult = md5($testVal);
        $oOrderArticle = oxNew(OrderArticle::class);
        $oOrderArticle->oxorderarticles__oxartnum = new Field($testVal, Field::T_RAW);
        $oOrderArticle->fcKustom_setArtNum();
        $this->assertEquals($expectedResult, $oOrderArticle->oxorderarticles__fckustom_artnum->value);
    }

    public function testGetAmount()
    {
        $oOrderArticle = oxNew(OrderArticle::class);
        $oOrderArticle->oxorderarticles__oxamount = new Field(3, Field::T_RAW);

        $result = $oOrderArticle->getAmount();

        $this->assertEquals(3, $result);
    }

    public function setTitleDataProvider()
    {
        return [
            [0,'Produktname 0'],
            [1,'Product name 0']

        ];
    }
}

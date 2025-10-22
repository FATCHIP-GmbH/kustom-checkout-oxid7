<?php


namespace Fatchip\FcKustom\Model;


use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Field;

class KustomOrderArticle extends KustomOrderArticle_parent
{
    public function getAmount()
    {
        return $this->oxorderarticles__oxamount->value;
    }

    /**
     * @codeCoverageIgnore
     * @return mixed
     */
    public function getRegularUnitPrice()
    {
        return $this->getBasePrice();
    }

    /**
     * @codeCoverageIgnore
     * @return mixed
     */
    public function getUnitPrice()
    {
        return $this->getPrice();
    }

    /**
     * @param $index
     * @param int|string $iOrderLang
     */
    public function fcKustom_setTitle($index, $iOrderLang = '')
    {
        $name                           = KustomUtils::getShopConfVar('sKustomAnonymizedProductTitle_' . $this->getLangTag($iOrderLang));
        $this->oxorderarticles__fckustom_title = new Field(html_entity_decode("$name $index", ENT_QUOTES));
    }

    public function fcKustom_setArtNum()
    {
        $this->oxorderarticles__fckustom_artnum = new Field(md5($this->oxorderarticles__oxartnum->value));
    }

    protected function getLangTag($iOrderLang)
    {
        return strtoupper(Registry::getLang()->getLanguageAbbr($iOrderLang));
    }
}
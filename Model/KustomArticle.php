<?php


namespace Fatchip\FcKustom\Model;


use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class Kustom_oxArticle extends oxArticle class
 */
class KustomArticle extends KustomArticle_parent
{
    /**
     * Array of Kustom_PClass objects
     * @var array
     */
    protected $_aPClassList = null;

    /**
     * Show monthly cost?
     * @var bool
     */
    protected $_blShowMonthlyCost = null;

    /**
     * Check if article stock is good for expire check
     *
     * @return bool
     */
    public function isGoodStockForExpireCheck()
    {
        return (
            $this->getFieldData('oxstock') == 0
            && ($this->getFieldData('oxstockflag') == 1 || $this->getFieldData('oxstockflag') == 4)
        );
    }


    /**
     * Returning stock items by article number
     *
     * @param $sArtNum
     * @return object Article
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public function kustom_loadByArtNum($sArtNum)
    {
        $sArticleTable = $this->getViewName();
        if (strlen($sArtNum) === 64) {
            $sArtNum   .= '%';
            $sSQL      = "SELECT art.oxid FROM {$sArticleTable} art WHERE art.OXACTIVE=1 AND art.OXARTNUM LIKE \"{$sArtNum}\"";
            $articleId = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->getOne($sSQL);
        } else {
            if (KustomUtils::getShopConfVar('blKustomEnableAnonymization')) {
                $sSQL = "SELECT oxartid 
                            FROM fckustom_anon_lookup 
                            JOIN {$sArticleTable} art
                            ON art.OXID=oxartid
                            WHERE art.OXACTIVE=1 AND fckustom_artnum = ?";
            } else {
                $sSQL = "SELECT art.oxid FROM {$sArticleTable} art WHERE art.OXACTIVE=1 AND art.OXARTNUM = ?";
            }
            $articleId = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->getOne($sSQL, array($sArtNum));
        }

        return $this->load($articleId);
    }


    /**
     * Return anonymized or regular product title
     *
     * @param null $counter
     * @param null $iOrderLang
     * @return mixed
     */
    public function fcKustom_getOrderArticleName($counter = null, $iOrderLang = null)
    {

        if (KustomUtils::getShopConfVar('blKustomEnableAnonymization')) {
            if ($iOrderLang) {
                $lang = strtoupper(Registry::getLang()->getLanguageAbbr($iOrderLang));
            } else {
                $lang = strtoupper(Registry::getLang()->getLanguageAbbr());
            }

            $name = KustomUtils::getShopConfVar('sKustomAnonymizedProductTitle_' . $lang);

            return html_entity_decode("$name $counter", ENT_QUOTES);
        }

        $name = $this->getFieldData('oxtitle');

        if (!$name && $parent = $this->getParentArticle()) {
            if ($iOrderLang) {
                $this->loadInLang($iOrderLang, $parent->getId());
            } else {
                $this->load($parent->getId());
            }
            $name = $this->getFieldData('oxtitle');
        }

        return html_entity_decode($name, ENT_QUOTES) ?: '(no title)';
    }

    /**
     * @return array
     */
    public function fcKustom_getArticleUrl()
    {
        if (KustomUtils::getShopConfVar('blKustomSendProductUrls') === true &&
            KustomUtils::getShopConfVar('blKustomEnableAnonymization') === false) {

            $link = $this->getLink(null, true);

            $link = preg_replace('/\?.+/', '', $link);

            return $link ?: null;
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function fcKustom_getArticleImageUrl()
    {
        if (KustomUtils::getShopConfVar('blKustomSendImageUrls') === true &&
            KustomUtils::getShopConfVar('blKustomEnableAnonymization') === false) {

            $link = $this->getPictureUrl();
        }

        return $link ?: null;
    }

    /**
     * @return null
     */
    public function fcKustom_getArticleEAN()
    {
        if (KustomUtils::getShopConfVar('blKustomEnableAnonymization') === false) {
            $ean = $this->getFieldData('oxean');
        }

        return $ean ?: null;
    }

    /**
     * @return null
     */
    public function fcKustom_getArticleMPN()
    {
        if (KustomUtils::getShopConfVar('blKustomEnableAnonymization') === false) {
            $mpn = $this->getFieldData('oxmpn');
        }

        return $mpn ?: null;
    }

    public function fcKustom_getArtNum()
    {
        return $this->getFieldData('oxartnum');
    }

    /**
     * @return string
     */
    public function fcKustom_getArticleCategoryPath()
    {
        $sCategories = null;
        if (KustomUtils::getShopConfVar('blKustomEnableAnonymization') === false) {
            $oCat = $this->getCategory();

            if ($oCat) {
                $aCategories = KustomUtils::getSubCategoriesArray($oCat);
                $sCategories = html_entity_decode(implode(' > ', array_reverse($aCategories)), ENT_QUOTES);
            }

        }

        return $sCategories ?: null;
    }

    /**
     * @return string|null
     */
    public function fcKustom_getArticleManufacturer()
    {
        if (KustomUtils::getShopConfVar('blKustomEnableAnonymization') === false) {
            if (!$oManufacturer = $this->getManufacturer())
                return null;
        }

        return html_entity_decode($oManufacturer->getTitle(), ENT_QUOTES) ?: null;
    }

}

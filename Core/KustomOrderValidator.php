<?php


namespace Fatchip\FcKustom\Core;


use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Core\Base;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class KustomOrderValidator
 * @package Fatchip\FcKustom\Core
 */
class KustomOrderValidator extends Base
{
    protected $aOrderData;

    /**
     * Reference prefix exclude from array
     *
     * @var string
     */
    protected $_sReferencePrefix = "SRV_";

    /**
     * Errors might occur when validating
     *
     * @var array
     */
    protected $_aResultErrors = array();

    /**
     * @var boolean
     */
    protected $_bResult;

    /**
     * @return array
     */
    public function getResultErrors()
    {
        return $this->_aResultErrors;
    }

    /**
     * KustomOrderValidator constructor.
     * @param array $aOrderData from Kustom validation request
     */
    public function __construct($aOrderData)
    {
        parent::__construct();
        $this->aOrderData = $aOrderData;
    }

    /**
     * @return bool
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function validateOrder()
    {
        $aOrderItems = $this->_fetchOrderItems();
        if (empty($aOrderItems)) {
            return $this->_bResult = false;
        }

        $this->_validateItemsBuyable($aOrderItems);

        return count($this->_aResultErrors) === 0 ? $this->_bResult = true : $this->_bResult = false;
    }

    /**
     * Returning order articles list
     *
     * @return int|mixed
     */
    protected function _fetchOrderItems()
    {
        // remove services from articles list
        foreach ($this->aOrderData['order_lines'] as $index => $aItem) {
            if ($this->isService($aItem)) {
                unset($this->aOrderData['order_lines'][$index]);
            }
        }

        return $this->aOrderData['order_lines'];
    }

    /**
     * Validating if product items buyable and with enough stock
     *
     * @param array $aItems
     * @return void
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    protected function _validateItemsBuyable($aItems)
    {
        $mergedProducts = array();
        foreach ($aItems as $item) {
            if (!isset($mergedProducts[$item['reference']])) {
                $mergedProducts[$item['reference']] = 0;
            }
            $mergedProducts[$item['reference']] += $item['quantity'];
        }
        $this->_validateOxidProductsBuyable($mergedProducts);
    }

    /**
     * Check if provided products with requested amount are buyable
     *
     * @param $mergedProducts
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    protected function _validateOxidProductsBuyable($mergedProducts)
    {
        $oArticleObject = oxNew(Article::class);

        foreach ($mergedProducts as $itemKey => $itemAmount) {
            /** @var Article $oArticleObject */
            $oArticleObject->kustom_loadByArtNum($itemKey);

            if ($oArticleObject->checkForStock($itemAmount) !== true) {
                $this->_aResultErrors['FCKUSTOM_ERROR_NOT_ENOUGH_IN_STOCK'] = $oArticleObject->getFieldData('oxartnum');

                return;
            }

            if (!$oArticleObject->isLoaded()) {
                $this->_aResultErrors['ERROR_MESSAGE_ARTICLE_ARTICLE_DOES_NOT_EXIST'] = $oArticleObject->getFieldData('oxartnum');

                return;
            }

            if (!$oArticleObject->isBuyable()) {
                $this->_aResultErrors['ERROR_MESSAGE_ARTICLE_ARTICLE_NOT_BUYABLE'] = $oArticleObject->getFieldData('oxartnum');

                return;
            }
        }
    }

    public function isValid()
    {
        return $this->_bResult;
    }

    /** @param $item array
     * @return string
     */
    protected function isService($item)
    {
        return strpos($item['reference'], $this->_sReferencePrefix) === 0;
    }
}
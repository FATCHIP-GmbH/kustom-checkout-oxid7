<?php


namespace Fatchip\FcKustom\Model;

use OxidSolutionCatalysts\PayPal\Core\PayPalDefinitions;
use Fatchip\FcKustom\Core\KustomOrderManagementClient;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class KustomOrder
 * @package Fatchip\FcKustom\Model
 *
 * @property bool _isLoaded
 */
class KustomOrder extends KustomOrder_parent
{

    protected $isAnonymous;

    /**
     * Validates order parameters like stock, delivery and payment
     * parameters
     *
     * @param Basket $oBasket basket object
     * @param User $oUser order user
     *
     * @return bool|null|void
     */
    public function validateOrder($oBasket, $oUser)
    {
        $paymentId = $oBasket->getPaymentId();

        if(KustomPaymentHelper::isKustomPayment($paymentId)) {
            $_POST['sDeliveryAddressMD5'] = Registry::getSession()->getVariable('sDelAddrMD5');
        }

        return parent::validateOrder($oBasket, $oUser);
    }

    /**
     * @param null|KustomOrderManagementClient $client for UnitTest purpose
     * @return mixed
     */
    protected function setNumber()
    {
        if ($blUpdate = parent::setNumber()) {
            if ($this->isKCO()) {
                $session = Registry::getSession();
                $kustom_id = $session->getVariable('kustom_checkout_order_id');
                $this->oxorder__fckustom_orderid = new Field($kustom_id, Field::T_RAW);
                $this->saveMerchantIdAndServerMode();
                $this->save();
            }

            try {
                $sCountryISO = KustomUtils::getCountryISO($this->getFieldData('oxbillcountryid'));
                $client = KustomOrderManagementClient::getInstance($sCountryISO); // @codeCoverageIgnore
                $client->sendOxidOrderNr($this->oxorder__oxordernr->value, $kustom_id);
            } catch (KustomClientException $e) {
                KustomUtils::logException($e);
            }
        }
        return $blUpdate;
    }

    /**
     *
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function saveMerchantIdAndServerMode()
    {
        $sCountryISO = KustomUtils::getCountryISO($this->getFieldData('oxbillcountryid'));

        $aKustomCredentials = KustomUtils::getAPICredentials($sCountryISO);
        $test               = KustomUtils::getShopConfVar('blIsKustomTestMode');

        preg_match('/(?<mid>^[a-zA-Z0-9]+)/', $aKustomCredentials['mid'], $matches);
        $mid        = $matches['mid'];
        $serverMode = $test ? 'playground' : 'live';

        $this->oxorder__fckustom_merchantid = new Field($mid, Field::T_RAW);
        $this->oxorder__fckustom_servermode = new Field($serverMode, Field::T_RAW);
    }

    /**
     * @return bool
     */
    public function isKCO()
    {
        return $this->oxorder__oxpaymenttype->value === KustomPayment::KUSTOM_PAYMENT_CHECKOUT_ID;
    }

    /**
     * Check if order is Kustom order
     *
     * @return boolean
     */
    public function isKustomOrder()
    {
        return KustomPaymentHelper::isKustomPayment($this->oxorder__oxpaymenttype->value);
    }

    /**
     * @param $orderId
     * @param null $sCountryISO
     * @param KustomOrderManagementClient|null $client
     * @return mixed
     */
    public function cancelKustomOrder($orderId = null, $sCountryISO = null, KustomOrderManagementClient $client = null)
    {
        $orderId = $orderId ?: $this->getFieldData('fckustom_orderid');

        if (!$client) {
            $client = KustomOrderManagementClient::getInstance($sCountryISO); // @codeCoverageIgnore
        }

        return $client->cancelOrder($orderId);
    }

    /**
     * @param $data
     * @param $orderId
     * @param $sCountryISO
     * @return string
     */
    public function updateKustomOrder($data, $orderId, $sCountryISO = null, KustomOrderManagementClient $client = null)
    {
        if (!$client) {
            $client = KustomOrderManagementClient::getInstance($sCountryISO); // @codeCoverageIgnore
        }

        try {
            $client->updateOrderLines($data, $orderId);
            $this->oxorder__fckustom_sync = new Field(1);
            $this->save();

        } catch (KustomClientException $e) {

            $this->oxorder__fckustom_sync = new Field(0, Field::T_RAW);
            $this->save();

            return $e->getMessage();
        }
    }

    /**
     * @param $data
     * @param $orderId
     * @param null $sCountryISO
     * @param KustomOrderManagementClient|null $client
     * @return array
     */
    public function captureKustomOrder($data, $orderId, $sCountryISO = null, KustomOrderManagementClient $client = null)
    {
        if ($trackcode = $this->getFieldData('oxtrackcode')) {
            $data['shipping_info'] = array(array('tracking_number' => $trackcode));
        }
        if (!$client) {
            $client = KustomOrderManagementClient::getInstance($sCountryISO); // @codeCoverageIgnore
        }

        return $client->captureOrder($data, $orderId);
    }

    /**
     * @param $orderLang
     * @param bool $isCapture
     * @return mixed
     */
    public function getNewOrderLinesAndTotals($orderLang, $isCapture = false)
    {
        $cur = $this->getOrderCurrency();
        Registry::getConfig()->setActShopCurrency($cur->id);
        if ($isCapture) {
            $this->reloadDiscount(false);
        }
        $oBasket = $this->getOrderBasket();
        $oBasket->setKustomOrderLang($orderLang);
        $this->addOrderArticlesToBasket($oBasket, $this->getOrderArticles(true));

        $oBasket->calculateBasket(true);
        $orderLines = $oBasket->getKustomOrderLines($this->getId());

        return $orderLines;
    }

    /**
     * Set anonymous data if anonymization is enabled.
     *
     * @param $aArticleList
     */
    protected function setOrderArticles($aArticleList)
    {

        parent::setOrderArticles($aArticleList);

        if ($this->isKustomAnonymous()) {
            $oOrderArticles = $this->getOrderArticles();
            if ($oOrderArticles && count($oOrderArticles) > 0) {
                $this->setOrderArticleKustomInfo($oOrderArticles);
            }
        }
    }

    /**
     * @param $oOrderArticles
     */
    protected function setOrderArticleKustomInfo($oOrderArticles)
    {
        $iIndex = 0;
        foreach ($oOrderArticles as $oOrderArticle) {
            $iIndex++;
            $oOrderArticle->fcKustom_setTitle($iIndex);
            $oOrderArticle->fcKustom_setArtNum($iIndex);
        }
    }

    /**
     * @return mixed
     */
    protected function isKustomAnonymous()
    {
        if ($this->isAnonymous !== null)
            return $this->isAnonymous;

        return $this->isAnonymous = KustomUtils::getShopConfVar('blKustomEnableAnonymization');
    }

    public function loadByKustomId($id) {
        $query = $this->buildSelectString(array('fckustom_orderid' => $id));
        $this->_isLoaded = $this->assignRecord($query);

        return $this->_isLoaded;
    }
    
    protected function _sendOrderByEmail($oUser = null, $oBasket = null, $oPayment = null) {

        $isKustomPayment            = KustomPaymentHelper::isKustomPayment($oPayment->oxpayments__oxid->value);

        $isPayPalCheckoutPayment    = class_exists(PayPalDefinitions::class) &&
            defined(PayPalDefinitions::class . '::STANDARD_PAYPAL_PAYMENT_ID') &&
            $oPayment->oxpayments__oxid->value == PayPalDefinitions::STANDARD_PAYPAL_PAYMENT_ID;

        if (is_object($oPayment) && $isKustomPayment) {
            $oPayment->assign(
                [
                    'oxdesc' => str_replace('Kustom ', '', $oPayment->getFieldData('oxdesc'))
                ]
            );
        }else if (is_object($oPayment) && $isPayPalCheckoutPayment) {
            $oPayment->assign(
                [
                    'oxdesc' => "PayPal"
                ]
            );
        }

        return parent::sendOrderByEmail($oUser, $oBasket, $oPayment);
    }

    /**
     * Check if an order with the given id already exists and is not a Kustom Checkout order.
     */
    public function checkForeignOrderExist($oxid): bool
    {
        $masterDb = \OxidEsales\Eshop\Core\DatabaseProvider::getMaster();
        $params = [
            ':oxid' => $oxid
        ];
        $existingPaymentId = $masterDb->getOne('select OXPAYMENTID from oxorder where oxid = :oxid', $params);
        if (!empty($existingPaymentId) && $existingPaymentId !== 'kustom_checkout') {
            return true;
        }

        return false;
    }
}
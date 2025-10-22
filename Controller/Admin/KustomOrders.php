<?php

namespace Fatchip\FcKustom\Controller\Admin;


use Fatchip\FcKustom\Core\KustomOrderManagementClient;
use Fatchip\FcKustom\Core\Exception\KustomCaptureNotAllowedException;
use Fatchip\FcKustom\Model\KustomPaymentHelper;
use OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\UtilsView;
use Fatchip\FcKustom\Model\KustomOrder;
use Fatchip\FcKustom\Model\KustomPayment;

class KustomOrders extends AdminDetailsController
{
    const KUSTOM_PORTAL_PLAYGROUND_URL = 'https://portal.playground.kustom.co/orders/%s';
    const KUSTOM_PORTAL_LIVE_URL       = 'https://portal.kustom.co/orders/%s';

    protected $_sThisTemplate = '@fckustom/admin/fckustom_orders';

    public    $orderLang;
    protected $client;

    /**
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     * @return string
     */
    public function render()
    {
        $this->addTplParam("sOxid", $this->getEditObjectId());

        if (!$this->isKustomOrder()) {
            $this->addTplParam(
                'sMessage',
                Registry::getLang()->translateString("KUSTOM_ONLY_FOR_KUSTOM_PAYMENT")
            );

            return parent::render();
        }
        $oOrder          = $this->getEditObject();
        $this->orderLang = $oOrder->getFieldData('oxlang');

        $this->addTplParam('oOrder', $oOrder);
        $sCountryISO = KustomUtils::getCountryISO($oOrder->getFieldData('oxbillcountryid'));

        if (!$this->isCredentialsValid($sCountryISO)) {
            $wrongCredsMsg = sprintf(
                Registry::getLang()->translateString("KUSTOM_MID_CHANGED_FOR_COUNTRY"),
                $this->getViewDataElement('sMid'),
                $this->getViewDataElement('sCountryISO'),
                $this->getViewDataElement('currentMid'));

            $this->addTplParam('wrongCredentials', $wrongCredsMsg);

            return parent::render();
        }

        try {
            $kustomOrderData = $this->retrieveKustomOrder($this->getViewDataElement('sCountryISO'));
        } catch (KustomCaptureNotAllowedException $e) {
            $this->addTplParam('unauthorizedRequest',
                Registry::getLang()->translateString("KUSTOM_ORDER_NOT_FOUND")
            );

            return parent::render();
        } catch (KustomClientException $e) {
            $this->addTplParam('unauthorizedRequest', $e->getMessage());

            return parent::render();
        } catch (StandardException $e) {
            Registry::get('oxUtilsView')->addErrorToDisplay($e);

            return parent::render();
        }

        $this->addTplParam('sStatus', $kustomOrderData['status']);

        $this->setOrderSync($kustomOrderData);

        if(!empty($kustomOrderData['captures'])
            && is_array($kustomOrderData['captures'])
            && !empty(current($kustomOrderData['captures'])['captured_amount']))
        {
            $orderValue = KustomUtils::parseFloatAsInt($this->getEditObject()->getTotalOrderSum() * 100);

            if(current($kustomOrderData['captures'])['captured_amount'] === $orderValue) {
                $this->addTplParam('canRefund', $this->formatCaptures($kustomOrderData['captures']));
            }
        }

        $this->addTplParam('aCaptures', $this->formatCaptures($kustomOrderData['captures']));
        $this->addTplParam('aRefunds', $kustomOrderData['refunds']);
        $kustomRef = $kustomOrderData['kustom_reference'] ?: " - ";
        $this->addTplParam('sKustomRef', $kustomRef);
        $this->addTplParam('inSync', $this->getEditObject()->getFieldData('fckustom_sync') == 1);

        return parent::render();
    }

    /**
     * Returns editable order object
     * @return KustomOrder|Order
     */
    public function getEditObject()
    {
        $soxId = $this->getEditObjectId();
        if ($this->_oEditObject === null && isset($soxId) && $soxId != '-1') {
            $this->_oEditObject = oxNew(Order::class);
            $this->_oEditObject->load($soxId);
        }

        return $this->_oEditObject;
    }

    /**
     * Method checks if order was made with Kustom module
     *
     * @return bool
     */
    public function isKustomOrder()
    {
        $blActive = false;

        if($this->getEditObject()) {
            $paymentType = $this->getEditObject()->getFieldData('oxpaymenttype');

            if (KustomPaymentHelper::isKustomPayment($paymentType)) {
                $blActive = true;
            }
        }

        return $blActive;
    }

    /**
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function captureFullOrder()
    {
        $orderLines = $this->getEditObject()->getNewOrderLinesAndTotals($this->orderLang, true);

        $data = array(
            'captured_amount' => KustomUtils::parseFloatAsInt($this->getEditObject()->getTotalOrderSum() * 100),
            'order_lines'     => $orderLines['order_lines'],
        );

        $sCountryISO = KustomUtils::getCountryISO($this->getEditObject()->getFieldData('oxbillcountryid'));
        try {
            $this->getEditObject()->captureKustomOrder($data, $this->getEditObject()->getFieldData('fckustom_orderid'), $sCountryISO);
            $this->getEditObject()->oxorder__fckustom_sync = new Field(1);
            $this->getEditObject()->save();
        } catch (StandardException $e) {
            Registry::get(UtilsView::class)->addErrorToDisplay($e->getMessage());
        }
    }

    /**
     * @param null $sCountryISO
     * @return mixed
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function retrieveKustomOrder($sCountryISO = null)
    {
        if (!$sCountryISO) {
            $sCountryISO = KustomUtils::getCountryISO($this->getEditObject()->getFieldData('oxbillcountryid'));
        }

        $client = $this->getKustomMgmtClient($sCountryISO);

        return $client->getOrder($this->getEditObject()->getFieldData('fckustom_orderid'));
    }

    /**
     * @param $price
     * @return string
     */
    public function formatPrice($price)
    {
        return Registry::getLang()->formatCurrency($price / 100, $this->getEditObject()->getOrderCurrency())
            . " {$this->getEditObject()->oxorder__oxcurrency->value}";
    }

    /**
     * @param $amount
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function refundFullOrder()
    {
        $orderRefund = null;
        $data        = array(
            'refunded_amount' => KustomUtils::parseFloatAsInt($this->getEditObject()->getTotalOrderSum() * 100),
        );

        $sCountryISO = KustomUtils::getCountryISO($this->getEditObject()->getFieldData('oxbillcountryid'));
        $result = null;
        try {
            $client      = $this->getKustomMgmtClient($sCountryISO);
            $result = $client->createOrderRefund($data, $this->getEditObject()->getFieldData('fckustom_orderid'));
        } catch (\Exception $e) {
            Registry::get("oxUtilsView")->addErrorToDisplay($e->getMessage());
        }

        Registry::getSession()->setVariable($this->getEditObjectId().'orderRefund', $result);
    }

    /**
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function cancelOrder()
    {
        $oOrder = $this->getEditObject();
        $result = $this->cancelKustomOrder($oOrder);
        if ($result) {
            $oOrder->cancelOrder();
        }

        Registry::getSession()->setVariable($oOrder->getId().'orderCancel', $result);
    }

    /**
     *
     */
    public function getKustomPortalLink()
    {
        if ($this->getEditObject()->oxorder__fckustom_servermode->value === 'playground') {
            $url = self::KUSTOM_PORTAL_PLAYGROUND_URL;
        } else {
            $url = self::KUSTOM_PORTAL_LIVE_URL;
        }

        $orderId = $this->getEditObject()->oxorder__fckustom_orderid->value;

        return sprintf($url, $orderId);
    }

    /**
     * @return bool
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function isCredentialsValid($sCountryISO)
    {
        $currentMid = KustomUtils::getAPICredentials($sCountryISO);

        $this->addTplParam('sMid', $this->getEditObject()->getFieldData('fckustom_merchantid'));
        $this->addTplParam(
            'sCountryISO',
            KustomUtils::getCountryISO($this->getEditObject()->getFieldData('oxbillcountryid'))
        );
        $this->addTplParam('currentMid', $currentMid['mid']);

        if (strstr($currentMid['mid'], $this->getViewDataElement('sMid'))) {
            return true;
        }

        return false;
    }

    /**
     * @param $kustomOrderData
     */
    protected function setOrderSync($kustomOrderData)
    {
        $sync = $this->isOrderCancellationInSync();

        $totalOrderSum = KustomUtils::parseFloatAsInt($this->getEditObject()->getTotalOrderSum() * 100);
        if ($sync && $kustomOrderData['order_amount'] === $totalOrderSum) {
            $this->getEditObject()->oxorder__fckustom_sync = new Field(1, Field::T_RAW);
        } else {
            $this->getEditObject()->oxorder__fckustom_sync = new Field(0, Field::T_RAW);
        }
        $this->getEditObject()->save();
    }

    /**
     * @return bool
     */
    protected function isOrderCancellationInSync()
    {
        if (strtolower($this->getViewDataElement('sStatus')) === 'cancelled') {
            if ($this->getEditObject()->oxorder__oxstorno->value == 1) {
                $this->addTplParam('cancelled', true);

                return true;
            }

            return false;
        }
        if ($this->getEditObject()->getFieldData('oxstorno') == 1) {

            return false;
        }

        return true;
    }

    /**
     * @param $aCaptures
     * @return array
     */
    public function formatCaptures($aCaptures)
    {
        if (!is_array($aCaptures)) {
            return array();
        }
        foreach ($aCaptures as $i => $capture) {
            $kustomTime = new \DateTime($capture['captured_at']);
            $kustomTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));

            $aCaptures[$i]['captured_at'] = $kustomTime->format('Y-m-d H:m:s');
            unset($kustomTime);
        }

        return $aCaptures;
    }

    /**
     * @param $sCountryISO
     * @return \Fatchip\FcKustom\Core\KustomClientBase
     */
    protected function getKustomMgmtClient($sCountryISO)
    {
        return KustomOrderManagementClient::getInstance($sCountryISO);
    }

    /**
     * @param KustomOrder|Order $oOrder
     * @return bool
     * @throws \oxSystemComponentException
     */
    protected function cancelKustomOrder($oOrder)
    {
        if (!$oOrder->isLoaded()) {
            return false;
        }

        if ($oOrder->isKustomOrder() && !$oOrder->getFieldData('oxstorno')) {
            $orderId     = $oOrder->getFieldData('fckustom_orderid');
            $sCountryISO = KustomUtils::getCountryISO($oOrder->getFieldData('oxbillcountryid'));

            try {
                $oOrder->cancelKustomOrder($orderId, $sCountryISO);
                $oOrder->oxorder__fckustom_sync = new Field(1);
                $oOrder->save();
            } catch (StandardException $e) {
                if (strstr($e->getMessage(), 'is canceled.')) {

                    return true;
                }

                Registry::get(UtilsView::class)->addErrorToDisplay($e);
                $this->resetCache();

                return false;
            }
        }

        return true;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function resetCache()
    {
        $this->resetContentCache();
        $this->init();
    }
}
<?php

namespace Fatchip\FcKustom\Controller\Admin;


use Fatchip\FcKustom\Core\KustomClientBase;
use Fatchip\FcKustom\Core\KustomOrderManagementClient;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Core\Exception\KustomOrderNotFoundException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;
use Fatchip\FcKustom\Model\KustomOrder;

class KustomOrderMain extends KustomOrderMain_parent
{
    protected $kustomOrderData;
    protected $client;

    /**
     * @return mixed
     */
    public function init()
    {
        $result = parent::init();

        $oOrder = $this->getEditObject();
        if ($this->isKustomOrder() && $oOrder->getFieldData('oxstorno') == 0) {
            //check if credentials are valid
            if (!$this->isCredentialsValid()) {
                $oOrder->oxorder__fckustom_sync = new Field(0);
                $oOrder->save();

                return $result;
            }

            try {
                $this->kustomOrderData = $this->retrieveKustomOrder($this->getViewDataElement('sCountryISO'));
            } catch (KustomWrongCredentialsException $e) {
                $this->addTplParam('sErrorMessage', $e->getMessage());

                $oOrder->oxorder__fckustom_sync = new Field(0);
                $oOrder->save();

                return $result;
            } catch (KustomOrderNotFoundException $e) {
                $this->addTplParam('sErrorMessage', $e->getMessage());

                $oOrder->oxorder__fckustom_sync = new Field(0);
                $oOrder->save();

                return $result;
            } catch (StandardException $e) {
                $this->addTplParam('sErrorMessage', $e->getMessage());

                $oOrder->oxorder__fckustom_sync = new Field(0);
                $oOrder->save();

                return $result;
            }

            $this->setInitSyncStatus($oOrder);
        }

        return $result;
    }

    /**
     *
     */
    public function render()
    {
        $parent = parent::render();

        $this->addTplParam('isKustomOrder', $this->isKustomOrder());
        $oOrder = $this->getEditObject(true);

        if ($this->getViewDataElement('isKustomOrder') && $oOrder->getFieldData('oxstorno') == 0) {

            //check if credentials are valid
            if (!$this->isCredentialsValid()) {
                $this->addTplParam('sWarningMessage', sprintf(Registry::getLang()->translateString("KUSTOM_MID_CHANGED_FOR_COUNTRY"),
                    $this->getViewDataElement('sMid'),
                    $this->getViewDataElement('sCountryISO'),
                    $this->getViewDataElement('currentMid')
                ));

                return $parent;
            }

            if (Registry::get(Request::class)->getRequestEscapedParameter('fnc')) {

                try {
                    $this->kustomOrderData = $this->retrieveKustomOrder($this->getViewDataElement('sCountryISO'));
                } catch (StandardException $e) {
                    $this->addTplParam('sErrorMessage', $e->getMessage());

                    $oOrder->oxorder__fckustom_sync = new Field(0);
                    $oOrder->save();

                    return $parent;
                }
            }

            $this->handleResponseOrderData($oOrder);
        }

        return $parent;
    }

    /**
     * Sends order. Captures kustom order.
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function sendorder()
    {
        $cancelled = $this->getEditObject() ? ($this->getEditObject()->getFieldData('oxstorno') == 1) : false;

        $result = parent::sendorder();

        if (!$this->isKustomOrder()) {
            return $result;
        }

        //force reload
        /** @var KustomOrder|Order $oOrder */
        $oOrder = $this->getEditObject(true);
        $inSync = $oOrder->getFieldData('fckustom_sync') == 1;

        if ($cancelled) {
            $this->addTplParam('sErrorMessage', Registry::getLang()->translateString("FCKUSTOM_CAPUTRE_FAIL_ORDER_CANCELLED"));

            return $result;
        }

        if ($inSync && $this->kustomOrderData['remaining_authorized_amount'] != 0) {
            $orderLang   = (int)$oOrder->getFieldData('oxlang');
            $orderLines  = $oOrder->getNewOrderLinesAndTotals($orderLang, true);
            $data        = array(
                'captured_amount' => KustomUtils::parseFloatAsInt($oOrder->getTotalOrderSum() * 100),
                'order_lines'     => $orderLines['order_lines'],
            );
            $sCountryISO = KustomUtils::getCountryISO($oOrder->getFieldData('oxbillcountryid'));

            try {
                $this->addTplParam('sErrorMessage', '');

                $response = $oOrder->captureKustomOrder($data, $oOrder->getFieldData('fckustom_orderid'), $sCountryISO);
            } catch (StandardException $e) {
                $this->addTplParam('sErrorMessage', $e->getMessage());

                return $result;
            }
            if ($response === true) {
                $this->addTplParam('sMessage', Registry::getLang()->translateString("KUSTOM_CAPTURE_SUCCESSFULL"));
            }
            $this->kustomOrderData = $this->retrieveKustomOrder($this->getViewDataElement('sCountryISO'));
        }

        return $result;
    }


    /**
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function save()
    {
        $oldDiscountVal = $this->getEditObject()->getFieldData('oxdiscount');
        $oldOrderNum    = $this->getEditObject()->getFieldData('oxordernr');
        parent::save();

        if (!$this->isKustomOrder()) {
            return;
        }

        //force reload
        $oOrder    = $this->getEditObject(true);
        $inSync    = $oOrder->getFieldData('fckustom_sync') == 1;
        $cancelled = $oOrder->getFieldData('oxstorno') == 1;

        if ($cancelled || !$inSync) {
            return;
        }

        $orderLang       = (int)$oOrder->getFieldData('oxlang');
        $kustomOrderId   = $oOrder->getFieldData('fckustom_orderid');
        $sCountryISO     = $oOrder->getFieldData('oxbillcountryid');
        $captured        = $this->kustomOrderData['captured_amount'] > 0;
        $discountChanged = $this->discountChanged($oldDiscountVal);

        //new discount
        if ($discountChanged) {
            if ($captured) {
                $this->addTplParam('sErrorMessage', Registry::getLang()->translateString('FCKUSTOM_ORDER_UPDATE_CANT_BE_SENT_TO_KUSTOM'));

                return;
            }

            Registry::getSession()->deleteVariable('Errors');
            $orderLines = $oOrder->getNewOrderLinesAndTotals($orderLang);
            $error      = $oOrder->updateKustomOrder($orderLines, $kustomOrderId, $sCountryISO);
            if ($error) {
                $this->addTplParam('sErrorMessage', $error);
            }
        }

        $this->handleKustomUpdates($sCountryISO, $oldOrderNum, $kustomOrderId, $captured, $oOrder);
    }

    /**
     * @param $oldDiscountVal
     * @return bool
     */
    protected function discountChanged($oldDiscountVal)
    {
        $edit = Registry::get(Request::class)->getRequestEscapedParameter('editval');

        return $edit['oxorder__oxdiscount'] != $oldDiscountVal;
    }

    /**
     * @return bool
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function isCredentialsValid()
    {
        $this->addTplParam('sMid', $this->getEditObject()->getFieldData('fckustom_merchantid'));
        $countryISO = KustomUtils::getCountryISO($this->getEditObject()->getFieldData('oxbillcountryid'));
        $this->addTplParam('sCountryISO', $countryISO);
        $currentMid = KustomUtils::getAPICredentials($this->getViewDataElement('sCountryISO'));
        $this->addTplParam('currentMid', $currentMid['mid']);

        if (strstr($this->getViewDataElement('currentMid'), $this->getViewDataElement('sMid'))) {
            return true;
        }

        return false;
    }

    /**
     * Returns editable order object
     *
     * @param bool $reset
     * @return Order
     */
    public function getEditObject($reset = false)
    {
        $soxId = $this->getEditObjectId();
        if (($this->_oEditObject === null && isset($soxId) && $soxId != '-1') || $reset) {
            $this->_oEditObject = oxNew(Order::class);
            $this->_oEditObject->load($soxId);
        }

        return $this->_oEditObject;
    }

    /**
     * Method checks is order was made with Kustom module
     *
     * @return bool
     */
    public function isKustomOrder()
    {
        $blActive = false;

        if ($this->getEditObject() && stripos($this->getEditObject()->getFieldData('oxpaymenttype'), 'kustom_') !== false) {
            $blActive = true;
        }

        return $blActive;
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
     * @param $kustomOrderData
     * @return bool
     */
    public function isCaptureInSync($kustomOrderData)
    {
        if ($kustomOrderData['status'] === 'PART_CAPTURED') {
            if ($this->getEditObject()->getFieldData('oxsenddate') != "-") {
                return true;
            }

            return false;
        }
        if ($kustomOrderData['status'] === 'AUTHORIZED') {

            return true;
        }

        return true;
    }

    /**
     * @param $oOrder
     */
    protected function setInitSyncStatus($oOrder)
    {
        if (is_array($this->kustomOrderData)) {
            $kustomOrderTotalSum = KustomUtils::parseFloatAsInt($oOrder->getTotalOrderSum() * 100);

            if ($this->kustomOrderData['order_amount'] != $kustomOrderTotalSum ||
                ($this->kustomOrderData['remaining_authorized_amount'] != $kustomOrderTotalSum &&
                 $this->kustomOrderData['remaining_authorized_amount'] != 0
                ) || !$this->isCaptureInSync($this->kustomOrderData)
                || $this->kustomOrderData['status'] === 'CANCELLED'
            ) {
                $oOrder->oxorder__fckustom_sync = new Field(0);
            } else {
                $oOrder->oxorder__fckustom_sync = new Field(1);
            }
            $oOrder->save();
        }
    }

    /**
     * @param $oOrder
     */
    protected function handleResponseOrderData($oOrder)
    {
        if (is_array($this->kustomOrderData)) {
            $apiDisabled = Registry::getLang()->translateString("FCKUSTOM_NO_REQUESTS_WILL_BE_SENT");
            if ($this->kustomOrderData['status'] === 'CANCELLED') {
                $oOrder->oxorder__fckustom_sync = new Field(0);

                $orderCancelled = Registry::getLang()->translateString("KUSTOM_ORDER_IS_CANCELLED");
                $this->addTplParam('sWarningMessage', $orderCancelled . $apiDisabled);

            } else if ($this->kustomOrderData['order_amount'] != KustomUtils::parseFloatAsInt($oOrder->getTotalOrderSum() * 100)
                       || !$this->isCaptureInSync($this->kustomOrderData)) {
                $oOrder->oxorder__fckustom_sync = new Field(0);

                $orderNotInSync = Registry::getLang()->translateString("KUSTOM_ORDER_NOT_IN_SYNC");
                $this->addTplParam('sWarningMessage', $orderNotInSync . $apiDisabled);

            } else {
                $oOrder->oxorder__fckustom_sync = new Field(1);
            }
            $oOrder->save();
        }
    }

    /**
     * @param $sCountryISO
     * @return KustomClientBase|KustomOrderManagementClient
     */
    protected function getKustomMgmtClient($sCountryISO)
    {
        return KustomOrderManagementClient::getInstance($sCountryISO);
    }

    /**
     * @param $sCountryISO
     * @param $edit
     * @param $oldOrderNum
     * @param $kustomOrderId
     * @param $oLang
     * @param $captured
     * @param $oOrder
     */
    protected function handleKustomUpdates($sCountryISO, $oldOrderNum, $kustomOrderId, $captured, $oOrder)
    {
        $edit         = Registry::get(Request::class)->getRequestEscapedParameter('editval');
        $trackingCode = $edit['oxorder__oxtrackcode'];
        $orderNr      = (int)$edit['oxorder__oxordernr'];
        //new order number
        if ($orderNr != $oldOrderNum) {
            $this->changeOrderNr($kustomOrderId, $sCountryISO, $orderNr);
        }
        //shipment tracking number
        if ($trackingCode && $captured) {
            $this->addTrackingCode($kustomOrderId, $trackingCode, $sCountryISO);
        }

        $oOrder->oxorder__fckustom_sync = new Field(1);
        $oOrder->save();
    }

    /**
     * @param $kustomOrderId
     * @param $trackingCode
     * @param $sCountryISO
     */
    protected function addTrackingCode($kustomOrderId, $trackingCode, $sCountryISO)
    {
        if (!$trackingCode) {
            return;
        }
        $client = $this->getKustomMgmtClient($sCountryISO);

        $data       = [
            'shipping_info' => [
                [
                    'tracking_number' => $trackingCode,
                ],
            ],
        ];
        $capture_id = $this->kustomOrderData['captures'][0]['capture_id'];

        try {
            $client->addShippingToCapture($data, $kustomOrderId, $capture_id);
        } catch (StandardException $e) {
            $this->addTplParam('sErrorMessage', Registry::getLang()->translateString('FCKUSTOM_ORDER_UPDATE_CANT_BE_SENT_TO_KUSTOM'));
        }
    }

    /**
     * @param $kustomOrderId
     * @param $sCountryISO
     * @param $orderNr
     */
    protected function changeOrderNr($kustomOrderId, $sCountryISO, $orderNr)
    {
        $client = $this->getKustomMgmtClient($sCountryISO);

        try {
            $client->sendOxidOrderNr($orderNr, $kustomOrderId);
        } catch (StandardException $e) {
            $this->addTplParam('sErrorMessage', Registry::getLang()->translateString('FCKUSTOM_ORDER_UPDATE_CANT_BE_SENT_TO_KUSTOM'));
        }
    }
}
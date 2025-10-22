<?php

namespace Fatchip\FcKustom\Controller\Admin;


use Fatchip\FcKustom\Core\KustomOrderManagementClient;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Core\Exception\KustomOrderNotFoundException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use Fatchip\FcKustom\Model\KustomOrder;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;

class KustomOrderArticle extends KustomOrderArticle_parent
{
    public $orderLang;

    protected $kustomOrderData;

    /**
     * @return mixed
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
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
                $this->addTplParam('sErrorMessage', Registry::getLang()->translateString("KUSTOM_UNAUTHORIZED_REQUEST"));

                $oOrder->oxorder__fckustom_sync = new Field(0);
                $oOrder->save();

                return $result;
            } catch (KustomOrderNotFoundException $e) {
                $this->addTplParam('sErrorMessage', Registry::getLang()->translateString("KUSTOM_ORDER_NOT_FOUND"));

                $oOrder->oxorder__fckustom_sync = new Field(0);
                $oOrder->save();

                return $result;
            } catch (StandardException $e) {
                $this->addTplParam('sErrorMessage', $e->getMessage());

                $oOrder->oxorder__fckustom_sync = new Field(0);
                $oOrder->save();

                return $result;
            }

            $this->setinitSyncStatus($oOrder);
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
                } catch (KustomWrongCredentialsException $e) {
                    $this->addTplParam('sErrorMessage', Registry::getLang()->translateString("KUSTOM_UNAUTHORIZED_REQUEST"));
                    $oOrder->oxorder__fckustom_sync = new Field(0);
                    $oOrder->save();

                    return $parent;
                } catch (KustomOrderNotFoundException $e) {
                    $this->addTplParam('sErrorMessage', Registry::getLang()->translateString("KUSTOM_ORDER_NOT_FOUND"));

                    $oOrder->oxorder__fckustom_sync = new Field(0);
                    $oOrder->save();

                    return $parent;
                } catch (StandardException $e) {
                    $this->addTplParam('sErrorMessage', $e->getMessage());

                    $oOrder->oxorder__fckustom_sync = new Field(0);
                    $oOrder->save();

                    return $parent;
                }
            }

            $this->handleWarningMessages($oOrder);
        }

        return $parent;
    }

    /**
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function updateOrder()
    {
        parent::updateOrder();
        $this->updateKustomOrder(true);
    }

    /**
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function deleteThisArticle()
    {
        parent::deleteThisArticle();
        $this->updateKustomOrder(true);
    }

    /**
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function storno()
    {
        parent::storno();
        $this->updateKustomOrder();
    }

    /**
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function addThisArticle()
    {
        parent::addThisArticle();
        $this->updateKustomOrder();
    }

    protected function updateKustomOrder($reset = false)
    {
        /** @var Order $oOrder */
        if ($this->isKustomOrder() && $this->getEditObject()->getFieldData('fckustom_sync') == 1) {

            $orderLines  = $this->getEditObject($reset)->getNewOrderLinesAndTotals($this->orderLang);
            $sCountryISO = KustomUtils::getCountryISO($this->getEditObject()->oxorder__oxbillcountryid->value);

            $error = $this->getEditObject()->updateKustomOrder($orderLines, $this->getEditObject()->oxorder__fckustom_orderid->value, $sCountryISO);

            if ($error) {
                $this->addTplParam('sErrorMessage', $error);
            }
        }
    }

    /**
     * Method checks is order was made with Kustom module
     *
     * @return bool
     */
    public function isKustomOrder()
    {
        $blActive = false;

        if ($this->getEditObject(true) && stripos($this->getEditObject()->getFieldData('oxpaymenttype'), 'kustom_') !== false) {
            $blActive = true;
        }

        return $blActive && $this->getEditObject()->getFieldData('oxstorno') == 0;
    }

    /**
     * @return bool
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function isCredentialsValid()
    {
        $this->addTplParam('sMid', $this->getEditObject()->getFieldData('fckustom_merchantid'));
        $this->addTplParam('sCountryISO', KustomUtils::getCountryISO($this->getEditObject()->getFieldData('oxbillcountryid')));
        $currentMid = KustomUtils::getAPICredentials($this->getViewDataElement('sCountryISO'));
        $this->addTplParam('currentMid', $currentMid['mid']);

        if (strstr($this->getViewDataElement('currentMid'), $this->getViewDataElement('sMid'))) {
            return true;
        }

        return false;
    }

    /**
     * @param null $sCountryISO
     * @return mixed
     * @throws StandardException
     * @throws \Fatchip\FcKustom\Core\Exception\KustomClientException
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function retrieveKustomOrder($sCountryISO = null)
    {
        if (!$sCountryISO) {
            $sCountryISO = KustomUtils::getCountryISO($this->getEditObject()->getFieldData('oxbillcountryid'));
        }

        /** @var KustomOrderManagementClient $client */
        $client = KustomOrderManagementClient::getInstance($sCountryISO);

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
     * Returns editable order object
     *
     * @param bool $reset
     * @return Order|KustomOrder
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
     * @param $oOrder
     */
    protected function setinitSyncStatus($oOrder)
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
    protected function handleWarningMessages($oOrder)
    {
        if (is_array($this->kustomOrderData)) {
            $apiDisabled = Registry::getLang()->translateString("FCKUSTOM_NO_REQUESTS_WILL_BE_SENT");
            if ($this->kustomOrderData['status'] === 'CANCELLED') {
                $oOrder->oxorder__fckustom_sync = new Field(0);

                $orderCancelled = Registry::getLang()->translateString("KUSTOM_ORDER_IS_CANCELLED");
                $this->addtplParam('sWarningMessage', $orderCancelled . $apiDisabled);

            } else if ($this->kustomOrderData['order_amount'] != KustomUtils::parseFloatAsInt($oOrder->getTotalOrderSum() * 100)
                       || !$this->isCaptureInSync($this->kustomOrderData)) {
                $oOrder->oxorder__fckustom_sync = new Field(0);

                $orderNotInSync = Registry::getLang()->translateString("KUSTOM_ORDER_NOT_IN_SYNC");
                $this->addtplParam('sWarningMessage', $orderNotInSync . $apiDisabled);

            } else {
                $oOrder->oxorder__fckustom_sync = new Field(1);
            }
            $oOrder->save();
        }
    }
}
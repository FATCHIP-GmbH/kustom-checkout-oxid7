<?php

namespace Fatchip\FcKustom\Controller\Admin;


use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\UtilsView;

class KustomOrderList extends KustomOrderList_parent
{
    /**
     *
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function deleteEntry()
    {
        $result = $this->cancelKustomOrder();

        if ($result) {
            parent::deleteEntry();
        }
    }


    /**
     *
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function cancelOrder()
    {
        $result = $this->cancelKustomOrder();
        if ($result) {
            parent::cancelOrder();
        }
    }

    protected function cancelKustomOrder()
    {
        $orderId = $this->getEditObjectId();
        $oOrder  = oxNew(Order::class);
        $oOrder->load($orderId);

        if ($oOrder->isLoaded() && $oOrder->isKustomOrder() && !$oOrder->getFieldData('oxstorno')) {
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
                $_POST['oxid'] = -1;
                $this->resetContentCache();
                $this->init();

                return false;
            }
        }

        return true;
    }
}
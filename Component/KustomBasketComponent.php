<?php

namespace Fatchip\FcKustom\Component;


use Fatchip\FcKustom\Core\KustomClientBase;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Core\KustomCheckoutClient;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Registry;

/**
 * Basket component
 *
 * @package Kustom
 * @extend OxCmp_basket
 */
class KustomBasketComponent extends KustomBasketComponent_parent
{

    /**
     * @param null $sProductId
     * @param null $dAmount
     * @param null $aSel
     * @param null $aPersParam
     * @param bool $blOverride
     */
    public function changebasket($sProductId = null, $dAmount = null, $aSel = null, $aPersParam = null, $blOverride = true)
    {
        parent::changebasket($sProductId, $dAmount, $aSel, $aPersParam, $blOverride);

        if (Registry::getSession()->hasVariable('kustom_checkout_order_id')) {
            try {
                $this->updateKustomOrder();
            } catch (StandardException $e) {
                KustomUtils::logException($e);
                KustomUtils::fullyResetKustomSession();
            }
        }
    }

    /**
     * @param null $sProductId
     * @param null $dAmount
     * @param null $aSel
     * @param null $aPersParam
     * @param bool $blOverride
     */
    public function toBasket($sProductId = null, $dAmount = null, $aSel = null, $aPersParam = null, $blOverride = false)
    {
        $result = parent::toBasket($sProductId, $dAmount, $aSel, $aPersParam, $blOverride);

        if (Registry::getSession()->hasVariable('kustom_checkout_order_id')) {
            try {
                $this->updateKustomOrder();
            } catch (StandardException $e) {
                KustomUtils::logException($e);
                KustomUtils::fullyResetKustomSession();
            }
        }

        return $result;
    }

    /**
     * Sends update request to checkout API
     * @return array order data
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     * @throws \oxSystemComponentException
     * @internal param oxBasket $oBasket
     * @internal param oxUser $oUser
     */
    protected function updateKustomOrder()
    {
        $orderLines = Registry::getSession()->getBasket()->getKustomOrderLines();
        $oClient    = $this->getKustomCheckoutClient();

        return $oClient->createOrUpdateOrder(json_encode($orderLines));
    }

    /**
     * @codeCoverageIgnore
     * @return KustomCheckoutClient|KustomClientBase
     */
    protected function getKustomCheckoutClient()
    {
        return KustomCheckoutClient::getInstance();
    }
}

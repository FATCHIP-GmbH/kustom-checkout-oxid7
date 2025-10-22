<?php


namespace Fatchip\FcKustom\Core;


use OxidEsales\Eshop\Core\Registry;

class KustomShopControl extends KustomShopControl_parent
{

    protected function initializeViewObject($sClass, $sFunction, $aParams = null, $aViewsChain = null)
    {
        // detect paypal button clicks
        $searchTerm = 'paypalExpressCheckoutButton';
        $found = array_filter(array_keys($_REQUEST), function ($paramName) use($searchTerm) {
            return strpos($paramName, $searchTerm) !== false;
        });
        // remove KCO id from session
        if ($found) {
            Registry::getSession()->deleteVariable('kustom_checkout_order_id');
            KustomUtils::log('debug','Paypal button usage detected: ' . json_encode($found, 128));
        }

        return parent::initializeViewObject($sClass, $sFunction, $aParams, $aViewsChain);
    }
}
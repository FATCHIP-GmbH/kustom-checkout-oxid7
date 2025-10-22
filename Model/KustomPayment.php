<?php


namespace Fatchip\FcKustom\Model;


use OxidEsales\Eshop\Core\Registry;
use Fatchip\FcKustom\Core\KustomConsts;

/**
 * Class Kustom_oxPayment extends OXID default oxPayment class to add additional
 * parameters and payment logic required by specific Kustom payments.
 *
 * @package Kustom
 * @extend oxPayment
 */
class KustomPayment extends KustomPayment_parent
{
    /**
     * Oxid value of Kustom Checkout payment
     *
     * @var string
     */
    const KUSTOM_PAYMENT_CHECKOUT_ID = 'kustom_checkout';

    public static function getKustomPaymentsId()
    {
        return self::KUSTOM_PAYMENT_CHECKOUT_ID;
    }


    /**
     * Check if payment is Kustom payment
     *
     * @deprecated use KustomPaymentHelper
     * @param string $paymentId
     * @return bool
     */
    public static function isKustomPayment($paymentId)
    {
        return $paymentId === self::KUSTOM_PAYMENT_CHECKOUT_ID;
    }

    /**
     * Fetch badge url from kustom session data kept in the user session object.
     * @param string $variant
     * @return string
     */
    public function getBadgeUrl($variant = 'standard')
    {
        $klName = $this->getPaymentCategoryName();

        $oSession = Registry::getSession();
        if ($sessionData = $oSession->getVariable('kustom_session_data')) {
            $methodData = array_search($klName, array_column($sessionData['payment_method_categories'], 'identifier'));
            if ($methodData !== null) {

                return $sessionData['payment_method_categories'][$methodData]['asset_urls'][$variant];
            }
        }

        $from   = '/' . preg_quote('-', '/') . '/';
        $locale = preg_replace($from, '_', strtolower(oxNew(KustomConsts::class)->getLocale()), 1);

        //temp fix for payment name mismatch slice_it -> pay_over_time
        if ($klName === 'pay_over_time') {
            $klName = 'slice_it';
        }

        if ($this->checkUrl(
                sprintf(
                    "https://cdn.klarna.com/1.0/shared/image/generic/badge/%s/%s/standard/pink.png",
                    $locale,
                    $klName
                )
            ) == false) {
            $locale = preg_replace($from, '_', strtolower(oxNew(KustomConsts::class)->getLocale(true)), 1);
        }

        return sprintf("https://cdn.klarna.com/1.0/shared/image/generic/badge/%s/%s/standard/pink.png",
            $locale,
            $klName
        );

    }

    /**
     * @param $url
     * @return bool
     */
    protected function checkUrl($url) {
        if (!$url) { return false; }
        $curl_resource = curl_init($url);
        curl_setopt($curl_resource, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curl_resource);
        if(curl_getinfo($curl_resource, CURLINFO_HTTP_CODE) == 404) {
            curl_close($curl_resource);
            return false;
        } else {
            curl_close($curl_resource);
            return true;
        }
    }
}

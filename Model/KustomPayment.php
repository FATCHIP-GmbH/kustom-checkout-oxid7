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

}

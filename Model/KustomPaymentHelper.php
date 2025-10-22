<?php


namespace Fatchip\FcKustom\Model;


class KustomPaymentHelper
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
     * @param string $paymentId
     * @return bool
     */
    public static function isKustomPayment($paymentId)
    {
        return $paymentId === self::KUSTOM_PAYMENT_CHECKOUT_ID;
    }

}

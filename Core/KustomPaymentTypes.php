<?php

namespace Fatchip\FcKustom\Core;

use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Database\Adapter\Doctrine\ResultSet;

class KustomPaymentTypes extends KustomClientBase
{
    /**
     * Oxid value of Kustom Checkout payment
     *
     * @var string
     */
    const KUSTOM_PAYMENT_CHECKOUT_ID = 'kustom_checkout';

    /**
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     * @return array
     */
    public static function getKustomAllowedExternalPayments()
    {
        $result = array();
        $db     = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
        $sql    = 'SELECT oxid FROM oxpayments WHERE OXACTIVE=1 AND FCKUSTOM_EXTERNALPAYMENT=1';
        /** @var ResultSet $oRs */
        $oRs = $db->select($sql);
        foreach ($oRs->getIterator() as $payment) {
            $result[] = $payment['oxid'];
        }

        return $result;
    }
}
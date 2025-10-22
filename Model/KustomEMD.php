<?php


namespace Fatchip\FcKustom\Model;


use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Model\EmdPayload\KustomCustomerAccountInfo;
use Fatchip\FcKustom\Model\EmdPayload\KustomPaymentHistoryFull;
use OxidEsales\Eshop\Application\Model\User;

/**
 * Class KustomEMD
 *
 * @package Kustom
 */
class KustomEMD
{
    /**
     * Date format
     *
     * @var string
     */
    const EMD_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * Get attachments from basket
     *
     * @param User $oUser
     * @return array
     */
    public function getAttachments(User $oUser)
    {
        $return = array();

        if (KustomUtils::getShopConfVar('blKustomEmdCustomerAccountInfo')) {
            $return = array_merge($return, $this->getCustomerAccountInfo($oUser));
        }
        if (KustomUtils::getShopConfVar('blKustomEmdPaymentHistoryFull')) {
            $return = array_merge($return, $this->getPaymentHistoryFull($oUser));
        }

        return $return;
    }

    /**
     * Get customer account info
     *
     * @param User $oUser
     * @return array
     */
    protected function getCustomerAccountInfo(User $oUser)
    {
        /** @var KustomCustomerAccountInfo $oKustomPayload */
        $oKustomPayload = oxNew(KustomCustomerAccountInfo::class);

        return $oKustomPayload->getCustomerAccountInfo($oUser);
    }

    /**
     * Get payment history
     *
     * @param User $oUser
     * @return array
     */
    protected function getPaymentHistoryFull(User $oUser)
    {
        /** @var KustomPaymentHistoryFull $oKustomPayload */
        $oKustomPayload = oxNew(KustomPaymentHistoryFull::class);

        return $oKustomPayload->getPaymentHistoryFull($oUser);
    }
}

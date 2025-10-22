<?php


namespace Fatchip\FcKustom\Model\EmdPayload;


use Fatchip\FcKustom\Model\KustomEMD;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\ShopVersion;

/**
 * Class for getting customer information
 *
 * @package Kustom
 */
class KustomCustomerAccountInfo
{
    /**
     * Max length of user ID (_sOXID value)
     *
     * @var int
     */
    const MAX_IDENTIFIER_LENGTH = 24;

    /**
     * "type": "string",
     * "maxLength": 24
     *
     * @var string
     */
    protected $unique_account_identifier;

    /**
     * "description": "ISO 8601 e.g. 2012-11-24T15:00",
     * "type": "string",
     * "format": "date-time",
     * "pattern": "^[0-9][0-9][0-9][0-9]-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-5][0-9](:[0-5][0-9]){0,1}Z{0,1}$"
     *
     * @var string
     */
    protected $account_registration_date;

    /**
     * "description": "ISO 8601 e.g. 2012-11-24T15:00",
     * "type": "string",
     * "format": "date-time",
     * "pattern": "^[0-9][0-9][0-9][0-9]-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-5][0-9](:[0-5][0-9]){0,1}Z{0,1}$"
     *
     * @var string
     */
    protected $account_last_modified;

    /**
     * unique_account_identifier - OXUSER.OXID
     * account_registration_date - OXUSER.OXCREATE
     * account_last_modified - OXUSER.OXTIMESTAMP
     *
     * @param User $user
     * @return array
     */
    public function getCustomerAccountInfo(User $user)
    {
        $oxCreate = $user->oxuser__oxcreate->value;

        if (isset($oxCreate) && $oxCreate != '-') {
            $registration = new \DateTime($user->oxuser__oxcreate->value);
        } else {
            $registration = new \DateTime($user->oxuser__oxregister->value);
        }

        $registration->setTimezone(new \DateTimeZone('Europe/London'));
        $customerInfo = array(
            "unique_account_identifier" => substr($user->getId(), 0, self::MAX_IDENTIFIER_LENGTH),
            "account_registration_date" => $registration->format(KustomEMD::EMD_FORMAT),
        );


        $modification = new \DateTime($user->oxuser__oxtimestamp->value);
        $modification->setTimezone(new \DateTimeZone('Europe/London'));
        $customerInfo["account_last_modified"] = $modification->format(KustomEMD::EMD_FORMAT);


        $customerInfo = array($customerInfo);

        return array(
            "customer_account_info" => $customerInfo,
        );
    }
}

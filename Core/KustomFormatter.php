<?php


namespace Fatchip\FcKustom\Core;


use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Base;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use Fatchip\FcKustom\Model\KustomUser;

/**
 * Class KustomFormatter
 */
class KustomFormatter extends Base
{
    static $aFieldMapper = array(
        'oxusername'    => 'email',
        'oxfname'       => 'given_name',
        'oxlname'       => 'family_name',
        'joinedAddress' => 'street_address',
        'oxstreet'      => 'street_name',
        'oxstreetnr'    => 'street_number',
        'oxzip'         => 'postal_code',
        'oxcity'        => 'city',
        'oxstateid'     => 'region',
        'oxmobfon'      => 'phone',
        'oxfon'         => 'phone',
        'oxcountryid'   => 'country',
        'oxsal'         => 'title',
        'oxcompany'     => 'organization_name',
        'oxaddinfo'     => 'street_address2',
        'oxbirthdate'   => 'date_of_birth',
    );

    static $aMaleSalutations = array(
        'Mr'   => array('gb', 'other'),
        'Herr' => array('de', 'at', 'ch'),
        'Dhr.' => array('nl'),
    );

    static $aFemaleSalutations = array(
        'Ms'    => array('gb', 'other'),
        'Mrs'   => array('gb'),
        'Miss'  => array('gb'),
        'Frau'  => array('de', 'at', 'ch'),
        'Mevr.' => array('nl'),
    );

    /**
     * @param $aCheckoutData array  Kustom address
     * @param $sKey string kustom address key ('billing_address'|'shipping_address')
     * @return array
     */
    public static function kustomToOxidAddress($aCheckoutData, $sKey)
    {
        if(!$aCheckoutData){
            return null;
        }

        $aAddressData = $aCheckoutData[$sKey];
        $sTable       = ($sKey == 'billing_address') ? 'oxuser__' : 'oxaddress__';

        $matches = array();
        preg_match('/([^0-9])+/', $aAddressData['street_address'], $matches);
        $aAddressData['street_name']   = $matches[0];
        $aAddressData['street_number'] = str_replace($aAddressData['street_name'], '', $aAddressData['street_address']);

        $oCountry                = oxNew(Country::class);
        $countryISO = $aAddressData['country'];
        $aAddressData['country'] = $oCountry->getIdByCode(strtoupper($countryISO));

        $aUserData = array();
        foreach (self::$aFieldMapper as $oxName => $kustomName) {
            if ($kustomName === 'street_address') {
                continue;
            } else if ($kustomName === 'title') {
                $aUserData[$sTable . $oxName] = self::formatSalutation($aAddressData[$kustomName], strtolower($countryISO));
            } else {
                $aUserData[$sTable . $oxName] = trim($aAddressData[$kustomName]);
            }
        }

        return $aUserData;
    }

    /**
     * @param $oxObject KustomUser|User|Address
     * @return array
     * @throws \Exception
     */
    public static function oxidToKustomAddress($oxObject)
    {
        $sTable = self::validateInstance($oxObject);

        $aUserData   = array();
        $sCountryISO = strtolower(KustomUtils::getCountryISO($oxObject->{$sTable . 'oxcountryid'}->value));

        self::compileUserData($aUserData, $oxObject, $sTable, $sCountryISO);

        //clean up
        foreach ($aUserData as $key => $value) {
            $aUserData[$key] = html_entity_decode($aUserData[$key], ENT_QUOTES);
            if (!$value) {
                unset($aUserData[$key]);
            }
        }

        return $aUserData;
    }

    protected static function compileUserData(&$aUserData, $oxObject, $sTable, $sCountryISO)
    {
        $ignoreNames = ['date_of_birth', 'street_name', 'street_number'];
        $aExtendFieldMapper = self::$aFieldMapper;
        $aExtendFieldMapper['oxaddinfo'] = "care_of";
        
        //Remove unwanted fields
        $validMappedFields = array_diff($aExtendFieldMapper, $ignoreNames);

        foreach ($validMappedFields as $oxName => $kustomName) {
            switch ($kustomName) {
                case 'street_address':
                    $aUserData[$kustomName] = "{$oxObject->{$sTable . 'oxstreet'}->value} {$oxObject->{$sTable . 'oxstreetnr'}->value}";
                    $aUserData[$kustomName] = $aUserData[$kustomName] !== ' ' ? $aUserData[$kustomName] : null;
                    break;
                case 'country':
                    $aUserData[$kustomName] = $sCountryISO;
                    break;
                case 'title':
                    $aUserData[$kustomName] = null;
                    $sTitle = self::formatSalutation($oxObject->{$sTable.'oxsal'}->value, $sCountryISO);
                    if (!empty($sTitle)) {
                        $aUserData[$kustomName] = $sTitle;
                    }
                    break;
                case 'phone':
                    if (empty($aUserData[$kustomName])) {
                        $value = $oxObject->{$sTable.$oxName}->value;
                        $aUserData[$kustomName] = !empty($value) ? $value : null;
                    }
                    break;

                default:
                    $value = $oxObject->{$sTable.$oxName}->value;
                    $aUserData[$kustomName] = null;
                    if (!empty($value)) {
                        $aUserData[$kustomName] = $value;
                    }
            }
        }
        if (!empty($aUserData["care_of"])) {
            $aUserData["care_of"] = preg_replace("/^c\/o /", "", $aUserData["care_of"]);
        }
    }

    /**
     * @param $oxObject
     * @return string
     * @throws \Exception
     */
    protected static function validateInstance(&$oxObject)
    {
        if ($oxObject instanceof User) {
            $sTable = 'oxuser__';
        } else if ($oxObject instanceof Address) {
            $sTable   = 'oxaddress__';
            $oxObject = self::completeUserData($oxObject);
        } else{
            throw new \Exception('Argument must be instance of User|Address.');
        }

        return $sTable;
    }

    /**
     * @param Address $oAddress
     * @return Address
     */
    public static function completeUserData(Address $oAddress)
    {
        $oUser  = Registry::getConfig()->getUser();
        $sEmail = $oUser->oxuser__oxusername->value;
        if (!$oUser) {
            $sEmail = Registry::getSession()->getVariable('kustom_checkout_user_email');
        }
        $oAddress->oxaddress__oxusername = new Field($sEmail, Field::T_RAW);

        return $oAddress;
    }

    /**
     * Resolve the proper salutation for any country.
     *
     * @param $title
     * @param $sCountryISO
     * @return string
     */
    public static function formatSalutation($title, $sCountryISO)
    {
        if (!$title) {
            return false;
        }

        $title = ucfirst(strtolower($title));
        if (!in_array(strtolower($sCountryISO), array('gb', 'de', 'at', 'ch', 'nl'))) {
            $sCountryISO = 'other';
        }

        if (key_exists($title, self::$aMaleSalutations)) {
            $table = self::$aMaleSalutations;
        } else {
            $table = self::$aFemaleSalutations;
        }

        if (in_array(strtolower($sCountryISO), $table[$title])) {
            return $title;
        }

        foreach ($table as $sSal => $aCountries) {
            if (in_array(strtolower($sCountryISO), $aCountries)) {
                return $sSal;
            }
        }
        //@codeCoverageIgnoreStart
        return false;
        //@codeCoverageIgnoreEnd
    }


    public static function getFormattedUserAddresses($_oUser)
    {
        $db      = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
        $sql     = 'SELECT oxid, oxfname, oxlname, oxstreet, oxstreetnr, oxzip, oxcity FROM oxaddress WHERE oxuserid=?';
        $results = $db->getAll($sql, array($_oUser->getId()));

        if (!is_array($results) || empty($results)) {
            return false;
        }

        $formattedResults = array();
        foreach ($results as $data) {
            $formattedResults[$data['oxid']] =
                $data['oxfname'] . ' ' .
                $data['oxlname'] . ', ' .
                $data['oxstreet'] . ' ' .
                $data['oxstreetnr'] . ', ' .
                $data['oxzip'] . ' ' .
                $data['oxcity'];
        }

        return $formattedResults;
    }

}

<?php


namespace Fatchip\FcKustom\Core;


use OxidEsales\Eshop\Application\Model\BasketItem;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Price;
use OxidEsales\Eshop\Core\UtilsObject;
use Fatchip\FcKustom\Model\KustomCountryList;
use Fatchip\FcKustom\Model\KustomUser;
use OxidEsales\Eshop\Application\Model\Category;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\CountryList;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingService;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

/**
 * Class KustomUtils
 * @package Fatchip\FcKustom\Core
 */
class KustomUtils
{
    /**
     * Datatype string to get/set ModuleVars
     */
    public const DATA_TYPE_STRING = 'string';

    /**
     * Datatype boolean to get/set ModuleVars
     */
    public const DATA_TYPE_BOOLEAN = 'boolean';

    /**
     * Datatype integer to get/set ModuleVars
     */
    public const DATA_TYPE_INTEGER = 'integer';

    /**
     * Datatype collection to get/set ModuleVars
     */
    public const DATA_TYPE_COLLECTION = 'collection';

    /**
     * @param null $email
     * @return User
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public static function getFakeUser($email = null)
    {
        /** @var User | KustomUser $oUser */
        $oUser = oxNew(User::class);
        $oUser->loadByEmail($email);

        $sCountryISO = Registry::getSession()->getVariable('sCountryISO');
        if ($sCountryISO) {
            $oCountry   = oxNew(Country::class);
            $sCountryId = $oCountry->getIdByCode($sCountryISO);
            $oCountry->load($sCountryId);
            $oUser->oxuser__oxcountryid = new Field($sCountryId);
            $oUser->oxuser__oxcountry   = new Field($oCountry->oxcountry__oxtitle->value);
        }
        Registry::getConfig()->setUser($oUser);

        if ($email) {
            Registry::getSession()->setVariable('kustom_checkout_user_email', $email);
        }

        return $oUser;
    }

    /**
     * @param string $sName
     * @param string $sDataType
     * @return mixed
     */
    public static function getShopConfVar($sName, $sDataType = ''): mixed
    {
        /** @var ModuleSettingService $oModuleSettingService */
        $oModuleSettingService = self::getModuleSettings();
        $return = null;

        if(strlen($sDataType) == 0) {
            switch ($sName[0]) {
                case 'b':
                    $return = $oModuleSettingService->getBoolean($sName, 'fckustom');
                    break;
                case 'i':
                    $return = $oModuleSettingService->getInteger($sName, 'fckustom');
                    break;
                case 's':
                    $return = $oModuleSettingService->getString($sName, 'fckustom')->toString();
                    break;
                case 'a':
                    $return = $oModuleSettingService->getCollection($sName, 'fckustom');
                    break;
            }
        } else {
            switch ($sDataType) {
                case self::DATA_TYPE_BOOLEAN:
                    $return = $oModuleSettingService->getBoolean($sName, 'fckustom');
                    break;
                case self::DATA_TYPE_INTEGER:
                    $return = $oModuleSettingService->getInteger($sName, 'fckustom');
                    break;
                case self::DATA_TYPE_STRING:
                    $return = $oModuleSettingService->getString($sName, 'fckustom')->toString();
                    break;
                case self::DATA_TYPE_COLLECTION:
                    $return = $oModuleSettingService->getCollection($sName, 'fckustom');
                    break;
            }
        }

        return $return;
    }

    /**
     * @param $sCountryId
     * @return mixed
     */
    public static function getCountryISO($sCountryId)
    {
        /** @var Country $oCountry */
        $oCountry = oxNew(Country::class);
        $oCountry->load($sCountryId);

        return $oCountry->getFieldData('oxisoalpha2');
    }

    /**
     * @param null $iLang
     * @return CountryList
     */
    public static function getActiveShopCountries($iLang = null)
    {
        /** @var CountryList $oCountryList */
        $oCountryList = oxNew(CountryList::class);
        $oCountryList->loadActiveCountries($iLang);

        return $oCountryList;
    }

    /**
     * @param null $sCountryISO
     * @return array|mixed
     */
    public static function getAPICredentials($sCountryISO = null)
    {
        if (!$sCountryISO) {
            $sCountryISO = Registry::getSession()->getVariable('sCountryISO');
        }

        if (!$aCredentials = KustomUtils::getShopConfVar('aarrKustomCreds')['aKlarnaCreds_'.$sCountryISO]) {
            $aCredentials = array(
                'mid'      => KustomUtils::getShopConfVar('sKustomMerchantId'),
                'password' => KustomUtils::getShopConfVar('sKustomPassword'),
            );
        }

        return $aCredentials;
    }

    /**
     * @param $sCountryISO
     * @param bool $filterKcoList
     * @return bool
     */
    public static function isCountryActiveInKustomCheckout($sCountryISO, $filterKcoList = true)
    {
        if ($sCountryISO === null) {
            return true;
        }

        /** @var CountryList | \Fatchip\FcKustom\Model\KustomCountryList $activeKustomCountries */
        $activeKustomCountries = Registry::get(CountryList::class);
        $activeKustomCountries->loadActiveKustomCheckoutCountries($filterKcoList);
        if (!count($activeKustomCountries)) {
            return false;
        }
        foreach ($activeKustomCountries as $country) {
            if (strtoupper($sCountryISO) == $country->oxcountry__oxisoalpha2->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function isNonKustomCountryActive()
    {
        $activeNonKustomCountries = Registry::get(CountryList::class);
        $activeNonKustomCountries->loadActiveNonKustomCheckoutCountries();
        if (count($activeNonKustomCountries) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param int|null $iLang
     * @return CountryList|KustomCountryList
     */
    public static function getKustomGlobalActiveShopCountries($iLang = null)
    {
        $oCountryList = oxNew(CountryList::class);
        $oCountryList->loadActiveKustomCheckoutCountries($iLang);

        return $oCountryList;

    }

    /**
     * @return array
     * @codeCoverageIgnore
     *
     */
    public static function getKustomGlobalActiveShopCountryISOs($iLang = null)
    {
        $oCountryList = oxNew(CountryList::class);
        $oCountryList->loadActiveKustomCheckoutCountries($iLang);

        $result = array();
        foreach ($oCountryList as $country) {
            $result[] = $country->oxcountry__oxisoalpha2->value;
        }

        return $result;
    }

    /**
     * @param null $iLang
     * @return CountryList|KustomCountryList
     */
    public static function getAllActiveKCOGlobalCountryList($iLang = null)
    {
        $oCountryList = oxNew(CountryList::class);
        $oCountryList->loadActiveKCOGlobalCountries($iLang);

        return $oCountryList;
    }

    /**
     * @param BasketItem $oItem
     * @param $isOrderMgmt
     * @return array
     * @throws \oxArticleInputException
     * @throws \oxNoArticleException
     */
    public static function calculateOrderAmountsPricesAndTaxes($oItem, $isOrderMgmt)
    {
        $quantity           = self::parseFloatAsInt($oItem->getAmount());
        $regular_unit_price = 0;
        $basket_unit_price  = 0;

        if (!$oItem->isBundle()) {
            $regUnitPrice = $oItem->getRegularUnitPrice();
            if ($isOrderMgmt) {
                if($oItem->getArticle()->isOrderArticle()) {
                    $orderArticlePrice = oxNew(Price::class);
                    $orderArticlePrice->setPrice($oItem->getArticle()->oxorderarticles__oxbprice->value);
                    $regUnitPrice = $orderArticlePrice;
                    $unitPrice = $orderArticlePrice;
                } else {
                    $unitPrice = $oItem->getArticle()->getUnitPrice();
                }
            } else {
                $unitPrice = $oItem->getUnitPrice();
            }

            $regular_unit_price = self::parseFloatAsInt($regUnitPrice->getBruttoPrice() * 100);
            $basket_unit_price  = self::parseFloatAsInt($unitPrice->getBruttoPrice() * 100);
        }

        $total_discount_amount = ($regular_unit_price - $basket_unit_price) * $quantity;
        $total_amount          = $basket_unit_price * $quantity;

        if ($oItem->isBundle()) {
            $tax_rate = self::parseFloatAsInt($oItem->getUnitPrice()->getVat() * 100);
        } else {
            $tax_rate = self::parseFloatAsInt($oItem->getUnitPrice()->getVat() * 100);
        }
//        $total_tax_amount = self::parseFloatAsInt($oItem->getPrice()->getVatValue() * 100);
        $total_tax_amount = self::parseFloatAsInt(
            $total_amount - round($total_amount / ($tax_rate / 10000 + 1), 0)
        );

        $quantity_unit = 'pcs';

        return array($quantity, $regular_unit_price, $total_amount, $total_discount_amount, $tax_rate, $total_tax_amount, $quantity_unit);
    }

    /**
     * @param $number
     *
     * @return int
     */
    public static function parseFloatAsInt($number)
    {
        return (int)(Registry::getUtils()->fRound($number));
    }

    /**
     * @param Category $oCat
     * @param array $aCategories
     * @return array
     */
    public static function getSubCategoriesArray(Category $oCat, $aCategories = array())
    {
        $aCategories[] = $oCat->getTitle();

        if ($oParentCat = $oCat->getParentCategory()) {
            return self::getSubCategoriesArray($oParentCat, $aCategories);
        }

        return $aCategories;
    }

    /**
     * @param $sCountryISO
     * @return string
     */
    public static function resolveLocale($sCountryISO)
    {
        $lang = Registry::getLang()->getLanguageAbbr();
        Registry::getSession()->setVariable('kustom_iframe_lang', $lang);

        return strtolower($lang) . '-' . strtoupper($sCountryISO);
    }

    /**
     * @return bool
     */
    public static function is_ajax()
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower(getenv('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest'));
    }

    /**
     *
     */
    public static function fullyResetKustomSession()
    {
        $session = Registry::getSession();

        $session->deleteVariable('paymentid');
        $session->deleteVariable('kustom_checkout_order_id');
        $session->deleteVariable('amazonOrderReferenceId');
        $session->deleteVariable('kustom_checkout_user_email');
        $session->deleteVariable('externalCheckout');
        $session->deleteVariable('sAuthToken');
        $session->deleteVariable('kustom_session_data');
        $session->deleteVariable('finalizeRequired');
        $session->deleteVariable('sCountryISO');
        $session->deleteVariable('sFakeUserId');

        $session = Registry::getSession();
        if ($session->getVariable("blNeedLogout") && !$session->getVariable("kustomLoggedInNaturally")) {
            if ($session->getUser() && $session->getUser()->logout()) {
                $session->deleteVariable("blNeedLogout");
            }
        }
    }

    /**
     * @param $text
     * @return string|null
     */
    public static function stripHtmlTags($text)
    {
        $result = preg_replace('/<(\/)?[a-z]+[^<]*>/', '', $text);

        return $result ?: null;
    }

    /**
     * @param $iso3
     * @return false|string
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public static function getCountryIso2fromIso3($iso3)
    {
        $sql = 'SELECT oxisoalpha2 FROM oxcountry WHERE oxisoalpha3 = ?';

        return DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->getOne($sql, [$iso3]);
    }

    /**
     * @codeCoverageIgnore
     * @param $orderId
     * @return Order
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public static function loadOrderByKustomId($orderId)
    {
        $oOrder = oxNew(Order::class);
        $oxid   = DatabaseProvider::getDb()->getOne('SELECT oxid from oxorder where fckustom_orderid=?', array($orderId));
        $oOrder->load($oxid);

        return $oOrder;
    }

    public static function registerKustomAckRequest($orderId)
    {
        $sql = 'INSERT INTO `fckustom_ack` (`oxid`, `klreceived`, `fckustom_orderid`) VALUES (?,?,?)';
        DatabaseProvider::getDb()->Execute(
            $sql,
            array(UtilsObject::getInstance()->generateUID(), date('Y-m-d H:i:s'), $orderId)
        );
    }


    public static function addAuthToken($sessionId, $authtoken)
    {
        $sql = 'INSERT INTO fckustom_authtokens (oxid, fckustom_authtoken, fckustom_sessionid) VALUES (?,?,?)';
        DatabaseProvider::getDb()->Execute(
            $sql,
            array(UtilsObject::getInstance()->generateUID(), $authtoken, $sessionId)
        );
    }

    public static function getAuthToken($sessionId)
    {
        $sql = 'SELECT * FROM fckustom_authtokens WHERE fckustom_sessionid = ?';

        return DatabaseProvider::getDb()->getOne($sql, array($sessionId));
    }

    /**
     * @param $e \Exception
     */
    public static function logException($e) {
        if (method_exists(Registry::class, 'getLogger')) {
            Registry::getLogger()->error('KUSTOM ' . $e->getMessage(), [$e]);
        } else {
            $e->debugOut();
        }
    }

    public static function log($level, $message, $context = []) {
        if (method_exists(Registry::class, 'getLogger')) {
            Registry::getLogger()->log($level, 'KUSTOM ' . $message, $context);
        } else {
            $targetLogFile = 'oxideshop.log';
            // eshop 6.0 log wrapper
            $oConfig = Registry::getConfig();
            $iDebug = $oConfig->getConfigParam('iDebug');
            $level =  strtoupper($level);
            $context = json_encode($context);
            if ($level !== 'ERROR' && $iDebug === 0) {
                return; // don't log anything besides errors in production mode
            }
            $date = (new \DateTime())->format('Y-m-d H:i:s');
            Registry::getUtils()->writeToLog(
                "[$date] OXID Logger.$level: KUSTOM $message $context\n",
                $targetLogFile
            );
        }
    }

    /**
     * @param $aArrToMerge
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getModuleSettingsBools($aArrToMerge) {
        return self::getAllModuleSettings(['bool'], $aArrToMerge);
    }

    /**
     * @param $aArrToMerge
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getModuleSettingsAarrs($aArrToMerge) {
        return self::getAllModuleSettings(['aarr', 'arr'], $aArrToMerge);
    }

    /**
     * @param $aArrToMerge
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getModuleSettingsStrs($aArrToMerge) {
        return self::getAllModuleSettings(['str'], $aArrToMerge);
    }


    /**
     * @param $aSettingTypes
     * @param $aArrToMerge
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private static function getAllModuleSettings($aSettingTypes, $aArrToMerge) {
        /** @var ModuleConfiguration $oModuleConfiguration */
        $oModuleConfiguration = self::getModuleConfigs('fckustom');
        $aModuleSettings = $oModuleConfiguration->getModuleSettings();

        foreach ($aModuleSettings as $oModuleSetting) {
            if(in_array($oModuleSetting->getType(), $aSettingTypes)) {
                $aArrToMerge[$oModuleSetting->getName()] = $oModuleSetting->getValue();
            }
        }

        return $aArrToMerge;
    }

    /**
     * @param string $moduleId
     * @return mixed
     */
    protected static function getModuleConfigs(string $moduleId)
    {
        $oContainer = ContainerFactory::getInstance()->getContainer();
        return $oContainer->get(ModuleConfigurationDaoBridgeInterface::class)->get($moduleId);
    }

    /**
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected static function getModuleSettings() {
        return ContainerFactory::getInstance()
            ->getContainer()
            ->get(ModuleSettingServiceInterface::class);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param string $sDataType
     */
    public static function saveShopConfVar($name, $value, $sDataType = ''): void
    {
        $oModuleSettingService = self::getModuleSettings();

        if(strlen($sDataType) == 0) {
            switch ($name[0]) {
                case 'b':
                    $oModuleSettingService->saveBoolean($name, $value, 'fckustom');
                    break;
                case 'i':
                    $oModuleSettingService->saveInteger($name, $value, 'fckustom');
                    break;
                case 's':
                    $oModuleSettingService->saveString($name, $value, 'fckustom');
                    break;
                case 'a':
                    $oModuleSettingService->saveCollection($name, $value, 'fckustom');
                    break;
            }
        } else {
            switch ($sDataType) {
                case self::DATA_TYPE_BOOLEAN:
                    $oModuleSettingService->saveBoolean($name, $value, 'fckustom');
                    break;
                case self::DATA_TYPE_INTEGER:
                    $oModuleSettingService->saveInteger($name, $value, 'fckustom');
                    break;
                case self::DATA_TYPE_STRING:
                    $oModuleSettingService->saveString($name, $value, 'fckustom');
                    break;
                case self::DATA_TYPE_COLLECTION:
                    $oModuleSettingService->saveCollection($name, $value, 'fckustom');
                    break;
            }
        }
    }
}

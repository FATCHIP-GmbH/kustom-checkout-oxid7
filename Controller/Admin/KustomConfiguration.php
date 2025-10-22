<?php

namespace Fatchip\FcKustom\Controller\Admin;

use Fatchip\FcKustom\Model\KustomPaymentHelper;
use OxidEsales\Eshop\Core\Exception\SystemComponentException;
use Fatchip\FcKustom\Core\KustomConsts;
use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class Kustom_Config for module configuration in OXID backend
 */
class KustomConfiguration extends KustomBaseConfig
{

    protected $_sThisTemplate = '@fckustom/admin/fckustom_kco_config';

    /** @inheritdoc */
    protected $MLVars = ['sKustomTermsConditionsURI_', 'sKustomCancellationRightsURI_', 'sKustomShippingDetails_'];

    /**
     * Render logic
     *
     * @see admin/oxAdminDetails::render()
     * @return string
     */
    public function render()
    {
        parent::render();
        // force shopid as parameter
        // Pass shop OXID so that shop object could be loaded
        $sShopOXID = Registry::getConfig()->getShopId();
        $this->setEditObjectId($sShopOXID);

        if (KustomUtils::is_ajax()) {
            $output = $this->getMultiLangData();

            return Registry::getUtils()->showMessageAndExit(json_encode($output));
        }

        $oPayment = oxNew(Payment::class);
        $this->addTplParam('sLocale', oxNew(KustomConsts::class)->getLocale(true));

        if (Registry::getConfig()->getConfigParam('sSSLShopURL') == null) {
            $this->addTplParam('sslNotSet', true);
        }
        $oPayment->load(KustomPaymentHelper::getKustomPaymentsId());
        $kustomActiveInOxid = $oPayment->oxpayments__oxactive->value == 1;
        if (!$kustomActiveInOxid) {
            $this->addTplParam('KCOinactive', true);
        }

        $this->addTplParam('blGermanyActive', $this->isGermanyActiveShopCountry());
        $this->addTplParam('blAustriaActive', $this->isAustriaActiveShopCountry());
        $this->addTplParam('activeCountries', KustomUtils::getAllActiveKCOGlobalCountryList($this->getViewDataElement('adminlang')));
        $this->addTplParam('fckustom_countryList', json_encode(KustomUtils::getKustomGlobalActiveShopCountries($this->getViewDataElement('adminlang'))));


        $this->_sThisTemplate = '@fckustom/admin/fckustom_kco_config';

        return $this->_sThisTemplate;
    }

    public function getErrorMessages()
    {
        return htmlentities(json_encode(array(
            'valueMissing'    => Registry::getLang()->translateString('FCKUSTOM_EXTERNAL_IMAGE_URL_EMPTY'),
            'patternMismatch' => Registry::getLang()->translateString('FCKUSTOM_EXTERNAL_IMAGE_URL_INVALID'),
        )));
    }

    /**
     * @return array
     */
    public function getKustomCheckboxOptions()
    {
        $selectValues = array(
            KustomConsts::EXTRA_CHECKBOX_NONE                =>
                Registry::getLang()->translateString('FCKUSTOM_NO_CHECKBOX'),
            KustomConsts::EXTRA_CHECKBOX_CREATE_USER         =>
                Registry::getLang()->translateString('FCKUSTOM_CREATE_USER_ACCOUNT'),
            KustomConsts::EXTRA_CHECKBOX_SIGN_UP             =>
                Registry::getLang()->translateString('FCKUSTOM_SUBSCRIBE_TO_NEWSLETTER'),
            KustomConsts::EXTRA_CHECKBOX_CREATE_USER_SIGN_UP =>
                Registry::getLang()->translateString('FCKUSTOM_CREATE_USER_ACCOUNT_AND_SUBSCRIBE'),
        );

        return $selectValues;
    }

    /**
     * @return array
     */
    public function getKustomValidationOptions()
    {
        $selectValues = array(
            KustomConsts::NO_VALIDATION            =>
                Registry::getLang()->translateString('FCKUSTOM_NO_VALIDATION_NEEDED'),
            KustomConsts::VALIDATION_WITH_SUCCESS  =>
                Registry::getLang()->translateString('FCKUSTOM_VALIDATION_IGNORE_TIMEOUTS_NEEDED'),
            KustomConsts::VALIDATION_WITH_NO_ERROR =>
                Registry::getLang()->translateString('FCKUSTOM_SUCCESSFUL_VALIDATION_NEEDED'),
        );

        return $selectValues;
    }

    /**
     * @return int
     */
    public function getChosenValidation()
    {
        return (int)KustomUtils::getShopConfVar('iKustomValidation');
    }

    /**
     * @return bool
     */
    public function isGermanyActiveShopCountry()
    {
        /** @var \OxidEsales\Eshop\Application\Model\CountryList $activeCountries */
        $activeCountries = KustomUtils::getActiveShopCountries();
        foreach ($activeCountries as $oCountry) {
            if ($oCountry->oxcountry__oxisoalpha2->value == 'DE')
                return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isAustriaActiveShopCountry()
    {
        /** @var \OxidEsales\Eshop\Application\Model\CountryList $activeCountries */
        $activeCountries = KustomUtils::getActiveShopCountries();
        foreach ($activeCountries as $oCountry) {
            if ($oCountry->oxcountry__oxisoalpha2->value == 'AT')
                return true;
        }

        return false;
    }

    /**
     * @throws SystemComponentException
     */
    public function checkEuropeanCountries()
    {
        $separateShippingEnabled = Registry::getRequest()->getRequestEscapedParameter("separate_shipping_enabled");

        $message = null;
        if ($separateShippingEnabled) {

            $result = self::getEuropeanCountries();
            foreach ($result as $alpha2 => $title) {
                $check = KustomUtils::isCountryActiveInKustomCheckout($alpha2);
                if ($check == false) {
                    $missingCountries[] = $title;
                }
            }

            if (!empty($missingCountries)) {
                $message = sprintf(
                    Registry::getLang()->translateString('FCKUSTOM_EU_WARNING'),
                    implode(", ", $missingCountries)
                );
            }
        }
        Registry::getUtils()->showMessageAndExit(json_encode(array('warningMessage' => $message)));
    }

    public static function getEuropeanCountries()
    {
        return [
            'AT' => "Österreich",
            'BE' => "Belgien",
            'BG' => "Bulgarien",
            'CY' => "Zypern",
            'CZ' => "Tschechische Republik",
            'DE' => "Deutschland",
            'DK' => "Dänemark",
            'EE' => "Estland",
            'ES' => "Spanien",
            'FI' => "Finnland",
            'FR' => "Frankreich",
            'GR' => "Griechenland",
            'HR' => "Kroatien",
            'HU' => "Ungarn",
            'IE' => "Irland",
            'IT' => "Italien",
            'LT' => "Litauen",
            'LU' => "Luxemburg",
            'LV' => "Lettland",
            'MT' => "Malta",
            'NL' => "Niederlande",
            'PL' => "Polen",
            'PT' => "Portugal",
            'RO' => "Rumänien",
            'SE' => "Schweden",
            'SI' => "Slowenien",
            'SK' => "Slowakei",
            'UK' => "Großbritannien",
        ];
    }
}
<?php


namespace Fatchip\FcKustom\Core;


use OxidEsales\Eshop\Core\Registry;

/**
 * @codeCoverageIgnore
 * Class KustomConsts
 * @package Fatchip\FcKustom\Core
 */
class KustomConsts
{

    const MODULE_MODE_KCO = 'KCO';

    const EXTRA_CHECKBOX_NONE = 0;

    const EXTRA_CHECKBOX_CREATE_USER = 1;

    const EXTRA_CHECKBOX_SIGN_UP = 2;

    const EXTRA_CHECKBOX_CREATE_USER_SIGN_UP = 3;

    const NO_VALIDATION = 0;

    const VALIDATION_WITH_SUCCESS = 1;

    const VALIDATION_WITH_NO_ERROR = 2;

    const EMD_ORDER_HISTORY_ALL = 0;

    const EMD_ORDER_HISTORY_PAID = 1;

    const EMD_ORDER_HISTORY_NONE = 2;

    const KUSTOM_PREFILL_NOTIF_URL = 'https://cdn.klarna.com/1.0/shared/content/legal/terms/%s/%s/checkout';

    const KUSTOM_MANUAL_DOWNLOAD_LINK = 'https://www.cgrd.de/customer/klarna/docs/klarna-module-for-oxid-%s-%s.pdf';

    /**
     * @codeCoverageIgnore
     * @return array
     */
    public function getKustomGlobalCountries()
    {
        return array('AX', 'AL', 'DZ', 'AS', 'AD', 'AI', 'AQ', 'AG', 'AR', 'AM', 'AW', 'AU', 'AT', 'AZ', 'BS', 'BH',
                     'BD', 'BB', 'BE', 'BZ', 'BJ', 'BM', 'BT', 'BO', 'BQ', 'BW', 'BV', 'BR', 'IO', 'BN', 'BG', 'BF',
                     'KH', 'CM', 'CA', 'CV', 'KY', 'TD', 'CL', 'CN', 'CX', 'CC', 'CO', 'KM', 'CG', 'CK', 'CR', 'CI',
                     'HR', 'CU', 'CW', 'CY', 'CZ', 'DK', 'DJ', 'DM', 'DO', 'EC', 'EG', 'SV', 'GQ', 'EE', 'ET', 'FK',
                     'FO', 'FJ', 'FI', 'FR', 'GF', 'PF', 'TF', 'GA', 'GM', 'GE', 'DE', 'GH', 'GI', 'GR', 'GL', 'GD',
                     'GP', 'GU', 'GT', 'GG', 'HM', 'VA', 'HN', 'HK', 'HU', 'IS', 'IN', 'ID', 'IE', 'IM', 'IL', 'IT',
                     'JM', 'JP', 'JE', 'JO', 'KZ', 'KE', 'KI', 'KR', 'KW', 'KG', 'LV', 'LB', 'LS', 'LR', 'LI', 'LT',
                     'LU', 'MO', 'MK', 'MG', 'MW', 'MY', 'MV', 'ML', 'MT', 'MH', 'MQ', 'MR', 'MU', 'YT', 'MX', 'FM',
                     'MD', 'MC', 'MN', 'ME', 'MS', 'MA', 'MZ', 'NA', 'NR', 'NP', 'NL', 'NC', 'NZ', 'NI', 'NE', 'NG',
                     'NU', 'NF', 'MP', 'NO', 'OM', 'PK', 'PW', 'PS', 'PA', 'PY', 'PE', 'PH', 'PN', 'PL', 'PT', 'PR',
                     'QA', 'RE', 'RO', 'RU', 'RW', 'BL', 'SH', 'KN', 'LC', 'MF', 'PM', 'VC', 'WS', 'SM', 'ST', 'SA',
                     'SN', 'RS', 'SC', 'SL', 'SG', 'SX', 'SK', 'SI', 'SB', 'ZA', 'GS', 'ES', 'LK', 'SR', 'SJ', 'SZ',
                     'SE', 'CH', 'TW', 'TJ', 'TZ', 'TH', 'TL', 'TG', 'TK', 'TO', 'TT', 'TN', 'TR', 'TM', 'TC', 'TV',
                     'AE', 'GB', 'US', 'UM', 'UY', 'UZ', 'VE', 'VN', 'VG', 'VI', 'WF', 'EH', 'ZM');
    }

    /**
     * @codeCoverageIgnore
     * @return array
     */
    public function getKustomCoreCountries()
    {
        return array('SE', 'NO', 'FI', 'DE', 'AT', 'NL', 'GB', 'DK', 'CH', 'ES', 'FR', 'BE', 'IT', 'IE');
    }

    /**
     * @codeCoverageIgnore
     * @return array
     */
    public function getKustomKCOB2BCountries()
    {
        return array('SE', 'NO', 'FI');
    }

    /**
     * @codeCoverageIgnore
     * @return array
     */
    public function getCountry2CurrencyArray()
    {
        return array(
            'SE' => 'SEK',
            'NO' => 'NOK',
            'DK' => 'DKK',
            'DE' => 'EUR',
            'FI' => 'EUR',
            'NL' => 'EUR',
            'AT' => 'EUR',
            'GB' => 'GBP',
            'CH' => 'CHF',
            'BE' => 'EUR',
            'FR' => 'EUR',
            'ES' => 'EUR',
            'IT' => 'EUR',
            'IE' => 'EUR',
        );
    }

    /**
     * Override to add other possible payment methods
     * @codeCoverageIgnore
     * @return array
     */
    public function getKustomExternalPaymentNames()
    {
        return array(
            'Nachnahme', 'Vorkasse', 'Amazon Payments', 'PayPal', 'Google Pay', 'Apple Pay'
        );
    }

    /**
     * @codeCoverageIgnore
     * @return array
     */
    public function getEmdPaymentTypeOptions()
    {
        return array(
            'other'          => Registry::getLang()->translateString('FCKUSTOM_OTHER_PAYMENT'),
            'direct banking' => Registry::getLang()->translateString('FCKUSTOM_DIRECT_BANKING'),
            'card'           => Registry::getLang()->translateString('FCKUSTOM_CARD'),
        );
    }

    /**
     * @codeCoverageIgnore
     * @return array
     */
    public function getFullHistoryOrdersOptions()
    {
        return array(
            self::EMD_ORDER_HISTORY_ALL  => Registry::getLang()->translateString('FCKUSTOM_EMD_ORDER_HISTORY_ALL'),
            self::EMD_ORDER_HISTORY_PAID => Registry::getLang()->translateString('FCKUSTOM_EMD_ORDER_HISTORY_PAID'),
            self::EMD_ORDER_HISTORY_NONE => Registry::getLang()->translateString('FCKUSTOM_EMD_ORDER_HISTORY_NONE'),
        );
    }


    /**
     * @param null $key
     * @return array|mixed
     */
    public static function getFooterImgUrls($key = null)
    {
        $aFooterImgUrls = array(
            'logoBlack'  => '/out/modules/fckustom/out/src/img/kustom_logo.png',
        );

        if ($key)
            return $aFooterImgUrls[$key];
        else
            return $aFooterImgUrls;
    }

    /**
     * @param bool $default
     * @return mixed
     */
    public function getLocale($default = false)
    {
        $oLang = Registry::getLang();

        $lang = $oLang->getLanguageAbbr();
        if ($default) {
            $langArray = $oLang->getLanguageArray();
            $lang      = $langArray[$oLang->getTplLanguage()]->abbr;
        }

        $defaultLocales = [
            'en' => 'en-GB',
            'nb' => 'nb-NO',
            'da' => 'da-DK',
            'de' => 'de-DE',
            'nl' => 'nl-NL',
            'fi' => 'fi-FI',
            'sv' => 'sv-SE',
            'at' => 'de-AT',
            'us' => 'en-US',
            'be' => 'fr-BE',
            'fr' => 'fr-fr',
            'es' => 'es-ES',
            'it' => 'it-IT',
            'ie' => 'en-IE',
        ];

        if(isAdmin() && $default === true) {
            return $locale = isset($defaultLocales[$lang]) ? $defaultLocales[$lang] : 'en-GB';
        }

        $sCountryISO = Registry::getSession()->getVariable('sCountryISO');

        $locale = $lang.'-'.$sCountryISO;
        if($default || !$lang || !$sCountryISO)
        {
            $locale = isset($defaultLocales[$lang]) ? $defaultLocales[$lang] : 'en-GB';

            if($sCountryISO){
                $locale = isset($defaultLocales[strtolower($sCountryISO)]) ? $defaultLocales[strtolower($sCountryISO)] : 'en-GB';
            }

        }

        return $locale;
    }

    /**
     * @codeCoverageIgnore
     * Override to change which countries are shown separately with a flag in the Kustom Checkout country popup.
     * Need to
     *
     * @return array
     */
    public function getKustomPopUpFlagCountries()
    {
        return array('DE', 'AT', 'CH');
    }
    
    public function getCustomerTypes() {
        return array(
            'B2C'     => array('person'),
            'B2B'     => array('organization'),
            'B2C_B2B' => array('person', 'organization'),
            'B2B_B2C' => array('organization', 'person'),
            'B2BOTH'  => array('organization', 'person') // old config value
        );
    }
}

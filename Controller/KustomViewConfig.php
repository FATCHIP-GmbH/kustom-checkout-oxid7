<?php


namespace Fatchip\FcKustom\Controller;


use Fatchip\FcKustom\Core\KustomConsts;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Model\KustomUser;
use OxidEsales\Eshop\Application\Model\CountryList;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Registry;

class KustomViewConfig extends KustomViewConfig_parent
{

    /**
     * Kustom Express controller class name
     *
     * @const string
     */
    const CONTROLLER_CLASSNAME_KUSTOM_EXPRESS = 'KustomExpress';
    const FCKUSTOM_FOOTER_DISPLAY_LOGO            = 2;

    protected $fckustomButton;

    public function isActiveControllerKustomExpress()
    {
        return strcasecmp($this->getActiveClassName(), self::CONTROLLER_CLASSNAME_KUSTOM_EXPRESS) === 0;
    }

    /**
     *
     */
    public function getKustomFooterContent()
    {
        $sCountryISO = KustomUtils::getShopConfVar('sKustomDefaultCountry');
        if (!in_array($sCountryISO, oxNew(KustomConsts::class)->getKustomCoreCountries())) {
            return false;
        }

        $response = false;

        $klFooter = intval(KustomUtils::getShopConfVar('sKustomFooterDisplay'));
        if ($klFooter) {

            if ($klFooter === self::FCKUSTOM_FOOTER_DISPLAY_LOGO)
                $sLocale = '';
            else
                return false;

            $url  = sprintf(oxNew(KustomConsts::class)->getFooterImgUrls(KustomUtils::getShopConfVar('sKustomFooterValue')), $sLocale);
            $from = '/' . preg_quote('-', '/') . '/';
            if(KustomUtils::getShopConfVar('sKustomFooterValue') != 'logoFooter') {
                $url  = preg_replace($from, '_', $url, 1);
            }

            $response = array(
                'url'   => $url,
                'class' => KustomUtils::getShopConfVar('sKustomFooterValue')
            );
        }

        if(KustomUtils::getShopConfVar('sKustomMessagingScript')) {
            $response['script'] = KustomUtils::getShopConfVar('sKustomMessagingScript');
        }

        return $response;
    }

    public function getOnSitePromotionInfo($key, $detailProduct = null)
    {
        if($this->getActiveClassName() != 'basket' && $key == "sKustomCreditPromotionBasket") {
            return '';
        }

        if($key == "sKustomCreditPromotionBasket" || $key == "sKustomCreditPromotionProduct") {

            $promotion = KustomUtils::getShopConfVar($key);
            $promotion = preg_replace('/data-purchase-amount=\"(\d*)\"/', 'data-purchase-amount="%s"', $promotion);
            $price = 0;
            $productHasPrice = Registry::getConfig()->getConfigParam('bl_perfLoadPrice');
            if($key == "sKustomCreditPromotionProduct" && $detailProduct != null && $productHasPrice) {
                $price = $detailProduct->getPrice()->getBruttoPrice();
                $price = number_format((float)$price*100., 0, '.', '');
            }

            if($key == "sKustomCreditPromotionBasket" ) {
                $price = Registry::getSession()->getBasket()->getPrice()->getNettoPrice();
                $price = number_format((float)$price*100., 0, '.', '');
            }

            return sprintf($promotion, $price);

        }

        return KustomUtils::getShopConfVar($key);
    }

    public function getLocale()
    {
        return oxNew(KustomConsts::class)->getLocale();
    }

    /**
     * @param bool $blShipping
     * @return mixed
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function getCountryList($blShipping = false)
    {
        if ($this->isCheckoutNonKustomCountry() && $this->getActiveClassName() !== 'account_user' && !$blShipping) {
            $this->_oCountryList = oxNew(CountryList::class);
            $this->_oCountryList->loadActiveNonKustomCheckoutCountries();

            return $this->_oCountryList;
        } else {
            unset($this->_oCountryList);

            return parent::getCountryList();
        }
    }

    /**
     *
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     * @return bool
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function isCheckoutNonKustomCountry()
    {
        $sCountryIso = Registry::getSession()->getVariable('sCountryISO');

        return !KustomUtils::isCountryActiveInKustomCheckout($sCountryIso);
    }

    /**
     * @return bool
     */
    public function isUserLoggedIn()
    {
        if ($user = $this->getUser()) {
            return $user->oxuser__oxid->value == Registry::getSession()->getVariable('usr');
        }

        return false;
    }

    /**
     * Confirm present country is Germany
     *
     * @return bool
     */
    public function getIsGermany()
    {
        if ($user = $this->getUser()) {
            $sCountryISO2 = $user->resolveCountry();
        } else {
            $sCountryISO2 = KustomUtils::getShopConfVar('sKustomDefaultCountry');
        }

        return $sCountryISO2 == 'DE';
    }

    /**
     * Show Checkout terms
     *
     * @return bool true if current country is Austria
     */
    public function getIsAustria()
    {
        /** @var User|KustomUser $user */
        if ($user = $this->getUser()) {
            $sCountryISO2 = $user->resolveCountry();
        } else {
            $sCountryISO2 = KustomUtils::getShopConfVar('sKustomDefaultCountry');
        }

        return $sCountryISO2 == 'AT';
    }

    /**
     * @return bool
     */
    public function showCheckoutTerms()
    {
        if ($this->isShowPrefillNotif()) {
            if ($this->getIsAustria() || $this->getIsGermany())

                return true;
        }

        return false;
    }

    /**
     * Get DE notification link for KCO
     *
     * @return string
     */
    public function getLawNotificationsLinkKco()
    {
        $sCountryISO = Registry::getSession()->getVariable('sCountryISO');

        if(!$sCountryISO)
        {
            $sCountryISO = KustomUtils::getShopConfVar('sKustomDefaultCountry');
        }

        $mid         = KustomUtils::getAPICredentials($sCountryISO);
        preg_match('/^(?P<mid>(.)+)(\_)/', $mid['mid'], $matches);

        return sprintf(KustomConsts::KUSTOM_PREFILL_NOTIF_URL,
            $matches['mid'], $this->getActLanguageAbbr()
        );
    }

    /**
     *
     */
    public function isShowPrefillNotif()
    {
        return (bool)KustomUtils::getShopConfVar('blKustomPreFillNotification');
    }

    public function getKcoApplePayDeviceEligibility()
    {
        $oSession = Registry::getSession();

        return $oSession->getVariable("kcoApplePayDeviceEligible");
    }
}
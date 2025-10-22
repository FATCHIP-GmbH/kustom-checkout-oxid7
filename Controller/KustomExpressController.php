<?php


namespace Fatchip\FcKustom\Controller;


use Fatchip\FcKustom\Core\KustomCheckoutClient;
use Fatchip\FcKustom\Core\KustomConsts;
use Fatchip\FcKustom\Core\KustomFormatter;
use Fatchip\FcKustom\Core\KustomOrder;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Core\Exception\KustomBasketTooLargeException;
use Fatchip\FcKustom\Core\Exception\KustomConfigException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use Fatchip\FcKustom\Model\KustomPaymentHelper;
use Fatchip\FcKustom\Model\KustomUser;

use OxidEsales\Eshop\Application\Model\CountryList;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\UtilsUrl;
use OxidEsales\Eshop\Core\UtilsView;

class KustomExpressController extends FrontendController
{
    /**
     * @var string
     */
    protected $_sThisTemplate = '@fckustom/checkout/fckustom_checkout';

    /**
     * @var \Fatchip\FcKustom\Core\KustomOrder
     */
    protected $_oKustomOrder;

    /**
     * @var User|KustomUser
     */
    protected $_oUser;

    /**
     * @var bool
     */
    protected $blockIframeRender;

    /**
     * @var array
     */
    protected $_aOrderData;

    /** @var string country selected by the user in the popup */
    protected $selectedCountryISO;

    /** @var bool show select country popup to the user */
    protected $blShowPopup;

    /** @var Request */
    protected $_oRequest;

    /**
     *
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function init()
    {
        $oSession        = Registry::getSession();
        $oBasket         = $oSession->getBasket();
        $this->_oRequest = Registry::get(Request::class);

        /**
         * Reset Kustom session if flag set by changing user address data in the User Controller earlier.
         */
        $this->checkForSessionResetFlag();

        $this->determineUserControllerAccess($this->_oRequest);

        /**
         * Returning from legacy checkout for guest user.
         * Request parameter reset_kustom_country is checked and $this->blockIframeRender is set.
         */
        if ($this->_oRequest->getRequestEscapedParameter('reset_kustom_country') == 1) {
            $this->blockIframeRender = true;
        }

        $oBasket->setPayment(KustomPaymentHelper::getKustomPaymentsId());
        $oSession->setVariable('paymentid', KustomPaymentHelper::getKustomPaymentsId());

        parent::init();
    }

    /**
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     * @throws \oxSystemComponentException
     * @return string
     */
    public function render()
    {
        $oSession = Registry::getSession();
        $oBasket = $oSession->getBasket();
        $this->rebuildFakeUser($oBasket);

        $result = parent::render();

        /**
         * Reload page with ssl if not secure already.
         */
        $this->checkSsl($this->_oRequest);

        /**
         * Check if we have a logged in user.
         * If not create a fake one.
         */
        if(!$this->_oUser){
            $this->_oUser = $this->resolveUser();
        }

        $oBasket->setBasketUser($this->_oUser);

        $this->blShowPopup = $this->showCountryPopup();


        if ($this->blockIframeRender) {
            return $this->_sThisTemplate;
        }

        $this->addTplParam('blShowCountryReset', KustomUtils::isNonKustomCountryActive());

        try {
            $oKustomOrder = $this->getKustomOrder($oBasket);
        } catch (KustomConfigException $e) {

            Registry::get(UtilsView::class)->addErrorToDisplay($e);
            KustomUtils::fullyResetKustomSession();

            return $this->_sThisTemplate;

        } catch (KustomBasketTooLargeException $e) {
            Registry::get(UtilsView::class)->addErrorToDisplay($e);

            $this->redirectForNonKustomCountry(Registry::getSession()->getVariable('sCountryISO'), false);

            return $this->_sThisTemplate;
        }

        if ($oSession->getVariable('wrong_merchant_urls')) {

            $oSession->deleteVariable('wrong_merchant_urls');

            Registry::get(UtilsView::class)->addErrorToDisplay('KUSTOM_WRONG_URLS_CONFIG', false, true);

            $this->addTplParam('confError', true);

            return $this->_sThisTemplate;
        }
        $orderData = $oKustomOrder->getOrderData();

        if (!KustomUtils::isCountryActiveInKustomCheckout(strtoupper($orderData['purchase_country']))) {

            $sUrl = Registry::getConfig()->getShopHomeURL() . 'cl=user';
            Registry::getUtils()->redirect($sUrl, false, 302);

            return;
        }

        try {
            $this->getKustomClient(Registry::getSession()->getVariable('sCountryISO'))
                ->initOrder($oKustomOrder)
                ->createOrUpdateOrder();

        } catch (KustomWrongCredentialsException $oEx) {
            KustomUtils::fullyResetKustomSession();
            Registry::get(UtilsView::class)->addErrorToDisplay(
                Registry::getLang()->translateString('KUSTOM_UNAUTHORIZED_REQUEST', null, true));
            Registry::getUtils()->redirect(Registry::getConfig()->getShopSecureHomeURL() . 'cl=start', true, 301);

            return $this->_sThisTemplate;
        } catch (KustomClientException $oEx) {
            KustomUtils::fullyResetKustomSession();
            Registry::get(UtilsView::class)->addErrorToDisplay(
                Registry::getLang()->translateString('KUSTOM_GENERAL_ERROR', null, true));
            Registry::getUtils()->redirect(Registry::getConfig()->getShopSecureHomeURL() . 'cl=start', true, 301);

        } catch (StandardException $oEx) {
            KustomUtils::logException($oEx);
            KustomUtils::fullyResetKustomSession();
            Registry::getUtils()->redirect(Registry::getConfig()->getShopSecureHomeURL() . 'cl=KustomExpress', false, 302);

            return $this->_sThisTemplate;
        }

        $this->addTemplateParameters();

        return $result;
    }

    /**
     * @return bool
     */
    protected function showCountryPopup()
    {
        $sCountryISO        = Registry::getSession()->getVariable('sCountryISO');
        $resetKustomCountry = $this->_oRequest->getRequestEscapedParameter('reset_kustom_country');

        if ($resetKustomCountry) {
            return true;
        }

        if ($this->isKLUserLoggedIn()) {
            return false;
        }

        if ($sCountryISO) {
            return false;
        }

        return true;
    }

    /**
     *
     * @return bool
     * @throws \oxSystemComponentException
     */
    protected function isKLUserLoggedIn()
    {
        $oUser = $this->getUser();

        if ($oUser && $oUser->getType() === KustomUser::LOGGED_IN) {
            return true;
        }

        return false;
    }

    /**
     * @return KustomCheckoutClient | \Fatchip\FcKustom\Core\KustomClientBase
     */
    public function getKustomClient($sCountryISO)
    {
        return KustomCheckoutClient::getInstance($sCountryISO);
    }

    /**
     * Get addresses saved by the user if any exist.
     * @return array|bool
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     * @throws \oxSystemComponentException
     */
    public function getFormattedUserAddresses()
    {
        if ($this->_oUser->isFake()) {
            return false;
        }

        return KustomFormatter::getFormattedUserAddresses($this->_oUser);
    }

    /**
     *
     */
    public function getKustomModalFlagCountries()
    {
        $flagCountries = oxNew(KustomConsts::class)->getKustomPopUpFlagCountries();

        $result = array();
        foreach ($flagCountries as $isoCode) {
            $country = oxNew(Country::class);
            $id      = $country->getIdByCode($isoCode);
            $country->load($id);
            if ($country->oxcountry__oxactive->value == 1) {
                $result[] = $country;
            }
        }

        return $result;
    }

    /**
     *
     */
    public function getKustomModalOtherCountries()
    {
        $flagCountries               = oxNew(KustomConsts::class)->getKustomPopUpFlagCountries();
        $activeKustomGlobalCountries = KustomUtils::getKustomGlobalActiveShopCountries();

        $result = array();
        foreach ($activeKustomGlobalCountries as $country) {
            if (in_array($country->oxcountry__oxisoalpha2->value, $flagCountries)) {
                continue;
            }
            $result[] = $country;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getBreadCrumb()
    {
        $aPaths        = array();
        $aPath         = array();
        $iBaseLanguage = Registry::getLang()->getBaseLanguage();

        $aPath['title'] = Registry::getLang()->translateString('FCKUSTOM_CHECKOUT', $iBaseLanguage, false);
        $aPath['link']  = $this->getLink();
        $aPaths[]       = $aPath;

        return $aPaths;
    }

    /**
     *
     */
    public function getActiveShopCountries()
    {
        $list = oxNew(CountryList::class);
        $list->loadActiveCountries();

        return $list;
    }

    /**
     *
     */
    public function getNonKustomCountries()
    {
        $list = oxNew(CountryList::class);
        $list->loadActiveCountries();

        foreach ($list->getArray() as $id => $country)
        {
            if(KustomUtils::isCountryActiveInKustomCheckout($country->oxcountry__oxisoalpha2->value, false)) {
                unset($list[$id]);
            }
        }

        return $list;
    }


    /**
     * @param $sCountryISO
     */
    protected function redirectForNonKustomCountry($sCountryISO, $blShippingOptionsSet = true)
    {
        if ($blShippingOptionsSet === false) {
            $sUrl = Registry::getConfig()->getShopSecureHomeUrl() . 'cl=basket';
        } else {
            $sUrl = Registry::getConfig()->getShopSecureHomeUrl() . 'cl=user&non_kco_global_country=' . $sCountryISO;
        }
        Registry::getUtils()->redirect($sUrl, false, 302);
    }

    /**
     *
     */
    public function setKustomDeliveryAddress()
    {
        $oxidAddress = $this->_oRequest->getRequestEscapedParameter('kustom_address_id');
        Registry::getSession()->setVariable('deladrid', $oxidAddress);
        Registry::getSession()->setVariable('blshowshipaddress', 1);
        Registry::getSession()->deleteVariable('kustom_checkout_order_id');
    }



    /**
     *
     * @param $oBasket
     * @return KustomOrder
     */
    protected function getKustomOrder($oBasket)
    {
        return oxNew(KustomOrder::class,$oBasket, $this->_oUser);
    }

    /**
     *
     */
    protected function checkForSessionResetFlag()
    {
        if (Registry::getSession()->getVariable('resetKustomSession') == 1) {
            KustomUtils::fullyResetKustomSession();
        }
    }

    /**
     *
     */
    protected function changeUserCountry()
    {
        if ($this->getUser()) {
            $oCountry   = oxNew(Country::class);
            $sCountryId = $oCountry->getIdByCode($this->selectedCountryISO);
            $oCountry->load($sCountryId);
            $this->getUser()->oxuser__oxcountryid = new Field($sCountryId);
            $this->getUser()->oxuser__oxcountry   = new Field($oCountry->oxcountry__oxtitle->value);
            $this->getUser()->save();
        }
    }

    /**
     * @param $oSession
     * @param $oUtils
     */
    protected function handleCountryChangeFromPopup()
    {
        $oUtils   = Registry::getUtils();
        if (KustomUtils::isCountryActiveInKustomCheckout($this->selectedCountryISO)) {
            $sUrl = Registry::getConfig()->getShopSecureHomeUrl() . 'cl=KustomExpress';
            $oUtils->redirect($sUrl, false, 302);
            /**
             * Redirect to legacy oxid checkout if selected country is not a KCO country.
             */
        } else {
            $this->redirectForNonKustomCountry($this->selectedCountryISO);
        }
    }

    /**
     * @param $oSession
     * @param Request $oRequest
     * @throws \oxSystemComponentException
     */
    protected function handleLoggedInUserWithNonKustomCountry($oSession, $oRequest)
    {
        /**
         * User is coming back from legacy oxid checkout wanting to change the country to one of KCO ones
         */
        if ($oRequest->getRequestEscapedParameter('reset_kustom_country') == 1) {
            $oSession->setVariable('sCountryISO', KustomUtils::getShopConfVar('sKustomDefaultCountry'));
            /**
             * User is trying to access the kustom checkout for the first time and has to be redirected to legacy oxid checkout
             */
        } else {
            $oSession->setVariable('sCountryISO', $this->getUser()->getUserCountryISO2());
            $this->redirectForNonKustomCountry($this->getUser()->getUserCountryISO2());
        }
    }

    /**
     * Handle country changes from within or outside the iframe.
     * Redirect to legacy oxid checkout if country not valid for Kustom Checkout.
     * Receive redirects from legacy oxid checkout when changing back to a country handled by KCO
     *
     * @param Request $oRequest
     * @throws \oxSystemComponentException
     */
    protected function determineUserControllerAccess($oRequest)
    {
        $oSession = Registry::getSession();
        /**
         * A country has been selected from the country popup.
         */
        $this->selectedCountryISO = $oRequest->getRequestEscapedParameter('selected-country');
        if ($this->selectedCountryISO) {
            $oSession->setVariable('sCountryISO', $this->selectedCountryISO);

            /**
             * Remove delivery address on country change
             */
            Registry::getSession()->setVariable('blshowshipaddress', 0);
            /**
             * If user logged in - save the new country choice.
             */
            $this->changeUserCountry();
            /**
             * Restart kustom session on country change and reload the page
             * or redirect to legacy oxid checkout if selected country is not a KCO country.
             */
            $this->handleCountryChangeFromPopup();

            return;
        }
        /**
         * Logged in user with a non KCO country attempting to render the kustom checkout.
         */
        if ($this->getUser() && !KustomUtils::isCountryActiveInKustomCheckout($this->getUser()->getUserCountryISO2())) {
            /**
             * User is coming back from legacy oxid checkout wanting to change the country to one of KCO ones
             * or user is trying to access the kustom checkout for the first time and has to be redirected to
             * legacy oxid checkout
             */
            $this->handleLoggedInUserWithNonKustomCountry($oSession, $oRequest);

            return;
        }

        /**
         * Default country is not KCO and we need the country popup without rendering the iframe.
         */
        if (!$oSession->getVariable('sCountryISO') &&
            !KustomUtils::isCountryActiveInKustomCheckout(KustomUtils::getShopConfVar('sKustomDefaultCountry')) &&
            $this->_oRequest->getRequestEscapedParameter('reset_kustom_country') != 1
        ) {
            $oSession->setVariable('sCountryISO', KustomUtils::getShopConfVar('sKustomDefaultCountry'));
            $this->redirectForNonKustomCountry(KustomUtils::getShopConfVar('sKustomDefaultCountry'));
        }
    }

    protected function addTemplateParameters()
    {
        $sCountryISO = Registry::getSession()->getVariable('sCountryISO');

        if (!KustomUtils::is_ajax()) {
            $oCountry = oxNew(Country::class);
            $oCountry->load($oCountry->getIdByCode($sCountryISO));
            $this->addTplParam("sCountryName", $oCountry->oxcountry__oxtitle->value);
            $this->addTplParam("blShowPopUp", $this->blShowPopup);
            $this->addTplParam("sPurchaseCountry", $sCountryISO);
            $this->addTplParam("sKustomIframe", $this->getKustomClient($sCountryISO)->getHtmlSnippet());
            $this->addTplParam("sCurrentUrl", Registry::get(UtilsUrl::class)->getCurrentUrl());
            $this->addTplParam("shippingAddressAllowed", KustomUtils::getShopConfVar('blKustomAllowSeparateDeliveryAddress'));
        }
    }

    /**
     * @throws \oxSystemComponentException
     */
    protected function resolveUser()
    {
        $oSession = Registry::getSession();
        
        /** @var KustomUser|User $oUser */
        $oUser = $this->getUser();

        if ($oUser && !empty($oUser->oxuser__oxpassword->value)) {
            $oUser->checkUserType();
        } else {
            $email = $oSession->getVariable('kustom_checkout_user_email');
            /** @var KustomUser|User $oUser */
            $oUser = KustomUtils::getFakeUser($email);
        }

        return $oUser;
    }

    /**
     * @param Request $oRequest
     * @return string
     */
    protected function checkSsl($oRequest)
    {
        $blAlreadyRedirected = $oRequest->getRequestEscapedParameter('sslredirect') == 'forced';
        $oConfig             = Registry::getConfig();
        $oUtils              = Registry::getUtils();
        if ($oConfig->getCurrentShopURL() != $oConfig->getSSLShopURL() && !$blAlreadyRedirected) {
            $sUrl = $oConfig->getShopSecureHomeUrl() . 'sslredirect=forced&cl=KustomExpress';

            $oUtils->redirect($sUrl, false, 302);
        }
    }

    /**
     * @param $oBasket
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    protected function rebuildFakeUser($oBasket)
    {
        /** @var KustomUser|User $user */
        $user = $this->getUser();

        if ($user && empty($user->oxuser__oxpassword->value)) {
            try{
                $_aOrderData = $this->getKustomCheckoutClient()->getOrder();
            } catch (KustomClientException $e){
                $user->logout();
                return;
            }


            Registry::getSession()->setBasket($oBasket);

            if ($_aOrderData && isset($_aOrderData['billing_address']['email'])) {
                $user->loadByEmail($_aOrderData['billing_address']['email']);
                $this->_oUser = $user;
                Registry::getSession()->setVariable('kustom_checkout_order_id', $_aOrderData['order_id']);
                Registry::getSession()->setVariable(
                    'kustom_checkout_user_email',
                    $_aOrderData['billing_address']['email']
                );
            }
        }
    }

    /**
     * @codeCoverageIgnore
     * @return KustomCheckoutClient|KustomClientBase
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    protected function getKustomCheckoutClient()
    {
        return KustomCheckoutClient::getInstance();
    }
}

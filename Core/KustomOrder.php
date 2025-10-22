<?php


namespace Fatchip\FcKustom\Core;

use OxidEsales\Eshop\Application\Model\DeliveryList;
use OxidEsales\Eshop\Application\Model\DeliverySetList;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\UtilsView;
use OxidEsales\Eshop\Core\Exception\SystemComponentException;
use Fatchip\FcKustom\Controller\Admin\KustomShipping;
use Fatchip\FcKustom\Core\Exception\KustomConfigException;
use Fatchip\FcKustom\Model\EmdPayload\KustomPassThrough;
use Fatchip\FcKustom\Model\KustomEMD;
use Fatchip\FcKustom\Model\KustomPayment;
use Fatchip\FcKustom\Model\KustomUser;
use OxidEsales\Eshop\Application\Controller\PaymentController;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\CountryList;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Model\BaseModel;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Application\Model\PaymentList;

class KustomOrder extends BaseModel
{
    const PACK_STATION_PREFIX = 'fckustom_pack_station_';
    /**
     * @var array data to post to Kustom
     */
    protected $_aOrderData;

    /**
     *
     * @var User|KustomUser
     */
    protected $_oUser;

    /**
     * @var PaymentController
     */
    protected $_oPayment;

    /**
     * @var string
     */
    protected $_selectedShippingSetId;

    /** @var array Order error messages to display to the user */
    protected $errors;

    /**
     * List of available shipping methods for Kustom Checkout
     *
     * @var array
     */
    protected $_kustomShippingSets;

    /** @var boolean KCO allowed for b2b clients */
    protected $b2bAllowed;

    /** @var boolean KCO allowed for b2c clients */
    protected $b2cAllowed;
    
    /** @var string */
    protected $activeB2Option;

    protected $_aUserData;
    protected $_kustomCountryList;

    /**
     * @return array
     */
    public function getOrderData()
    {
        return $this->_aOrderData;
    }

    /**
     * KustomOrder constructor.
     * @param Basket $oBasket
     * @param User $oUser
     * @throws SystemComponentException
     */
    public function __construct(Basket $oBasket, User $oUser, $ignoreMerchantUrls = false)
    {
        parent::__construct();
        $this->_oUser      = $oUser;
        $oConfig           = Registry::getConfig();
        $urlShopParam      = method_exists($oConfig, 'mustAddShopIdToRequest')
                             && $oConfig->mustAddShopIdToRequest()
                                ? '&shp=' . $oConfig->getShopId()
                                : '';
        $sSSLShopURL       = $oConfig->getSslShopUrl();
        $sCountryISO       = $this->_oUser->resolveCountry();
        $this->resolveB2Options($sCountryISO);
        $currencyName      = $oBasket->getBasketCurrency()->name;
        $sLocale           = $this->_oUser->resolveLocale($sCountryISO);
        $lang              = strtoupper(Registry::getLang()->getLanguageAbbr());
        $this->_aUserData    = $this->_oUser->getKustomData();
        $cancellationTerms = KustomUtils::getShopConfVar('aarrKustomCancellationRightsURI')['sKustomCancellationRightsURI_' . $lang];
        $terms             = KustomUtils::getShopConfVar('aarrKustomTermsConditionsURI')['sKustomTermsConditionsURI_' . $lang];

        if (empty($cancellationTerms) || empty($terms)) {
            Registry::getSession()->setVariable('wrong_merchant_urls', true);

            return false;
        }

        $sGetChallenge  = Registry::getSession()->getSessionChallengeToken();
        $sessionId      = Registry::getSession()->getId();
        $this->_aOrderData = array(
            "purchase_country"  => $sCountryISO,
            "purchase_currency" => $currencyName,
            "locale"            => $sLocale,
        );

        if (!$ignoreMerchantUrls) {
            $this->_aOrderData["merchant_urls"] = array (
                "terms"        =>
                    $terms,
                "checkout"     =>
                    $sSSLShopURL . "?cl=KustomExpress$urlShopParam",
                "confirmation" =>
                    $sSSLShopURL . "?cl=order$urlShopParam&fnc=execute&kustom_order_id={checkout.order.id}&stoken=$sGetChallenge",
                "push"         =>
                    $sSSLShopURL . "?cl=KustomAcknowledge$urlShopParam&kustom_order_id={checkout.order.id}",
            );

            if ($this->isValidationEnabled()) {
                $this->_aOrderData["merchant_urls"]["validation"] =
                    $sSSLShopURL . "?cl=KustomValidate&s=$sessionId";
            }

            if (!empty($cancellationTerms)) {
                $this->_aOrderData["merchant_urls"]["cancellation_terms"] = $cancellationTerms;
            }
        }

        $this->_aOrderData = array_merge(
            $this->_aOrderData,
            $this->_aUserData
        );

        //clean up in case of returning to the iframe with an open order
        Registry::getSession()->deleteVariable('externalCheckout');

        // merge with order_lines and totals
        $this->_aOrderData = array_merge(
            $this->_aOrderData,
            $oBasket->getKustomOrderLines()
        );

        // skip all other data if there are no items in the basket
        if (!empty($this->_aOrderData['order_lines'])) {

            $this->_aOrderData['billing_countries'] = array_values($this->getKustomCountryList());
            $allowSeperateDel = (bool)KustomUtils::getShopConfVar('blKustomAllowSeparateDeliveryAddress');
            if($allowSeperateDel === true) {
                $this->_aOrderData['shipping_countries'] = array_values($this->getShippingCountries($oBasket));
            }

            $this->_aOrderData['shipping_options'] = $this->fckustom_getAllSets($oBasket);

            $externalMethods = $this->getExternalPaymentMethods($oBasket, $this->_oUser);

            $this->_aOrderData['external_payment_methods'] = $externalMethods['payments'];
            $this->_aOrderData['external_checkouts']       = $externalMethods['checkouts'];

            $this->addOptions();

            if (!$this->isAutofocusEnabled()) {
                $this->_aOrderData['gui']['options'] = array(
                    'disable_autofocus',
                );
            }
            $this->setCustomerData();
            $this->setAttachmentsData();
            $this->setPassThroughField();
        }
    }


    /** Passes internal errors to oxid in order to display theme to the user */
    public function displayErrors()
    {
        foreach ($this->errors as $message) {
            Registry::get(UtilsView::class)->addErrorToDisplay($message);
        }
    }

    /** Adds Error message in current language
     * @param $translationKey string message key
     */
    public function addErrorMessage($translationKey)
    {
        $message        = Registry::getLang()->translateString($translationKey);
        $this->errors[$translationKey] = $message;
    }

    /**
     * @param $sCountryISO
     */
    protected function resolveB2Options($sCountryISO)
    {
        $this->b2bAllowed = false;
        $this->b2cAllowed = true;
        $this->activeB2Option = KustomUtils::getShopConfVar('sKustomB2Option');

        if (str_contains($this->activeB2Option, 'B2B')) {
            $this->b2bAllowed = in_array($sCountryISO, oxNew(KustomConsts::class)->getKustomKCOB2BCountries());
        }

        if($this->activeB2Option === 'B2B'){
            $this->b2cAllowed = false;
        }
    }

    protected function isB2B()
    {
       return $this->_aUserData['billing_address']['organization_name'] && $this->b2bAllowed;
    }
    
    protected function setCustomerData() {
        $append = array();
        $typeList = oxNew(KustomConsts::class)->getCustomerTypes();
        $type = $typeList[$this->activeB2Option];
        if ($this->b2bAllowed && empty($this->_aUserData['billing_address']['organization_name']) === false) {
            $append['customer']['type'] = 'organization';
        } else {
            $append['customer']['type'] = reset($type);
        }
        $this->_aOrderData = array_merge_recursive($this->_aOrderData, $append);
    }

    /**
     * Template variable getter. Returns all delivery sets
     *
     * @param Basket $oBasket
     * @return mixed :
     */
    public function fcKustom_getAllSets(Basket $oBasket)
    {
        if ($this->_kustomShippingSets === null) {
            $this->_kustomShippingSets = $this->getSupportedShippingMethods($oBasket);
        }

        return $this->_kustomShippingSets;
    }

    /**
     * @param $oBasket
     * @return array
     */
    protected function getShippingCountries($oBasket)
    {
        $list = $this->fcKustom_getAllSets($oBasket);
        $aCountries = $this->getKustomCountryList();
        $oDelList = Registry::get(DeliveryList::class);
        $shippingCountries = [];
        foreach ($list as $l)
        {
            $sShipSetId = $l['id'];
            foreach ($aCountries as $sCountryId => $alpha2) {
                if ($oDelList->hasDeliveries($oBasket, $this->_oUser, $sCountryId, $sShipSetId)) {
                    $shippingCountries[$alpha2] = $alpha2;
                }
            }

        }

        return $shippingCountries;
    }

    /**
     * Get shipping methods that support Kustom Checkout payment
     * @param Basket $oBasket
     * @return array
     * @throws KustomConfigException
     * @throws \oxSystemComponentException
     */
    protected function getSupportedShippingMethods(Basket $oBasket)
    {
        $allSets  = $this->getCheckoutShippingSets($this->_oUser);
        $currency = Registry::getConfig()->getActShopCurrencyObject();
        $methods  = array();
        if (!is_array($allSets)) {
            return $methods;
        }

        $this->_selectedShippingSetId = $oBasket->getShippingId();

        $shippingOptions = array();
        $shippingMap = KustomUtils::getShopConfVar('aarrKustomShippingMap');

        foreach ($allSets as $shippingId => $shippingMethod) {
            $assignedShippingMethod = $shippingMap[$shippingId] ?? false;
            
            $oBasket->setShipping($shippingId);
            $oPrice      = $oBasket->fcKustom_calculateDeliveryCost();
            $basketPrice = $oBasket->getPriceForPayment() / $currency->rate;
            if ($this->doesShippingMethodSupportKCO($shippingId, $basketPrice)) {
                $method = clone $shippingMethod;

                $price             = KustomUtils::parseFloatAsInt($oPrice->getBruttoPrice() * 100);
                $tax_rate          = KustomUtils::parseFloatAsInt($oPrice->getVat() * 100);
                $tax_amount        = KustomUtils::parseFloatAsInt($price - round($price / ($tax_rate / 10000 + 1), 0));
                $option = array(
                    "id"          => $shippingId,
                    "name"        => html_entity_decode($method->oxdeliveryset__oxtitle->value, ENT_QUOTES),
                    "description" => null,
                    "promo"       => null,
                    "tax_amount"  => $tax_amount,
                    'price'       => $price,
                    'tax_rate'    => $tax_rate,
                    'preselected' => $shippingId === $this->_selectedShippingSetId,
                );
                
                if ($assignedShippingMethod) {
                    if (KustomShipping::POSTAL_WITH_DHL_PACK_STATION === $assignedShippingMethod) {
                        $selectedShippingDuplicate = Registry::getSession()->getVariable('fckustomSelectedDuplicate');
                        $duplicate = $option;
                        $duplicate['shipping_method'] = KustomShipping::DHL_PACK_STATION;
                        $duplicate['id'] = self::PACK_STATION_PREFIX . $option['id'];
                        $duplicate['preselected'] = $duplicate['id'] === $selectedShippingDuplicate;
                        $shippingOptions[] = $duplicate;
                        if ($duplicate['preselected']) {
                            $option['preselected'] = false;
                        }
                    } else {
                        $option['shipping_method'] = $assignedShippingMethod;
                    }
                }
                $shippingOptions[] = $option;
            }
        }
        // set basket back to selected shipping option
        $oBasket->setShipping($this->_selectedShippingSetId);

        if (empty($shippingOptions)) {
            $oCountry = oxNew(Country::class);
            $oCountry->load($this->_oUser->getActiveCountry());

            throw new KustomConfigException(sprintf(
                Registry::getLang()->translateString('FCKUSTOM_ERROR_NO_SHIPPING_METHODS_SET_UP'),
                $oCountry->oxcountry__oxtitle->value
            ));
        }

        return $shippingOptions;
    }

    protected function getCheckoutShippingSets($oUser)
    {
        $sActShipSet = Registry::get(Request::class)->getRequestEscapedParameter('sShipSet');
        if (!$sActShipSet) {
            $sActShipSet = Registry::getSession()->getVariable('sShipSet');
        }
        $oBasket = Registry::getSession()->getBasket();
        list($aAllSets) =
            Registry::get(DeliverySetList::class)->getDeliverySetData($sActShipSet, $oUser, $oBasket);

        return $aAllSets;
    }

    /**
     * @param string $shippingId
     * @param float $basketPrice
     * @return bool
     */
    protected function doesShippingMethodSupportKCO($shippingId, $basketPrice)
    {
        $oPayList    = Registry::get(PaymentList::class);
        $paymentList = $oPayList->getPaymentList($shippingId, $basketPrice, $this->_oUser);

        return count($paymentList) && in_array('kustom_checkout', array_keys($paymentList));
    }

    /**
     *
     */
    public function getKustomCountryList()
    {
        if ($this->_kustomCountryList === null) {
            $this->_kustomCountryList = array();
            $oCountryList = oxNew(CountryList::class);
            $oCountryList->loadActiveKustomCheckoutCountries();
            foreach ($oCountryList as $oCountry) {
                $this->_kustomCountryList[$oCountry->oxcountry__oxid->value] = $oCountry->oxcountry__oxisoalpha2->value;
            }
        }

        return $this->_kustomCountryList;
    }

    /**
     * Gets an array of all countries the given payment type can be used in.
     *
     * @param Payment $oPayment
     * @param $aActiveCountries
     * @return array
     */
    public function getKustomCountryListByPayment(Payment $oPayment, $aActiveCountries)
    {
        $result            = array();
        $aPaymentCountries = $oPayment->getCountries();
        foreach ($aPaymentCountries as $oxid) {
            if (isset($aActiveCountries[$oxid]))
                $result[] = $aActiveCountries[$oxid];
        }

        return empty($result) ? array_values($aActiveCountries) : $result;
    }

    /**
     * @param Basket $oBasket
     * @param User $oUser
     * @return array
     */
    public function getExternalPaymentMethods(Basket $oBasket, User $oUser)
    {
        $oPayList     = Registry::get(PaymentList::class);
        $dBasketPrice = $oBasket->getPriceForPayment();

        $externalPaymentMethods  = array();
        $externalCheckoutMethods = array();

        $paymentList = $oPayList->getPaymentList($oBasket->getShippingId(), $dBasketPrice, $oUser);

        foreach ($paymentList as $paymentId => $oPayment) {
            $oConfig = Registry::getConfig();
            $oPayment->calculate($oBasket);
            $aCountryISO = $this->getKustomCountryListByPayment($oPayment, $this->getKustomCountryList());
            $oPrice      = $oPayment->getPrice();

            $requestParams = method_exists($oConfig, 'mustAddShopIdToRequest')
                             && $oConfig->mustAddShopIdToRequest()
                                ? '&shp=' . $oConfig->getShopId()
                                : '';

            $externalName = $oPayment->oxpayments__fckustom_externalname->value;
            if ($oPayment->oxpayments__fckustom_externalpayment->value) {

                if ($paymentId === 'oxidpaypal') {
                    $requestParams .= '&displayCartInPayPal=1';
                }

                // don't add Apple Pay as external payment if user's device is not eligible
                if ($externalName !== "Apple Pay" || $this->userIsApplePayEligible()) {
                    $externalPaymentMethods[] = array(
                        'name'         => $externalName,
                        'redirect_url' => $oConfig->getSslShopUrl() .
                            'index.php?cl=order&fnc=kustomExternalPayment&payment_id=' . $paymentId . $requestParams,
                        'image_url'    => $this->resolveImageUrl($oPayment),
                        'fee'          => KustomUtils::parseFloatAsInt($oPrice->getBruttoPrice() * 100),
                        'description'  => KustomUtils::stripHtmlTags($oPayment->oxpayments__oxlongdesc->getRawValue()),
                        'countries'    => $aCountryISO,
                    );
                }
            }

            if ($oPayment->oxpayments__fckustom_externalcheckout->value) {
                $requestParams             .= '&externalCheckout=1';
                $externalCheckoutMethods[] = array(
                    'name'         => $externalName,
                    'redirect_url' => $oConfig->getSslShopUrl() .
                                      'index.php?cl=order&fnc=kustomExternalPayment&payment_id=' . $paymentId . $requestParams,
                    'image_url'    => $this->resolveImageUrl($oPayment, true),
                    'fee'          => KustomUtils::parseFloatAsInt($oPrice->getBruttoPrice() * 100),
                    'description'  => KustomUtils::stripHtmlTags($oPayment->oxpayments__oxlongdesc->getRawValue()),
                    'countries'    => $aCountryISO,
                );
            }
        }

        return array('payments' => $externalPaymentMethods, 'checkouts' => $externalCheckoutMethods);
    }

    protected function userIsApplePayEligible()
    {
        return Registry::getSession()->getVariable("kcoApplePayDeviceEligible");
    }

    /**
     *
     */
    public function addOptions()
    {
        $options = array();

        $options['additional_checkbox']               = $this->getAdditionalCheckboxData();
        $options['allow_separate_shipping_address']   = $this->isSeparateDeliveryAddressAllowed();
        $options['phone_mandatory']                   = $this->isPhoneMandatory();
        $options['date_of_birth_mandatory']           = $this->isBirthDateMandatory();
        $options['require_validate_callback_success'] = $this->isValidateCallbackSuccessRequired();
        $options['shipping_details']                  =
            $this->getShippingDetailsMsg();


        /*** add design settings ***/
        if (!$designSettings = KustomUtils::getShopConfVar('aKustomDesign')) {
            $designSettings = array();
        }
        
        $typeList = oxNew(KustomConsts::class)->getCustomerTypes();
        $type = $typeList[$this->activeB2Option];
        $options['allowed_customer_types'] = $type;
        
        $options = array_merge($options, $designSettings);

        $this->_aOrderData['options'] = $options;
    }

    /**
     * @return bool
     */
    public function isAutofocusEnabled()
    {
        return KustomUtils::getShopConfVar('blKustomEnableAutofocus');
    }

    /**
     * @return string
     */
    public function getShippingDetailsMsg()
    {
        $langTag = strtoupper(Registry::getLang()->getLanguageAbbr());

        return KustomUtils::getShopConfVar('aarrKustomShippingDetails')['sKustomShippingDetails_' . $langTag];
    }

    /**
     * @return int
     * @throws \oxSystemComponentException
     */
    protected function getAdditionalCheckbox()
    {
        $iActiveCheckbox = KustomUtils::getShopConfVar('iKustomActiveCheckbox');

        $type = $this->_oUser->getType();
        if ($type === KustomUser::LOGGED_IN || $type === KustomUser::REGISTERED) {
            if ($this->_oUser->getNewsSubscription()->getOptInStatus() == 1) {

                return KustomConsts::EXTRA_CHECKBOX_NONE;
            }
            if ($iActiveCheckbox > KustomConsts::EXTRA_CHECKBOX_CREATE_USER) {

                return KustomConsts::EXTRA_CHECKBOX_SIGN_UP;
            }

            return KustomConsts::EXTRA_CHECKBOX_NONE;
        }

        return (int)$iActiveCheckbox;
    }

    protected function setAttachmentsData()
    {
        if (!$this->_oUser->isFake()) {
            $emd = $this->getEmd();

            if (!empty($emd)) {
                $this->_aOrderData['attachment'] = array(
                    'content_type' => 'application/vnd.klarna.internal.emd-v2+json',
                    'body'         => json_encode($emd),
                );
            }
        }
    }

    /**
     * @return array
     */
    protected function getEmd()
    {
        /** @var KustomEMD $kustomEmd */
        $kustomEmd = oxNew(KustomEMD::class);
        $emd       = $kustomEmd->getAttachments($this->_oUser);

        return $emd;
    }

    /**
     * @return mixed
     */
    protected function isSeparateDeliveryAddressAllowed()
    {
        return (bool) KustomUtils::getShopConfVar('blKustomAllowSeparateDeliveryAddress');
    }

    /**
     * Check if user already has an account and if he's subscribed to the newsletter
     * Don't add the extra checkbox if not needed.
     */
    protected function getAdditionalCheckboxData()
    {
        $activeCheckbox = $this->getAdditionalCheckbox();

        switch ($activeCheckbox) {
            case 0:
                return null;
                break;
            case 1:
                return array(
                    'text'     => Registry::getLang()->translateString('FCKUSTOM_CREATE_USER_ACCOUNT'),
                    'checked'  => false,
                    'required' => false,
                );
                break;
            case 2:
                return array(
                    'text'     => Registry::getLang()->translateString('FCKUSTOM_SUBSCRIBE_TO_NEWSLETTER'),
                    'checked'  => false,
                    'required' => false,
                );
                break;
            case 3:
                return array(
                    'text'     => Registry::getLang()->translateString('FCKUSTOM_CREATE_USER_ACCOUNT_AND_SUBSCRIBE'),
                    'checked'  => false,
                    'required' => false,
                );
                break;
            default:
                return null;
                break;
        }
    }

    /**
     * @return bool
     */
    protected function isPhoneMandatory()
    {
        return KustomUtils::getShopConfVar('blKustomMandatoryPhone');
    }

    /**
     * @return bool
     */
    protected function isBirthDateMandatory()
    {
        return KustomUtils::getShopConfVar('blKustomMandatoryBirthDate');
    }

    /**
     * @return bool
     */
    protected function isValidateCallbackSuccessRequired()
    {
        return KustomUtils::getShopConfVar('iKustomValidation') == 2;
    }

    /**
     * @return bool
     */
    protected function isValidationEnabled()
    {
        return KustomUtils::getShopConfVar('iKustomValidation') != 0;
    }

    /**
     * @param $oPayment
     * @param bool $checkoutImgUrl
     * @return mixed
     */
    protected function resolveImageUrl($oPayment, $checkoutImgUrl = false)
    {
        if ($checkoutImgUrl) {
            $url = $oPayment->oxpayments__fckustom_checkoutimageurl->value;
        } else {
            $url = $oPayment->oxpayments__fckustom_paymentimageurl->value;
        }

        $result = preg_replace('/http:/', 'https:', $url);

        return $result ?: null;
    }

    /**
     *
     */
    protected function setPassThroughField()
    {
        $oKustomPassThrough = oxNew(KustomPassThrough::class);
        $data               = $oKustomPassThrough->getPassThroughField();
        if (!empty($data)) {
            $this->_aOrderData['merchant_data'] = $data;
        }
    }

    /**
     * @return bool
     */
    public function isError()
    {
        return (bool)$this->errors;
    }
}
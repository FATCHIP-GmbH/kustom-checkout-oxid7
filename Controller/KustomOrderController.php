<?php


namespace Fatchip\FcKustom\Controller;


use Fatchip\FcKustom\Core\KustomPaymentTypes;
use Fatchip\FcKustom\Core\KustomUserUpdater;
use OxidEsales\PayPalModule\Controller\ExpressCheckoutDispatcher;
use OxidEsales\PayPalModule\Controller\StandardDispatcher;
use Fatchip\FcKustom\Core\KustomCheckoutClient;
use Fatchip\FcKustom\Core\KustomClientBase;
use Fatchip\FcKustom\Core\KustomConsts;
use Fatchip\FcKustom\Core\KustomFormatter;
use Fatchip\FcKustom\Core\KustomLogs;
use Fatchip\FcKustom\Core\KustomOrder;
use Fatchip\FcKustom\Core\KustomOrderManagementClient;
use Fatchip\FcKustom\Core\KustomPaymentsClient;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Model\KustomPaymentHelper;
use Fatchip\FcKustom\Model\KustomUser;
use Fatchip\FcKustom\Model\KustomPayment as KustomPaymentModel;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\UtilsView;
use VIISON\AddressSplitter\AddressSplitter;

/**
 * Extends default OXID order controller logic.
 */
class KustomOrderController extends KustomOrderController_parent
{
    protected $_aResultErrors;

    /** @var Request */
    protected $oRequest;

    /** @var string  KustomExpressController url */
    protected $selfUrl;

    /**
     * @var User|KustomUser
     */
    protected $_oUser;

    /**
     * @var array data fetched from KustomCheckout
     */
    protected $_aOrderData;

    /** @var bool create new order on country change */
    protected $forceReloadOnCountryChange = false;

    /** @var  bool */
    public $loadKustomPaymentWidget = false;

    /**
     * @var bool
     */
    protected $isExternalCheckout = false;

    protected function getTimeStamp()
    {
        $dt = new \DateTime();

        return $dt->getTimestamp();
    }

    /**
     *
     * @throws StandardException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public function init()
    {
        parent::init();

        //Re-set country to session if empty
        if(empty(Registry::getSession()->getVariable('sCountryISO')) && !empty($this->getUser())) {
            Registry::getSession()->setVariable('sCountryISO', $this->getUser()->getUserCountryISO2());
        }

        $oSession = Registry::getSession();
        if ($kebauthresponse = json_decode(Registry::getRequest()->getRequestParameter("kebauthresponse"))) {

            $kustomPaymentclient = KustomPaymentsClient::getInstance();
            $address = (array)$kebauthresponse->collected_shipping_address;

            $kustomPaymentclient->createKEXSession($kebauthresponse->session_id);

            $oSession->deleteVariable('reauthorizeRequired');
            if ($deladrid) {
                $oSession->setVariable('kebmail', $address["email"]);
                $oSession->setVariable('deladrid', $deladrid);
            }
            $oSession->setVariable('finalizeRequired', $kebauthresponse->finalize_required);

        }

        $paymentId = Registry::getRequest()->getRequestParameter("payment_id");
        $isExternalPayment = $paymentId && $paymentId != KustomPaymentHelper::getKustomPaymentsId();

        if ($isExternalPayment) {
            $kustomFakeUsername = Registry::getSession()->getVariable('kustom_checkout_user_email');
            $fakeUser = KustomUtils::getFakeUser($kustomFakeUsername);
            $fakeUser->setActiveUser();
            Registry::getSession()->setVariable("usr", $fakeUser->getId());
        }

        $oConfig = Registry::getConfig();
        $shopParam = method_exists($oConfig, 'mustAddShopIdToRequest')
        && $oConfig->mustAddShopIdToRequest()
            ? '&shp=' . $oConfig->getShopId()
            : '';
        $this->oRequest = Registry::get(Request::class);
        $oBasket        = Registry::getSession()->getBasket();
        $this->selfUrl  = $oConfig->getShopSecureHomeUrl() . 'cl=KustomExpress';

        if ($this->oRequest->getRequestEscapedParameter('externalCheckout') == 1) {
            Registry::getSession()->setVariable('externalCheckout', true);
        }
        $this->isExternalCheckout = Registry::getSession()->getVariable('externalCheckout');

        if ($this->isKustomCheckoutOrder($oBasket)) {
            if ($newCountry = $this->isCountryChanged()) {

                $this->_aOrderData = [
                    'merchant_urls'    => [
                        'checkout' => $oConfig->getSslShopUrl() . "?cl=KustomExpress" . $shopParam,
                    ],
                    'billing_address'  => [
                        'country' => $newCountry,
                        'email'   => Registry::getSession()->getVariable('kustom_checkout_user_email'),
                    ],
                    'shipping_address' => [
                        'country' => $newCountry,
                        'email'   => Registry::getSession()->getVariable('kustom_checkout_user_email'),
                    ],
                ];
                Registry::getSession()->setVariable('sCountryISO', $newCountry);
            } else {
                $oClient = $this->getKustomCheckoutClient();
                try {
                    $this->_aOrderData = $oClient->getOrder();
                } catch (KustomClientException $oEx) {
                    KustomUtils::logException($oEx);
                    return;
                }

                if (KustomUtils::is_ajax() && $this->_aOrderData['status'] === 'checkout_complete') {
                    $this->jsonResponse('ajax', 'read_only');
                }
            }

            $this->initUser();
            /** @var KustomUserUpdater $userUpdater */
            $userUpdater = oxNew(KustomUserUpdater::class);
            $paymentId = Registry::getRequest()->getRequestParameter("payment_id");
            $userUpdater->updateUserObject($this->_oUser, $this->_aOrderData, $paymentId);
        }
    }

    /**
     * Logging push state message to database
     *
     *
     * @param $action
     * @param $requestBody
     * @param $url
     * @param $response
     * @param $errors
     * @param string $redirectUrl
     * @throws \Exception
     * @internal param KustomOrderValidator $oValidator
     */
    protected function logKustomData($action, $requestBody, $url, $response, $errors, $redirectUrl = '')
    {
        $order_id = isset($requestBody['order_id']) ? $requestBody['order_id'] : '';

        $oKustomLog = new KustomLogs;
        $aData      = array(
            'fckustom_logs__fckustom_method'      => $action,
            'fckustom_logs__fckustom_url'         => $url,
            'fckustom_logs__fckustom_orderid'     => $order_id,
            'fckustom_logs__fckustom_requestraw'  => json_encode($requestBody) .
                " \nERRORS:" . var_export($errors, true) .
                " \nHeader Location:" . $redirectUrl,
            'fckustom_logs__fckustom_responseraw' => $response,
            'fckustom_logs__fckustom_date'        => date("Y-m-d H:i:s"),
        );
        $oKustomLog->assign($aData);
        $oKustomLog->save();
    }

    protected function getKustomAllowedExternalPayments()
    {
        return KustomPaymentTypes::getKustomAllowedExternalPayments();
    }

    protected function isKustomExternalPaymentMethod($paymentId, $sCountryISO)
    {
        if (!in_array($paymentId, $this->getKustomAllowedExternalPayments())) {
            return false;
        }
        if (!KustomUtils::isCountryActiveInKustomCheckout($sCountryISO)) {
            return false;
        }

        return true;
    }

    /**
     * @param $oBasket Basket
     * @return bool
     */
    protected function isKustomCheckoutOrder($oBasket)
    {
        $paymentId   = $oBasket->getPaymentId();
        $sCountryISO = Registry::getSession()->getVariable('sCountryISO');

        if (!($paymentId === 'kustom_checkout' || $this->isKustomExternalPaymentMethod($paymentId, $sCountryISO))) {
            return false;
        }

        if ($oBasket->getPaymentId() === 'bestitamazon') {
            return false;
        }

        $kcoId = Registry::getSession()->getVariable('kustom_checkout_order_id');
        if ($oBasket->getPaymentId() === 'oxidpaypal' && $kcoId === null) {
            return false;
        }

        return true;
    }

    /**
     * @codeCoverageIgnore
     * @return KustomCheckoutClient|KustomClientBase
     */
    protected function getKustomCheckoutClient()
    {
        return KustomCheckoutClient::getInstance();
    }

    /**
     *
     * @return KustomPaymentsClient|KustomClientBase
     */
    protected function getKustomPaymentsClient()
    {
        return KustomPaymentsClient::getInstance();
    }

    /**
     * Runs security checks. Returns true if all passes
     * @return bool
     */
    protected function kustomCheckoutSecurityCheck()
    {
        /** @var Request $oRequest */
        $oRequest = Registry::get(Request::class);
        $requestedKustomId = $oRequest->getRequestParameter('kustom_order_id');
        $sessionKustomId = Registry::getSession()->getVariable('kustom_checkout_order_id');

        // compare kustom ids - request to session
        if(empty($requestedKustomId) || $requestedKustomId !== $sessionKustomId){
            return false;
        }
        // make sure if kustom order was validated
        if (!$this->_aOrderData || $this->_aOrderData['status'] !== 'checkout_complete') {
            return false;
        }

        return true;
    }

    /**
     * Kustom confirmation callback. Calls only parent execute (standard oxid order creation) if not kustom_checkout
     * @return string
     * @throws StandardException
     */
    public function execute()
    {
        $oBasket = Registry::getSession()->getBasket();
        $paymentId = $oBasket->getPaymentId() ?? Registry::getRequest()->getRequestParameter("kexpaymentid");

        if(KustomPaymentHelper::isKustomPayment($paymentId)){
            /**
             * sDelAddrMD5 value is up to date with kustom user data (we updated user object in the init method)
             *  It is required later to validate user data before order creation
             */
            if($this->_oUser || $this->getUser()){
                Registry::getSession()->setVariable('sDelAddrMD5', $this->getDeliveryAddressMD5());
            }

            if (!Registry::getSession()->checkSessionChallenge()) {
                return;
            }

            if (!$this->kustomCheckoutSecurityCheck()) {
                return 'KustomExpress';
            }

            $this->kcoBeforeExecute();
            $iSuccess = $this->kcoExecute($oBasket);

            return $this->getNextStep($iSuccess);
        } else if (Registry::getRequest()->getRequestParameter('kustom_order_id')) {
            // executing Kustom order but basket has no Kustom paymentId -> basket is not properly init'd, customer must try again
            KustomUtils::fullyResetKustomSession();
            Registry::getUtilsView()->addErrorToDisplay('KUSTOM_WENT_WRONG_TRY_AGAIN', false, true);
            return Registry::getUtils()->redirect($this->selfUrl, true, 302);
        }

        // if user is not logged in set the user
        if(!$this->getUser() && isset($this->_oUser)){
            $this->setUser($this->_oUser);
        }

        return parent::execute(); // @codeCoverageIgnore
    }

    /**
     *
     * @throws StandardException
     */
    protected function kcoBeforeExecute()
    {
        try {
            $oBasket      = Registry::getSession()->getBasket();
            $oKustomOrder = $this->initKustomOrder($oBasket);
            $oKustomOrder->validateKustomB2B();
            if($oKustomOrder->isError()) {
                $oKustomOrder->displayErrors();
                Registry::getUtils()->redirect(Registry::getConfig()->getShopSecureHomeUrl() . 'cl=order', false);

                return;
            }

            $this->_validateUser($this->_aOrderData);
        } catch (StandardException $exception) {
            $this->_aResultErrors[] = $exception->getMessage();
            $this->logKustomData(
                'Order Execute',
                $this->_aOrderData,
                '',
                '',
                $this->_aResultErrors,
                ''
            );
        }

        // send newsletter confirmation
        if ($this->isNewsletterSignupNeeded()) {
            if ($oUser = $this->getUser()) {
                $oUser->setNewsSubscription(true, true);  // args = [value, send_confirmation]
            } else {
                throw new StandardException('no user object');
            }
        }
    }


    /**
     * Check if user is logged in, if not check if user is in oxid and log them in
     * or create a user
     *
     *
     * @return bool
     */
    protected function _validateUser()
    {
        switch ($this->_oUser->getType()) {

            case KustomUser::NOT_EXISTING:
            case KustomUser::NOT_REGISTERED:
                // create regular account with password or temp account - empty password
                $result = $this->_createUser();

                return $result;

            default:
                break;
        }
    }

    /**
     * Create a user in oxid from kustom checkout data
     *
     *
     * @return bool
     * @throws \oxUserException
     * @throws \oxSystemComponentException
     */
    protected function _createUser()
    {
        $aBillingAddress  = KustomFormatter::kustomToOxidAddress($this->_aOrderData, 'billing_address');

        $aDeliveryAddress = null;
        if($this->_aOrderData['billing_address'] !== $this->_aOrderData['shipping_address']){
            $aDeliveryAddress = KustomFormatter::kustomToOxidAddress($this->_aOrderData, 'shipping_address');
        }

        $this->_oUser->oxuser__oxusername = new Field($this->_aOrderData['billing_address']['email'], Field::T_RAW);
        $this->_oUser->oxuser__oxactive   = new Field(1, Field::T_RAW);

        if (isset($this->_aOrderData['customer']['date_of_birth'])) {
            $this->_oUser->oxuser__oxbirthdate = new Field($this->_aOrderData['customer']['date_of_birth']);
        }

        $this->_oUser->createUser();

        //NECESSARY to have all fields initialized.
        $this->_oUser->load($this->_oUser->getId());

        $password = $this->isRegisterNewUserNeeded() ? $this->getRandomPassword(8) : null;
        $this->_oUser->setPassword($password);

        $this->_oUser->changeUserData($this->_oUser->oxuser__oxusername->value, $password, $password, $aBillingAddress, $aDeliveryAddress);

        // login only if registered a new account with password
        if ($this->isRegisterNewUserNeeded()) {
            Registry::getSession()->setVariable('usr', $this->_oUser->getId());
            Registry::getSession()->setVariable('blNeedLogout', true);
        }

        $this->setUser($this->_oUser);

        if($aDeliveryAddress){
            $this->_oUser->updateDeliveryAddress($aDeliveryAddress);
        }

        return true;
    }

    /**
     * Save order to database, delete order_id from session and redirect to thank you page
     *
     *
     * @param Basket $oBasket
     * @return
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    protected function kcoExecute(Basket $oBasket)
    {
        // reload blocker
        if (!Registry::getSession()->getVariable('sess_challenge')) {
            $sGetChallenge = Registry::getUtilsObject()->generateUID();
            Registry::getSession()->setVariable('sess_challenge', $sGetChallenge);
        } else {
            // Check if the existing session challenge exists in the database in a non-KCO order
            $orderId = Registry::getSession()->getVariable('sess_challenge');
            /** @var \Fatchip\FcKustom\Model\KustomOrder $oOrder */
            $oOrder = oxNew(Order::class);
            if ($oOrder->checkForeignOrderExist($orderId)) {
                $sGetChallenge = Registry::getUtilsObject()->generateUID();
                Registry::getSession()->setVariable('sess_challenge', $sGetChallenge);
            }
        }

        $oBasket->calculateBasket(true);

        $oOrder = oxNew(Order::class);
        try {
            $iSuccess = $oOrder->finalizeOrder($oBasket, $this->_oUser);
        } catch (StandardException $e) {
            Registry::getSession()->deleteVariable('kustom_checkout_order_id');

            Registry::get(UtilsView::class)->addErrorToDisplay($e);
        }

        if ($iSuccess === 1) {
            if (
                ($this->_oUser->getType() === KustomUser::NOT_REGISTERED ||
                    $this->_oUser->getType() === KustomUser::NOT_EXISTING) &&
                $this->isRegisterNewUserNeeded()
            ) {
                $this->_oUser->save();
            }
            if ($this->_oUser->isFake()){
                $this->_oUser->clearDeliveryAddress();
            }
            // performing special actions after user finishes order (assignment to special user groups)
            $this->_oUser->onOrderExecute($oBasket, $iSuccess);

            if ($this->isRegisterNewUserNeeded()) {
                $oEmail = oxNew(\OxidEsales\Eshop\Core\Email::class);
                $oEmail->sendForgotPwdEmail($this->_oUser->oxuser__oxusername->value);
            }

            Registry::getSession()->setVariable('paymentid', 'kustom_checkout');
        }

        return $iSuccess;
    }


    /**
     * General Ajax entry point for this controller
     * @throws KustomClientException
     * @throws StandardException
     * @throws \ReflectionException
     * @throws \Fatchip\FcKustom\Core\Exception\KustomOrderNotFoundException
     * @throws \Fatchip\FcKustom\Core\Exception\KustomOrderReadOnlyException
     * @throws \Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException
     */
    public function updateKustomAjax()
    {
        $aPost = $this->getJsonRequest();

        switch ($aPost['action']) {
            case 'shipping_option_change':
                $this->shipping_option_change($aPost);
                break;

            case 'shipping_address_change':
                $this->shipping_address_change();
                break;

            case 'change':
                $this->updateSession($aPost);
                break;

            default:
                $this->jsonResponse('undefined action', 'error');
        }
    }

    /**
     * Ajax - updates country heading above iframe
     * @param $aPost
     * @return string
     *
     */
    protected function updateSession($aPost)
    {
        $responseData   = array();
        $responseStatus = 'success';

        if ($aPost['country']) {

            $oCountry = oxNew(Country::class);
            $sSql     = $oCountry->buildSelectString(array('oxisoalpha3' => $aPost['country']));
            $oCountry->assignRecord($sSql);

            Registry::getSession()->setVariable('sCountryISO', $oCountry->oxcountry__oxisoalpha2->value);
            $this->forceReloadOnCountryChange = true;

            try {
                $this->updateKustomOrder();
            } catch (StandardException $e) {
                KustomUtils::logException($e);
            }

            $responseData['url'] = $this->_aOrderData['merchant_urls']['checkout'];
            $responseStatus      = 'redirect';
        }

        return Registry::getUtils()->showMessageAndExit(
            $this->jsonResponse(__FUNCTION__, $responseStatus, $responseData)
        );
    }

    /**
     * Ajax shipping_option_change action
     * @param $aPost
     * @return null
     */
    protected function shipping_option_change($aPost)
    {
        if (isset($aPost['id'])) {
            // clean up duplicated method id
            $selectedDuplicate = null;
            if (strpos($aPost['id'], KustomOrder::PACK_STATION_PREFIX) === 0) {
                $selectedDuplicate = $aPost['id'];
                $aPost['id'] = substr($aPost['id'], strlen(KustomOrder::PACK_STATION_PREFIX));
            }
            Registry::getSession()->setVariable('fckustomSelectedDuplicate', $selectedDuplicate);

            // update basket
            $oSession = Registry::getSession();
            $oBasket  = $oSession->getBasket();
            $oBasket->setShipping($aPost['id']);

            // update kustom order
            try {
                $this->updateKustomOrder();
            } catch (StandardException $e) {
                KustomUtils::logException($e);
            }

            $responseData = array();
            $this->jsonResponse(__FUNCTION__, 'changed', $responseData);
        } else {
            $this->jsonResponse(__FUNCTION__, 'error');
        }
    }

    /**
     * Ajax shipping_address_change action
     */
    protected function shipping_address_change()
    {
        $status = null;
        try {
            $oSession = Registry::getSession();
            $oBasket  = $oSession->getBasket();
            if($vouchersCount = count($oBasket->getVouchers())){
                $oBasket->kustomValidateVouchers();
                // update widget if there was some invalid vouchers
                if($vouchersCount !== count($oBasket->getVouchers())){
                    $status = 'update_voucher_widget';
                }
            }
            $this->updateKustomOrder();
            $status = isset($status) ? $status : 'changed';
        } catch (StandardException $e) {
            KustomUtils::logException($e);
        }

        return $this->jsonResponse(__FUNCTION__, $status);

    }

    /**
     * Sends update request to checkout API
     * @return array|bool order data
     * @throws \oxSystemComponentException
     */
    protected function updateKustomOrder()
    {
        if ($this->_oUser) {
            $oSession = Registry::getSession();
            /** @var Basket|\Fatchip\FcKustom\Model\KustomBasket $oBasket */
            $oBasket = $oSession->getBasket();
            $oKustomOrder = oxNew(KustomOrder::class, $oBasket, $this->_oUser);
            $oClient      = $this->getKustomCheckoutClient();
            $aOrderData   = $oKustomOrder->getOrderData();
            if ($this->forceReloadOnCountryChange && isset($this->_aOrderData['billing_address']) && isset($this->_aOrderData['shipping_address'])) {
                $aOrderData['billing_address']  = $this->_aOrderData['billing_address'];
                $aOrderData['shipping_address'] = $this->_aOrderData['shipping_address'];
            }

            $oClient->createOrUpdateOrder(
                json_encode($aOrderData)
            );
        }
    }

    /**
     * Initialize oxUser object and get order data from Kustom
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    protected function initUser()
    {
        if ($this->_oUser = $this->getUser()) {
            if ($this->getViewConfig()->isUserLoggedIn()) {
                $this->_oUser->setType(KustomUser::LOGGED_IN);
            }
        } else {
            $this->_oUser = KustomUtils::getFakeUser($this->_aOrderData['billing_address']['email']);
        }

        $oCountry                          = oxNew(Country::class);
        $this->_oUser->oxuser__oxcountryid = new Field(
            $oCountry->getIdByCode(
                strtoupper($this->_aOrderData['billing_address']['country'])
            ),
            Field::T_RAW
        );

        $oBasket = Registry::getSession()->getBasket();
        $oBasket->setBasketUser($this->_oUser);


    }
    /**
     * Clear KCO session.
     * Destroy client instance / force to use new credentials. This allow us to
     * create new order (using new merchant account) in this request
     *
     */
    protected function resetKustomCheckoutSession()
    {
        KustomCheckoutClient::resetInstance(); // we need new instance with new credentials
        Registry::getSession()->deleteVariable('kustom_checkout_order_id');
    }

    /**
     * Handles external payment
     * @throws \oxSystemComponentException
     * @throws \oxUserException
     */
    public function kustomExternalPayment()
    {
        $oSession = Registry::getSession();

        $orderId   = $oSession->getVariable('kustom_checkout_order_id');
        $paymentId = Registry::get(Request::class)->getRequestEscapedParameter('payment_id');
        if (!$orderId || !$paymentId || !$this->isActivePayment($paymentId)) {

            var_dump($orderId);
            var_dump($paymentId);
            var_dump($this->isActivePayment($paymentId));
            Registry::get(UtilsView::class)->addErrorToDisplay('KUSTOM_WENT_WRONG_TRY_AGAIN', false, true);
            Registry::getUtils()->redirect($this->selfUrl, true, 302);

            return;
        }

        $oSession->setVariable("paymentid", $paymentId);
        $oBasket = $oSession->getBasket();
        // make sure we have the right shipping option
        $oBasket->setShipping($this->_aOrderData['selected_shipping_option']['id']);
        $oBasket->setPayment($paymentId);
        $oBasket->onUpdate();

        if ($this->isExternalCheckout) {
            die("external");
            $this->kustomExternalCheckout($paymentId);
            return;
        }

        if ($paymentId === 'bestitamazon') {
            if ($this->_oUser->isCreatable()) {
                // create user
                $this->_createUser();
            }

            die("HÄ");
            return Registry::getUtils()->redirect(Registry::getConfig()->getShopSecureHomeUrl() . "cl=KustomEpmDispatcher&fnc=amazonLogin", false);
        } else {
            Registry::getConfig()->setConfigParam('blAmazonLoginActive', false);
        }

        if ($paymentId === 'oxidpaypal') {
            return Registry::get(StandardDispatcher::class)->setExpressCheckout();
        }

        // if user is not logged in set the user to render order
        if(!$this->getUser() && isset($this->_oUser)){
            $this->setUser($this->_oUser);
        }
    }

    /**
     * @param $paymentId
     */
    public function kustomExternalCheckout($paymentId)
    {
        if ($paymentId === 'bestitamazon') {
            Registry::getUtils()->redirect(Registry::getConfig()->getShopSecureHomeUrl() . "cl=KustomEpmDispatcher&fnc=amazonLogin", false);
        } else if ($paymentId === 'oxidpaypal') {
            $useStandardDispatcher = $this->getUser()->tcklrnaHasValidInfo();
            if ($useStandardDispatcher) {
                return Registry::get(StandardDispatcher::class)->setExpressCheckout();
            }
            return Registry::get(ExpressCheckoutDispatcher::class)->setExpressCheckout();

        } else {
            KustomUtils::fullyResetKustomSession();
            Registry::get(UtilsView::class)->addErrorToDisplay('KUSTOM_WENT_WRONG_TRY_AGAIN', false, true);
            return Registry::getUtils()->redirect($this->selfUrl, true, 302);
        }
    }

    /**
     * Should we register a new user account with the order?
     * @return bool
     * @internal param $aOrderData
     */
    protected function isRegisterNewUserNeeded()
    {
        $checked          = $this->_aOrderData['merchant_requested']['additional_checkbox'] === true;
        $checkboxFunction = KustomUtils::getShopConfVar('iKustomActiveCheckbox');

        return $checkboxFunction > 0 && $checked;
    }

    /**
     * Should we sign the user up for the newsletter?
     * @return bool
     * @internal param $aOrderData
     */
    protected function isNewsletterSignupNeeded()
    {
        $checked          = $this->_aOrderData['merchant_requested']['additional_checkbox'] === true;
        $checkboxFunction = KustomUtils::getShopConfVar('iKustomActiveCheckbox');

        return $checkboxFunction > 1 && $checked;
    }

    /**
     * @param $len int
     * @return string
     */
    protected function getRandomPassword($len)
    {
        $alphabet    = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass        = array();
        $alphaLength = strlen($alphabet) - 1;
        for ($i = 0; $i < $len; $i++) {
            $n      = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }

        return implode($pass);
    }

    /**
     * Formats Json response
     * @param $action
     * @param $status
     * @param $data
     * @return string
     */
    private function jsonResponse($action, $status = null, $data = null)
    {
        return Registry::getUtils()->showMessageAndExit(json_encode(array(
            'action' => $action,
            'status' => $status,
            'data'   => $data,
        )));
    }

    /**
     * Gets data from request body
     * @return array
     * @codeCoverageIgnore
     */
    protected function getJsonRequest()
    {
        $requestBody = file_get_contents('php://input');

        return json_decode($requestBody, true);
    }

    /**
     * @param $paymentId
     * @return bool
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    protected function isActivePayment($paymentId)
    {
        $oPayment = oxNew(Payment::class);
        $oPayment->load($paymentId);

        return (boolean)$oPayment->oxpayments__oxactive->value;
    }

    /**
     * @return null|string
     */
    public function render()
    {
        if (Registry::getSession()->getVariable('paymentid') === "kustom_checkout") {
            Registry::getSession()->deleteVariable('paymentid');
            Registry::getUtils()->redirect(
                Registry::getConfig()->getShopSecureHomeUrl() . "cl=basket", false
            );

            return;
        }

        $template = parent::render();

        if (!Registry::getRequest()->getRequestParameter("kcoreloaded")) {
            $queryString = $_SERVER['QUERY_STRING'];
            Registry::getUtils()->redirect(Registry::getConfig()->getShopSecureHomeUrl() . $queryString . "&kcoreloaded=1", false);
        }

        return $template;
    }

    /**
     * @param null $sCountryISO
     * @return \Fatchip\FcKustom\Core\KustomClientBase
     */
    protected function getKustomOrderClient($sCountryISO = null)
    {
        return KustomOrderManagementClient::getInstance($sCountryISO);
    }

    /**
     *
     * @param $oUser
     * @return bool
     */
    public function isCountryHasKustomPaymentsAvailable($oUser = null)
    {
        if ($oUser === null) {
            $oUser = $this->getUser();
        }
        $sCountryISO = KustomUtils::getCountryISO($oUser->getFieldData('oxcountryid'));
        if (in_array($sCountryISO, oxNew(KustomConsts::class)->getKustomCoreCountries())) {
            return true;
        }

        return false;
    }

    /**
     * @return bool|false|string
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    protected function isCountryChanged()
    {
        $requestData = $this->getJsonRequest();
        $newCountry  = KustomUtils::getCountryIso2fromIso3(strtoupper($requestData['country']));
        $oldCountry  = Registry::getSession()->getVariable('sCountryISO');

        if (!$newCountry) {
            return false;
        }

        return $newCountry != $oldCountry ? $newCountry : false;
    }

    public function getDeliveryAddressMD5()
    {
        // bill address
        $oUser = $this->getUser()?$this->getUser():$this->_oUser;
        $sDelAddress = $oUser->getEncodedDeliveryAddress();

        // delivery address
        if (Registry::getSession()->getVariable('deladrid')) {
            $oDelAddress = oxNew(\OxidEsales\Eshop\Application\Model\Address::class);
            $oDelAddress->load(Registry::getSession()->getVariable('deladrid'));

            $sDelAddress .= $oDelAddress->getEncodedDeliveryAddress();
        }

        return $sDelAddress;
    }

    /**
     * @param $oBasket
     * @return KustomOrder
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    protected function initKustomOrder($oBasket)
    {
        return oxNew(KustomOrder::class, $oBasket, $this->_oUser);
    }

    public function getPayment() {
        $oPayment = parent::getPayment();

        if (is_object($oPayment) && $oPayment->oxpayments__oxid->value != KustomPaymentModel::getKustomPaymentsId()) {
            $oPayment->assign(
                [
                    'oxdesc' => str_replace('Kustom ', '', $oPayment->getFieldData('oxdesc'))
                ]
            );
        }

        return $oPayment;
    }


    /**
     * @param $street_address
     * @return array
     */
    protected function getSplitAddress($street_address): array
    {
        if ($street_address) {
            $addressData = AddressSplitter::splitAddress($street_address);
        }

        $street = $addressData['streetName'] ?? '';
        $streetNo = $addressData['houseNumber'] ?? '';
        return array($street, $streetNo);
    }
}

<?php


namespace Fatchip\FcKustom\Controller;


use Fatchip\FcKustom\Core\KustomCheckoutClient;
use Fatchip\FcKustom\Core\KustomClientBase;
use Fatchip\FcKustom\Core\KustomFormatter;
use Fatchip\FcKustom\Core\KustomOrder;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Model\KustomPaymentHelper;
use Fatchip\FcKustom\Model\KustomUser;
use OxidEsales\Eshop\Application\Controller\BasketController;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;

class KustomAjaxController extends FrontendController
{

    /**
     * @var string
     */
    protected $_sThisTemplate = null;

    /** @var User|KustomUser */
    protected $_oUser;


    /** @var array */
    protected $_aOrderData;

    /** @var \Exception[] */
    protected $_aErrors;

    /**
     * @return void Return is only used in PHPUnit context
     * @throws StandardException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     * @throws \oxSystemComponentException
     */
    public function init()
    {
        $oSession = Registry::getSession();
        $oBasket  = $oSession->getBasket();

        if (KustomPaymentHelper::isKustomPayment($oBasket->getPaymentId())) {
            $oClient = $this->getKustomCheckoutClient();
            try {
                $this->_aOrderData = $oClient->getOrder();
            } catch (KustomClientException $oEx) {
                if ($oEx->getCode() == 401 || $oEx->getCode() == 404) {
                    // create new order. restart session.
                    return $this->jsonResponse(__FUNCTION__, 'restart needed', $data = null);
                }
            }

            if ($this->_aOrderData['status'] === 'checkout_complete'){
                return $this->jsonResponse('ajax', 'read_only');
            }

            $this->initUser();
            $this->updateUserObject();

        } else {
            return Registry::getUtils()->showMessageAndExit('Invalid payment ID');
        }

        parent::init();
    }

    /**
     * Updates Kustom API
     * @return null
     */
    public function render()
    {
        // request update kustom order if no errors
        if (!$this->_aErrors) {
            try {
                $this->updateKustomOrder();
            } catch (StandardException $e) {
                KustomUtils::logException($e);
            }
        }

        return parent::render();

    }

    /**
     * @return KustomCheckoutClient|KustomClientBase
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function getKustomCheckoutClient()
    {
        return KustomCheckoutClient::getInstance();
    }

    /**
     * Initialize oxUser object and get order data from Kustom
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \oxSystemComponentException
     */
    protected function initUser()
    {
        if ($this->_oUser = $this->getUser()) {
            if ($this->getViewConfig()->isUserLoggedIn()) {
                $this->_oUser->setType(KustomUser::LOGGED_IN);
            } else {
                $this->_oUser->setType(KustomUser::NOT_REGISTERED);
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
        if ($this->_oUser->isWritable()) {
            $this->_oUser->save();
        }

        $oBasket = Registry::getSession()->getBasket();
        $oBasket->setBasketUser($this->_oUser);
    }

    /**
     * Update User object
     */
    protected function updateUserObject()
    {
        // if the user is registered, we need the whole object not just the fake user to ensure no data is lost
        $paymentId = Registry::getRequest()->getRequestParameter("payment_id");
        $isExternalPayment = $paymentId && !KustomPaymentHelper::isKustomPayment($paymentId);
        if ($isExternalPayment && $this->_oUser->getType() === KustomUser::LOGGED_IN) {
            //reload the user by their email to get a clean object
            $mail = $this->_oUser->oxuser__oxusername->value;
            $this->_oUser = oxNew(User::class);
            $this->_oUser->loadByEmail($mail);
            // ensure user is always logged out
            Registry::getSession()->setVariable('blNeedLogout', true);
        }
        
        if ($this->_aOrderData['billing_address'] !== $this->_aOrderData['shipping_address']) {
            $this->_oUser->updateDeliveryAddress(KustomFormatter::kustomToOxidAddress($this->_aOrderData, 'shipping_address'));
        } else {
            $this->_oUser->clearDeliveryAddress();
        }

        $this->_oUser->assign(KustomFormatter::kustomToOxidAddress($this->_aOrderData, 'billing_address'));
        if (isset($this->_aOrderData['customer']['date_of_birth'])) {
            $this->_oUser->oxuser__oxbirthdate = new Field($this->_aOrderData['customer']['date_of_birth']);
        }
        if ($this->_oUser->isWritable() && $this->_oUser->oxuser__oxusername->value) {
            $this->_oUser->save();
        }
    }

    /**
     * Sends update request to checkout API
     * @return array order data
     * @throws StandardException
     * @internal param oxBasket $oBasket
     * @internal param oxUser $oUser
     */
    protected function updateKustomOrder()
    {
        $oSession     = Registry::getSession();
        $oBasket      = $oSession->getBasket();
        $oKustomOrder = oxNew(KustomOrder::class, $oBasket, $this->_oUser);
        $oClient      = $this->getKustomCheckoutClient();
        $aOrderData   = $oKustomOrder->getOrderData();

        return $oClient->createOrUpdateOrder(
            json_encode($aOrderData)
        );
    }

    public function setKustomDeliveryAddress()
    {
        $oxidAddress = Registry::get(Request::class)->getRequestEscapedParameter('kustom_address_id');
        Registry::getSession()->setVariable('deladrid', $oxidAddress);
        Registry::getSession()->setVariable('blshowshipaddress', 1);
        Registry::getSession()->deleteVariable('kustom_checkout_order_id');

        $this->_sThisTemplate = null;
    }

    /**
     * Add voucher
     *
     * @see Basket::addVoucher
     */
    public function addVoucher()
    {
        Registry::get(BasketController::class)->addVoucher();
        $this->updateVouchers();
    }

    /**
     * Remove voucher
     *
     * @see Basket::removeVoucher
     */
    public function removeVoucher()
    {
        Registry::get(BasketController::class)->removeVoucher();
        $this->updateVouchers();
    }

    /**
     * Sets partial templates to render
     * Rendered content will be return in json format in ajax response
     * and will replace document elements. This way vouchers widget will be updated
     */
    public function updateVouchers()
    {
        $this->_sThisTemplate = '@fckustom/checkout/inc/fckustom_json';
        $includes             = array(
            'vouchers' => '@fckustom/checkout/inc/fckustom_checkout_voucher_data',
            'error'    => '@fckustom/checkout/inc/fckustom_checkout_voucher_data',
        );
        $this->addTplParam('aIncludes', $includes);
    }

    /**
     * Formats Json response
     * @param $action
     * @param $status
     * @param $data
     * @return string
     */
    protected function jsonResponse($action, $status, $data = null)
    {
        return Registry::getUtils()->showMessageAndExit(json_encode(array(
            'action' => $action,
            'status' => $status,
            'data'   => $data,
        )));
    }
}
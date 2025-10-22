<?php


namespace Fatchip\FcKustom\Core;


use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Core\Exception\KustomOrderNotFoundException;
use Fatchip\FcKustom\Core\Exception\KustomOrderReadOnlyException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use OxidEsales\Eshop\Core\Registry;

class KustomCheckoutClient extends KustomClientBase

{
    const ORDERS_ENDPOINT = '/checkout/v3/orders/%s';

    /**
     * @var array
     * Let's save kustom checkout data after each request
     */
    protected $aOrder;

    /**
     * @var KustomOrder object
     */
    protected $_oKustomOrder;


    /**
     * @param $oKustomOrder
     * @return $this
     */
    public function initOrder(KustomOrder $oKustomOrder)
    {
        $this->_oKustomOrder = $oKustomOrder;

        return $this;
    }

    /**
     * Creates or Updates existing Kustom Order
     * Saves Kustom order_id to the session and keeps Kustom response in aOrder property
     * what allows us access html_snippet later
     * @param string $requestBody in json format
     * @return mixed
     */
    public function createOrUpdateOrder($requestBody = null)
    {
        if (!$requestBody)
            $requestBody = $this->formatOrderData();

        try {
            // update existing order
            return $this->postOrder($requestBody, $this->getOrderId());
        } catch (KustomOrderNotFoundException  $oEx) {
            /**
             * Try again with a new session ( no order id )
             */
            KustomUtils::logException($oEx);
            return $this->postOrder($requestBody);
        }
        return;
    }

    /**
     * @param $data
     * @param string $order_id
     * @return array|bool
     * @throws KustomOrderNotFoundException
     * @throws KustomOrderReadOnlyException
     * @throws KustomWrongCredentialsException
     * @throws \OxidEsales\Eshop\Core\Exception\StandardException
     * @throws \Exception
     * @throws KustomClientException
     */
    protected function postOrder($data, $order_id = '')
    {
        $oResponse = $this->post(sprintf(self::ORDERS_ENDPOINT, $order_id), $data);
        $this->logKustomData(
            $order_id === '' ? 'Create Order' : 'Update Order',
            $data,
            self::ORDERS_ENDPOINT,
            $oResponse->body,
            $order_id,
            $oResponse->status_code
        );

        $this->aOrder = $this->handleResponse($oResponse, __CLASS__, __FUNCTION__);

        Registry::getSession()->setVariable('kustom_checkout_order_id', $this->aOrder['order_id']);
        Registry::getSession()->setVariable('kustom_checkout_user_email', $this->aOrder['billing_address']['email']);

        return $this->aOrder;
    }

    /**
     * @param null $order_id
     * @return array
     * @throws KustomOrderNotFoundException
     * @throws KustomOrderReadOnlyException
     * @throws KustomWrongCredentialsException
     * @throws \Fatchip\FcKustom\Core\Exception\KustomClientException
     * @throws \OxidEsales\Eshop\Core\Exception\StandardException
     * @throws \Exception
     */
    public function getOrder($order_id = null)
    {
        if ($order_id === null) {
            $order_id = $this->getOrderId();
        }

        $oResponse = $this->get(sprintf(self::ORDERS_ENDPOINT, $order_id));

        $this->logKustomData(
            'Get Order',
            '',
            self::ORDERS_ENDPOINT,
            $oResponse->body,
            $order_id,
            $oResponse->status_code
        );

        $this->aOrder = $this->handleResponse($oResponse, __CLASS__, __FUNCTION__);
        Registry::getSession()->setVariable('kustom_checkout_user_email', $this->aOrder['billing_address']['email']);

        return $this->aOrder;
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        if (isset($this->aOrder)) {
            return $this->aOrder['order_id'];
        }

        return Registry::getSession()->getVariable('kustom_checkout_order_id') ?: '';
    }

    /**
     * @return bool|string
     */
    public function getHtmlSnippet()
    {
        if (isset($this->aOrder)) {
            return $this->aOrder['html_snippet'];
        }

        return false;
    }
}
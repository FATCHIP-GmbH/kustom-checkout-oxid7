<?php


namespace Fatchip\FcKustom\Controller;


use Fatchip\FcKustom\Core\KustomClientBase;
use Fatchip\FcKustom\Core\KustomOrderManagementClient;
use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;

/**
 * Controller for Kustom Checkout Acknowledge push request
 */
class KustomAcknowledgeController extends FrontendController
{
    protected $aOrder;

    /**
     * @codeCoverageIgnore
     * @param string $sCountryISO
     * @return KustomOrderManagementClient|KustomClientBase $kustomClient
     */
    protected function getKustomClient($sCountryISO)
    {
        return KustomOrderManagementClient::getInstance($sCountryISO);
    }

    public function init()
    {
        parent::init();

        $orderId = Registry::get(Request::class)->getRequestEscapedParameter('kustom_order_id');

        if (empty($orderId)) {
            $this->setValidResponseHeader(404, "Not found");
        }

        $this->registerKustomAckRequest($orderId);
        try {
            $oOrder     = $this->loadOrderByKustomId($orderId);
            $countryISO = KustomUtils::getCountryISO($oOrder->oxorder__oxbillcountryid->value);
            if ($oOrder->isLoaded()) {
                $this->getKustomClient($countryISO)->acknowledgeOrder($orderId);
            }
        } catch (StandardException $e) {
            KustomUtils::logException($e);

            $this->setValidResponseHeader(400, "Bad request");
        }

        $this->setValidResponseHeader(200, "OK");
    }

    /**
     * @codeCoverageIgnore
     * @param $orderId
     * @return Order
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    protected function loadOrderByKustomId($orderId)
    {
        return KustomUtils::loadOrderByKustomId($orderId);
    }


    /**
     * Register Kustom request in DB
     * @param $orderId
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function registerKustomAckRequest($orderId)
    {
        KustomUtils::registerKustomAckRequest($orderId);
    }

    /**
     * @codeCoverageIgnore
     * @param $responseStatus
     * @param $responseText
     * @return bool
     */
    protected function setValidResponseHeader($responseStatus, $responseText)
    {

        Registry::getUtils()->setHeader("HTTP/1.0 ".$responseStatus." ".$responseText);
        Registry::getUtils()->setHeader("Content-Type: text/html; charset=UTF-8");
        Registry::getUtils()->showMessageAndExit($responseText);

        return true;
    }
}
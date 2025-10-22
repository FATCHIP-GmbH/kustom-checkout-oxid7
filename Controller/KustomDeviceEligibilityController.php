<?php


namespace Fatchip\FcKustom\Controller;

use Fatchip\FcKustom\Model\KustomUser;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Registry;

class KustomDeviceEligibilityController extends FrontendController
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

    public function setKcoApplePayDeviceEligibility()
    {
        $isEligible = Registry::getRequest()->getRequestParameter("isEligible");

        $oSession = Registry::getSession();
        $oSession->setVariable("kcoApplePayDeviceEligible", $isEligible);

        $this->setValidResponseHeader(200, "OK");
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
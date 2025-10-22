<?php


namespace Fatchip\FcKustom\Controller;


use OxidEsales\Eshop\Application\Controller\FrontendController;

/**
 * Class KustomEpmDispatcher
 * @package Fatchip\FcKustom\Controllers
 */
class KustomEpmDispatcher extends FrontendController
{
    protected $_sThisTemplate = '@fckustom/checkout/incfckustom_amazon_login';

    /**
     * @throws \oxFileException
     * @throws \oxSystemComponentException
     */
    public function amazonLogin()
    {
        $oViewConf = $this->getViewConfig();

        /** @var AmazonViewConfig $oViewConf */
        $this->addTplParam('sAmazonWidgetUrl', $oViewConf->getAmazonProperty('sAmazonLoginWidgetUrl'));
        $this->addTplParam('sAmazonSellerId', $oViewConf->getAmazonConfigValue('sAmazonSellerId'));
        $this->addTplParam('sModuleUrl', $oViewConf->getModuleUrl('bestitamazonpay4oxid'));
    }
}
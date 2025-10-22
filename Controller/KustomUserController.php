<?php


namespace Fatchip\FcKustom\Controller;


use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Application\Component\UserComponent;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\ViewConfig;

class KustomUserController extends KustomUserController_parent
{
    /**
     *
     */
    public function init()
    {
        parent::init();

        if ($amazonOrderId = Registry::get(Request::class)->getRequestParameter('amazonOrderReferenceId')) {
            Registry::getSession()->setVariable('amazonOrderReferenceId', $amazonOrderId);
        }

        $sCountryISO = Registry::getSession()->getVariable('sCountryISO');

        if (KustomUtils::isCountryActiveInKustomCheckout($sCountryISO) &&
            !Registry::getSession()->hasVariable('amazonOrderReferenceId')
        ) {
            Registry::getUtils()->redirect(Registry::getConfig()->getShopSecureHomeUrl() .
                                           'cl=KustomExpress', false, 302);
        }

    }

    /**
     * @return mixed
     */
    public function getInvoiceAddress()
    {
        $result   = parent::getInvoiceAddress();
        $viewConf = Registry::get(ViewConfig::class);

        if (!$result && $viewConf->isCheckoutNonKustomCountry()) {
            $oCountry                      = oxNew(Country::class);
            $result['oxuser__oxcountryid'] = $oCountry->getIdByCode(Registry::getSession()->getVariable('sCountryISO'));
        }

        return $result;
    }

    /**
     *
     */
    public function kustomResetCountry()
    {
        $invadr = Registry::get(Request::class)->getRequestEscapedParameter('invadr');
        Registry::get(UserComponent::class)->changeuser();
        unset($invadr['oxuser__oxcountryid']);
        unset($invadr['oxuser__oxzip']);
        unset($invadr['oxuser__oxstreet']);
        unset($invadr['oxuser__oxstreetnr']);
        $invadr['oxuser__oxusername'] = Registry::get(Request::class)->getRequestParameter('lgn_usr');
        Registry::getSession()->setVariable('invadr', $invadr);
        KustomUtils::fullyResetKustomSession();

        $sUrl = Registry::getConfig()->getShopSecureHomeURL() . 'cl=KustomExpress&reset_kustom_country=1';
        Registry::getUtils()->showMessageAndExit($sUrl);
    }
}
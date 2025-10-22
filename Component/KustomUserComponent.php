<?php

namespace Fatchip\FcKustom\Component;


use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class KustomOxCmp_User user component
 *
 * @package Kustom
 * @extend OxCmp_User
 */
class KustomUserComponent extends KustomUserComponent_parent
{
    /**
     * Redirect to kustom express page from this classes
     *
     * @var array
     */
    protected $_aClasses = array(
        'user',
        'KustomExpress',
    );

    /**
     * Login user without redirection
     */
    public function login_noredirect()
    {
        parent::login_noredirect();

        Registry::getSession()->setVariable("iShowSteps", 1);

        KustomUtils::fullyResetKustomSession();
        Registry::getSession()->deleteVariable('sFakeUserId');
        if ($this->kustomRedirect()) {
            Registry::getUtils()->redirect(
                Registry::getConfig()->getShopSecureHomeUrl() . 'cl=KustomExpress',
                false
            );
        }
    }

    /**
     * Redirect to kustom checkout
     * @return bool
     */
    protected function kustomRedirect()
    {
        $sClass = Registry::get(Request::class)->getRequestEscapedParameter('cl');

        return in_array($sClass, $this->_aClasses);
    }


    protected function getLogoutLink()
    {
        if ($this->kustomRedirect()) {
            /** @var Config $oConfig */
            $oConfig     = Registry::getConfig();
            $sLogoutLink = $oConfig->isSsl() ? $oConfig->getShopSecureHomeUrl() : $oConfig->getShopHomeUrl();
            $sLogoutLink .= 'cl=' . 'basket' . $this->getParent()->getDynUrlParams();

            return $sLogoutLink . '&amp;fnc=logout';
        } else {
            return parent::getLogoutLink();
        }
    }

    public function changeUserWithoutRedirect()
    {
        if ($user = $this->getUser()) {
            $aInvAddress = Registry::getRequest()->getRequestEscapedParameter("invadr");

            if ((string)$user->oxuser__oxcompany->value !== (string)$aInvAddress["oxuser__oxcompany"]) {
                Registry::getSession()->setVariable("kustomB2BSessionWasChanged",true);
                KustomUtils::fullyResetKustomSession();
            }
        }

        return parent::changeUserWithoutRedirect();
    }

    /**
     * @return string
     */
    public function changeuser_testvalues()
    {
        $result = parent::changeuser_testvalues();
        if ($result === 'account_user') {

            Registry::getSession()->setVariable('resetKustomSession', 1);

            if (Registry::get(Request::class)->getRequestParameter('blshowshipaddress')) {
                Registry::getSession()->setVariable('blshowshipaddress', 1);
                Registry::getSession()->setVariable('deladrid', Registry::get(Request::class)->getRequestEscapedParameter('oxaddressid'));
            } else {
                Registry::getSession()->deleteVariable('deladrid');
            }
        }

        return $result;
    }
}

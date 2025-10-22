<?php

namespace Fatchip\FcKustom\Controller\Admin;

use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Application\Controller\Admin\ShopConfiguration;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\Shop;
use OxidEsales\Eshop\Core\Database\Adapter\Doctrine\Database;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;

class KustomBaseConfig extends ShopConfiguration
{
    /**
     * Request parameter container
     *
     * @var array
     */
    protected $_aParameters = array();

    /**
     * @var Request
     */
    protected $_oRequest;

    /** @var array kustom multilang config vars */
    protected $MLVars = array();

    /**
     * Sets parameter
     *
     * @param $sName
     * @param $sValue
     */
    public function setParameter($sName, $sValue)
    {
        $this->_aParameters[$sName] = $sValue;
    }

    /**
     * Return parameter from container
     *
     * @param $sName
     * @return string
     */
    public function getParameter($sName)
    {
        return $this->_aParameters[$sName];
    }

    public function init()
    {
        parent::init();
        $this->_oRequest = Registry::get(Request::class);
    }

    public function render()
    {
        parent::render();

        $this->addTplParam(
            'confaarrs',
            KustomUtils::getModuleSettingsAarrs($this->getViewDataElement('confaarrs'))
        );
        $this->addTplParam(
            'confbools',
            KustomUtils::getModuleSettingsBools($this->getViewDataElement('confbools'))
        );
        $this->addTplParam(
            'confstrs',
            KustomUtils::getModuleSettingsStrs($this->getViewDataElement('confstrs'))
        );
    }

    /**
     * Save configuration values
     *
     * @return void
     * @throws \Exception
     */
    public function
    save()
    {
        // Save parameters to container
        $this->fillContainer();
        $this->doSave();
    }

    /**
     * Fill parameter container with request values
     */
    protected function fillContainer()
    {
        foreach ($this->_aConfParams as $sType => $sParam) {
            if ($sType === 'aarr') {
                $this->setParameter($sParam,
                    $this->convertNestedParams(
                        Registry::get(Request::class)->getRequestEscapedParameter($sParam)
                    ));
            } else {
                $this->setParameter($sParam, Registry::get(Request::class)->getRequestEscapedParameter($sParam));
            }
        }
    }

    /**
     * @param $nestedArray
     * @return array
     */
    protected function convertNestedParams($nestedArray)
    {
        if (is_array($nestedArray)) {
            foreach ($nestedArray as $key => $arr) {
                /*** serialize all assoc arrays ***/
                $nestedArray[$key] = $this->aarrayToMultiline($arr);
            }
        }

        return $nestedArray;
    }

    /**
     * @param $aKeys
     * @return int
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     * @codeCoverageIgnore
     */
    protected function removeConfigKeys($aKeys)
    {
        /** @var Database $db */
        $db = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);

        $config = Registry::getConfig();
        $sql = "DELETE 
                FROM oxconfig
                WHERE oxvarname IN ('" . implode("','", $aKeys) . "')
                AND oxshopid = '{$config->getShopId()}'";

        return $db->execute($sql);
    }

    /**
     * Save vars as shop config does
     * @throws \Exception
     */
    private function doSave()
    {
        $this->performConfVarsSave();
        $sOxid = $this->getEditObjectId();

        //saving additional fields ("oxshops__oxdefcat") that goes directly to shop (not config)
        /** @var Shop $oShop */
        $oShop = oxNew(Shop::class);
        if ($oShop->load($sOxid)) {
            $oShop->assign(Registry::get(Request::class)->getRequestEscapedParameter("editval"));
            $oShop->save();
        }
    }

    /**
     * Shop config variable saving
     */
    private function performConfVarsSave()
    {
        $this->resetContentCache();
        foreach ($this->_aConfParams as $sType => $sParam) {
            $aConfVars = $this->getParameter($sParam);

            if (!is_array($aConfVars)) {
                continue;
            }

            $this->performConfVarsSave2($sType, $aConfVars);
        }
    }

    /**
     * Save config parameter
     *
     * @param $sConfigType
     * @param $aConfVars
     */
    protected function _performConfVarsSave($sConfigType, $aConfVars)
    {
        $myConfig = Registry::getConfig();
        $sShopId  = $this->getEditObjectId();
        $sModule  = $this->getModuleForConfigVars();

        foreach ($aConfVars as $sName => $sValue) {
            $oldValue = $myConfig->getConfigParam($sName);
            if ($sValue !== $oldValue) {
                $myConfig->saveShopConfVar(
                    $sConfigType,
                    $sName,
                    $this->serializeConfVar($sConfigType, $sName, $sValue),
                    $sShopId,
                    $sModule
                );
            }
        }
    }

    /**
     * Save config parameter
     *
     * @param $sConfigType
     * @param $aConfVars
     */
    protected function performConfVarsSave2($sConfigType, $aConfVars)
    {
        foreach ($aConfVars as $sName => $sValue) {
            if (str_contains($sName, '_')) {
                $aName = explode("_", $sName);
                //Change to ModuleSettingType associative array (aarr)
                $aName[0] = 'aarr' . substr($aName[0], 1);
                $aModulSettingVar = KustomUtils::getShopConfVar($aName[0]);
                $aModulSettingVar[$sName] = $this->serializeConfVar($sConfigType, $sName, $sValue);
                KustomUtils::saveShopConfVar($aName[0], $aModulSettingVar);
            } else {
                KustomUtils::saveShopConfVar($sName, $this->serializeConfVar($sConfigType, $sName, $sValue));
            }
        }
    }

    /**
     * @return string
     */
    protected function _getModuleForConfigVars()
    {
        return 'module:fckustom';
    }

    /**
     * @return \OxidEsales\Eshop\Core\Database\Adapter\ResultSetInterface
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    public function getAllActiveOxPaymentIds()
    {
        $db = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);

        $sql = 'SELECT oxid FROM oxpayments WHERE oxactive=1 AND oxid != "oxempty"';

        $result = $db->select($sql);

        return $result;
    }

    /**
     * @param string $oxid
     * @param bool|int $lang
     * @return mixed
     * @throws oxSystemComponentException
     */
    public function getPaymentData($oxid, $lang = false)
    {
        $lang      = $lang !== false ? $lang : $this->getViewDataElement('adminlang');
        $oxpayment = oxnew(Payment::class);
        $oxpayment->loadInLang($lang, $oxid);

        $result['oxid']                            = $oxid;
        $result['desc']                            = $oxpayment->oxpayments__oxdesc->value;
        $result['fckustom_externalname']           = $oxpayment->oxpayments__fckustom_externalname->value;
        $result['fckustom_externalpayment']        = $oxpayment->oxpayments__fckustom_externalpayment->value;
        $result['fckustom_externalcheckout']       = $oxpayment->oxpayments__fckustom_externalcheckout->value;
        $result['fckustom_paymentimageurl']        = $oxpayment->oxpayments__fckustom_paymentimageurl->value;
        $result['fckustom_checkoutimageurl']       = $oxpayment->oxpayments__fckustom_checkoutimageurl->value;
        $result['fckustom_paymentoption']          = $oxpayment->oxpayments__fckustom_paymentoption->value;
        $result['fckustom_emdpurchasehistoryfull'] = $oxpayment->oxpayments__fckustom_emdpurchasehistoryfull->value;
        $result['isCheckout']                      = preg_match('/([pP]ay[pP]al|[Aa]mazon)/', $result['desc']) == 1;
        $result['isExternalEnabled']               = $result['fckustom_externalpayment'] == 1 || $result['fckustom_externalcheckout'] == 1;

        return $result;
    }

    public function getLangs()
    {
        return htmlentities(json_encode(
            Registry::getLang()->getLanguageArray()
        ));
    }

    public function getFlippedLangArray()
    {
        $aLangs = Registry::getLang()->getLanguageArray();

        $return = array();
        foreach ($aLangs as $oLang) {
            $return[$oLang->abbr] = $oLang;
        }

        return $return;
    }

    protected function getMultiLangData()
    {
        $output = array();

        foreach ($this->MLVars as $fieldName) {
            foreach ($this->getViewDataElement('confstrs') as $name => $value) {
                if (str_starts_with($name, $fieldName)) {
                    $output['confstrs[' . $name . ']'] = $value;
                }
            }
        }

        return $output;
    }

}
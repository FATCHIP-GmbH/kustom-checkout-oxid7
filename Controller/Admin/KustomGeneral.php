<?php

namespace Fatchip\FcKustom\Controller\Admin;


use Fatchip\FcKustom\Core\KustomConsts;
use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\TableViewNameGenerator;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Application\Model\DeliverySetList;

/**
 * Class Kustom_Config for module configuration in OXID backend
 */
class KustomGeneral extends KustomBaseConfig
{

    protected $_sThisTemplate = '@fckustom/admin/fckustom_general';

    protected $_aKustomCountryCreds = array();

    protected $_aKustomCountries = array();

    /** @inheritdoc */
    protected $MLVars = ['sKustomAnonymizedProductTitle_','sKustomTermsConditionsURI_', 'sKustomCancellationRightsURI_', 'sKustomShippingDetails_'];

    /**
     * Render logic
     *
     * @see admin/oxAdminDetails::render()
     * @return string
     */
    public function render()
    {
        parent::render();
        // force shopid as parameter
        // Pass shop OXID so that shop object could be loaded
        $sShopOXID = Registry::getConfig()->getShopId();

        $this->setEditObjectId($sShopOXID);

        if(KustomUtils::is_ajax()){
            $output = $this->getMultiLangData();
            return Registry::getUtils()->showMessageAndExit(json_encode($output));
        }

        $this->addTplParam('fckustom_countryCreds', $this->getKustomCountryCreds());
        $this->addTplParam('fckustom_countryList', json_encode($this->getKustomCountryAssocList()));
        $this->addTplParam(
            'fckustom_notSetUpCountries',
            array_diff_key($this->_aKustomCountries, $this->_aKustomCountryCreds) ?: false
        );
        $this->addTplParam('b2options', array('B2C', 'B2B', 'B2C_B2B', 'B2B_B2C'));

        $this->addTplParam('kcoshippingmethods', $this->getShippingMethods());

        return $this->_sThisTemplate;
    }

    /**
     * @return array|false
     */
    public function getKustomCountryCreds()
    {
        if($this->_aKustomCountryCreds){
            return $this->_aKustomCountryCreds;
        }
        $this->_aKustomCountryCreds = array();
        foreach ($this->getViewDataElement('confaarrs') as $sKey => $serializedArray) {
            if (strpos($sKey, 'aKustomCreds_') === 0) {

                $this->_aKustomCountryCreds[substr($sKey, -2)] = $serializedArray;
            }
        }
        
        return $this->_aKustomCountryCreds ?: false;
    }

    protected function convertNestedParams($nestedArray)
    {
        /*** get Country Specific Credentials Config Keys for all Kustom Countries ***/
        $db  = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
        $config = Registry::getConfig();
        $sql = "SELECT oxvarname
                FROM oxconfig 
                WHERE oxvarname LIKE 'aKustomCreds_%'
                AND oxshopid = :shopId";
        $aCountrySpecificCredsConfigKeys = $db->getCol($sql, [':shopId' => $config->getShopId()]);

        if (is_array($nestedArray)) {
            foreach ($nestedArray as $key => $arr) {
                if (strpos($key, 'aKustomCreds_') === 0) {
                    /*** remove key from the list if present in POST data ***/
                    unset($aCountrySpecificCredsConfigKeys[array_search($key, $aCountrySpecificCredsConfigKeys)]);
                }
                /*** serialize all assoc arrays ***/
                $nestedArray[$key] = $this->aarrayToMultiline($arr);
            }
        }

        if ($aCountrySpecificCredsConfigKeys)
            /*** drop all keys that was not passed with POST data ***/
            $this->removeConfigKeys($aCountrySpecificCredsConfigKeys);

        return $nestedArray;
    }

    /**
     * @return mixed
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function getKustomCountryAssocList()
    {
        if ($this->_aKustomCountries) {
            return $this->_aKustomCountries;
        }
        $oTableViewNameGenerator = oxNew(TableViewNameGenerator::class);
        $sViewName = $oTableViewNameGenerator->getViewName(
            'oxcountry',
            $this->getViewDataElement('adminlang')
        );
        $isoList = KustomConsts::getKustomCoreCountries();

        /** @var QueryBuilderFactoryInterface $oQueryBuilderFactory */
        $oQueryBuilderFactory = $this->getQueryBuilder();
        $oQueryBuilder = $oQueryBuilderFactory->create();
        $oQueryBuilder
            ->select('oxisoalpha2, oxtitle')
            ->from($sViewName, 'c')
            ->where('oxisoalpha2 IN ("' . implode('","', $isoList) . '")')
            ->andWhere('oxactive = :oxactive')
            ->setParameter(':oxactive', 1);
        $aResult = $oQueryBuilder->execute();
        $aResult = $aResult->fetchAllAssociative();

        foreach($aResult as $aCountry){
            $this->_aKustomCountries[$aCountry['OXISOALPHA2']] = $aCountry['OXTITLE'];
        }

        return $this->_aKustomCountries;
    }

    public function getShippingMethods() {

        $list = Registry::get(DeliverySetList::class);
        $viewName = $list->getBaseObject()->getViewName();

        $sql = "
            select 
                $viewName.*
            from
                $viewName
            join
                oxobject2payment o2p 
                on $viewName.oxid = o2p.oxobjectid
                and o2p.oxtype = 'oxdelset'
            where 
                " . $list->getBaseObject()->getSqlActiveSnippet() . "
            order by oxpos"

        ;
        $list->selectString($sql);

        return $list;
    }

    protected function getQueryBuilder() {
        $oContainer = ContainerFactory::getInstance()->getContainer();
        /** @var QueryBuilderFactoryInterface $oQueryBuilderFactory */
        return $oContainer->get(QueryBuilderFactoryInterface::class);
    }
}
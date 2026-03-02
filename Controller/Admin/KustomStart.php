<?php

namespace Fatchip\FcKustom\Controller\Admin;


use OxidEsales\Eshop\Application\Model\CountryList;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class Kustom_Config for module configuration in OXID backend
 */
class KustomStart extends KustomBaseConfig
{

    protected $_sThisTemplate = '@fckustom/admin/fckustom_start';

    /**
     * Render logic
     *
     * @see admin/oxAdminDetails::render()
     * @return string
     */
    public function render()
    {
        // force shopid as parameter
        // Pass shop OXID so that shop object could be loaded
        $sShopOXID = Registry::getConfig()->getShopId();

        $this->setEditObjectId($sShopOXID);

        parent::render();
        $oCountryList = oxNew(CountryList::class);
        $oSupportedCountryList = $oCountryList->getKustomCountriesTitles(
            $this->getViewDataElement('adminlang')
        );

        $this->addTplParam('countries', $oSupportedCountryList);


        return $this->_sThisTemplate;
    }

    /**
     * @return string
     */
    public function getKustomModuleInfo()
    {
        /** @var Module $module */
        $module = oxNew(Module::class);
        $module->load('fckustom');

        $version     = $module->getInfo('version');

        return " VERSION " . $version;
    }
}
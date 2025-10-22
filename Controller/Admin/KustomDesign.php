<?php

namespace Fatchip\FcKustom\Controller\Admin;


use Fatchip\FcKustom\Core\KustomConsts;
use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class Kustom_Config for module configuration in OXID backend
 */
class KustomDesign extends KustomBaseConfig
{

    protected $_sThisTemplate = '@fckustom/admin/fckustom_design';

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

        if (KustomUtils::is_ajax()) {
            $output = $this->getMultiLangData();

            return Registry::getUtils()->showMessageAndExit(json_encode($output));
        }

        $from   = '/' . preg_quote('-', '/') . '/';
        $locale = preg_replace($from, '_', strtolower(oxNew(KustomConsts::class)->getLocale(true)), 1);

        $this->addTplParam('locale', $locale);

        return $this->_sThisTemplate;
    }
}
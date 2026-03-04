<?php


namespace Fatchip\FcKustom\Model;


use OxidEsales\Eshop\Core\DatabaseProvider;
use Fatchip\FcKustom\Core\KustomConsts;
use OxidEsales\Eshop\Core\TableViewNameGenerator;

class KustomCountryList extends KustomCountryList_parent
{
    /**
     * Selects and loads all active countries that are assigned to kustom_checkout
     * loads all active countries if none are assigned
     *
     * @param integer $iLang language
     * @param bool $filterKcoList
     */
    public function loadActiveKustomCheckoutCountries($iLang = null, $filterKcoList = true)
    {
        $sViewName = $this->getCountryViewName($iLang);
        $sSelect   = "SELECT {$sViewName}.oxid, {$sViewName}.oxtitle, {$sViewName}.oxisoalpha2 FROM {$sViewName}
                      JOIN oxobject2payment 
                      ON oxobject2payment.oxobjectid = {$sViewName}.oxid
                      WHERE oxobject2payment.oxpaymentid = 'kustom_checkout'
                      AND oxobject2payment.oxtype = 'oxcountry'
                      AND {$sViewName}.oxactive=1";
        $params = [];

        if($filterKcoList === true) {
            $sSelect.= " AND {$sViewName}.oxisoalpha2 IN (:countries)";
            $params[':countries'] = oxNew(KustomConsts::class)->getKustomGlobalCountries();
        }

        $this->selectString($sSelect, $params);

        if(!count($this)) {
            $sSelect = "SELECT {$sViewName}.oxid, {$sViewName}.oxtitle, {$sViewName}.oxisoalpha2 
                        FROM {$sViewName}
                        WHERE {$sViewName}.oxactive=1";

            $this->selectString($sSelect);
        }
    }

    /**
     * Selects and loads all active countries that are NOT Kustom Global countries
     *
     * @param integer $iLang language
     */
    public function loadActiveNonKustomCheckoutCountries($iLang = null)
    {
        $sViewName = $this->getCountryViewName($iLang);
        $sSelect   = "SELECT oxid, oxtitle, oxisoalpha2 FROM {$sViewName}
                      WHERE oxactive=1 
                      AND (
                      oxisoalpha2 NOT IN (:countries)
                      OR oxid NOT IN (SELECT oxobjectid FROM oxobject2payment WHERE oxpaymentid = 'kustom_checkout')
                      )
                      ORDER BY oxorder, oxtitle";
        $this->selectString($sSelect, [':countries' => oxNew(KustomConsts::class)->getKustomGlobalCountries()]);
    }

    /**
     * Selects and loads all active countries that are on Kustom's KCO Global list
     * @param null $iLang
     */
    public function loadActiveKCOGlobalCountries($iLang = null)
    {
        $sViewName = $this->getCountryViewName($iLang);
        $sSelect   = "SELECT {$sViewName}.oxid, {$sViewName}.oxtitle, {$sViewName}.oxisoalpha2 FROM {$sViewName}
                      WHERE {$sViewName}.oxactive=1 
                      AND {$sViewName}.oxisoalpha2 IN (:countries)";
        $this->selectString($sSelect, [':countries' => oxNew(KustomConsts::class)->getKustomGlobalCountries()]);
    }

    public function getKustomCountriesTitles($iLang)
    {
        $sViewName = $this->getCountryViewName($iLang);
        $sSelect   = "SELECT {$sViewName}.oxisoalpha2, {$sViewName}.oxtitle FROM {$sViewName}
            WHERE {$sViewName}.oxisoalpha2 IN (:countries)";

        $this->selectString($sSelect, [':countries' => oxNew(KustomConsts::class)->getKustomCoreCountries()]);
        $result = array();
        foreach($this as $country) {
            $result[$country->oxcountry__oxisoalpha2->value] = $country->oxcountry__oxtitle->value;
        }

        return $result;
    }

    public function loadActiveKustomCountriesByPaymentId($paymentId)
    {
        $paymentId = DatabaseProvider::getDb()->quote($paymentId);
        $sViewName = $this->getCountryViewName();
        $isoList   = oxNew(KustomConsts::class)->getKustomGlobalCountries();
        $isoList   = implode("','", $isoList);
        $sSelect   = "SELECT {$sViewName}.oxid, {$sViewName}.oxtitle, {$sViewName}.oxisoalpha2 FROM {$sViewName}
                      JOIN oxobject2payment 
                      ON oxobject2payment.oxobjectid = {$sViewName}.oxid
                      WHERE oxobject2payment.oxpaymentid = {$paymentId}
                      AND oxobject2payment.oxtype = 'oxcountry'
                      AND {$sViewName}.oxactive=1";

        $sSelect.= " AND {$sViewName}.oxisoalpha2 IN ('{$isoList}')";

        $this->selectString($sSelect);
    }

    protected function getCountryViewName($iLang = null)
    {
        $oTableViewNameGenerator = oxNew(TableViewNameGenerator::class);
        return $oTableViewNameGenerator->getViewName('oxcountry', $iLang);
    }
}
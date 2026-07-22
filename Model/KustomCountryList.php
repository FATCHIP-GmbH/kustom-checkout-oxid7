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
            $sSelect.= " AND {$sViewName}.oxisoalpha2 IN (";
            foreach (oxNew(KustomConsts::class)->getKustomGlobalCountries() as $iso) {
                if (!empty($params)) {
                    $sSelect .= ',';
                }
                $sSelect .= '?';
                $params[] = $iso;
            }
            $sSelect .= ")";
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
                      oxisoalpha2 NOT IN  (";
        $params = [];
        foreach (oxNew(KustomConsts::class)->getKustomGlobalCountries() as $iso) {
            if (!empty($params)) {
                $sSelect .= ',';
            }
            $sSelect .= '?';
            $params[] = $iso;
        }
        $sSelect .= ")
                      OR oxid NOT IN (SELECT oxobjectid FROM oxobject2payment WHERE oxpaymentid = 'kustom_checkout')
                      )
                      ORDER BY oxorder, oxtitle";
        $this->selectString($sSelect, $params);
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
                      AND {$sViewName}.oxisoalpha2 IN (";
        $params = [];
        foreach (oxNew(KustomConsts::class)->getKustomGlobalCountries() as $iso) {
            if (!empty($params)) {
                $sSelect .= ',';
            }
            $sSelect .= '?';
            $params[] = $iso;
        }
        $sSelect .= ')';
        $this->selectString($sSelect, $params);
    }


    protected function getCountryViewName($iLang = null)
    {
        $oTableViewNameGenerator = oxNew(TableViewNameGenerator::class);
        return $oTableViewNameGenerator->getViewName('oxcountry', $iLang);
    }
}
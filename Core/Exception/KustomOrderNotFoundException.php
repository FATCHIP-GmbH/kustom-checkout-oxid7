<?php


namespace Fatchip\FcKustom\Core\Exception;


use OxidEsales\Eshop\Core\Registry;

class KustomOrderNotFoundException extends KustomClientException
{
    public function __construct($sMessage = "not set", $iCode = 0, \Exception $previous = null)
    {
        if($sMessage){
            $sMessage = sprintf(Registry::getLang()->translateString("KUSTOM_ORDER_NOT_FOUND"), $iCode);
        }
        parent::__construct($sMessage, $iCode = 0, $previous = null);
    }
}
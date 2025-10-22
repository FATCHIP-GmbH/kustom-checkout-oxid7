<?php


namespace Fatchip\FcKustom\Controller;


use OxidEsales\Eshop\Core\Registry as oxRegistry;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\UtilsView;

/**
 * Class KustomBasketController
 * @package Fatchip\FcKustom\Controllers
 */
class KustomBasketController extends KustomBasketController_parent
{
    /**
     * Rendering template
     *
     * @return mixed
     */
    public function render()
    {
        if(oxRegistry::get(Request::class)->getRequestEscapedParameter('openAmazonLogin')){
            $this->addTplParam('openAmazonLogin', true);
        }

        $oSession = oxRegistry::getSession();
        $oBasket = $oSession->getBasket();
        $kustomInvalid = oxRegistry::get(Request::class)->getRequestEscapedParameter('kustomInvalid');
        if($oBasket->getPaymentId() === 'kustom_checkout' && $kustomInvalid){
            $this->displayKustomValidationErrors();
        }

        return parent::render();
    }

    /**
     *
     * @codeCoverageIgnore
     */
    protected function displayKustomValidationErrors()
    {
        parse_str($_SERVER['QUERY_STRING'], $query);

        $oLang          = oxRegistry::getLang();
        foreach($query as $errorId => $articleId){
            if(strstr($errorId, 'ERROR')){
                oxRegistry::get(UtilsView::class)->addErrorToDisplay(
                    sprintf($oLang->translateString($errorId), $articleId)
                );
            }
        }
    }
}
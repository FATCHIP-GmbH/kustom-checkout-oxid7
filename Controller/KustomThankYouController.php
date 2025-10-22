<?php


namespace Fatchip\FcKustom\Controller;


use Fatchip\FcKustom\Core\KustomCheckoutClient;
use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Model\KustomInstantBasket;

/**
 * Class KustomThankYouController
 * @package Fatchip\FcKustom\Controller
 *
 * @extends \OxidEsales\Eshop\Application\Controller\ThankYouController
 * @property $_oBasket
 */
class KustomThankYouController extends KustomThankYouController_parent
{
    /** @var KustomCheckoutClient */
    protected $client;

    /**
     * @return mixed
     */
    public function render()
    {
        $render = parent::render();

        if ($sKustomId = Registry::getSession()->getVariable('kustom_checkout_order_id')) {
            $oOrder = Registry::get(Order::class);
            $oOrder->loadByKustomId($sKustomId);
            if ($oOrder->isLoaded()) {
                $this->loadClient($oOrder);
                try {
                    $this->client->getOrder($sKustomId);

                } catch (KustomClientException $e) {
                    KustomUtils::logException($e);
                }
                // add kustom confirmation snippet
                $this->addTplParam("klOrder", $oOrder);
                $this->addTplParam("sKustomIframe", $this->client->getHtmlSnippet());
            }
        }

        KustomUtils::fullyResetKustomSession();

        return $render;
    }

    protected function loadClient($oOrder) {
        if(!$this->client){
            $this->client = KustomCheckoutClient::getInstance(
                KustomUtils::getCountryISO($oOrder->getFieldData('oxbillcountryid'))
            );
        }
    }
}
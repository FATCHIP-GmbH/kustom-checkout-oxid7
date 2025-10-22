<?php

namespace Fatchip\FcKustom\Controller;

use OxidEsales\Eshop\Core\Registry;

class KustomPPProxyController extends KustomPPProxyController_parent
{
    public function createOrder(): void
    {
        $session = Registry::getSession();
        $basket = $session->getBasket();

        // Kustom initiates the basket with an empty user.
        // PayPal does not find this empty user in the Express flow and throws an error.
        // So for PP Express to work after having initiated KCO, the user needs to be unset.
        $basketUser = $basket->getBasketUser();
        if (is_object($basketUser) && !$basketUser->oxuser__oxactive->value) {
            $basket->kustomDeleteUserFromBasket();
        }

        parent::createOrder();
    }
}
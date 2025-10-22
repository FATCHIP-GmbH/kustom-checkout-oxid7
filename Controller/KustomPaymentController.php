<?php

namespace Fatchip\FcKustom\Controller;

use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Core\Registry;

class KustomPaymentController extends KustomPaymentController_parent
{
    public function render()
    {
        if ($this->shouldRedirectToKustomExpress()) {
            $this->redirectToKustomExpress();
        }

        return parent::render();
    }

    /**
     * Determines if the user should be redirected to KustomExpress.
     *
     * Redirect when:
     * - no logged-in user
     * - missing or invalid country
     * - country active in Kustom Checkout
     */
    protected function shouldRedirectToKustomExpress(): bool
    {
        $user = $this->getUser();
        if (!$user) {
            return true;
        }

        $countryId = (string) $user->getFieldData('oxcountryid');
        if ($countryId === '') {
            return true;
        }

        /** @var Country $country */
        $country = oxNew(Country::class);
        if (!$country->load($countryId)) {
            return true;
        }

        $iso2 = (string) $country->getFieldData('oxisoalpha2');
        return KustomUtils::isCountryActiveInKustomCheckout($iso2);
    }

    protected function redirectToKustomExpress(): void
    {
        $url = Registry::getConfig()->getShopSecureHomeUrl() . 'cl=KustomExpress';
        Registry::getUtils()->redirect($url, false);
    }
}
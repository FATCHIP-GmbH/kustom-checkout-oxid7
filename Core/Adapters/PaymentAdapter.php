<?php


namespace Fatchip\FcKustom\Core\Adapters;

/**
 * Class PaymentAdapter
 * @package Fatchip\FcKustom\Core\Adapters
 *
 * Adapter stub, required to skip empty payment cost appended to basket by default.
 * Requires implementation if Adapters abstraction will be used with other Kustom services (KCO and KP)
 */
class PaymentAdapter extends BasketCostAdapter
{
    /**
     * @codeCoverageIgnore
     */
    protected function getName()
    {
        return null;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getReference()
    {
        return '';
    }

}
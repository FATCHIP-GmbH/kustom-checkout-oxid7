<?php


namespace Fatchip\FcKustom\Core\Adapters;


use Fatchip\FcKustom\Core\Exception\InvalidItemException;

class VoucherAdapter extends BaseBasketItemAdapter
{
    /**
     * Compares Kustom Order Line to oxid basket
     * object
     * @param $orderLine
     * @throws InvalidItemException
     */
    public function validateItem($orderLine)
    {
        $this->validateData(
            $orderLine,
            'total_amount',
            - $this->formatAsInt(   // Voucher is transferred to Kustom as negative value
                $this->formatPrice(
                    $this->oItem->dVoucherdiscount
                )
            )
        );
    }

    /**
     * Prepares item data before adding it to Order Lines
     * @param $iLang
     * @return $this
     */
    protected function prepareItemData($iLang)
    {
        $quantity = 1;
        $taxRate = $this->formatAsInt($this->oBasket->getAdditionalServicesVatPercent());
        $unitPrice = - $this->formatAsInt(
            $this->formatPrice(
                $this->oItem->dVoucherdiscount
            )
        );
        $this->itemData['type'] = $this->getKustomType();
        $this->itemData['reference'] =  $this->oItem->sVoucherId;
        $this->itemData['name'] = $this->oItem->sVoucherNr;
        $this->itemData['quantity'] = $quantity;
        $this->itemData['total_amount'] = $unitPrice * $quantity;
        $this->itemData['total_discount_amount'] = 0;
        $this->itemData['total_tax_amount'] = $this->calcTax($this->itemData['total_amount'], $taxRate);
        $this->itemData['unit_price'] = $unitPrice;
        $this->itemData['tax_rate'] = $taxRate;
        $this->itemData = array_merge(static::DEFAULT_ITEM_DATA, $this->itemData);

        return $this;
    }

    public function getReference()
    {
        if(isset($this->itemData['reference'])) {
            return $this->itemData['reference'];
        }

        return $this->oItem->sVoucherId;
    }
}
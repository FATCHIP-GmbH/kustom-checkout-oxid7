<?php


namespace Fatchip\FcKustom\Model;


use OxidEsales\Eshop\Application\Model\Discount;
use OxidEsales\Eshop\Application\Model\User;
use Fatchip\FcKustom\Core\KustomUtils;
use Fatchip\FcKustom\Core\Exception\KustomBasketTooLargeException;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\BasketItem;
use OxidEsales\Eshop\Application\Model\DeliveryList;
use OxidEsales\Eshop\Application\Model\DeliverySet;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Price;
use OxidEsales\Eshop\Core\Registry;


/**
 * OXID default class oxBasket extensions to add Kustom related logic
 */
class KustomBasket extends KustomBasket_parent
{
    /**
     * Array of articles that have long delivery term so Kustom cannot be used to pay for them
     *
     * @var array
     */
    protected $_aPreorderArticles = array();

    /**
     * @var string
     */
    protected $_orderHash = '';

    /**
     * Checkout configuration
     * @var array
     */
    protected $_aCheckoutConfig;

    /**
     * Kustom Order Lines
     * @var array
     */
    protected $kustomOrderLines;

    /**
     * @var int
     */
    protected $kustomOrderLang;

    /**
     * Format products for Kustom checkout
     *
     * @param bool $orderMgmtId
     * @return array
     * @throws KustomBasketTooLargeException
     * @throws \OxidEsales\Eshop\Core\Exception\ArticleInputException
     * @throws \OxidEsales\Eshop\Core\Exception\NoArticleException
     * @internal param $orderData
     */
    public function getKustomOrderLines($orderMgmtId = null)
    {
        $this->calculateBasket(true);
        $this->kustomOrderLines = array();
        $this->calcItemsPrice();

        $iOrderLang = $this->getOrderLang($orderMgmtId);

        $aItems = $this->getContents();
        usort($aItems, array($this, 'sortOrderLines'));

        $counter = 0;
        /* @var BasketItem $oItem */
        foreach ($aItems as $oItem) {
            $counter++;

            list($quantity,
                $regular_unit_price,
                $total_amount,
                $total_discount_amount,
                $tax_rate,
                $total_tax_amount,
                $quantity_unit) = KustomUtils::calculateOrderAmountsPricesAndTaxes($oItem, $orderMgmtId);

            /** @var Article | BasketItem | KustomArticle $oArt */
            $oArt = $oItem->getArticle();
            if (!$oArt instanceof Article) {
                $oArt = $oArt->getArticle();
            }

            if ($iOrderLang) {
                $oArt->loadInLang($iOrderLang, $oArt->getId());
            }

            $aProcessedItem = array(
                "type"             => "physical",
                'reference'        => $this->getArtNum($oArt),
                'quantity'         => $quantity,
                'unit_price'       => $regular_unit_price,
                'tax_rate'         => $tax_rate,
                "total_amount"     => $total_amount,
                "total_tax_amount" => $total_tax_amount,
            );

            if ($quantity_unit !== '') {
                $aProcessedItem["quantity_unit"] = $quantity_unit;
            }

            if ($total_discount_amount !== 0) {
                $aProcessedItem["total_discount_amount"] = $total_discount_amount;
            }

            $aProcessedItem['name'] = $oArt->fcKustom_getOrderArticleName($counter, $iOrderLang);
            if (KustomUtils::getShopConfVar('blKustomEnableAnonymization') === false) {
                $aProcessedItem['product_url']         = $oArt->fcKustom_getArticleUrl();
                $aProcessedItem['image_url']           = $oArt->fcKustom_getArticleImageUrl();
                $aProcessedItem['product_identifiers'] = array(
                    'category_path'            => $oArt->fcKustom_getArticleCategoryPath(),
                    'global_trade_item_number' => $oArt->fcKustom_getArticleEAN(),
                    'manufacturer_part_number' => $oArt->fcKustom_getArticleMPN(),
                    'brand'                    => $oArt->fcKustom_getArticleManufacturer(),
                );
            }

            $this->kustomOrderLines[] = $aProcessedItem;
        }

        $this->_addServicesAsProducts($orderMgmtId);
        $this->_orderHash = md5(json_encode($this->kustomOrderLines));

        $totals = $this->calculateTotals($this->kustomOrderLines);

        $aOrderLines = array(
            'order_lines'      => $this->kustomOrderLines,
            'order_amount'     => $totals['total_order_amount'],
            'order_tax_amount' => $totals['total_order_tax_amount'],
        );

        $this->_orderHash = md5(json_encode($aOrderLines));

        return $aOrderLines;
    }

    /**
     * Active user getter
     *
     * @return User
     */
    public function getUser()
    {
        $kustomFakeUsername = Registry::getSession()->getVariable('kustom_checkout_user_email');

        if (!$kustomFakeUsername) {
            return parent::getUser();
        }

        if ($user = parent::getUser()) {
            return $user;
        }

        return KustomUtils::getFakeUser($kustomFakeUsername);
    }

    protected function getOrderLang($orderMgmtId)
    {
        $iOrderLang = null;
        if ($orderMgmtId) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($orderMgmtId);
            $iOrderLang = $oOrder->getFieldData('oxlang');
        }

        return $iOrderLang;
    }

    /**
     * @param $aProcessedItems
     * @return array
     * @throws KustomBasketTooLargeException
     */
    protected function calculateTotals($aProcessedItems)
    {
        $total_order_amount = $total_order_tax_amount = 0;
        foreach ($aProcessedItems as $item) {
            $total_order_amount     += $item['total_amount'];
            $total_order_tax_amount += $item['total_tax_amount'];
        }

        if ($total_order_amount > 100000000) {
            throw new KustomBasketTooLargeException('FCKUSTOM_ORDER_AMOUNT_TOO_HIGH');
        }

        return array(
            'total_order_amount'     => $total_order_amount,
            'total_order_tax_amount' => $total_order_tax_amount,
        );
    }


    /**
     * Add OXID additional payable services as products to array
     * @param bool $orderMgmtId
     * @return void
     */
    protected function _addServicesAsProducts($orderMgmtId = false)
    {
        $iLang  = null;
        $oOrder = null;
        if ($orderMgmtId) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($orderMgmtId);
            $iLang = $oOrder->getFieldData('oxlang');
        }

        if ($oOrder) {
            $oDelivery    = parent::getCosts('oxdelivery');
            $oDeliverySet = oxNew(DeliverySet::class);
            if ($iLang) {
                $oDeliverySet->loadInLang($iLang, $this->getShippingId());
            } else {
                $oDeliverySet->load($this->getShippingId());
            }

            $this->kustomOrderLines[] = $this->getKustomPaymentDelivery($oDelivery, $oOrder, $oDeliverySet);
        }
        $this->_addDiscountsAsProducts($oOrder, $iLang);
        $this->_addGiftWrappingCost($iLang);
        $this->_addGiftCardProducts($iLang);
    }

    /**
     * @param null $iLang
     */
    protected function _addGiftWrappingCost($iLang = null)
    {
        /** @var \OxidEsales\Eshop\Core\Price $oWrappingCost */
        $oWrappingCost = $this->getWrappingCost();
        if (($oWrappingCost && $oWrappingCost->getPrice())) {
            $unit_price = KustomUtils::parseFloatAsInt($oWrappingCost->getBruttoPrice() * 100);

            if (!$this->is_fraction($this->getOrderVatAverage())) {
                $tax_rate = KustomUtils::parseFloatAsInt($this->getOrderVatAverage() * 100);
            } else {
                $tax_rate = KustomUtils::parseFloatAsInt($oWrappingCost->getVat() * 100);
            }

            $this->kustomOrderLines[] = array(
                'type'                  => 'physical',
                'reference'             => 'SRV_WRAPPING',
                'name'                  => html_entity_decode(Registry::getLang()->translateString('FCKUSTOM_GIFT_WRAPPING_TITLE', $iLang), ENT_QUOTES),
                'quantity'              => 1,
                'total_amount'          => $unit_price,
                'total_discount_amount' => 0,
                'total_tax_amount'      => KustomUtils::parseFloatAsInt(round($oWrappingCost->getVatValue() * 100, 0)),
                'unit_price'            => $unit_price,
                'tax_rate'              => $tax_rate,
            );
        }
    }

    /**
     * @param null $iLang
     */
    protected function _addGiftCardProducts($iLang = null)
    {
        /** @var \OxidEsales\Eshop\Core\Price $oWrappingCost */
        $oGiftCardCost = $this->getCosts('oxgiftcard');
        if (($oGiftCardCost && $oGiftCardCost->getPrice())) {
            $unit_price = KustomUtils::parseFloatAsInt($oGiftCardCost->getBruttoPrice() * 100);
            $tax_rate   = KustomUtils::parseFloatAsInt($oGiftCardCost->getVat() * 100);

            $this->kustomOrderLines[] = array(
                'type'                  => 'physical',
                'reference'             => 'SRV_GIFTCARD',
                'name'                  => html_entity_decode(Registry::getLang()->translateString('FCKUSTOM_GIFT_CARD_TITLE', $iLang), ENT_QUOTES),
                'quantity'              => 1,
                'total_amount'          => $unit_price,
                'total_discount_amount' => 0,
                'total_tax_amount'      => KustomUtils::parseFloatAsInt($unit_price - round($unit_price / ($tax_rate / 10000 + 1), 0)),
                'unit_price'            => $unit_price,
                'tax_rate'              => $tax_rate,
            );
        }
    }

    /**
     * Add OXID additional discounts as products to array
     * @param null $oOrder
     * @param null $iLang
     * @return void
     */
    protected function _addDiscountsAsProducts($oOrder = null, $iLang = null)
    {
        $oDiscount = $this->getVoucherDiscount();
        if ($this->isServicePriceSet($oDiscount)) {
            $this->kustomOrderLines[] = $this->_getKustomCheckoutVoucherDiscount($oDiscount, $iLang);
        }

        $oDiscount = $this->getOxDiscount();
        if ($oOrder) {
            $oDiscount = oxNew(Price::class);
            $oDiscount->setBruttoPriceMode();

            $oDiscount->setPrice($oOrder->getFieldData('oxdiscount'));
        }
        if ($this->isServicePriceSet($oDiscount)) {
            $taxInfo = [];
            foreach ($this->kustomOrderLines as $orderLine) {
                $taxInfo[$orderLine['tax_rate']] += $orderLine['unit_price'];
            }

            if(count($taxInfo) > 1) {
                $kustomDiscounts = $this->buildDiscounts();

                if(!empty($kustomDiscounts)) {
                    foreach ($kustomDiscounts as $discount) {
                        foreach ($taxInfo as $taxRate => $unitPrice) {
                            $this->kustomOrderLines[] = $this->_getKustomCheckoutDiscount($oDiscount, $iLang,
                                ['taxRate' => $taxRate, 'unitPrice' => $unitPrice, 'discount' => $discount]);
                        }
                    }
                }
            } else {
                $this->kustomOrderLines[] = $this->_getKustomCheckoutDiscount($oDiscount, $iLang);
            }
        }
    }

    /**
     * @codeIgnoreCoverage
     * @return array
     */
    protected function buildDiscounts()
    {
        $discounts = $this->getDiscounts();
        $kustomDiscounts = [];
        if (!is_array($discounts)) {
            $discount = oxNew(Discount::class);
            $discount->load($discounts->sOXID);
            if($discount->isLoaded()){
                $kustomDiscounts[] = $discount;
            }
            return $kustomDiscounts;
        }

        foreach ($discounts as $discount) {
            if ($discount->sType == 'itm') {
                continue;
            }

            $discountobj = oxNew(Discount::class);
            $discountobj->load($discount->sOXID);
            if($discountobj->isLoaded()){
                $kustomDiscounts[] = $discountobj;
            }
        }

        return $kustomDiscounts;
    }

    /**
     * Check if service is set and has brutto price
     * @param $oService
     *
     * @return bool
     */
    protected function isServicePriceSet($oService)
    {
        return ($oService && $oService->getBruttoPrice() != 0);
    }

    /**
     * Returns delivery costs
     * @return Price
     */
    protected function getOxDiscount()
    {
        $totalDiscount = oxNew(Price::class);
        $totalDiscount->setBruttoPriceMode();
        $discounts = $this->getDiscounts();

        if (!is_array($discounts)) {
            return $totalDiscount;
        }

        foreach ($discounts as $discount) {
            if ($discount->sType == 'itm') {
                continue;
            }
            $totalDiscount->add($discount->dDiscount);
        }

        return $totalDiscount;
    }

    /**
     * Create kustom checkout product from delivery price
     *
     * @param Price $oPrice
     *
     * @param bool $oOrder
     * @param DeliverySet $oDeliverySet
     * @return array
     */
    public function getKustomPaymentDelivery(Price $oPrice, $oOrder = null, DeliverySet $oDeliverySet = null)
    {
        $unit_price = KustomUtils::parseFloatAsInt($oPrice->getBruttoPrice() * 100);
        $tax_rate   = KustomUtils::parseFloatAsInt($oPrice->getVat() * 100);

        $aItem = array(
            'type'                  => 'shipping_fee',
            'reference'             => 'SRV_DELIVERY',
            'name'                  => html_entity_decode($oDeliverySet->getFieldData('oxtitle'), ENT_QUOTES),
            'quantity'              => 1,
            'total_amount'          => $unit_price,
            'total_discount_amount' => 0,
            'total_tax_amount'      => KustomUtils::parseFloatAsInt($unit_price - round($unit_price / ($tax_rate / 10000 + 1), 0)),
            'unit_price'            => $unit_price,
            'tax_rate'              => $tax_rate,
        );

        if ($oOrder && $oOrder->isKCO()) {
            $aItem['reference'] = $oOrder->getFieldData('oxdeltype');
        }

        return $aItem;
    }

    /**
     * Create kustom checkout product from voucher discounts
     *
     * @param Price $oPrice
     * @param null $iLang
     * @return array
     */
    protected function _getKustomCheckoutVoucherDiscount(Price $oPrice, $iLang = null)
    {
        $unit_price = -KustomUtils::parseFloatAsInt($oPrice->getBruttoPrice() * 100);
        $tax_rate   = KustomUtils::parseFloatAsInt($this->_oProductsPriceList->getProportionalVatPercent() * 100);

        $aItem = array(
            'type'             => 'discount',
            'reference'        => 'SRV_COUPON',
            'name'             => html_entity_decode(Registry::getLang()->translateString('FCKUSTOM_VOUCHER_DISCOUNT', $iLang), ENT_QUOTES),
            'quantity'         => 1,
            'total_amount'     => $unit_price,
            'unit_price'       => $unit_price,
            'tax_rate'         => $tax_rate,
            'total_tax_amount' => KustomUtils::parseFloatAsInt($unit_price - round($unit_price / ($tax_rate / 10000 + 1), 0)),
        );

        return $aItem;
    }

    /**
     * Create kustom checkout product from non voucher discounts
     *
     * @param Price $oPrice
     * @param null $iLang
     * @param null $taxInfo
     * @return array
     */
    protected function _getKustomCheckoutDiscount(Price $oPrice, $iLang = null, $taxInfo = null)
    {
        $value = $oPrice->getBruttoPrice();
        $type = 'discount';
        $reference = 'SRV_DISCOUNT';
        $name = html_entity_decode(Registry::getLang()->translateString('FCKUSTOM_DISCOUNT_TITLE', $iLang), ENT_QUOTES);
        $unit_price = -KustomUtils::parseFloatAsInt( $value * 100);
        $tax_rate   = KustomUtils::parseFloatAsInt($this->getOrderVatAverage() * 100);
        if ($value < 0) {
            $type = 'surcharge';
            $reference = 'SRV_SURCHARGE';
            $name = html_entity_decode(Registry::getLang()->translateString('FCKUSTOM_SURCHARGE_TITLE', $iLang), ENT_QUOTES);
        } elseif(!empty($taxInfo) && $taxInfo['unitPrice'] && is_object($taxInfo['discount'])) {
            $unit_price = -$taxInfo['discount']->getAbsValue($taxInfo['unitPrice']);
            $tax_rate = $taxInfo['taxRate'];
        }

        $aItem = array(
            'type'             => $type,
            'reference'        => $reference,
            'name'             => $name,
            'quantity'         => 1,
            'total_amount'     => $unit_price,
            'unit_price'       => $unit_price,
            'tax_rate'         => $tax_rate,
            'total_tax_amount' => KustomUtils::parseFloatAsInt($unit_price - round($unit_price / ($tax_rate / 10000 + 1), 0)),
        );

        return $aItem;
    }


    /**
     * Original OXID method calcDeliveryCost
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function fcKustom_calculateDeliveryCost()
    {
        $oDeliveryList = oxNew(DeliveryList::class);
        Registry::set(DeliveryList::class, $oDeliveryList);

        return parent::calcDeliveryCost();
    }

    /**
     * Get average of order VAT
     * @return float
     */
    protected function getOrderVatAverage()
    {
        $vatAvg = ($this->getBruttoSum() / $this->getProductsNetPriceWithoutDiscounts() - 1) * 100;

        return number_format($vatAvg, 2);
    }

    /**
     * Returns sum of product netto prices
     * @return float
     */
    protected function getProductsNetPriceWithoutDiscounts()
    {
        $nettoSum = 0;

        if (!empty($this->_aBasketContents)) {
            foreach ($this->_aBasketContents as $oBasketItem) {
                $nettoSum += $oBasketItem->getPrice()->getNettoPrice();
            }
        }

        return $nettoSum;
    }

    /**
     * @param $oArt
     * @return bool|null|string
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function getArtNum($oArt)
    {
        $original = $oArt->oxarticles__oxartnum->value;
        if (KustomUtils::getShopConfVar('blKustomEnableAnonymization')) {
            $hash = md5($original);
            if (KustomUtils::getShopConfVar('iKustomValidation') != 0) {
                $this->addKustomAnonymousMapping($oArt->getId(), $hash);
            }

            return $hash;
        }

        return substr($original, 0, 64);
    }

    /**
     * @param $val
     * @return bool
     */
    protected function is_fraction($val)
    {
        return is_numeric($val) && fmod($val, 1);
    }

    /**
     * @codeIgnoreCoverage
     * @param $iLang
     */
    public function setKustomOrderLang($iLang)
    {
        $this->kustomOrderLang = $iLang;
    }

    /**
     * @param BasketItem $a
     * @param BasketItem $b
     * @return int
     * @throws \oxArticleInputException
     * @throws \oxNoArticleException
     * @throws \oxSystemComponentException
     */
    protected function sortOrderLines(BasketItem $a, BasketItem $b)
    {
        $oArtA = $a->getArticle();
        if (!$oArtA instanceof Article) {
            $oArtA = $oArtA->getArticle();
        }
        $oArtB = $b->getArticle();
        if (!$oArtB instanceof Article) {
            $oArtB = $oArtB->getArticle();
        }
        if (round(hexdec($oArtA->getId()), 3) > round(hexdec($oArtB->getId()), 3)) {
            return 1;
        } else if (round(hexdec($oArtA->getId()), 3) < round(hexdec($oArtB->getId()), 3)) {
            return -1;
        }

        return 0;
    }

    /**
     * @param $artOxid
     * @param $anonArtNum
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function addKustomAnonymousMapping($artOxid, $anonArtNum)
    {
        $db = DatabaseProvider::getDb();

        $sql = "INSERT IGNORE INTO fckustom_anon_lookup(fckustom_artnum, oxartid) values(?,?)";
        $db->execute($sql, array($anonArtNum, $artOxid));
    }

    /**
     * @codeCoverageIgnore
     * Check if vouchers are still valid. Usually used in the ajax requests
     */
    public function kustomValidateVouchers()
    {
        $this->_calcVoucherDiscount();
    }

    public function kustomDeleteUserFromBasket()
    {
        $this->_oUser = null;
    }
}
<?php

namespace Fatchip\FcKustom\Controller\Admin;


use Fatchip\FcKustom\Model\KustomPaymentHelper;

/**
 * Class Kustom_Order_Address
 */
class KustomOrderAddress extends KustomOrderAddress_parent
{
    /**
     * Executes parent method parent::render(), creates oxorder and
     * oxuserpayment objects, passes data to Smarty engine and returns
     * name of template file "order_main.tpl".
     *
     * @return string
     */
    public function render()
    {
        $parentOutput = parent::render();

        $order = $this->getViewDataElement('edit');
        $this->setReadonlyValue($order->oxorder__oxpaymenttype->value);

        return $parentOutput;
    }

    /**
     * @param string $paymentId
     */
    protected function setReadonlyValue($paymentId)
    {
        $this->addTplParam('readonly', KustomPaymentHelper::isKustomPayment( $paymentId ));
    }
}

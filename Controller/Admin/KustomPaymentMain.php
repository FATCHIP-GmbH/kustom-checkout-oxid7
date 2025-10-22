<?php

namespace Fatchip\FcKustom\Controller\Admin;


use Fatchip\FcKustom\Model\KustomPaymentHelper;

class KustomPaymentMain extends KustomPaymentMain_parent
{
    public function render()
    {
        $isKustomPayment = KustomPaymentHelper::isKustomPayment($this->getEditObjectid());
        $this->addTplParam('isKustomPayment', $isKustomPayment);

        return parent::render();
    }
}
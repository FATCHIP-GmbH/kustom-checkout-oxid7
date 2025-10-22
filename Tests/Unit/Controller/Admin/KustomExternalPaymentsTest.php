<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller\Admin;


use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\Request;
use Fatchip\FcKustom\Controller\Admin\KustomExternalPayments;
use Fatchip\FcKustom\Core\KustomConsts;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomExternalPaymentsTest extends ModuleUnitTestCase {

    public function testRender() {
        $controller = new KustomExternalPayments();
        $result = $controller->render();

        $viewData = $controller->getViewData();

        $this->assertEquals('@fckustom/admin/fckustom_external_payments', $result);
        $this->assertNotEmpty($viewData['activePayments']);
        $this->assertEquals(oxNew(KustomConsts::class)->getKustomExternalPaymentNames(), $viewData['paymentNames']);

    }

    public function testGetMultilangUrls() {
        $controller = new KustomExternalPayments();
        $result = $controller->getMultilangUrls();
        $this->assertNotEmpty($result);
        $this->assertJson($result);
    }
}

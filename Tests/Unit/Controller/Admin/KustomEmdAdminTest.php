<?php

namespace Fatchip\FcKustom\Tests\Unit\Controller\Admin;


use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\Request;
use Fatchip\FcKustom\Controller\Admin\KustomEmdAdmin;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomEmdAdminTest extends ModuleUnitTestCase
{

    public function testRender()
    {
        $emd = oxNew(KustomEmdAdmin::class);
        $activePayment = $emd->getViewDataElement('activePayments');
        $this->assertNull($activePayment);
        $result = $emd->render();
        $activePayment = $emd->getViewDataElement('activePayments');

        $this->assertEquals('@fckustom/admin/fckustom_emd_admin', $result);
        $this->assertNotEmpty($activePayment);
    }
}

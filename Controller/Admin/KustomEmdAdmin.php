<?php

namespace Fatchip\FcKustom\Controller\Admin;


use Fatchip\FcKustom\Core\KustomConsts;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Field;

/**
 * Class Kustom_Config for module configuration in OXID backend
 */
class KustomEmdAdmin extends KustomBaseConfig
{

    protected $_sThisTemplate = '@fckustom/admin/fckustom_emd_admin';

    /**
     * Render logic
     *
     * @see admin/oxAdminDetails::render()
     * @return string
     */
    public function render()
    {
        // force shopid as parameter
        // Pass shop OXID so that shop object could be loaded
        $sShopOXID = Registry::getConfig()->getShopId();

        $this->setEditObjectId($sShopOXID);

        $this->addTplParam('activePayments', $this->getPaymentList());

        parent::render();

        return $this->_sThisTemplate;
    }

    /**
     * @throws \Exception
     */
    public function save()
    {
        parent::save();

        $vars    = $this->_oRequest->getRequestEscapedParameter('payments');
        /** @var Payment $payment */
        $payment = oxNew(Payment::class);

        foreach ($vars as $oxid => $settings) {
            $payment->load($oxid);
            foreach ($settings as $key => $value) {
                $payment->{$key} = new Field($value, Field::T_RAW);
            }
            $payment->save();
        }
    }

    /**
     * @return array
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    public function getPaymentList()
    {
        $paymentIds = $this->getAllActiveOxPaymentIds();

        $payments = array();
        foreach ($paymentIds as $oxid) {
            $payments[] = $this->getPaymentData($oxid['oxid']);
        }

        return $payments;
    }

    /**
     * @return array
     * @codeCoverageIgnore
     */
    public function getEmdPaymentTypeOptions()
    {
        return oxNew(KustomConsts::class)->getEmdPaymentTypeOptions();
    }

    /**
     * @return array
     * @codeCoverageIgnore
     */
    public function getFullHistoryOrdersOptions()
    {
        return oxNew(KustomConsts::class)->getFullHistoryOrdersOptions();
    }

}
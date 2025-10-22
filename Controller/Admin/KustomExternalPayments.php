<?php

namespace Fatchip\FcKustom\Controller\Admin;


use Fatchip\FcKustom\Core\KustomConsts;
use Fatchip\FcKustom\Core\KustomUtils;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\DatabaseProvider;

/**
 * Class Kustom_Config for module configuration in OXID backend
 */
class KustomExternalPayments extends KustomBaseConfig
{

    protected $_sThisTemplate = '@fckustom/admin/fckustom_external_payments';

    /**
     * Render logic
     *
     * @see admin/oxAdminDetails::render()
     * @return string
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    public function render()
    {
        // force shopid as parameter
        // Pass shop OXID so that shop object could be loaded
        $sShopOXID = Registry::getConfig()->getShopId();

        $this->setEditObjectId($sShopOXID);

        parent::render();

        $this->addTplParam('activePayments', $this->getPaymentList());
        $this->addTplParam('paymentNames', oxNew(KustomConsts::class)->getKustomExternalPaymentNames());

        return $this->_sThisTemplate;
    }

    /**
     * @return array
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    public function getPaymentList()
    {
        /** @var \OxidEsales\Eshop\Core\Database\Adapter\Doctrine\Database $db */
        $db = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);

        $sql = 'SELECT oxid 
                FROM oxpayments
                WHERE oxid NOT LIKE "kustom%"
                AND oxid != "oxempty"
                AND oxactive = 1';

        $oxids = $db->select($sql);

        $payments = array();
        foreach ($oxids as $oxid) {
            $payments[] = $this->getPaymentData($oxid['oxid']);
        }

        return $payments;
    }


    /**
     * @throws \Exception
     */
    public function save()
    {
        $vars    = $this->_oRequest->getRequestEscapedParameter('payments');
        $payment = oxNew(Payment::class);
        $payment->setEnableMultilang(false);
        foreach ($vars as $oxid => $settings) {
            $payment->load($oxid);
            foreach ($settings as $key => $value) {
                $payment->{$key} = new Field($value, Field::T_RAW);
            }
            $payment->save();
        }
    }

    /**
     * Ajax endpoint for multilang input fields
     */
    public function getMultilangUrls()
    {
        $langs         = array_keys(Registry::getLang()->getLanguageIds());
        $fields        = array(
            'oxpayments__fckustom_paymentimageurl',
            'oxpayments__fckustom_checkoutimageurl',
        );
        $imageUrls = array();
        foreach ($this->getPaymentList() as $payment) {
            $oPayment = oxNew(Payment::class);
            $oPayment->setEnableMultilang(false);
            $oPayment->load($payment['oxid']);
            foreach ($langs as $langId) {
                $langSuffix = $langId == 0 ? '' : '_' . $langId;
                foreach ($fields as $field) {
                    $imageUrls[] = array(
                        'name'  => 'payments[' . $oPayment->getId() . '][' . $field . $langSuffix . ']',
                        'value' => $oPayment->{$field . $langSuffix}->value
                    );
                }
            }
        }
        $multiLangData['imageUrls'] = $imageUrls;
        $multiLangData['errorMsg'] = array(
            'valueMissing' => Registry::getLang()->translateString('FCKUSTOM_EXTERNAL_IMAGE_URL_EMPTY'),
            'patternMismatch' => Registry::getLang()->translateString('FCKUSTOM_EXTERNAL_IMAGE_URL_INVALID')
        );

        return Registry::getUtils()->showMessageAndExit(json_encode($multiLangData));
    }
}
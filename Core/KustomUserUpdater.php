<?php

namespace Fatchip\FcKustom\Core;

use Fatchip\FcKustom\Model\KustomPaymentHelper;
use Fatchip\FcKustom\Model\KustomUser;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;

class KustomUserUpdater
{
    /**
     * @param User $user
     * @param array $orderData
     * @param string $paymentId
     * @return void
     */
    public function updateUserObject($user, $orderData, $paymentId = "")
    {
        // if the user is registered, we need the whole object not just the fake user to ensure no data is lost
        $isExternalPayment = $paymentId && !KustomPaymentHelper::isKustomPayment($paymentId);
        if ($isExternalPayment && $user->getType() === KustomUser::LOGGED_IN) {
            //reload the user by their email to get a clean object
            $mail = $orderData["shipping_address"]["email"];
            $user = oxNew(User::class);
            $user->loadByEmail($mail);
            // ensure user is always logged out
            Registry::getSession()->setVariable('blNeedLogout', true);
        }

        if ($orderData['billing_address'] !== $orderData['shipping_address']) {
            $user->updateDeliveryAddress(KustomFormatter::kustomToOxidAddress($orderData, 'shipping_address'));
        } else {
            $user->clearDeliveryAddress();
        }

        $user->assign(KustomFormatter::kustomToOxidAddress($orderData, 'billing_address'));

        if (isset($orderData['customer']['date_of_birth'])) {
            $user->oxuser__oxbirthdate = new Field($orderData['customer']['date_of_birth']);
        }

        if (($user->isWritable() || $isExternalPayment) && $user->oxuser__oxusername->value) {
            try {
                if($user->getType() == KustomUser::NOT_EXISTING
                    && count($user->getUserGroups()) == 0){
                    $user->addToGroup('oxidnewcustomer');
                }
                $user->save();
            } catch (\Exception $e){
                if($e->getCode() == DatabaseInterface::DUPLICATE_KEY_ERROR_CODE && $user->getType() == KustomUser::LOGGED_IN){
                    $user->logout();
                }
            }
        }
    }
}
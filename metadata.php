<?php

use Fatchip\FcKustom\Controller\KustomPaymentController;
use Fatchip\FcKustom\Core\KustomConsts;
use Fatchip\FcKustom\Model\KustomPayment;
use OxidSolutionCatalysts\PayPal\Controller\ProxyController;
use Fatchip\FcKustom\Controller\KustomDeviceEligibilityController;
use Fatchip\FcKustom\Component\KustomBasketComponent;
use Fatchip\FcKustom\Component\KustomUserComponent;
use Fatchip\FcKustom\Controller\Admin\KustomConfiguration;
use Fatchip\FcKustom\Controller\Admin\KustomDesign;
use Fatchip\FcKustom\Controller\Admin\KustomEmdAdmin;
use Fatchip\FcKustom\Controller\Admin\KustomExternalPayments;
use Fatchip\FcKustom\Controller\Admin\KustomGeneral;
use Fatchip\FcKustom\Controller\Admin\KustomOrderAddress;
use Fatchip\FcKustom\Controller\Admin\KustomOrderArticle as KustomAdminOrderArticle;
use Fatchip\FcKustom\Controller\Admin\KustomOrderList;
use Fatchip\FcKustom\Controller\Admin\KustomOrderMain;
use Fatchip\FcKustom\Controller\Admin\KustomOrderOverview;
use Fatchip\FcKustom\Controller\Admin\KustomOrders;
use Fatchip\FcKustom\Controller\Admin\KustomPaymentMain;
use Fatchip\FcKustom\Controller\Admin\KustomShipping;
use Fatchip\FcKustom\Controller\Admin\KustomStart;
use Fatchip\FcKustom\Controller\KustomAuthCallbackEndpoint;
use Fatchip\FcKustom\Controller\KustomPPProxyController;
use Fatchip\FcKustom\Controller\KustomUserController;
use Fatchip\FcKustom\Controller\KustomAcknowledgeController;
use Fatchip\FcKustom\Controller\KustomAjaxController;
use Fatchip\FcKustom\Controller\KustomBasketController;
use Fatchip\FcKustom\Controller\KustomEpmDispatcher;
use Fatchip\FcKustom\Controller\KustomExpressController;
use Fatchip\FcKustom\Controller\KustomOrderController;
use Fatchip\FcKustom\Controller\KustomThankYouController;
use Fatchip\FcKustom\Controller\KustomValidationController;
use Fatchip\FcKustom\Controller\KustomNewsletterController;
use Fatchip\FcKustom\Controller\KustomViewConfig;
use Fatchip\FcKustom\Core\Config;
use Fatchip\FcKustom\Model\KustomAddress;
use Fatchip\FcKustom\Model\KustomArticle;
use Fatchip\FcKustom\Model\KustomBasket;
use Fatchip\FcKustom\Model\KustomCountryList;
use Fatchip\FcKustom\Model\KustomOrder;
use Fatchip\FcKustom\Model\KustomOrderArticle;
use Fatchip\FcKustom\Model\KustomUser;
use Fatchip\FcKustom\Model\KustomUserPayment;
use Fatchip\FcKustom\Core\KustomShopControl;

use OxidEsales\Eshop\Application\Component\BasketComponent;
use OxidEsales\Eshop\Application\Component\UserComponent;
use OxidEsales\Eshop\Application\Controller\Admin\OrderArticle as AdminOrderArticle;
use OxidEsales\Eshop\Application\Controller\Admin\OrderList;
use OxidEsales\Eshop\Application\Controller\Admin\OrderMain;
use OxidEsales\Eshop\Application\Controller\Admin\OrderOverview;
use OxidEsales\Eshop\Application\Controller\BasketController;
use OxidEsales\Eshop\Application\Controller\OrderController;
use OxidEsales\Eshop\Application\Controller\ThankYouController;
use OxidEsales\Eshop\Application\Controller\UserController;
use OxidEsales\Eshop\Application\Controller\NewsletterController;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\CountryList;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\OrderArticle;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\UserPayment;
use OxidEsales\Eshop\Core\ViewConfig;
use OxidEsales\Eshop\Application\Controller\Admin\PaymentMain;
use OxidEsales\Eshop\Application\Controller\PaymentController;

/**
 * Metadata version
 */
$sMetadataVersion = '2.0';

$aModule = array(
    'id'          => 'fckustom',
    'title'       => 'Kustom Checkout',
    'description' => array(
        'de' => 'Egal was Sie verkaufen, unsere Produkte sind dafür gemacht, Ihren Kunden das beste Erlebnis zu bereiten. Das gefällt nicht nur Ihnen, sondern auch uns! Die Kustom Plugins werden stets auf Herz und Nieren geprüft und können ganz einfach durch Sie oder Ihre technischen Ansprechpartner aktiviert werden. Das nennen wir smoooth. Hier können Sie mit der Komplettlösung, dem Kustom Checkout, Ihre Customer Journey optimieren. Erfahren Sie hier mehr zu Kustom für OXID: <a href="https://www.kustom.co/about</a>',
        'en' => 'No matter what you sell, our products are made to give your customers the best purchase experience. This is not only smoooth for you - it is smoooth for us, too! Kustom plugins are always tested and can be activated by you or your technical contact with just a few clicks. That is smoooth. Here you can optimize your customer journey with the complete Kustom Checkout solution. Find out more about Kustom for OXID: <a href="https://www.kustom.co/about" target="_blank">https://www.kustom.co/about</a>'
    ),
    'version'     => '1.0.3',
    'author'      => '<a href="https://www.fatchip.de" target="_blank">FATCHIP GmbH</a>',
    'thumbnail'   => '/out/admin/src/img/kustom-logo.png',
    'url'         => 'https://www.kustom.co/about',
    'email'       => 'support@kustom.co',

    'controllers' => array(
        // kustom admin
        'KustomStart'                   => KustomStart::class,
        'KustomGeneral'                 => KustomGeneral::class,
        'KustomConfiguration'           => KustomConfiguration::class,
        'KustomDesign'                  => KustomDesign::class,
        'KustomExternalPayments'        => KustomExternalPayments::class,
        'KustomEmdAdmin'                => KustomEmdAdmin::class,
        'KustomOrders'                  => KustomOrders::class,
        'KustomShipping'                => KustomShipping::class,
        // controllers
        'KustomExpress'                 => KustomExpressController::class,
        'KustomAjax'                    => KustomAjaxController::class,
        'KustomEpmDispatcher'           => KustomEpmDispatcher::class,
        'KustomAcknowledge'             => KustomAcknowledgeController::class,
        'KustomValidate'                => KustomValidationController::class,
        'KustomAuthCallbackEndpoint'    => KustomAuthCallbackEndpoint::class,
        'KustomDeviceEligibility'       => KustomDeviceEligibilityController::class,
    ),
    'extend'      => array(
        // models
        Basket::class               => KustomBasket::class,
        User::class                 => KustomUser::class,
        Article::class              => KustomArticle::class,
        Order::class                => KustomOrder::class,
        Address::class              => KustomAddress::class,
        Payment::class              => KustomPayment::class,
        CountryList::class          => KustomCountryList::class,
        OrderArticle::class         => KustomOrderArticle::class,
        UserPayment::class          => KustomUserPayment::class,

        // controllers
        ThankYouController::class   => KustomThankYouController::class,
        ViewConfig::class           => KustomViewConfig::class,
        OrderController::class      => KustomOrderController::class,
        UserController::class       => KustomUserController::class,
        BasketController::class     => KustomBasketController::class,
        ProxyController::class      => KustomPPProxyController::class,
        NewsletterController::class => KustomNewsletterController::class,
        PaymentController::class    => KustomPaymentController::class,

        // admin
        OrderAddress::class         => KustomOrderAddress::class,
        OrderList::class            => KustomOrderList::class,
        AdminOrderArticle::class    => KustomAdminOrderArticle::class,
        OrderMain::class            => KustomOrderMain::class,
        OrderOverview::class        => KustomOrderOverview::class,
        PaymentMain::class          => KustomPaymentMain::class,
        //components
        BasketComponent::class      => KustomBasketComponent::class,
        UserComponent::class        => KustomUserComponent::class,

        OxidEsales\Eshop\Core\Config::class                         => Config::class,
        OxidEsales\Eshop\Core\ShopControl::class                    => KustomShopControl::class
    ),
    'settings'    => array(
        //HiddenSettings
        ['name' => 'sKustomMerchantId', 'type' => 'str', 'value' => ''],
        ['name' => 'sKustomPassword', 'type' => 'str', 'value' => ''],
        ['name' => 'sKustomDefaultCountry', 'type' => 'str', 'value' => 'DE'],
        ['name' => 'sKustomStripPromotion', 'type' => 'str', 'value' => ''],
        ['name' => 'sKustomMessagingScript', 'type' => 'str', 'value' => ''],
        ['name' => 'sKustomBannerPromotion', 'type' => 'str', 'value' => ''],
        ['name' => 'kp_order_id', 'type' => 'str', 'value' => ''],
        ['name' => 'aarrKustomCreds', 'type' => 'aarr', 'value' => []],
        ['name' => 'aarrKustomAnonymizedProductTitle', 'type' => 'aarr', 'value' => [
            'sKustomAnonymizedProductTitle_EN' => 'Product name',
            'sKustomAnonymizedProductTitle_DE' => 'Produktname'
        ]],
        ['name' => 'aarrKustomTermsConditionsURI', 'type' => 'aarr', 'value' => [
            'sKustomTermsConditionsURI_DE' => '',
            'sKustomTermsConditionsURI_EN' => ''
        ]],
        ['name' => 'aarrKustomCancellationRightsURI', 'type' => 'aarr', 'value' => [
            'sKustomCancellationRightsURI_DE' => '',
            'sKustomCancellationRightsURI_EN' => ''
        ]],
        ['name' => 'aarrKustomShippingDetails', 'type' => 'aarr', 'value' => [
            'sKustomShippingDetails_DE' => '',
            'sKustomShippingDetails_EN' => ''
        ]],
        ['name' => 'sKustomB2Option', 'type' => 'str', 'value' => 'B2C'],
        ['name' => 'sKustomKCOMethod', 'type' => 'str', 'value' => 'oxidstandard'],
        ['name' => 'aarrKustomISButtonStyle', 'type' => 'aarr', 'value' => [
            'variation' => 'Kustom',
            'tagline' => 'light',
            'type' => 'pay',
        ]],
        ['name' => 'aarrKustomISButtonSettings', 'type' => 'aarr', 'value' => [
            'allow_separate_shipping_address' => 0,
            'date_of_birth_mandatory' => 0,
            'national_identification_number_mandatory' => 0,
            'phone_mandatory' => 0,
        ]],
        ['name' => 'aarrKustomShippingMap', 'type' => 'aarr', 'value' => []],
        ['name' => 'iKustomActiveCheckbox', 'type' => 'str', 'value' => KustomConsts::EXTRA_CHECKBOX_NONE],
        ['name' => 'iKustomValidation', 'type' => 'str', 'value' => KustomConsts::NO_VALIDATION],
        ['name' => 'blIsKustomTestMode', 'type' => 'bool', 'value' => true],
        ['name' => 'blKustomLoggingEnabled', 'type' => 'bool', 'value' => false],
        ['name' => 'blKustomAllowSeparateDeliveryAddress', 'type' => 'bool', 'value' => true],
        ['name' => 'blKustomEnableAnonymization', 'type' => 'bool', 'value' => false],
        ['name' => 'blKustomSendProductUrls', 'type' => 'bool', 'value' => true],
        ['name' => 'blKustomSendImageUrls', 'type' => 'bool', 'value' => true],
        ['name' => 'blKustomMandatoryPhone', 'type' => 'bool', 'value' => true],
        ['name' => 'blKustomMandatoryBirthDate', 'type' => 'bool', 'value' => true],
        ['name' => 'blKustomEmdCustomerAccountInfo', 'type' => 'bool', 'value' => false],
        ['name' => 'blKustomEmdPaymentHistoryFull', 'type' => 'bool', 'value' => false],
        ['name' => 'blKustomEmdPassThrough', 'type' => 'bool', 'value' => false],
        ['name' => 'blKustomEnableAutofocus', 'type' => 'bool', 'value' => true],
        ['name' => 'blKustomEnablePreFilling', 'type' => 'bool', 'value' => true],
        ['name' => 'blKustomPreFillNotification', 'type' => 'bool', 'value' => true],
        ['name' => 'blKustomDisplayBuyNow', 'type' => 'bool', 'value' => true],
        ['name' => 'sKustomFooterValue', 'type' => 'str', 'value' => ''],
        ['name' => 'sKustomFooterDisplay', 'type' => 'str', 'value' => ''],
        ['name' => 'sKustomCreditPromotionProduct', 'type' => 'str', 'value' => ''],
        ['name' => 'sKustomCreditPromotionBasket', 'type' => 'str', 'value' => ''],
        ['name' => 'aKustomDesign', 'type' => 'arr', 'value' => []],
        ['name' => 'aKustomDesignKP', 'type' => 'arr', 'value' => []],
    ),
    'events'      => array(
        'onActivate'   => '\Fatchip\FcKustom\Core\KustomInstaller::onActivate',
        'onDeactivate'   => '\Fatchip\FcKustom\Core\KustomInstaller::onDeactivate',
    ),
);

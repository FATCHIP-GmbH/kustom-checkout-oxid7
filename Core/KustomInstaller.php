<?php


namespace Fatchip\FcKustom\Core;


use OxidEsales\Eshop\Application\Controller\Admin\ShopConfiguration;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\DbMetaDataHandler;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Database\Adapter\Doctrine\Database;
use OxidEsales\DoctrineMigrationWrapper\MigrationsBuilder;
use Symfony\Component\Console\Output\BufferedOutput;

final class KustomInstaller
{
    const KUSTOM_MODULE_ID = 'fckustom';

    static private $instance = null;

    /**
     * @var database object
     */
    protected $db;

    /**
     * Database name
     * @var string $dbName
     */
    protected $dbName;

    protected $moduleRelativePath = 'modules//fc/fckustom';
    protected $modulePath;

    /**
     * @return KustomInstaller|null|object
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new KustomInstaller();
            /** @var Database db */
            self::$instance->db         = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
            self::$instance->dbName     = Registry::getConfig()->getConfigParam('dbName');
            self::$instance->modulePath = Registry::getConfig()->getConfigParam('sShopDir') . self::$instance->moduleRelativePath;
        }

        return self::$instance;
    }

    /**
     * Activation sequence
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     * @throws \Exception
     */
    public static function onActivate()
    {
        $instance = self::getInstance();

        $instance->checkAndUpdate();
        $instance->addConfigVars();

        $instance->executeModuleMigrations();

        $oMetaData = oxNew(DbMetaDataHandler::class);
        $oMetaData->updateViews();
    }

    /**
     * Execute necessary module migrations on activate event
     */
    private static function executeModuleMigrations(): void
    {
        $migrations = (new MigrationsBuilder())->build();

        $output = new BufferedOutput();
        $migrations->setOutput($output);
        $neeedsUpdate = $migrations->execute('migrations:up-to-date', 'fckustom');

        if ($neeedsUpdate) {
            $migrations->execute('migrations:migrate', 'fckustom');
        }
    }

    protected function checkAndUpdate() {
        // oxconfig.OXMODULE prefix
        $requireUpdate = $this->db->select(
            "SELECT `OXID` FROM `oxconfig` WHERE OXMODULE = ?;",
            array('fckustom')
        );
        if ($requireUpdate->count()) {
            foreach($requireUpdate->fetchAll() as $row) {
                $this->db->execute("UPDATE `oxconfig` SET OXMODULE = ? WHERE OXID = ?", array('module:fckustom', $row['OXID']));
            }
        }
    }

    /**
     * Add kustom config vars and set defaults
     */
    protected function addConfigVars()
    {
        $config = Registry::getConfig();
        $shopId = $config->getShopId();

        $defaultConfVars = array(
            'bool'   => array(

                'blIsKustomTestMode'                    => 1,
                'blKustomLoggingEnabled'                => 0,
                'blKustomAllowSeparateDeliveryAddress'  => 1,
                'blKustomEnableAnonymization'           => 0,
                'blKustomSendProductUrls'               => 1,
                'blKustomSendImageUrls'                 => 1,
                'blKustomMandatoryPhone'                => 1,
                'blKustomMandatoryBirthDate'            => 1,
                'blKustomEmdCustomerAccountInfo'        => 0,
                'blKustomEmdPaymentHistoryFull'         => 0,
                'blKustomEmdPassThrough'                => 0,
                'blKustomEnableAutofocus'               => 1,
                'blKustomEnablePreFilling'              => 1,
                'blKustomPreFillNotification'           => 1,
            ),
            'str'    => array(
                'sKustomMerchantId'             => '',
                'sKustomPassword'               => '',
                'sKustomDefaultCountry'         => 'DE',
                'iKustomActiveCheckbox'         => KustomConsts::EXTRA_CHECKBOX_NONE,
                'iKustomValidation'             => KustomConsts::NO_VALIDATION,
                'sKustomAnonymizedProductTitle' => 'anonymized product',

                // Multilang Data
                'sKustomAnonymizedProductTitle_EN'  => 'Product name',
                'sKustomAnonymizedProductTitle_DE'  => 'Produktname',

                'sKustomB2Option'      => 'B2C',
                'sKustomKCOMethod'     => 'oxidstandard',
                'sKustomFooterDisplay' => 0,
                'sKustomFooterValue'   => 'logoBlack',
            ),
            'arr'    => array(),
            'aarr'   => array(
                'aarrKustomISButtonStyle' => 'variation => kustom
                    tagline => light
                    type => pay',
                'aarrKustomISButtonSettings' => 'allow_separate_shipping_address => 0
                    date_of_birth_mandatory => 0
                    national_identification_number_mandatory => 0
                    phone_mandatory => 0',
                'aarrKustomShippingMap' => '',
                'aKustomDesign' => '',
                'aKustomDesignKP' => ''
            ),
            'select' => array(),
        );

        $oShopConf     = oxNew(ShopConfiguration::class);
        $savedConf     = $oShopConf->loadConfVars($shopId, 'module:'. self::KUSTOM_MODULE_ID);
        $savedConfVars = $savedConf['vars'];

        foreach ($defaultConfVars as $type => $values) {
            foreach ($values as $name => $data) {
                if (key_exists($name, $savedConfVars[$type])) {
                    continue;
                }
                if ($type === 'aarr') {
                    $data = html_entity_decode($data);
                }

                $config->saveShopConfVar(
                    $type,
                    $name,
                    $data,
                    $shopId,
                    "module:" . self::KUSTOM_MODULE_ID
                );
            }
        }
    }

    public static function onDeactivate()
    {
        $tempDirectory = Registry::getConfig()->getConfigParam("sCompileDir");
        $mask = $tempDirectory . '/smarty/*';
        $files = glob($mask);
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}
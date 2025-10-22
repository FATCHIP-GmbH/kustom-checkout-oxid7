<?php

declare(strict_types=1);

namespace Fatchip\FcKustom\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Exception;
use Fatchip\FcKustom\Core\KustomPaymentTypes;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Model\BaseModel;
use OxidEsales\Eshop\Core\Registry;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250909130314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->connection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        $this->extendDbTables($schema);
        $this->addAlterTables($schema);

        $this->addKustomPaymentMethod();
    }

    /**
     * Extend Kustom tables
     * @throws DatabaseErrorException
     */
    protected function extendDbTables(Schema $schema)
    {
        if (!$schema->hasTable('fckustom_logs')) {
            $this->addSql("
        CREATE TABLE `fckustom_logs` ( 
              `OXID`          CHAR(32)
                              CHARACTER SET latin1 COLLATE latin1_general_ci
                           NOT NULL DEFAULT '',
              `FCKUSTOM_ORDERID` VARCHAR(128) 
                            CHARACTER SET utf8 
                            DEFAULT '' NOT NULL,
              `OXSHOPID`      CHAR(32)
                              CHARACTER SET latin1 COLLATE latin1_general_ci
                           NOT NULL DEFAULT '',
              `FCKUSTOM_MID` VARCHAR(50) 
                            CHARACTER SET utf8 NOT NULL,
              `FCKUSTOM_STATUSCODE` VARCHAR(16) CHARACTER SET utf8 NOT NULL,
              `FCKUSTOM_METHOD`      VARCHAR(128)
                              CHARACTER SET utf8
                           NOT NULL DEFAULT '',
              `FCKUSTOM_URL` VARCHAR(256) CHARACTER SET utf8,
              `FCKUSTOM_REQUESTRAW`  TEXT CHARACTER SET utf8
                           NOT NULL,
              `FCKUSTOM_RESPONSERAW` TEXT CHARACTER SET utf8
                           NOT NULL,
              `FCKUSTOM_DATE`        DATETIME
                           NOT NULL DEFAULT '0000-00-00 00:00:00',
              PRIMARY KEY (`OXID`),
              KEY `FCKUSTOM_DATE` (`FCKUSTOM_DATE`)
            )
        ENGINE = InnoDB
        DEFAULT CHARSET = utf8;
    ");
        }

        if (!$schema->hasTable('fckustom_ack')) {
            $this->addSql("
        CREATE TABLE `fckustom_ack` (
              `OXID`       VARCHAR(32)
                           CHARACTER SET latin1 COLLATE latin1_general_ci
                           NOT NULL,
              `FCKUSTOM_ORDERID` VARCHAR(128) CHARACTER SET utf8 DEFAULT '' NOT NULL,
              `KLRECEIVED` DATETIME NOT NULL,
              PRIMARY KEY (`OXID`),
              KEY `FCKUSTOM_ORDERID` (`FCKUSTOM_ORDERID`)
            )
        ENGINE = InnoDB
        COMMENT ='List of all Kustom acknowledge requests'
        DEFAULT CHARSET = utf8;
    ");
        }

        if (!$schema->hasTable('fckustom_anon_lookup')) {
            $this->addSql("
                CREATE TABLE `fckustom_anon_lookup` (
                      `FCKUSTOM_ARTNUM` VARCHAR(32) NOT NULL,
                      `OXARTID`  VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
                      PRIMARY KEY (`FCKUSTOM_ARTNUM`)
                    )
                ENGINE = InnoDB
                COMMENT ='Mapping of annonymous article numbers to their oxids'
                DEFAULT CHARSET = utf8;
            ");
        }

        if (!$schema->hasTable('fckustom_instant_basket')) {
            $this->addSql("
                CREATE TABLE `fckustom_instant_basket` (
                    `OXID` VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
                    `BASKET_INFO` MEDIUMBLOB,
                    `STATUS`  VARCHAR(32) NOT NULL DEFAULT 'OPENED',
                    `TYPE` VARCHAR(32) NOT NULL DEFAULT '',
                    `TIMESTAMP` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp',
                    PRIMARY KEY (`OXID`)
                )
                ENGINE = InnoDB
                DEFAULT CHARSET = utf8;
            ");
        }

        if (!$schema->hasTable('fckustom_authtokens')) {
            $this->addSql("
                CREATE TABLE `fckustom_authtokens` (
                    `OXID`        
                        CHAR(32)
                        CHARACTER SET latin1 COLLATE latin1_general_ci
                        NOT NULL DEFAULT '',
                    `FCKUSTOM_AUTHTOKEN` 
                        CHAR(32) 
                        CHARACTER SET latin1 COLLATE latin1_general_ci 
                        NOT NULL,
                    `FCKUSTOM_SESSIONID`      
                        CHAR(32)
                        CHARACTER SET latin1 COLLATE latin1_general_ci
                        NOT NULL,
                    `TIMESTAMP` timestamp 
                        NOT NULL 
                        DEFAULT CURRENT_TIMESTAMP 
                        ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp',
                    PRIMARY KEY (`OXID`),
                    UNIQUE (FCKUSTOM_AUTHTOKEN),
                    UNIQUE (FCKUSTOM_SESSIONID)
                )
                ENGINE = InnoDB
                DEFAULT CHARSET = utf8;
            ");
        }
    }

    /**
     * @throws DatabaseErrorException
     */
    protected function addAlterTables(Schema $schema)
    {
        $aStructure = array(
            'oxorder' => array(
                'FCKUSTOM_MERCHANTID' => 'ADD COLUMN `FCKUSTOM_MERCHANTID` VARCHAR(128)  DEFAULT \'\' NOT NULL',
                'FCKUSTOM_SERVERMODE' => 'ADD COLUMN `FCKUSTOM_SERVERMODE` VARCHAR(16) NOT NULL DEFAULT \'\'',
                'FCKUSTOM_ORDERID' => 'ADD COLUMN `FCKUSTOM_ORDERID` VARCHAR(128)  DEFAULT \'\' NOT NULL',
                'FCKUSTOM_SYNC' => 'ADD COLUMN `FCKUSTOM_SYNC` TINYINT UNSIGNED NOT NULL DEFAULT \'1\'',
                'FCKUSTOM_KUSTOMPAYMENTMETHOD' => 'ADD COLUMN `FCKUSTOM_KUSTOMPAYMENTMETHOD` VARCHAR(128)',
            ),
            'oxorderarticles' => array(
                'FCKUSTOM_TITLE' => 'ADD COLUMN  `FCKUSTOM_TITLE` VARCHAR(255) NOT NULL DEFAULT \'\'',
                'FCKUSTOM_ARTNUM' => 'ADD COLUMN  `FCKUSTOM_ARTNUM` VARCHAR(255) NOT NULL DEFAULT \'\'',
            ),
            'oxpayments' => array(
                'FCKUSTOM_EXTERNALNAME' => 'ADD COLUMN `FCKUSTOM_EXTERNALNAME` VARCHAR(255) NULL DEFAULT \'\'',
                'FCKUSTOM_EXTERNALPAYMENT' => 'ADD COLUMN `FCKUSTOM_EXTERNALPAYMENT` TINYINT(1) UNSIGNED NOT NULL DEFAULT \'0\'',
                'FCKUSTOM_EXTERNALCHECKOUT' => 'ADD COLUMN `FCKUSTOM_EXTERNALCHECKOUT` TINYINT(1) UNSIGNED NOT NULL DEFAULT \'0\'',
                'FCKUSTOM_PAYMENTIMAGEURL' => 'ADD COLUMN `FCKUSTOM_PAYMENTIMAGEURL` VARCHAR(255) NULL DEFAULT \'\'',
                'FCKUSTOM_PAYMENTIMAGEURL_1' => 'ADD COLUMN `FCKUSTOM_PAYMENTIMAGEURL_1` VARCHAR(255) NULL DEFAULT \'\'',
                'FCKUSTOM_PAYMENTIMAGEURL_2' => 'ADD COLUMN `FCKUSTOM_PAYMENTIMAGEURL_2` VARCHAR(255) NULL DEFAULT \'\'',
                'FCKUSTOM_PAYMENTIMAGEURL_3' => 'ADD COLUMN `FCKUSTOM_PAYMENTIMAGEURL_3` VARCHAR(255) NULL DEFAULT \'\'',
                'FCKUSTOM_CHECKOUTIMAGEURL' => 'ADD COLUMN `FCKUSTOM_CHECKOUTIMAGEURL` VARCHAR(255) NULL DEFAULT \'\'',
                'FCKUSTOM_CHECKOUTIMAGEURL_1' => 'ADD COLUMN `FCKUSTOM_CHECKOUTIMAGEURL_1` VARCHAR(255) NULL DEFAULT \'\'',
                'FCKUSTOM_CHECKOUTIMAGEURL_2' => 'ADD COLUMN `FCKUSTOM_CHECKOUTIMAGEURL_2` VARCHAR(255) NULL DEFAULT \'\'',
                'FCKUSTOM_CHECKOUTIMAGEURL_3' => 'ADD COLUMN `FCKUSTOM_CHECKOUTIMAGEURL_3` VARCHAR(255) NULL DEFAULT \'\'',
                'FCKUSTOM_PAYMENTOPTION' => 'ADD COLUMN `FCKUSTOM_PAYMENTOPTION` SET(\'card\',\'direct banking\',\'other\') NOT NULL DEFAULT \'other\'',
                'FCKUSTOM_EMDPURCHASEHISTORYFULL' => 'ADD COLUMN `FCKUSTOM_EMDPURCHASEHISTORYFULL` TINYINT(1) UNSIGNED NOT NULL DEFAULT \'0\'',
            ),
            'oxaddress' => array(
                'FCKUSTOM_TEMPORARY' => 'ADD COLUMN `FCKUSTOM_TEMPORARY` TINYINT(1) UNSIGNED NOT NULL DEFAULT \'0\'',
            ),
        );

        // ADD COLUMNS
        foreach ($aStructure as $sTableName => $aColumns) {

            $query = "ALTER TABLE `$sTableName` ";
            $first = true;

            foreach ($aColumns as $sColumnName => $queryPart) {
                if (!$schema->getTable($sTableName)->hasColumn($sColumnName)) {
                    if (!$first) {
                        $query .= ', ';
                    }
                    $query .= $queryPart;
                    $first = false;
                }
            }

            $this->addSql($query);
        }
    }

    /**
     * Add Kustom payment option
     * @throws Exception
     */
    protected function addKustomPaymentMethod(): void
    {
        $oPayment = oxNew(BaseModel::class);
        $oPayment->init('oxpayments');

        $oPayment->load('oxidinvoice');
        $de_prefix = $oPayment->getFieldData('oxdesc') === 'Rechnung' ? 0 : 1;
        $en_prefix = $de_prefix === 1 ? 0 : 1;

        $sort = -350;
        $aLangs = Registry::getLang()->getLanguageArray();

        if ($aLangs) {
            $oxid = KustomPaymentTypes::KUSTOM_PAYMENT_CHECKOUT_ID;
            $aTitle = array($de_prefix => 'Kustom Checkout', $en_prefix => 'Kustom Checkout');
            /** @var Payment $oPayment */
            $oPayment = oxNew(BaseModel::class);
            $oPayment->init('oxpayments');

            $oPayment->load($oxid);
            if ($oPayment->isLoaded()) {
                $oPayment->oxpayments__oxactive = new Field(1, Field::T_RAW);
                $oPayment->save();
            }

            $oPayment->setId($oxid);
            $oPayment->oxpayments__oxactive = new Field(1, Field::T_RAW);
            $oPayment->oxpayments__oxaddsum = new Field(0, Field::T_RAW);
            $oPayment->oxpayments__oxaddsumtype = new Field('abs', Field::T_RAW);
            $oPayment->oxpayments__oxaddsumrules = new Field('31', Field::T_RAW);
            $oPayment->oxpayments__oxfromboni = new Field('0', Field::T_RAW);
            $oPayment->oxpayments__oxfromamount = new Field('0', Field::T_RAW);
            $oPayment->oxpayments__oxtoamount = new Field('1000000', Field::T_RAW);
            $oPayment->oxpayments__oxchecked = new Field(0, Field::T_RAW);
            $oPayment->oxpayments__oxsort = new Field(strval($sort), Field::T_RAW);
            $oPayment->oxpayments__oxtspaymentid = new Field('', Field::T_RAW);

            // set multi language fields
            foreach ($aLangs as $oLang) {
                $sTag = Registry::getLang()->getLanguageTag($oLang->id);
                $oPayment->{'oxpayments__oxdesc' . $sTag} = new Field($aTitle[$oLang->id], Field::T_RAW);
            }

            $oPayment->save();
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}

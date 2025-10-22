<?php

namespace Fatchip\FcKustom\Tests\Unit\Model\EmdPayload;


use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Field;
use Fatchip\FcKustom\Model\EmdPayload\KustomCustomerAccountInfo;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

/**
 * Class KustomCustomerAccountInfoTest
 * @package Fatchip\FcKustom\Tests\Unit\Models\EmdPayload
 * @covers \Fatchip\FcKustom\Model\EmdPayload\KustomCustomerAccountInfo
 *
 */
class KustomCustomerAccountInfoTest extends ModuleUnitTestCase
{

    /**
     * @dataProvider customerDataProvider
     * @param $user
     * @param $expectedResult
     */
    public function testGetCustomerAccountInfo($user, $expectedResult)
    {
        $accInfo = oxNew(KustomCustomerAccountInfo::class);
        $result = $accInfo->getCustomerAccountInfo($user);

        $this->assertEquals($expectedResult,$result);
    }

    public function customerDataProvider()
    {
        $expectedResult = [
            'customer_account_info' =>
                [
                    [
                        'unique_account_identifier' => "testId",
                        'account_last_modified' => "2018-03-22T10:33:29Z",
                    ],
                ],
        ];

        $user1 = $this->createKustomUser();
        $user1->oxuser__oxcreate = new Field('2018-03-21T10:33:29Z', Field::T_RAW);
        $expectedResult1 = $expectedResult;
        $expectedResult1['customer_account_info'][0]['account_registration_date'] = "2018-03-21T10:33:29Z";

        $user2 = $this->createKustomUser();
        $user2->oxuser__oxregister = new Field('2018-03-20T10:33:29Z', Field::T_RAW);
        $expectedResult2 = $expectedResult;
        $expectedResult2['customer_account_info'][0]['account_registration_date'] = "2018-03-20T10:33:29Z";

        return [
            [$user1, $expectedResult1],
            [$user2, $expectedResult2]
        ];
    }

    protected function createKustomUser()
    {
        $user = oxNew(User::class);
        $user->setId('testId');
        $user->oxuser__oxtimestamp = new Field('2018-03-22 11:33:29', Field::T_RAW);

        return $user;
    }
}

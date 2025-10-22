<?php

namespace Fatchip\FcKustom\Tests\Unit\Model;


use Fatchip\FcKustom\Model\KustomCountryList;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomCountryListTest extends ModuleUnitTestCase
{

    /**
     * @param $data
     */
    public function testLoadActiveKCOGlobalCountries()
    {
        $expectedCountries = ['8f241f11095649d18.02676059', '8f241f11096877ac0.98748826', 'a7c40f631fc920687.20179984', 'a7c40f6320aeb2ec2.72885259', 'a7c40f6321c6f6109.43859248', 'a7c40f632a0804ab5.18804076'];
        $kustomCountryList = oxNew(KustomCountryList::class);
        $kustomCountryList->loadActiveKustomCheckoutCountries();
        foreach ($kustomCountryList as $country) {
            $result[] = $country->getId();
        }
        $this->assertEquals($expectedCountries, $result);
    }

    /**
     * @param $data
     */
    public function testLoadActiveNonKustomCheckoutCountries()
    {
        $expectedCountries = Array (
            0 => 'a7c40f631fc920687.20179984',
            1 => '8f241f11095649d18.02676059',
            2 => 'a7c40f6320aeb2ec2.72885259',
            3 => 'a7c40f6321c6f6109.43859248',
            4 => '8f241f11096877ac0.98748826',
            5 => 'a7c40f632a0804ab5.18804076',
 );

        $kustomCountryList = oxNew(KustomCountryList::class);
        $kustomCountryList->loadActiveNonKustomCheckoutCountries();
        foreach ($kustomCountryList as $country) {
            $result[] = $country->getId();
        }

        $this->assertEquals($expectedCountries, $result);
    }

    /**
     * @param $data
     */
    public function testLoadActiveKustomCheckoutCountries()
    {
        $expectedCountries = [
            '8f241f11095649d18.02676059',
            '8f241f11096877ac0.98748826',
            'a7c40f631fc920687.20179984',
            'a7c40f6320aeb2ec2.72885259',
            'a7c40f6321c6f6109.43859248',
            'a7c40f632a0804ab5.18804076',
        ];
        $kustomCountryList = oxNew(KustomCountryList::class);
        $kustomCountryList->loadActiveKCOGlobalCountries();
        foreach ($kustomCountryList as $country) {
            $result[] = $country->getId();
        }

        $this->assertEquals($expectedCountries, $result);
    }
}

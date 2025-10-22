<?php

namespace Fatchip\FcKustom\Testes\Unit\Controllers;


use ReflectionClass;
use Fatchip\FcKustom\Controller\KustomValidationController;
use Fatchip\FcKustom\Core\KustomLogs;
use Fatchip\FcKustom\Core\KustomOrderValidator;
use Fatchip\FcKustom\Model\KustomPayment;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

/**
 * Class KustomValidationControllerTest
 * @package Fatchip\FcKustom\Testes\Unit\Controllers
 * @covers \Fatchip\FcKustom\Controller\KustomValidationController
 */
class KustomValidationControllerTest extends ModuleUnitTestCase
{

    public function setUp(): void
    {
        parent::setUp();
        $this->setModuleConfVar('blKustomLoggingEnabled', true, 'bool');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->setModuleConfVar('blKustomLoggingEnabled', false, 'bool');
    }

    /**
     * @dataProvider initDataProvider
     * @param $requestBody
     * @param $isValid
     * @param $errors
     * @param $eRes
     */
    public function testInit($requestBody, $isValid, $errors, $eRes)
    {
        \oxUtilsHelper::$iCode = null;
        $data                  = json_decode($requestBody, true);
        $validator             = $this->getMockBuilder(KustomOrderValidator::class)
            ->setMethods(['validateOrder', 'isValid', 'getResultErrors'])
            ->setConstructorArgs([$data])
            ->getMock();
        $validator->expects($this->once())
            ->method('isValid')
            ->willReturn($isValid);
        $validator->expects($this->any())
            ->method('getResultErrors')
            ->willReturn($errors);

        $validationController = $this->getMockBuilder(KustomValidationController::class)->setMethods(['getRequestBody', 'logKustomData', 'getValidator', 'setValidResponseHeader'])->getMock();
        $validationController->expects($this->once())
            ->method('getRequestBody')
            ->willReturn($requestBody);
        $validationController->expects($this->once())
            ->method('getValidator')
            ->willReturn($validator);
        $validationController->expects($this->once())
            ->method('logKustomData');
        $validationController->expects($this->any())
            ->method('setValidResponseHeader');


        $this->setProtectedClassProperty($validationController, 'order_id', $data['order_id']);

        $validationController->init();

        $this->assertEquals($eRes['code'], \oxUtilsHelper::$iCode);
    }

    public function initDataProvider()
    {
        $validResponse   = ['urlShouldContain' => "", 'code' => null];
        $invalidResponse = ['urlShouldContain' => "kustomInvalid=1", 'code' => 303];

        return [
            ["{\"order_id\": \"0000\"}", true, [], $validResponse],
            ["{\"order_id\": \"0001\"}", false, ['MY_ERROR' => 33], $invalidResponse],
        ];
    }

    public function testGetValidator()
    {
        $randId               = "rand_" . rand(1, 100000);
        $requestBody          = "{\"order_id\": \"$randId\", \"fake_order\": \"data\"}";
        $validationController = new KustomValidationController();
        $this->setProtectedClassProperty($validationController, 'requestBody', $requestBody);
        $class  = new ReflectionClass(get_class($validationController));
        $method = $class->getMethod('getValidator');
        $method->setAccessible(true);
        $result = $method->invokeArgs($validationController, []);

        $this->assertInstanceOf(KustomOrderValidator::class, $result);
        $this->assertEquals($randId, $this->getProtectedClassProperty($validationController, 'order_id'));
    }
}

<?php

namespace Fatchip\FcKustom\Tests\Unit\Core;

use Fatchip\FcKustom\Core\KustomClientBase;
use Fatchip\FcKustom\Core\KustomPaymentsClient;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Core\Exception\KustomOrderNotFoundException;
use Fatchip\FcKustom\Core\Exception\KustomOrderReadOnlyException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomClientBaseTest extends ModuleUnitTestCase
{

    /**
     * @dataProvider sessionDataProvider
     */
    public function testPostGetPatchAndDelete($method)
    {
        $methodReflection = new \ReflectionMethod(KustomClientBase::class, $method);
        $methodReflection->setAccessible(true);

        $response = new \Requests_Response();
        $response->body = json_encode(['test']);
        $response->status_code = 200;

        $kustomClientBase = $this->getMockForAbstractClass(KustomClientBase::class);
        $sessionMock = $this->getMockBuilder(\Requests_Session::class)
            ->setMethods([$method])->getMock();
        $sessionMock->expects($this->once())->method($method)->willReturn($response);

        $this->setProtectedClassProperty($kustomClientBase,'session',$sessionMock);

        $result = $methodReflection->invokeArgs($kustomClientBase, ['https://']);
        $this->assertEquals($response, $result);

    }

    public function sessionDataProvider()
    {
        return [
            ['post'],
            ['get'],
            ['patch'],
            ['delete']
        ];

    }

    /**
     * @dataProvider handleResponseDataprovider
     */
    public function testHandleResponse($code, $expectedException)
    {
        $method = new \ReflectionMethod(KustomClientBase::class, 'handleResponse');
        $method->setAccessible(true);

        $kustomClientBase = $this->getMockForAbstractClass(KustomClientBase::class);

        $response = new \Requests_Response();

        if($code == 400){
            $response->body = json_encode(['error_messages' => ['test']]);
        }
        $response->status_code = $code;
        !$expectedException ?: $this->expectException($expectedException);
        $result = $method->invokeArgs($kustomClientBase, [$response, __CLASS__, __METHOD__]);

        if($code === 200) {//assert only for status code 200
            $this->assertTrue($result);
        }

    }

    public function handleResponseDataprovider()
    {
        return [
            [200, null],
            [400, KustomClientException::class],
            [401, KustomWrongCredentialsException::class],
            [403, KustomOrderReadOnlyException::class],
            [404, KustomOrderNotFoundException::class],
            [0, KustomClientException::class],
            [422, KustomClientException::class],
        ];
    }
}

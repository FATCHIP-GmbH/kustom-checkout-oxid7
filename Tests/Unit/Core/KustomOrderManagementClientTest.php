<?php

namespace Fatchip\FcKustom\Tests\Unit\Core;

use Fatchip\FcKustom\Core\KustomOrderManagementClient;
use Fatchip\FcKustom\Core\Exception\KustomCaptureNotAllowedException;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Core\Exception\KustomOrderNotFoundException;
use Fatchip\FcKustom\Core\Exception\KustomOrderReadOnlyException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use Fatchip\FcKustom\Tests\Unit\ModuleUnitTestCase;

class KustomOrderManagementClientTest extends ModuleUnitTestCase
{

    /**
     * @dataProvider patchDataProvider
     */
    public function testPatchOrder($method, $params, $httpMethod)
    {
        $body = ['test' => 'test'];
        $getResponse = new \Requests_Response();
        $getResponse->body = json_encode($body);
        $getResponse->status_code = 200;

        $checkoutClient = $this->getMockBuilder(KustomOrderManagementClient::class)
            ->setMethods([$httpMethod])
            ->getMock();
        $checkoutClient->expects($this->once())->method($httpMethod)->willReturn($getResponse);

        $result = call_user_func_array([$checkoutClient, $method], $params);

        $this->assertEquals($body, $result);
    }

    public function patchDataProvider()
    {
        return [
            ['getOrder', [1], 'get'],
            ['acknowledgeOrder', [1], 'post'],
            ['cancelOrder', [1], 'post'],
            ['getAllCaptures', [1], 'get'],
            ['sendOxidOrderNr', [1, 1], 'patch'],
            ['updateOrderLines', [1, 1], 'patch'],
            ['captureOrder', [1, 1], 'post'],
            ['createOrderRefund', [1, 1], 'post'],
            ['addShippingToCapture', [1, 1, 1], 'post']
        ];
    }

    /**
     * @dataProvider handleResponseDataprovider
     */
    public function testHandleResponse($code, $expectedException)
    {
        $method = new \ReflectionMethod(KustomOrderManagementClient::class, 'handleResponse');
        $method->setAccessible(true);

        $kustomOrderManagementClient = $this->getMockBuilder(KustomOrderManagementClient::class)
            ->setMethods(['getOrder'])->getMock();
        $response = new \Requests_Response();

        if ($code !== 200) {
            $response->body = json_encode(['test' => 'test']);
        }

        if ($code == 400 || $code == 401) {
            $response->body = json_encode(['error_messages' => ['test']]);
        }

        if ($code == 404) {
            $response->body = '<title>404</title>';
        }

        if ($expectedException == KustomCaptureNotAllowedException::class) {
            $response->body = json_encode([
                'error_messages' => ['some error'],
                'error_code' => 'CAPTURE_NOT_ALLOWED']);
        }

        $response->status_code = $code;

        !$expectedException ?: $this->expectException($expectedException);
        $result = $method->invokeArgs($kustomOrderManagementClient, [$response, __CLASS__, __METHOD__]);

        if ($code === 200) {//assert only for status code 200
            $this->assertTrue($result);
        }

    }

    public function handleResponseDataprovider()
    {
        return [
            [200, null],
            [400, KustomClientException::class],
            [401, KustomWrongCredentialsException::class, 'KUSTOM_UNAUTHORIZED_REQUEST'],
            [403, KustomOrderReadOnlyException::class],
            [403, KustomCaptureNotAllowedException::class],
            [404, KustomOrderNotFoundException::class, 'KUSTOM_ORDER_NOT_FOUND'],
            [0, KustomClientException::class],
        ];
    }
}

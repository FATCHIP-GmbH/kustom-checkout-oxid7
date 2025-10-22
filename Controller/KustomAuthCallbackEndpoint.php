<?php


namespace Fatchip\FcKustom\Controller;

use Exception;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Registry;
use Fatchip\FcKustom\Core\KustomUtils;

class KustomAuthCallbackEndpoint extends FrontendController
{
    protected $requestBody = null;

    public function render()
    {
        try {
            if (!$this->validateSecurityParam()) {
                $this->outputJson(["ERROR" => "security parameter not set or incorrect"], 401);
            }

            if (!list($authToken, $sessionId) = $this->getAuthFromRequest()) {
                $this->outputJson(["ERROR" => "Bad Request. Authorization_token or session_id is missing."], 400);
            }

            KustomUtils::addAuthToken($sessionId, $authToken);
        } catch (Exception $e) {
            $this->outputJson(["ERROR" => "An error has occurred."], 500);
        }

        $this->outputJson(["SUCCESS" => "Created"], 201);
    }

    protected function validateSecurityParam()
    {
        $body = $this->getRequestBody();
        $user = oxNew(User::class);

        if (!$secret = $body["secret"]) {
            return false;
        }

        return $user->load($secret);
    }

    protected function outputJson($response, $responseCode)
    {
        http_response_code($responseCode);
        $utils = Registry::getUtils();
        $utils->setHeader("Content-Type: application/json");

        $utils->showMessageAndExit(json_encode($response));
    }

    protected function getAuthFromRequest()
    {
        $body = $this->getRequestBody();

        if (!$authToken = $body["authorization_token"]) {
            return false;
        }

        if (!$sessionId = $body["session_id"]) {
            return false;
        }

        return [$authToken, $sessionId];
    }

    protected function getRequestBody()
    {
        if (!$this->requestBody) {
            $this->requestBody = file_get_contents('php://input');
        }

        return json_decode($this->requestBody, true);
    }
}
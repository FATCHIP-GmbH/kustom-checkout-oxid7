<?php


namespace Fatchip\FcKustom\Core;


use OxidEsales\Facts\Facts;
use OxidEsales\EshopCommunity\Core\ShopVersion;
use Fatchip\FcKustom\Core\Exception\KustomClientException;
use Fatchip\FcKustom\Core\Exception\KustomOrderNotFoundException;
use Fatchip\FcKustom\Core\Exception\KustomOrderReadOnlyException;
use Fatchip\FcKustom\Core\Exception\KustomWrongCredentialsException;
use OxidEsales\Eshop\Core\Base;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\UtilsView;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridgeInterface;

abstract class KustomClientBase extends Base
{
    const TEST_API_URL = 'https://api.playground.kustom.co/';
    const LIVE_API_URL = 'https://api.kustom.co/';

    /**
     * @var \Requests_Session
     */
    protected $session;

    /**
     * @var Base | KustomClientBase
     */
    protected static $instance;

    /**
     * @var KustomOrder
     */
    protected $_oKustomOrder;

    /**
     * @var array
     */
    protected $aCredentials;

    /**
     * @param null $sCountryISO
     * @return KustomClientBase
     */
    static function getInstance($sCountryISO = null)
    {
        $calledClass = get_called_class();
        if (static::$instance === null || !static::$instance instanceof $calledClass) {

            static::$instance = new $calledClass();
            static::$instance->resolveCredentials($sCountryISO);
            static::$instance->initHttpHandler();
        }

        return static::$instance;
    }

    public function resolveCredentials($sCountryISO)
    {
        $this->aCredentials = KustomUtils::getAPICredentials($sCountryISO);
    }

    public function isTest()
    {
        return KustomUtils::getShopConfVar('blIsKustomTestMode');
    }

    static function resetInstance()
    {
        static::$instance = null;
    }

    public function setKustomOrder($oKustomOrder)
    {
        $this->_oKustomOrder = $oKustomOrder;
    }

    /**
     * @param \Requests_Session $session
     */
    protected function initHttpHandler()
    {
        $apiUrl = $this->isTest() ? self::TEST_API_URL : self::LIVE_API_URL;
        $this->session = new \Requests_Session($apiUrl, $this->getApiClientHeader());
    }

    /**
     * @param string $endpoint
     * @param array $data json
     * @param array $headers
     * @return \Requests_Response
     */
    protected function post($endpoint, $data = array(), $headers = array())
    {
        return $this->session->post($endpoint, $headers, $data);
    }

    /**
     * @param string $endpoint
     * @param array $headers
     * @return \Requests_Response
     */
    protected function get($endpoint, $headers = array())
    {
        return $this->session->get($endpoint, $headers);
    }

    /**
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return \Requests_Response
     */
    protected function patch($endpoint, $data = array(), $headers = array())
    {
        return $this->session->patch($endpoint, $headers, $data);
    }

    /**
 * @param $endpoint
 * @param array $data
 * @param array $headers
 * @return \Requests_Response
 */
    protected function delete($endpoint, $data = array(), $headers = array())
    {
        return $this->session->delete($endpoint, $headers, $data);
    }

    /**
     * @param $endpoint
     * @param array $data
     * @param array $headers
     * @return \Requests_Response
     */
    protected function put($endpoint, $data = array(), $headers = array())
    {
        return $this->session->put($endpoint, $headers, $data);
    }

    /**
     * @param \Requests_Response $oResponse
     * @param $class
     * @param $method
     * @return array|bool
     * @throws \OxidEsales\Eshop\Core\Exception\StandardException
     * @throws KustomOrderNotFoundException
     * @throws KustomOrderReadOnlyException
     * @throws KustomWrongCredentialsException
     * @throws KustomClientException
     */
    protected function handleResponse(\Requests_Response $oResponse, $class, $method)
    {
        $successCodes = array(200, 201, 204);
        $errorCodes   = array(400, 422, 500);

        if (in_array($oResponse->status_code, $successCodes)) {
            if ($oResponse->body) {
                return json_decode($oResponse->body, true);
            }

            return true;
        }
        if ($oResponse->status_code == 401) {
            throw new KustomWrongCredentialsException('Unauthorized request', $oResponse->status_code);
        }
        if ($oResponse->status_code == 404) {
            throw new KustomOrderNotFoundException($oResponse->body, 404);
        }
        if ($oResponse->status_code == 403) {
            throw new KustomOrderReadOnlyException($oResponse->body, 403);
        }
        if (in_array($oResponse->status_code, $errorCodes)) {
            $this->formatAndShowErrorMessage($oResponse);
            throw new KustomClientException($oResponse->body, $oResponse->status_code);
        }
        throw new KustomClientException('Unknown error.', $oResponse->status_code);
    }

    /**
     * @param $aErrors
     * @codeCoverageIgnore
     */
    public static function addErrors($aErrors)
    {
        foreach ($aErrors as $message) {
            Registry::get(UtilsView::class)->addErrorToDisplay($message);
        }
    }

    /**
     * @return array
     */
    protected function getApiClientHeader()
    {
        $php = phpversion();
        $phpVer = 'PHP' . $php;

        $shopEdition = (new Facts())->getEdition();
        $shopRev = ShopVersion::getVersion();
        $shopVer = 'OXID_' . $shopEdition . '_' . $shopRev;
        $oModuleConfiguration = $this->getModuleConfigs('fckustom');

        $aModuleTitle = $oModuleConfiguration->getTitle();
        if (array_key_exists('de', $aModuleTitle)) {
            $sModuleTitle = $aModuleTitle['de'];
        } else {
            $sModuleTitle = $aModuleTitle['en'];
        }
        $sModuleVer = $oModuleConfiguration->getVersion();
        $moduleInfo = str_replace(' ', '_', $sModuleTitle . "_" . $sModuleVer);

        $os = php_uname('s');
        $os .= "_" . php_uname('r');
        $os .= "_" . php_uname('m');

        return [
            'Authorization' => 'Basic ' . base64_encode(
                    "{$this->aCredentials['mid']}:{$this->aCredentials['password']}"
                ),
            'Content-Type' => 'application/json',
            'User-Agent' => 'OS/' . $os . ' Language/' . $phpVer . ' Cart/' . $shopVer . '-' . ' Plugin/' . $moduleInfo
        ];
    }

    /**
     * Logging push state message to database
     * @param $action
     * @param string|array $requestBody
     * @param $url
     * @param $responseRaw
     * @param string $order_id
     * @param $statusCode
     * @throws \Exception
     */
    protected function logKustomData($action, $requestBody, $url, $responseRaw, $order_id = '', $statusCode)
    {
        if (is_array($requestBody)) {
            $requestBody = json_encode($requestBody);
        }

        if ($order_id === '') {
            $response = json_decode($responseRaw, true);
            $order_id = isset($response['order_id']) ? $response['order_id'] : '';
        }
        $url = substr($this->session->url, 0, -1) . sprintf($url, $order_id);

        $mid        = $this->aCredentials['mid'];
        $oKustomLog = new KustomLogs;
        $aData      = array(
            'fckustom_logs__fckustom_method'      => $action,
            'fckustom_logs__fckustom_url'         => $url,
            'fckustom_logs__fckustom_orderid'     => $order_id,
            'fckustom_logs__fckustom_mid'         => $mid,
            'fckustom_logs__fckustom_statuscode'  => $statusCode,
            'fckustom_logs__fckustom_requestraw'  => $requestBody,
            'fckustom_logs__fckustom_responseraw' => $responseRaw,
            'fckustom_logs__fckustom_date'        => date("Y-m-d H:i:s"),
        );
        $oKustomLog->assign($aData);
        $oKustomLog->save();
    }

    /**
     * @return string
     */
    protected function formatOrderData()
    {
        return json_encode($this->_oKustomOrder->getOrderData());
    }

    /**
     * @param \Requests_Response $oResponse
     */
    protected function formatAndShowErrorMessage(\Requests_Response $oResponse)
    {
        $aResponse = json_decode($oResponse->body, true);
        if (is_array($aResponse)) {
            $this->addErrors($aResponse['error_messages']);

            return;
        }

        $matches = array();
        preg_match('/\<title\>(?P<msg>.+)\<\/title\>/', $oResponse->body, $matches);
        $this->addErrors(array($matches['msg']));

        return;
    }

    /**
     * @param string $moduleId
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getModuleConfigs(string $moduleId)
    {
        $oContainer = ContainerFactory::getInstance()->getContainer();
        return $oContainer->get(ModuleConfigurationDaoBridgeInterface::class)->get($moduleId);
    }
}
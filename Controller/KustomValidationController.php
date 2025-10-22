<?php


namespace Fatchip\FcKustom\Controller;


use Fatchip\FcKustom\Core\KustomLogs;
use Fatchip\FcKustom\Core\KustomOrderValidator;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;

class KustomValidationController extends FrontendController
{
    /** @var string */
    protected $order_id;

    /** @var string */
    protected $requestBody;

    /**
     * Kustom order validation callback
     * @throws \Exception
     */
    public function init()
    {
        parent::init();

        $redirectUrl       = null;
        $this->requestBody = $this->getRequestBody();
        $validator         = $this->getValidator();
        $validator->validateOrder();

        if ($validator->isValid()) {
            $responseStatus = 200;
            $this->logKustomData(
                'Validate Order',
                $this->order_id,
                'FROMKUSTOM: ' . $this->requestBody,
                $_SERVER['REQUEST_URI'],
                $responseStatus,
                $validator->getResultErrors() ?: '',
                $redirectUrl ?: ''
            );

            $this->setValidResponseHeader($responseStatus);
            Registry::getUtils()->showMessageAndExit('');
        } else {
            $sid            = Registry::get(Request::class)->getRequestEscapedParameter('s');
            $redirectUrl    = Registry::getConfig()->getShopSecureHomeURL() . "cl=basket&force_sid=$sid&kustomInvalid=1&";
            $redirectUrl    .= http_build_query($validator->getResultErrors());
            $responseStatus = 303;

            $this->logKustomData(
                'Validate Order',
                $this->order_id,
                'FROMKUSTOM: ' . $this->requestBody,
                $_SERVER['REQUEST_URI'],
                $responseStatus,
                $validator->getResultErrors(),
                $redirectUrl
            );

            Registry::getUtils()->redirect($redirectUrl, true, $responseStatus);
        }
    }

    /**
     * Logging push state message to database
     * @param $action
     * @param $order_id
     * @param string $requestBody
     * @param $url
     * @param $response
     * @param $errors
     * @param string $redirectUrl
     * @throws \Exception
     */
    protected function logKustomData($action, $order_id, $requestBody, $url, $response, $errors, $redirectUrl = '')
    {
        $oKustomLog = new KustomLogs;
        $aData      = array(
            'fckustom_logs__fckustom_method'      => $action,
            'fckustom_logs__fckustom_url'         => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $url,
            'fckustom_logs__fckustom_orderid'     => $order_id,
            'fckustom_logs__fckustom_requestraw'  => $requestBody,
            'fckustom_logs__fckustom_responseraw' => "Code: " . $response .
                                                     " \nHeader Location:" . $redirectUrl .
                                                     " \nERRORS:" . var_export($errors, true),
            'fckustom_logs__fckustom_date'        => date("Y-m-d H:i:s"),
        );
        $oKustomLog->assign($aData);
        $oKustomLog->save();
    }

    /**
     * @codeCoverageIgnore
     * @return bool|string
     */
    protected function getRequestBody()
    {
        return file_get_contents('php://input');
    }

    /**
     * @return KustomOrderValidator
     */
    protected function getValidator()
    {
        $aKustomOrderData = json_decode($this->requestBody, true);
        $this->order_id   = $aKustomOrderData['order_id'];

        return new KustomOrderValidator($aKustomOrderData);
    }

    /**
     * @codeCoverageIgnore
     * @param $responseStatus
     * @return bool
     */
    protected function setValidResponseHeader($responseStatus)
    {
        header("", true, $responseStatus);

        return true;
    }
}
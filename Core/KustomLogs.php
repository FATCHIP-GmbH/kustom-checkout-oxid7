<?php


namespace Fatchip\FcKustom\Core;


use OxidEsales\Eshop\Core\Model\BaseModel;

/**
 * Kustom model class for table 'fckustom_logs'
 */
class KustomLogs extends BaseModel
{
    protected $validObjectIds = [
        'order_id',
//        'authorization_token'
    ];

    /**
     * Class constructor, initiates parent constructor.
     * @codeCoverageIgnore
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->init('fckustom_logs');
    }

    /**
     * @throws \Exception
     * @return bool|string
     */
    public function save()
    {
        if (KustomUtils::getShopConfVar('blKustomLoggingEnabled')) {
            return parent::save();
        }
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function logData($action, $requestBody, $url, $object_id, $response, $statusCode, $mid = '')
    {
        if ($object_id === null) {
            $object_id = $this->resolveObjectId($response);
        }

        if (is_array($response)) {
            $response = json_encode($response);
        }

        if (is_array($requestBody)) {
            $requestBody = json_encode($requestBody);
        }

        $aData      = array(
            'fckustom_logs__fckustom_method'      => $action,
            'fckustom_logs__fckustom_url'         => $url,
            'fckustom_logs__fckustom_orderid'     => $object_id,
            'fckustom_logs__fckustom_mid'         => $mid,
            'fckustom_logs__fckustom_statuscode'  => $statusCode,
            'fckustom_logs__fckustom_requestraw'  => $requestBody,
            'fckustom_logs__fckustom_responseraw' => $response,
            'fckustom_logs__fckustom_date'        => date("Y-m-d H:i:s"),
        );
        $this->assign($aData);
        $this->save();
    }

    /**
     * @codeCoverageIgnore
     */
    protected function resolveObjectId($data) {
        if (is_string($data)) {
            $data = (array)json_decode($data, true);
        }
        foreach($this->validObjectIds as $key) {
            if (isset($data[$key])) {
                return $data[$key];
            }
        }
        return '';
    }
}
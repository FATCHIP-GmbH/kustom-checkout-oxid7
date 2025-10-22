<?php


namespace Fatchip\FcKustom\Model;

class KustomUserPayment extends KustomUserPayment_parent
{
    /**
     * @return string
     */
    public function getBadgeUrl()
    {
        return '//cdn.klarna.com/1.0/shared/image/generic/logo/en_gb/basic/logo_black.png';
    }
}
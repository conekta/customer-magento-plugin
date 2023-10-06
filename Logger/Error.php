<?php

namespace Conekta\Payments\Logger;
use Magento\Framework\Logger\Handler\Base;
class Error extends Base
{
    /**
     * File name
     *
     * @var string
     */
    protected $fileName = '/var/log/conekta_error.log';

    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = \Monolog\Logger::ERROR;
}

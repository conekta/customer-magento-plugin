<?php

namespace Conekta\Payments\Logger;

use Conekta\Payments\Helper\Data;
use Magento\Framework\App\ObjectManager;
use Monolog\Logger as MonoLogger;

class Logger
{
    private const LoggerName = 'ConektaPaymentsLogger';
    /**
     * @var MonoLogger
     */
    private $monolog;

    public function __construct()
    {
        $this->monolog = new MonoLogger(self::LoggerName);
    }

    /**
     * @param int $level
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function addRecord(int $level, string $message, array $context = []): bool
    {
        $objectManager = ObjectManager::getInstance();
        $conektaHelper = $objectManager->create(Data::class);

        if ((int)$conektaHelper->getConfigData('conekta/conekta_global', 'debug')) {
            return $this->monolog->addRecord($level, $message, $context);
        }

        return true;
    }

    /**
     * @param string $string
     * @param array $customerRequest
     * @return void
     */
    public function info(string $string, array $customerRequest = []): void
    {
        $this->monolog->info($string, $customerRequest);
    }

    /**
     * @param string $string
     * @param array $array
     * @return void
     */
    public function error(string $string, array $array = []): void
    {
        $this->monolog->error($string, $array);
    }

    /**
     * @param string $message
     * @param array $array
     * @return void
     */
    public function debug(string $message, array $array = [])
    {
        $this->monolog->debug($message, $array);
    }

    /**
     * @param \Exception $e
     * @param array $orderParams
     * @return void
     */
    public function critical(\Exception $e, array $orderParams)
    {
        $this->monolog->critical($e->getMessage(), $orderParams);
    }
}

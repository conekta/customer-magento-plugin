<?php
namespace Conekta\Payments\Logger;

use Conekta\Payments\Helper\Data;

class Logger extends \Monolog\Logger
{
    public function addRecord($level, $message, array $context = [])
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $conektaHelper = $objectManager->create(Data::class);

        if ((int)$conektaHelper->getConfigData('conekta/conekta_global', 'debug')) {
            return parent::addRecord($level, $message, $context);
        }
        return true;
    }
}

<?php

namespace Conekta\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Conekta\Payments\Model\Config;
use Magento\Framework\Event\Observer;
use Conekta\Payments\Logger\Logger as ConektaLogger;

/**
 * Class CreateWebhook
 */
class NotificationObserver implements ObserverInterface
{

    private $_conektaLogger;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var ManagerInterface
     */
    protected $messageManager;
    /**
     * @param Config $config
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Config $config,
        ConektaLogger $conektaLogger,
        ManagerInterface $messageManager
    ) {
        $this->config = $config;
        $this->messageManager = $messageManager;
        $this->_conektaLogger = $conektaLogger;
    }
    /**
     * Create Webhook
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $this->_conektaLogger->info('NotificationObserver Observer');
        $this->_conektaLogger->info('NotificationObserver Observer', ['event'=>$observer->getEvent()]);
        return -1;
    }
}

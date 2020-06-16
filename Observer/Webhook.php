<?php

namespace Conekta\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Conekta\Payments\Model\Config;
use Magento\Framework\Event\Observer;

/**
 * Class CreateWebhook
 */
class Webhook implements ObserverInterface
{
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
        ManagerInterface $messageManager
    ) {
        $this->config = $config;
        $this->messageManager = $messageManager;
    }
    /**
     * Create Webhook
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        return $this->config->createWebhook();
    }
}

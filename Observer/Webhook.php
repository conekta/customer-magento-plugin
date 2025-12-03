<?php

namespace Conekta\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Conekta\Payments\Model\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Validator\Exception;
use Conekta\Payments\Helper\Data as ConektaHelper;

/**
 * Class CreateWebhook
 */
class Webhook implements ObserverInterface
{
    /**
     * @var Config
     */
    protected Config $config;

    protected ConektaHelper $_conektaHelper;
    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $messageManager;
    /**
     * @param Config $config
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Config $config,
        ManagerInterface $messageManager,
        ConektaHelper $_conektaHelper
    ) {
        $this->config = $config;
        $this->messageManager = $messageManager;
        $this->_conektaHelper = $_conektaHelper;
    }

    /**
     * Create Webhook
     *
     * @param Observer $observer
     * @throws Exception
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        if ($this->_conektaHelper->isCashEnabled()
            || $this->_conektaHelper->isBankTransferEnabled()
            || $this->_conektaHelper->isCreditCardEnabled()
            || $this->_conektaHelper->isBnplEnabled()
            || $this->_conektaHelper->isPayByBankEnabled()
        ) {
            $this->config->createWebhook();
        }
    }
}

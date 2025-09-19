<?php

namespace Conekta\Payments\Observer;

use Conekta\Payments\Logger\Logger;
use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class StatusObserver implements ObserverInterface
{
    public Logger $_logger ;

    public function __construct(Logger $logger)
    {
        $this->_logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $this->_logger->info("execute StatusObserver");
        $order = $observer->getEvent()->getOrder();
        if ($order->getPayment()->getMethod() != ConfigProvider::CODE ) {
            return;
        }

        $paymentMethodConekta = $order->getPayment()->getAdditionalInformation('payment_method');
        $this->_logger->info("execute paymentMethodConekta",["paymentMethodConekta"=> $paymentMethodConekta]);
        if (!in_array($paymentMethodConekta, [ConfigProvider::PAYMENT_METHOD_CASH,ConfigProvider::PAYMENT_METHOD_BANK_TRANSFER,ConfigProvider::PAYMENT_METHOD_BNPL])) {
            return;
        }

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->save();
    }
}
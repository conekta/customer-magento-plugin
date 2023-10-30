<?php

namespace Conekta\Payments\Observer;

use Conekta\Payments\Logger\Logger;
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
        $this->_logger->info("Se ha creado StatusObserver");
        $order = $observer->getEvent()->getOrder();
        // Obtén los datos adicionales (additional_data)
        $additionalData = $order->getData('additional_data');

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->save();
        $paymentMethod = $order->getPayment()->getMethod();
        $this->_logger->info("Se ha creado una ", ["data"=>$additionalData, "method"=> $paymentMethod]);
    }
}
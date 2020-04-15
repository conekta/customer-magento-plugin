<?php

namespace Conekta\Payments\Controller\Webhook;

use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;
class Index extends Action
{
    public function __construct(\Magento\Framework\App\Action\Context $context) {
        parent::__construct($context);
    }
    public function execute() {
        $body = @file_get_contents('php://input');
        $event = json_decode($body);
        
        $charge = $event->data->object;
        $order = $this->_objectManager->create('Magento\Sales\Model\Order');
        
        if ($charge->status === "paid"){
            try{ 
                $order->loadByIncrementId($charge->metadata->checkout_id);
                $order->setSate(Order::STATE_COMPLETE);
                $order->setStatus(Order::STATE_COMPLETE);
                
                $order->addStatusHistoryComment("Payment received successfully")
                        ->setIsCustomerNotified(true);
                
                $order->save();
                
                header('HTTP/1.1 200 OK');
                return;
            } catch (\Exception $e) {
                \Magento\Framework\App\ObjectManager::getInstance()
                                                                ->get(\Psr\Log\LoggerInterface::class)
                                                                ->critical(
                                                                    'Error processing webhook notification', 
                                                                    ['exception' => $e]
                                                                );
            }
        }
    }

}
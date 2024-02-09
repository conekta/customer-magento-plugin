<?php

namespace Conekta\Payments\Gateway\Http\Client\CreditCard;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Exception;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Event\Observer;
class TransactionRefund implements ObserverInterface
{
    protected ConektaHelper $_conektaHelper;

    private ConektaLogger $_conektaLogger;

    private Logger $logger;

    /**
     * @var ConektaApiClient
     */
    private ConektaApiClient $conektaApiClient;

    public function __construct(
        ConektaHelper      $conektaHelper,
        ConektaLogger      $conektaLogger,
        ConektaApiClient   $conektaApiClient,
        Logger             $logger
    )
    {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->conektaApiClient = $conektaApiClient;
        $this->logger = $logger;

        $this->_conektaLogger->info('HTTP Client CreditCard TransactionRefund :: __construct');
    }

    public function execute(Observer $observer)
    {
        $this->_conektaLogger->info('HTTP Client CreditCard TransactionRefund :: placeRequest');

        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();

        if ($order->getPayment()->getMethod() != ConfigProvider::CODE ) {
            return;
        }
        $paymentMethodConekta = $order->getPayment()->getAdditionalInformation('payment_method');
        $this->_conektaLogger->info("execute paymentMethodConekta",["paymentMethodConekta"=> $paymentMethodConekta]);
        if ($paymentMethodConekta != ConfigProvider::PAYMENT_METHOD_CREDIT_CARD) {
            return;
        }

        $conektaOrderId = $order->getExtOrderId();

        $amount = $creditmemo->getGrandTotal() * 100;
        try {
            $refund = $this->conektaApiClient->orderRefund($conektaOrderId, [
                'reason' => 'requested_by_client',
                'amount' => $amount
            ]);
            if (!empty( $refund->getCharges()->getData()) &&  !empty($refund->getCharges()->getData()[0]->getRefunds()->getData())){
                $refundId = $refund->getCharges()->getData()[0]->getRefunds()->getData()[0]->getId();
                $order->addCommentToStatusHistory(
                    __('Conekta refund id #%1.', $refundId)
                )
                    ->setIsCustomerNotified(false)
                    ->save();
            }
        } catch (Exception $e) {
            $error_code = $e->getMessage();
            $this->logger->debug(
                [
                    'transaction_id' => $conektaOrderId,
                    'exception' => $e->getMessage()
                ]
            );

            $this->_conektaLogger->info(
                'HTTP Client  CreditCard TransactionRefund :: placeRequest: Payment refund error ' . $error_code
            );
        }
    }
}

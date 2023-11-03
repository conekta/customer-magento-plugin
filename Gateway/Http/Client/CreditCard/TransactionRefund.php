<?php

namespace Conekta\Payments\Gateway\Http\Client\CreditCard;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Exception;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

class TransactionRefund implements ClientInterface
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

    public function placeRequest(TransferInterface $transferObject): array
    {
        $this->_conektaLogger->info('HTTP Client CreditCard TransactionRefund :: placeRequest');

        $request = $transferObject->getBody();
        $transactionId = $request['payment_transaction_id'];
        $amount = (int)($request['payment_transaction_amount'] * 100);
        $response = [];
        $response['refund_result']['transaction_id'] = $transactionId;
        try {
            $order = $this->conektaApiClient->getOrderByID($transactionId);
            $this->conektaApiClient->orderRefund($order->getId(), [
                'reason' => 'requested_by_client',
                'amount' => $amount
            ]);

            $response['refund_result']['status'] = 'SUCCESS';
            $response['refund_result']['status_message'] = 'Refunded';
        } catch (Exception $e) {
            $error_code = $e->getMessage();
            $this->logger->debug(
                [
                    'transaction_id' => $transactionId,
                    'exception' => $e->getMessage()
                ]
            );

            $this->_conektaLogger->info(
                'HTTP Client  CreditCard TransactionRefund :: placeRequest: Payment refund error ' . $error_code
            );
            $response['refund_result']['status'] = 'ERROR';
            $response['refund_result']['status_message'] = $error_code;
        }

        $response['transaction_id'] = $transactionId;

        $this->_conektaLogger->info(
            'HTTP Client TransactionCapture :: placeRequest',
            [
                'request' => $request,
                'response' => $response
            ]
        );

        return $response;
    }
}

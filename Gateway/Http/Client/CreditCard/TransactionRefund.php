<?php
namespace Conekta\Payments\Gateway\Http\Client\CreditCard;

use Conekta\Payments\Gateway\Http\Util\HttpUtil;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Model\Context;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Conekta\Order as ConektaOrder;

class TransactionRefund implements ClientInterface
{
    protected $_conektaHelper;

    private $_conektaLogger;

    private $logger;

    protected $_httpUtil;

    private $_conektaOrder;

    public function __construct(
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        ConektaOrder $conektaOrder,
        HttpUtil $httpUtil,
        Context $context,
        EncryptorInterface $encryptor,
        Logger $logger,
        array $data = []
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaOrder = $conektaOrder;
        $this->_httpUtil = $httpUtil;
        $this->_encryptor = $encryptor;
        $this->logger = $logger;

        $this->_conektaLogger->info('HTTP Client CreditCard TransactionRefund :: __construct');

        $config = [
            'locale' => 'es'
        ];
        $this->_httpUtil->setupConektaClient($config);
    }

    public function placeRequest(TransferInterface $transferObject)
    {
        $this->_conektaLogger->info('HTTP Client CreditCard TransactionRefund :: placeRequest');

        $request = $transferObject->getBody();
        $transactionId = $request['payment_transaction_id'];
        $amount = (int)($request['payment_transaction_amount'] * 100);
        $response = [];
        $response['refund_result']['transaction_id'] = $transactionId;
        try {
            $order = $this->_conektaOrder->find($transactionId);
            $order->refund([
                'reason' => 'requested_by_client',
                'amount' => $amount
            ]);
            $response['refund_result']['status'] = 'SUCCESS';
            $response['refund_result']['status_message'] = 'Refunded';
        } catch (\Exception $e) {
            $error_code = $e->getMessage();
            $this->logger->debug(
                [
                    'transaction_id' => $transactionId,
                    'exception'      => $e->getMessage()
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

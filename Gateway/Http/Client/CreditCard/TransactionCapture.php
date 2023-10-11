<?php

namespace Conekta\Payments\Gateway\Http\Client\CreditCard;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Conekta\Payments\Model\ConektaSalesOrderFactory;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

class TransactionCapture implements ClientInterface
{
    const SUCCESS = 1;

    /**
     * @var Logger
     */
    private Logger $logger;

    protected ConektaHelper $_conektaHelper;

    private ConektaLogger $_conektaLogger;

    protected ConektaSalesOrderFactory $conektaSalesOrderFactory;

    /**
     * @var ConektaApiClient
     */
    private ConektaApiClient $conektaApiClient;

    /**
     * @param Logger $logger
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     * @param ConektaApiClient $conektaApiClient
     * @param ConektaSalesOrderFactory $conektaSalesOrderFactory
     */
    public function __construct(
        Logger                   $logger,
        ConektaHelper            $conektaHelper,
        ConektaLogger            $conektaLogger,
        ConektaApiClient         $conektaApiClient,
        ConektaSalesOrderFactory $conektaSalesOrderFactory
    )
    {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->conektaApiClient = $conektaApiClient;
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: __construct');
        $this->logger = $logger;
        $this->conektaSalesOrderFactory = $conektaSalesOrderFactory;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws LocalizedException
     * @throws Exception
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: placeRequest');
        $request = $transferObject->getBody();

        $orderParams['currency'] = $request['CURRENCY'];
        $orderParams['line_items'] = $request['line_items'];
        $orderParams['tax_lines'] = $request['tax_lines'];
        $orderParams['customer_info'] = $request['customer_info'];
        $orderParams['discount_lines'] = $request['discount_lines'];
        if (!empty($request['shipping_lines'])) {
            $orderParams['shipping_lines'] = $request['shipping_lines'];
        }
        if (!empty($request['shipping_contact'])) {
            $orderParams['shipping_contact'] = $request['shipping_contact'];
        }
        $orderParams['metadata'] = $request['metadata'];
        $chargeParams = $request['payment_method_details'];

        $txn_id = '';
        $ord_id = '';
        $error_code = '';

        try {
            $newOrder = $this->conektaApiClient->createOrder($orderParams);
            $newCharge = $this->conektaApiClient->createOrderCharge($newOrder->getId(), $chargeParams);
            if (!empty($newCharge->getId()) ) {
                $result_code = 1;
                $txn_id = $newCharge->getId();
                $ord_id = $newOrder->getId();

                $this->conektaSalesOrderFactory
                    ->create()
                    ->setData([
                        ConektaSalesOrderInterface::CONEKTA_ORDER_ID => $ord_id,
                        ConektaSalesOrderInterface::INCREMENT_ORDER_ID => $request['metadata']['order_id']
                    ])
                    ->save();
            } else {
                $result_code = 666;
            }
        } catch (Exception $e) {
            $this->logger->debug(
                [
                    'request' => $request,
                    'response' => $e->getMessage()
                ]
            );
            $this->_conektaLogger->info(
                'HTTP Client TransactionCapture :: placeRequest: Payment capturing error ' . $e->getMessage()
            );

            $error_code = $e->getMessage();
            throw new LocalizedException(__($error_code));
        }

        $response = $this->generateResponseForCode(
            $result_code,
            $txn_id,
            $ord_id
        );
        $response['error_code'] = $error_code;
        $response['payment_method_details'] = $request['payment_method_details'];

        $this->logger->debug(
            [
                'request' => $request,
                'response' => $response
            ]
        );

        $this->_conektaLogger->info(
            'HTTP Client TransactionCapture :: placeRequest',
            [
                'request' => $request,
                'response' => $response
            ]
        );

        return $response;
    }

    /**
     * @throws Exception
     */
    protected function generateResponseForCode($resultCode, $txn_id, $ord_id): array
    {
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: generateResponseForCode');

        if (empty($txn_id)) {
            $txn_id = $this->generateTxnId();
        }
        return array_merge(
            [
                'RESULT_CODE' => $resultCode,
                'TXN_ID' => $txn_id,
                'ORD_ID' => $ord_id
            ]
        );
    }

    /**
     * @throws Exception
     */
    protected function generateTxnId(): string
    {
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: generateTxnId');

        return sha1(random_int(0, 1000));
    }
}

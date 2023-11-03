<?php

namespace Conekta\Payments\Gateway\Http\Client\BankTransfer;

use Conekta\ApiException;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Conekta\Payments\Model\ConektaSalesOrderFactory;

class TransactionAuthorize implements ClientInterface
{
    const SUCCESS = 1;
    const FAILURE = 0;

    /**
     * @var array
     */
    private array $results = [
        self::SUCCESS,
        self::FAILURE
    ];

    /**
     * @var Logger
     */
    private $logger;

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
        $this->_conektaLogger->info('HTTP Client BankTransfer TransactionAuthorize :: __construct');
        $this->logger = $logger;
        $this->conektaSalesOrderFactory = $conektaSalesOrderFactory;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws ApiException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $this->_conektaLogger->info('HTTP Client BankTransfer TransactionAuthorize :: placeRequest');
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

        $result_code = '';
        $txn_id = '';
        $ord_id = '';
        $error_code = '';

        try {
            $conektaOrder = $this->conektaApiClient->createOrder($orderParams);

            $charge = $this->conektaApiClient->createOrderCharge($conektaOrder->getId(), $chargeParams);

            if ($charge->getId() && $conektaOrder->getId()) {
                $result_code = 1;
                $txn_id = $charge->getId();
                $ord_id = $conektaOrder->getId();

                $this->conektaSalesOrderFactory
                    ->create()
                    ->setData([
                        ConektaSalesOrderInterface::CONEKTA_ORDER_ID => $ord_id,
                        ConektaSalesOrderInterface::INCREMENT_ORDER_ID => $orderParams['metadata']['order_id']
                    ])
                    ->save();

            } else {
                $result_code = 666;
            }
        } catch (ApiException $e) {
            $this->_conektaLogger->error(__('[Conekta]: Payment capturing error.'));
            $this->logger->debug(
                [
                    'request' => $request,
                    'response' => $e->getMessage()
                ]
            );
            $this->_conektaLogger->info(
                'HTTP Client BankTransfer TransactionAuthorize :: placeRequest: Payment authorize error ' . $e->getMessage()
            );
            throw new ApiException(__($e->getMessage()));
        }

        $response = $this->generateResponseForCode(
            $result_code,
            $txn_id,
            $ord_id
        );

        $response['offline_info'] = [
            "type" => $charge->getPaymentMethod()->getType(),
            "data" => [
                "clabe" => $charge->getPaymentMethod()->getClabe(),
                "bank_name" => $charge->getPaymentMethod()->getBank(),
                "expires_at" => $charge->getPaymentMethod()->getExpiresAt()
            ]
        ];

        $response['error_code'] = $error_code;

        $this->logger->debug(
            [
                'request' => $request,
                'response' => $response
            ]
        );

        $this->_conektaLogger->info(
            'HTTP Client BankTransfer TransactionAuthorize :: placeRequest',
            [
                'request' => $request,
                'response' => $response
            ]
        );

        $response['payment_method_details'] = $request['payment_method_details'];

        return $response;
    }

    protected function generateResponseForCode($resultCode, $txn_id, $ord_id): array
    {
        $this->_conektaLogger->info('HTTP Client BankTransfer TransactionAuthorize :: generateResponseForCode');

        return array_merge(
            [
                'RESULT_CODE' => $resultCode,
                'TXN_ID' => $txn_id,
                'ORD_ID' => $ord_id
            ]
        );
    }
}
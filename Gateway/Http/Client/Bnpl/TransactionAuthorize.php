<?php
namespace Conekta\Payments\Gateway\Http\Client\Bnpl;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\ConektaSalesOrderFactory;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Psr\Log\LoggerInterface;
use Conekta\ApiException;

class TransactionAuthorize implements ClientInterface
{
    const TXN_ID = 'TXN_ID';
    const ORD_ID = 'ORD_ID';

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ConektaLogger
     */
    private ConektaLogger $_conektaLogger;

    /**
     * @var ConektaApiClient
     */
    private ConektaApiClient $conektaApiClient;

    /**
     * @var ConektaSalesOrderFactory
     */
    private ConektaSalesOrderFactory $conektaSalesOrderFactory;

    /**
     * @param LoggerInterface $logger
     * @param ConektaLogger $conektaLogger
     * @param ConektaApiClient $conektaApiClient
     * @param ConektaSalesOrderFactory $conektaSalesOrderFactory
     */
    public function __construct(
        LoggerInterface $logger,
        ConektaLogger $conektaLogger,
        ConektaApiClient $conektaApiClient,
        ConektaSalesOrderFactory $conektaSalesOrderFactory
    ) {
        $this->logger = $logger;
        $this->_conektaLogger = $conektaLogger;
        $this->conektaApiClient = $conektaApiClient;
        $this->conektaSalesOrderFactory = $conektaSalesOrderFactory;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();
        $this->_conektaLogger->info('HTTP Client BNPL TransactionAuthorize :: placeRequest', $request);

        $orderParams = $request['order'];
        $chargeParams = $request['charge_request'];

        $result_code = 0;
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
                'HTTP Client BNPL TransactionAuthorize :: placeRequest: Payment authorize error ' . $e->getMessage()
            );
            throw new ApiException(__($e->getMessage()));
        }

        $response = $this->generateResponseForCode(
            $result_code,
            $txn_id,
            $ord_id
        );

        // BNPL specific offline info
        $paymentMethod = $charge->getPaymentMethod();
        $response['offline_info'] = [
            "type" => $paymentMethod->getType(),
            "data" => [
                "expires_at" => $paymentMethod->getExpiresAt(),
                "reference" => $paymentMethod->getReference() ?? null,
                "monthly_installments" => $paymentMethod->getMonthlyInstallments() ?? null,
                "payment_url" => $paymentMethod->getPaymentUrl() ?? null,
                "status" => $charge->getStatus() ?? 'pending'
            ]
        ];

        $response['error_code'] = $error_code;

        $this->logger->debug(
            [
                'request' => $request,
                'response' => $response
            ]
        );

        $this->_conektaLogger->info('HTTP Client BNPL TransactionAuthorize :: placeRequest', $response);

        return $response;
    }

    /**
     * Generates response
     *
     * @param int $resultCode
     * @param string $txnId
     * @param string $ordId
     * @return array
     */
    protected function generateResponseForCode(int $resultCode, string $txnId, string $ordId): array
    {
        return [
            self::TXN_ID => $txnId,
            self::ORD_ID => $ordId,
            'RESULT_CODE' => $resultCode
        ];
    }
}

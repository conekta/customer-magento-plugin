<?php
namespace Conekta\Payments\Gateway\Http\Client\BankTransfer;

use Conekta\ApiException;
use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Conekta\Order as ConektaOrder;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Conekta\Payments\Model\ConektaSalesOrderFactory;

class TransactionAuthorize implements ClientInterface
{
    const SUCCESS = 1;
    const FAILURE = 0;

    /**
     * @var array
     */
    private $results = [
        self::SUCCESS,
        self::FAILURE
    ];

    /**
     * @var Logger
     */
    private $logger;

    protected $_conektaHelper;

    private $_conektaLogger;

    /**
     * @var ConektaApiClient
     */
    private $conektaApiClient;

    protected $_httpUtil;

    protected $conektaSalesOrderFactory;

    /**
     * @param Logger $logger
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     */
    public function __construct(
        Logger $logger,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        ConektaApiClient $conektaApiClient,
        ConektaSalesOrderFactory $conektaSalesOrderFactory
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->conektaApiClient = $conektaApiClient;
        $this->_conektaLogger->info('HTTP Client BankTransfer TransactionAuthorize :: __construct');
        $this->logger = $logger;
        $this->conektaSalesOrderFactory = $conektaSalesOrderFactory;

        $config = [
            'locale' => 'es'
        ];
        $this->_httpUtil->setupConektaClient($config);
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws ApiException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $this->_conektaLogger->info('HTTP Client BankTransfer TransactionAuthorize :: placeRequest');
        $request = $transferObject->getBody();

        $orderParams['currency']         = $request['CURRENCY'];
        $orderParams['line_items']       = $request['line_items'];
        $orderParams['tax_lines']        = $request['tax_lines'];
        $orderParams['customer_info']    = $request['customer_info'];
        $orderParams['discount_lines']   = $request['discount_lines'];
        if (!empty($request['shipping_lines'])) {
            $orderParams['shipping_lines']   = $request['shipping_lines'];
        }
        if (!empty($request['shipping_contact'])) {
            $orderParams['shipping_contact'] = $request['shipping_contact'];
        }
        $orderParams['metadata']         = $request['metadata'];
        $chargeParams = $request['payment_method_details'];

        $result_code = '';
        $txn_id = '';
        $ord_id = '';
        $error_code = '';

        try {
            $conektaOrder= $this->conektaApiClient->createOrder($orderParams);
            
            $charge = $this->conektaApiClient->createOrderCharge($conektaOrder->getId(), $chargeParams);

            if (!empty($charge->getId()) && !empty($conektaOrder->getId())) {
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
            $error_code = $e->getMessage();
            $result_code = 666;
            $this->logger->error(__('[Conekta]: Payment capturing error.'));
            $this->_conektaHelper->deleteSavedCard($orderParams, $chargeParams);
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
                "clabe"         => $charge->getPaymentMethod()->getClabe(),
                "bank_name"     => $charge->getPaymentMethod()->getBank(),
                "expires_at"    => $charge->getPaymentMethod()->getExpiresAt()
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

        $response['payment_method_details'] =  $request['payment_method_details'];

        return $response;
    }

    protected function generateResponseForCode($resultCode, $txn_id, $ord_id)
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

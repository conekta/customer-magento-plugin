<?php

namespace Conekta\Payments\Gateway\Http\Client\EmbedForm;

use Conekta\Model\ChargeResponse;
use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Conekta\Payments\Model\ConektaSalesOrderFactory;
use Conekta\Payments\Model\ConektaQuoteFactory;
use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Exception;

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

    protected $conektaSalesOrderFactory;

    /**
     * @var ConektaQuoteFactory
     */
    protected $conektaQuoteFactory;

    /**
     * @var ConektaApiClient
     */
    private $conektaApiClient;

    /**
     * @var ChargeResponse
     */
    private $charge;

    /**
     * @param Logger $logger
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     * @param ConektaApiClient $conektaApiClient
     * @param ConektaSalesOrderFactory $conektaSalesOrderFactory
     * @param ConektaQuoteFactory $conektaQuoteFactory
     */
    public function __construct(
        Logger                   $logger,
        ConektaHelper            $conektaHelper,
        ConektaLogger            $conektaLogger,
        ConektaApiClient         $conektaApiClient,
        ConektaSalesOrderFactory $conektaSalesOrderFactory,
        ConektaQuoteFactory      $conektaQuoteFactory
    )
    {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: __construct');
        $this->logger = $logger;
        $this->conektaSalesOrderFactory = $conektaSalesOrderFactory;
        $this->conektaQuoteFactory = $conektaQuoteFactory;
        $this->conektaApiClient = $conektaApiClient;
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
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: placeRequest', $request);

        $txnId = $request['txn_id'];
        $conektaOrderId = $request['order_id'];

        // For Pay by Bank, order_id comes as 'pending' from frontend
        // We need to get the real conekta_order_id from conekta_quote table
        if ($conektaOrderId === 'pending' && isset($request['quote_id'])) {
            $quoteId = $request['quote_id'];
            $conektaQuote = $this->conektaQuoteFactory->create()->load($quoteId, 'quote_id');
            if ($conektaQuote->getId() && $conektaQuote->getConektaOrderId()) {
                $conektaOrderId = $conektaQuote->getConektaOrderId();
                $this->_conektaLogger->info('PayByBank: Retrieved real conekta_order_id from quote', [
                    'quote_id' => $quoteId,
                    'conekta_order_id' => $conektaOrderId
                ]);
            }
        }

        $this->conektaSalesOrderFactory
            ->create()
            ->setData([
                ConektaSalesOrderInterface::CONEKTA_ORDER_ID => $conektaOrderId,
                ConektaSalesOrderInterface::INCREMENT_ORDER_ID => $request['metadata']['order_id']
            ])
            ->save();

        $paymentMethod = $request['payment_method_details']['payment_method']['type'];
        $response = [];
        
        //If is offline-like payment, add extra info needed
        if ($paymentMethod == ConfigProvider::PAYMENT_METHOD_CASH ||
            $paymentMethod == ConfigProvider::PAYMENT_METHOD_BANK_TRANSFER ||
            $paymentMethod == ConfigProvider::PAYMENT_METHOD_BNPL ||
            $paymentMethod == ConfigProvider::PAYMENT_METHOD_PAY_BY_BANK
        ) {
            $response['offline_info'] = [];
            
            // Special handling for Pay By Bank with frontend data
            if ($paymentMethod == ConfigProvider::PAYMENT_METHOD_PAY_BY_BANK) {
                $hasRedirectUrl = isset($request['payment_method_details']['payment_method']['redirect_url']) && 
                                  !empty($request['payment_method_details']['payment_method']['redirect_url']);
                $hasDeepLink = isset($request['payment_method_details']['payment_method']['deep_link']) && 
                               !empty($request['payment_method_details']['payment_method']['deep_link']);
                
                if ($txnId === 'pending' || $hasRedirectUrl || $hasDeepLink) {
                    $expirationMinutes = $this->_conektaHelper->getPayByBankExpirationMinutes();

                    $response['offline_info'] = [
                        "type" => "payByBank",
                        "data" => [
                            "redirect_url" => $request['payment_method_details']['payment_method']['redirect_url'] ?? '',
                            "deep_link" => $request['payment_method_details']['payment_method']['deep_link'] ?? '',
                            "reference" => $request['payment_method_details']['payment_method']['reference'] ?? '',
                            "expires_at" => time() + ($expirationMinutes * 60)
                        ]
                    ];
                    // Skip API call for Pay By Bank when frontend data is available
                    return $this->generateResponseForCode(
                        $response,
                        1,
                        $txnId,
                        $conektaOrderId
                    ) + [
                        'error_code' => '',
                        'payment_method_details' => $request['payment_method_details']
                    ];
                }
            }
            
            // Fetch offline info from Conekta API for all offline payment methods
            try {
                $conektaOrder = $this->conektaApiClient->getOrderByID($request['order_id']);
                $charge = $conektaOrder->getCharges()->getData()[0];

                $txnId = $charge->getID();
                $paymentMethodResponse = $charge->getPaymentMethod();
                $response['offline_info'] = [
                    "type" => $paymentMethodResponse->getType(),
                    "data" => [
                        "expires_at" => $paymentMethodResponse->getExpiresAt()
                    ]
                ];

                if ($paymentMethod == ConfigProvider::PAYMENT_METHOD_CASH) {
                    $response['offline_info']['data']['barcode_url'] = $paymentMethodResponse->getBarcodeUrl();
                    $response['offline_info']['data']['reference'] = $paymentMethodResponse->getReference();
                } elseif ($paymentMethod == ConfigProvider::PAYMENT_METHOD_BANK_TRANSFER) {
                    $response['offline_info']['data']['clabe'] = $paymentMethodResponse->getClabe();
                    $response['offline_info']['data']['bank_name'] = $paymentMethodResponse->getBank();
                } elseif ($paymentMethod == ConfigProvider::PAYMENT_METHOD_BNPL) {
                    // BNPL does not have a reference
                } elseif ($paymentMethod == ConfigProvider::PAYMENT_METHOD_PAY_BY_BANK) {
                    if (method_exists($paymentMethodResponse, 'getDeepLink')) {
                        $response['offline_info']['data']['deep_link'] = $paymentMethodResponse->getDeepLink();
                    }
                    if (method_exists($paymentMethodResponse, 'getRedirectUrl')) {
                        $response['offline_info']['data']['redirect_url'] = $paymentMethodResponse->getRedirectUrl();
                    }
                    if (method_exists($paymentMethodResponse, 'getReference')) {
                        $response['offline_info']['data']['reference'] = $paymentMethodResponse->getReference();
                    }
                }
            } catch (Exception $e) {
                $this->_conektaLogger->error(
                    'EmbedForm :: HTTP Client TransactionCapture :: cannot get offline info. ',
                    ['exception' => $e]
                );
            }
        }

        $response = $this->generateResponseForCode(
            $response,
            1,
            $txnId,
            $request['order_id']
        );
        $response['error_code'] = '';
        $response['payment_method_details'] = $request['payment_method_details'];

        $this->_conektaLogger->info(
            'HTTP Client TransactionCapture Iframe Payment :: placeRequest',
            [
                'request' => $request,
                'response' => $response
            ]
        );

        return $response;
    }

    protected function generateResponseForCode($response, $resultCode, $txn_id, $ord_id)
    {
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: generateResponseForCode');

        if (empty($txn_id)) {
            $txn_id = $this->generateTxnId();
        }
        return array_merge(
            $response,
            [
                'RESULT_CODE' => $resultCode,
                'TXN_ID' => $txn_id,
                'ORD_ID' => $ord_id
            ]
        );
    }

    protected function generateTxnId()
    {
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: generateTxnId');

        return sha1(random_int(0, 1000));
    }
}

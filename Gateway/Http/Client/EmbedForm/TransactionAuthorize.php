<?php

namespace Conekta\Payments\Gateway\Http\Client\EmbedForm;

use Conekta\Model\ChargeResponse;
use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Conekta\Payments\Model\ConektaSalesOrderFactory;
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
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: __construct');
        $this->logger = $logger;
        $this->conektaSalesOrderFactory = $conektaSalesOrderFactory;
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

        $this->conektaSalesOrderFactory
            ->create()
            ->setData([
                ConektaSalesOrderInterface::CONEKTA_ORDER_ID => $request['order_id'],
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
            
            // Para pay_by_bank con txn_id "pending", usar datos del frontend
            if ($paymentMethod == ConfigProvider::PAYMENT_METHOD_PAY_BY_BANK && $txnId === 'pending') {
                $this->_conektaLogger->info('EmbedForm :: Using frontend data for pending pay_by_bank');
                $response['offline_info'] = [
                    "type" => "pay_by_bank",
                    "data" => [
                        "redirect_url" => $request['payment_method_details']['payment_method']['redirect_url'] ?? '',
                        "deep_link" => $request['payment_method_details']['payment_method']['deep_link'] ?? '',
                        "reference" => $request['payment_method_details']['payment_method']['reference'] ?? '',
                        "expires_at" => time() + (24 * 60 * 60) // 24 horas desde ahora
                    ]
                ];
            } else {
                // Flujo normal: obtener datos desde API de Conekta
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
                        // Pay By Bank - capturar deep_link y redirect_url
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

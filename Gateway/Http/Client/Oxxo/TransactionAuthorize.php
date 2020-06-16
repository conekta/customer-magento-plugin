<?php
namespace Conekta\Payments\Gateway\Http\Client\Oxxo;

use Conekta\Payments\Gateway\Http\Util\HttpUtil;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Conekta\Order as ConektaOrder;

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

    private $_conektaOrder;

    protected $_httpUtil;

    /**
     * @param Logger $logger
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     */
    public function __construct(
        Logger $logger,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        ConektaOrder $conektaOrder,
        HttpUtil $httpUtil
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaOrder = $conektaOrder;
        $this->_httpUtil = $httpUtil;
        $this->_conektaLogger->info('HTTP Client Oxxo TransactionAuthorize :: __construct');
        $this->logger = $logger;

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
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $this->_conektaLogger->info('HTTP Client Oxxo TransactionAuthorize :: placeRequest');
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
            $conektaOrder= $this->_conektaOrder->create($orderParams);
            $charge = $conektaOrder->createCharge($chargeParams);

            if (isset($charge->id) && isset($conektaOrder->id)) {
                $result_code = 1;
                $txn_id = $charge->id;
                $ord_id = $conektaOrder->id;
            } else {
                $result_code = 666;
            }
        } catch (ValidatorException $e) {
            $error_code = $e->getMessage();
            $result_code = 666;
            $this->logger->error(__('[Conekta]: Payment capturing error.'));
            $this->logger->debug(
                [
                    'request' => $request,
                    'response' => $e->getMessage()
                ]
            );
            $this->_conektaLogger->info(
                'HTTP Client Oxxo TransactionAuthorize :: placeRequest: Payment authorize error ' . $e->getMessage()
            );
            throw new ValidatorException(__($e->getMessage()));
        }

        $response = $this->generateResponseForCode(
            $result_code,
            $txn_id,
            $ord_id
        );

        $response['offline_info'] = [
            "type" => $charge->payment_method->type,
            'barcode_url' => $charge->payment_method->barcode_url,
            "data" => [
                "reference"     => $charge->payment_method->reference,
                "expires_at"    => $charge->payment_method->expires_at
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
            'HTTP Client Oxxo TransactionAuthorize :: placeRequest',
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
        $this->_conektaLogger->info('HTTP Client Oxxo TransactionAuthorize :: generateResponseForCode');

        return array_merge(
            [
                'RESULT_CODE' => $resultCode,
                'TXN_ID' => $txn_id,
                'ORD_ID' => $ord_id
            ]
        );
    }
}

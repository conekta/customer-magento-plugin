<?php
namespace Conekta\Payments\Gateway\Http\Client\CreditCard;

use Conekta\Order as ConektaOrder;
use Conekta\Payments\Gateway\Http\Util\HttpUtil;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\ConektaSalesOrderFactory;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

class TransactionCapture implements ClientInterface
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
        ConektaOrder $conektaOrder,
        HttpUtil $httpUtil,
        ConektaSalesOrderFactory $conektaSalesOrderFactory
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaOrder = $conektaOrder;
        $this->_httpUtil = $httpUtil;
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: __construct');
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
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: placeRequest');
        $request = $transferObject->getBody();
        if ($request['iframe_payment'] == true) {
            $response = $this->generateResponseForCode(
                1,
                $request['txn_id'],
                $request['order_id']
            );

            $this->conektaSalesOrderFactory
                        ->create()
                        ->setData(array(
                            'conekta_order_id' => $request['order_id'],
                            'order_id' => $request['metadata']['order_id']
                        ))
                        ->save();

            $response['error_code'] = '';
            $response['payment_method_details'] =  $request['payment_method_details'];

            $this->_conektaLogger->info(
                'HTTP Client TransactionCapture Iframe Payment :: placeRequest',
                [
                    'request' => $request,
                    'response' => $response
                ]
            );

            return $response;
        }

        $customerInfo = $request['customer_info'];
        if ($request['CONNEKTA_CUSTOMER_ID']) {
            $customerInfo = $request['CONNEKTA_CUSTOMER_ID'];
        }
        $orderParams['currency']         = $request['CURRENCY'];
        $orderParams['line_items']       = $request['line_items'];
        $orderParams['tax_lines']        = $request['tax_lines'];
        $orderParams['customer_info']    = $customerInfo;
        $orderParams['discount_lines']   = $request['discount_lines'];
        if (!empty($request['shipping_lines'])) {
            $orderParams['shipping_lines']   = $request['shipping_lines'];
        }
        if (!empty($request['shipping_contact'])) {
            $orderParams['shipping_contact'] = $request['shipping_contact'];
        }
        $chargeParams = $request['payment_method_details'];

        $txn_id = '';
        $ord_id = '';
        $error_code = '';

        try {
            $newOrder = $this->_conektaOrder->create($orderParams);
            $newCharge = $newOrder->createCharge($chargeParams);
            if (isset($newCharge->id) || !empty($newCharge->id)) {
                $result_code = 1;
                $txn_id = $newCharge->id;
                $ord_id = $newOrder->id;

                $this->conektaSalesOrderFactory
                        ->create()
                        ->setData(array(
                            'conekta_order_id' => $ord_id,
                            'order_id' => $request['metadata']['order_id']
                        ))
                        ->save();
            } else {
                $result_code = 666;
            }
        } catch (\Exception $e) {
            $this->logger->debug(
                [
                    'request' => $request,
                    'response' => $e->getMessage()
                ]
            );

            $this->_conektaHelper->deleteSavedCard($orderParams, $chargeParams);

            $this->_conektaLogger->info(
                'HTTP Client TransactionCapture :: placeRequest: Payment capturing error ' . $e->getMessage()
            );

            $error_code = $e->getMessage();
            $result_code = 666;
            throw new \Magento\Framework\Exception\LocalizedException(__($error_code));
        }

        $response = $this->generateResponseForCode(
            $result_code,
            $txn_id,
            $ord_id
        );
        $response['error_code'] = $error_code;
        $response['payment_method_details'] =  $request['payment_method_details'];

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

    protected function generateResponseForCode($resultCode, $txn_id, $ord_id)
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

    protected function generateTxnId()
    {
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: generateTxnId');

        return sha1(random_int(0, 1000));
    }
}

<?php
namespace Conekta\Payments\Gateway\Http\Client\EmbedForm;

use Conekta\Payments\Gateway\Http\Util\HttpUtil;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Conekta\Payments\Model\ConektaSalesOrderFactory;
use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Conekta\Order as ConektaOrder;
use Exception;

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
        $this->_httpUtil = $httpUtil;
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: __construct');
        $this->logger = $logger;
        $this->conektaSalesOrderFactory = $conektaSalesOrderFactory;
        $this->_conektaOrder = $conektaOrder;

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
        $request = $transferObject->getBody();
        $this->_conektaLogger->info('HTTP Client TransactionCapture :: placeRequest', $request);
        
        $response = $this->generateResponseForCode(
            1,
            $request['txn_id'],
            $request['order_id']
        );

        $this->conektaSalesOrderFactory
                    ->create()
                    ->setData([
                        ConektaSalesOrderInterface::CONEKTA_ORDER_ID => $request['order_id'],
                        ConektaSalesOrderInterface::INCREMENT_ORDER_ID => $request['metadata']['order_id']
                    ])
                    ->save();
        
        $paymentMethod = $request['payment_method_details']['payment_method']['type'];
        
        //If is offline payment, added extra info needed
        if ($paymentMethod == ConfigProvider::PAYMENT_METHOD_OXXO ||
            $paymentMethod == ConfigProvider::PAYMENT_METHOD_SPEI
        ){
            $response['offline_info'] = [];

            try {
                $conektaOrder = $this->_conektaOrder->find($request['order_id']);
                $charge = $conektaOrder->charges[0];
                $response['offline_info'] = [
                    "type" => $charge->payment_method->type,
                    'barcode_url' => $charge->payment_method->barcode_url,
                    "data" => [
                        "reference"     => $charge->payment_method->reference,
                        "expires_at"    => $charge->payment_method->expires_at
                    ]
                ];
            } catch(Exception $e) {
                $this->_conektaLogger->error(
                    'HTTP Client TransactionCapture Iframe Payment :: cannot get offline info. ',
                    [ 'exception' => $e ]
                );
            }
                
        }

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

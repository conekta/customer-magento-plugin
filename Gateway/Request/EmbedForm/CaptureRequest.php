<?php
namespace Conekta\Payments\Gateway\Request\EmbedForm;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\Config;
use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CaptureRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    protected ConfigInterface $config;
    /**
     * @var SubjectReader
     */
    protected SubjectReader $subjectReader;
    /**
     * @var ConektaHelper
     */
    protected ConektaHelper $_conektaHelper;
    /**
     * @var ConektaLogger
     */
    protected ConektaLogger $_conektaLogger;
    /**
     * @var Config
     */
    protected Config $conektaConfig;
    /**
     * @var CustomerSession
     */
    protected CustomerSession $customerSession;
    /**
     * @var CustomerRepositoryInterface
     */
    protected CustomerRepositoryInterface $customerRepository;

    /**
     * CaptureRequest constructor.
     * @param ConfigInterface $config
     * @param SubjectReader $subjectReader
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     * @param Config $conektaConfig
     * @param CustomerSession $session
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        ConfigInterface $config,
        SubjectReader $subjectReader,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        Config $conektaConfig,
        CustomerSession $session,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('EMBED Request CaptureRequest :: __construct');
        $this->config = $config;
        $this->subjectReader = $subjectReader;
        $this->conektaConfig = $conektaConfig;
        $this->customerSession = $session;
        $this->customerRepository = $customerRepository;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request CaptureRequest :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();
        $this->_conektaLogger->info('Request CaptureRequest :: build additional', $payment->getAdditionalInformation());
        $iframePayment = $payment->getAdditionalInformation('iframe_payment');
        $iframeOrderId = $payment->getAdditionalInformation('order_id');
        $txnId = $payment->getAdditionalInformation('txn_id');
        $conektaCustomerId = '';
        $amount = (int)($order->getGrandTotalAmount() * 100);

        $request['metadata'] = [
            'plugin' => 'Magento',
            'plugin_version' => $this->_conektaHelper->getMageVersion(),
            'order_id'       => $order->getOrderIncrementId(),
            'soft_validations'  => 'true'
        ];
        $request['payment_method_details'] = $this->getCharge($payment, $amount);
        $request['CURRENCY'] = $order->getCurrencyCode();
        $request['TXN_TYPE'] = 'A';
        $request['INVOICE'] = $order->getOrderIncrementId();
        $request['AMOUNT'] = number_format($order->getGrandTotalAmount(), 2);
        $request['iframe_payment'] = $iframePayment;
        $request['order_id'] = $iframeOrderId;
        $request['txn_id'] = $txnId;

        $request['conekta_customer_id'] = $payment->getAdditionalInformation('conekta_customer_id');

        $this->_conektaLogger->info('Request CaptureRequest :: build : return request', $request);

        return $request;
    }

    private function getCharge($payment, $orderAmount): array
    {

        $paymentMethod = $payment->getAdditionalInformation('payment_method');

        $charge = [
            'payment_method' => [
                'type' => $paymentMethod
            ],
            'amount' => $orderAmount
        ];
        switch ($paymentMethod) {
            case ConfigProvider::PAYMENT_METHOD_CREDIT_CARD:
                $token = $payment->getAdditionalInformation('card_token');
                $charge['payment_method']['token_id'] = $token;
                break;
            
            case ConfigProvider::PAYMENT_METHOD_CASH:
            case ConfigProvider::PAYMENT_METHOD_BANK_TRANSFER:
                $reference = $payment->getAdditionalInformation('reference');
                $expireAt = $this->_conektaHelper->getExpiredAt();
                $charge['payment_method']['reference'] = $reference;
                $charge['payment_method']['expires_at'] = $expireAt;
                break;
        }

        return $charge;
    }
}

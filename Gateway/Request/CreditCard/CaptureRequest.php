<?php
namespace Conekta\Payments\Gateway\Request\CreditCard;

use Conekta\Customer as ConektaCustomer;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class CaptureRequest
 * @package Conekta\Payments\Gateway\Request\CreditCard
 */
class CaptureRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    protected $config;
    /**
     * @var SubjectReader
     */
    protected $subjectReader;
    /**
     * @var ConektaHelper
     */
    protected $_conektaHelper;
    /**
     * @var ConektaLogger
     */
    protected $_conektaLogger;
    /**
     * @var \Conekta\Payments\Model\Config
     */
    protected $conektaConfig;
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;
    /**
     * @var ConektaCustomer
     */
    protected $conektaCustomer;

    /**
     * CaptureRequest constructor.
     * @param ConfigInterface $config
     * @param SubjectReader $subjectReader
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     * @param \Conekta\Payments\Model\Config $conektaConfig
     * @param CustomerSession $session
     * @param CustomerRepositoryInterface $customerRepository
     * @param ConektaCustomer $conektaCustomer
     */
    public function __construct(
        ConfigInterface $config,
        SubjectReader $subjectReader,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        \Conekta\Payments\Model\Config $conektaConfig,
        CustomerSession $session,
        CustomerRepositoryInterface $customerRepository,
        ConektaCustomer $conektaCustomer
    ) {
        $this->conektaCustomer = $conektaCustomer;
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request CaptureRequest :: __construct');
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

        $token = $payment->getAdditionalInformation('card_token');
        $savedCard = $payment->getAdditionalInformation('saved_card');
        $enableSavedCard = $payment->getAdditionalInformation('saved_card_later');
        $iframePayment = $payment->getAdditionalInformation('iframe_payment');
        $iframeOrderId = $payment->getAdditionalInformation('order_id');
        $txnId = $payment->getAdditionalInformation('txn_id');

        $conektaCustomerId = '';

        if ($this->customerSession->isLoggedIn()) {
            \Conekta\Conekta::setApiKey($this->_conektaHelper->getPrivateKey()); // Yo coloco aqui mi apiKey
            \Conekta\Conekta::setApiVersion("2.0.0");

            $customer = $this->customerSession->getCustomer();
            $conektaCustomerId = $customer->getConektaCustomerId();

            if ($conektaCustomerId) {
                $customerExist = $this->checkCustomerExist($conektaCustomerId);
                if ($customerExist == false) {
                    $conektaCustomerId = '';
                }
            }

            if ($conektaCustomerId && empty($savedCard) && $enableSavedCard) {
                try {
                    $customerApi = $this->conektaCustomer->find($conektaCustomerId);
                    $source = $customerApi->createPaymentSource([
                                'token_id' => $token,
                                'type' => 'card'
                            ]);
                    $savedCard = $source->id;
                } catch (\Conekta\Handler $error) {
                    $this->_conektaLogger->info($error->getMessage());
                }
            } elseif (empty($conektaCustomerId)) {
                try {
                    $request = [];
                    $request['name'] = $customer->getName();
                    $request['email'] = $customer->getEmail();
                    $request['phone'] = $order->getBillingAddress()->getTelephone();
                    if ($enableSavedCard) {
                        $request['payment_sources'] = $this->getCard($token);
                    }
                    $conektaAPI = $this->conektaCustomer->create($request);

                    if ($enableSavedCard) {
                        $conektaCustomerId = $conektaAPI->id;
                        $savedCard = $conektaAPI->payment_sources[0]->id;
                    }
                    $customerId = $customer->getId();
                    $customer = $this->customerRepository->getById($customerId);
                    $customer->setCustomAttribute('conekta_customer_id', $conektaCustomerId);
                    $this->customerRepository->save($customer);
                } catch (\Conekta\Handler $error) {
                    $this->_conektaLogger->info($error->getMessage());
                }
            }
        }

        $installments = $payment->getAdditionalInformation('monthly_installments');
        $amount = (int)($order->getGrandTotalAmount() * 100);
        $request = [];
        try {
            if ($conektaCustomerId && $savedCard) {
                $request['payment_method_details'] = $this->getChargeCard(
                    $amount,
                    $token,
                    $savedCard
                );
            } else {
                $request['payment_method_details'] = $this->getChargeCard(
                    $amount,
                    $token,
                    false
                );
            }

            if ($this->_validateMonthlyInstallments($amount, $installments)) {
                $request['payment_method_details']['payment_method']['monthly_installments'] = $installments;
            }
        } catch (\Exception $e) {
            $this->_conektaLogger->info('Request CaptureRequest :: build Problem', $e->getMessage());
            throw new \Magento\Framework\Validator\Exception(__('Problem Creating Charge'));
        }

        $request['CURRENCY'] = $order->getCurrencyCode();
        $request['TXN_TYPE'] = 'A';
        $request['INVOICE'] = $order->getOrderIncrementId();
        $request['AMOUNT'] = number_format($order->getGrandTotalAmount(), 2);
        $request['iframe_payment'] = $iframePayment;
        $request['order_id'] = $iframeOrderId;
        $request['txn_id'] = $txnId;

        $request['CONNEKTA_CUSTOMER_ID'] = $conektaCustomerId ? [
                "customer_id" => $conektaCustomerId
        ] : '';

        $this->_conektaLogger->info('Request CaptureRequest :: build : return request', $request);

        return $request;
    }

    /**
     * @param $customerId
     * @return bool
     */
    public function checkCustomerExist($customerId)
    {
        $customerExist = false;
        try {
            $customerApi = $this->conektaCustomer->find($customerId);
            if ($customerApi->id) {
                $customerExist = true;
            }
        } catch (\Conekta\Handler $error) {
            $customerExist = false;
        }
        return $customerExist;
    }

    public function getCard($tokenId)
    {
        $charge = [
            [
                "type" => "card",
                "token_id" => $tokenId
            ]
        ];

        return $charge;
    }

    public function getChargeCard($amount, $tokenId, $savedCard)
    {
        $charge = [
            'payment_method' => [
                'type'     => 'card'
            ],
            'amount' => $amount
        ];

        if ($savedCard != false) {
            $charge['payment_method']['payment_source_id'] = $savedCard;
        } else {
            $charge['payment_method']['token_id'] = $tokenId;
        }

        return $charge;
    }

    private function _validateMonthlyInstallments($amount, $installments)
    {
        $active_monthly_installments = $this->_conektaHelper->getConfigData(
            'conekta/conekta_creditcard',
            'active_monthly_installments'
        );
        if ($active_monthly_installments) {
            $minimum_amount_monthly_installments = $this->_conektaHelper->getConfigData(
                'conekta/conekta_creditcard',
                'minimum_amount_monthly_installments'
            );
            if ($amount >= ($minimum_amount_monthly_installments * 100) && $installments > 1) {
                return true;
            }
        }

        return false;
    }
}

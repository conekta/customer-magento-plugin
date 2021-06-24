<?php

namespace Conekta\Payments\Helper;

use Conekta\Customer as ConektaCustomer;
use Conekta\Order as ConektaOrderApi;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\Ui\CreditCard\ConfigProvider;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;

class ConektaOrder extends AbstractHelper
{
    const CURRENCY_CODE = 'mxn';
    const STREET = 'Conekta Street';
    /**
     * @var ConektaLogger
     */
    protected $conektaLogger;
    /**
     * @var ConektaCustomer
     */
    protected $conektaCustomer;
    /**
     * @var ConektaOrderApi
     */
    protected $conektaOrderApi;
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    /**
     * @var Data
     */
    protected $_conektaHelper;
    /**
     * @var Session
     */
    protected $_checkoutSession;
    /**
     * @var \Magento\Quote\Model\Quote|null
     */
    protected $quote = null;
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;
    /**
     * @var Escaper
     */
    protected $_escaper;
    /**
     * @var \Conekta\Payments\Model\Session
     */
    protected $conektaSession;
    /**
     * @var ConfigProvider
     */
    protected $conektaConfigProvider;

    /**
     * ConektaOrder constructor.
     * @param Context $context
     * @param Data $conektaHelper
     * @param ConektaLogger $conektaLogger
     * @param ConektaCustomer $conektaCustomer
     * @param ConektaOrderApi $conektaOrderApi
     * @param CustomerSession $customerSession
     * @param Session $_checkoutSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param Escaper $_escaper
     * @param \Conekta\Payments\Model\Session $conektaSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        ConektaCustomer $conektaCustomer,
        ConektaOrderApi $conektaOrderApi,
        CustomerSession $customerSession,
        Session $_checkoutSession,
        CustomerRepositoryInterface $customerRepository,
        Escaper $_escaper,
        \Conekta\Payments\Model\Session $conektaSession,
        ConfigProvider $conektaConfigProvider,
        array $data = []
    ) {
        parent::__construct($context);
        $this->conektaLogger = $conektaLogger;
        $this->conektaCustomer = $conektaCustomer;
        $this->conektaOrderApi = $conektaOrderApi;
        $this->customerSession = $customerSession;
        $this->_conektaHelper = $conektaHelper;
        $this->_checkoutSession = $_checkoutSession;
        $this->customerRepository = $customerRepository;
        $this->_escaper = $_escaper;
        $this->conektaSession = $conektaSession;
        $this->conektaConfigProvider = $conektaConfigProvider;
    }

    /**
     * @param $isLoggedInFlag
     * @param $guestEmail
     * @return mixed|string
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InputMismatchException
     */
    public function createOrder($guestEmail)
    {
        $this->conektaLogger->info('Create Blank Order :: createOrder', [$this->getQuote()->getBillingAddress()->getEmail()]);

        \Conekta\Conekta::setApiKey($this->_conektaHelper->getPrivateKey());
        \Conekta\Conekta::setApiVersion("2.0.0");
        try {
            $customer = $this->customerSession->getCustomer();
            $customerApi = null;
            $conektaCustomerId = $customer->getConektaCustomerId();
            
            try {
                $customerApi = $this->conektaCustomer->find($conektaCustomerId);
            } catch (Exception $error) {
                $this->conektaLogger->error('Create Order. Find Customer: ' . $error->getMessage());
                $conektaCustomerId = '';
            }

            //Customer Info for API
            $billingAddress = $this->getQuote()->getBillingAddress();
            $customerId = $customer->getId();
            $customerRequest = [];
            if ($customerId) {
                //name without numbers
                $customerRequest['name'] = preg_replace('/[0-9]+/', '', $customer->getName());
                $customerRequest['email'] = $customer->getEmail();
                //$customerRequest['phone'] = $billingAddress->getTelephone();
            } else {
                //name without numbers
                $customerRequest['name'] = preg_replace('/[0-9]+/', '', $billingAddress->getName());
                $customerRequest['email'] = $guestEmail;
                //$customerRequest['phone'] = $billingAddress->getTelephone();
            }
            $this->conektaLogger->error('Create Order. customer_req: ', $customerRequest);
            if (empty($conektaCustomerId)) {
                try {
                    $conektaAPI = $this->conektaCustomer->create($customerRequest);
                    $conektaCustomerId = $conektaAPI->id;
                    if ($customerId) {
                        $customer = $this->customerRepository->getById($customerId);
                        $customer->setCustomAttribute('conekta_customer_id', $conektaCustomerId);
                        $this->customerRepository->save($customer);
                    }
                } catch (\Conekta\Handler $error) {
                    $this->conektaLogger->info('Create Order. Create Customer: ' .$error->getMessage());
                }
            } else {
                //If cutomer API exists, always update error
                $customerApi->update($customerRequest);
            }
        } catch (\Conekta\ProcessingError $error) {
            $this->conektaLogger->info($error->getMessage());
        } catch (\Conekta\ParameterValidationError $error) {
            $this->conektaLogger->info($error->getMessage());
        } catch (\Conekta\Handler $error) {
            $this->conektaLogger->info($error->getMessage());
        }

        $orderItems = $this->getQuote()->getAllItems();

        $validOrderWithCheckout = [];
        $validOrderWithCheckout['line_items'] = $this->_conektaHelper->getLineItems($orderItems);
        $validOrderWithCheckout['shipping_lines'] = $this->_conektaHelper->getShippingLines(
                                                                        $this->getQuote()->getId()
                                                                    );
        $needsShippingContact = !$this->getQuote()->getIsVirtual();
        if($needsShippingContact){
            $validOrderWithCheckout['shipping_contact'] = $this->_conektaHelper->getShippingContact(
                $this->getQuote()->getId()
            );
        }
        
        $validOrderWithCheckout['customer_info'] = [
            'customer_id' => $conektaCustomerId
        ];
        
        $threeDsEnabled =  $this->_conektaHelper->is3DSEnabled();
        $saveCardEnabled =  $this->_conektaHelper->isSaveCardEnabled();
        $installments = $this->getMonthlyInstallments();
        $validOrderWithCheckout['checkout'] = [
            'allowed_payment_methods'      => ["card"],//, "cash", "bank_transfer"],
            'monthly_installments_enabled' => $installments['active_installments'] ? true : false,
            'monthly_installments_options' => $installments['monthly_installments'],
            'on_demand_enabled'            => $saveCardEnabled,
            'force_3ds_flow'               => $threeDsEnabled,
            'expires_at'                   => $this->getExpiredAt(),
            'needs_shipping_contact'       => $needsShippingContact
        ];
        $validOrderWithCheckout['currency']= self::CURRENCY_CODE;
        $validOrderWithCheckout['metadata'] = $this->getMetadataOrder($orderItems);
        
        $checkoutId = '';
        try {
            $this->conektaLogger->info('Creating Order. Parameters: ', $validOrderWithCheckout);
            $order = $this->conektaOrderApi->create($validOrderWithCheckout);
            $this->conektaLogger->info('The Order has been created');
            $order = (array) $order;
            $checkoutId =  $order['checkout']['id'];
            $this->conektaSession->setConektaCheckoutId($checkoutId);
        } catch (\Conekta\ProcessingError $error) {
            $this->conektaLogger->error($error->getMessage());
        } catch (\Conekta\ParameterValidationError $error) {
            $this->conektaLogger->error($error->getMessage());
        } catch (\Conekta\Handler $error) {
            $this->conektaLogger->error($error->getMessage());
        }
        return $checkoutId;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getMonthlyInstallments()
    {
        $result = [];
        $isInstallmentsAvilable = (int)true;
        $quote = $this->getQuote();
        $total = $quote->getGrandTotal();
        $active_monthly_installments = $this->_conektaHelper->getConfigData(
            'conekta/conekta_creditcard',
            'active_monthly_installments'
        );
        if ($active_monthly_installments) {
            $minimumAmountMonthlyInstallments = $this->conektaConfigProvider->getMinimumAmountMonthlyInstallments();
            if ((int)$minimumAmountMonthlyInstallments < (int)$total) {
                $months = explode(',', $this->_conektaHelper->getConfigData('conekta_cc', 'monthly_installments'));
                foreach ($months as $k => $v) {
                    if ((int)$total < ($v * 100)) {
                        unset($months[$k]);
                    } else {
                        $months[$k] = (int) $months[$k];
                    }
                }
                $result['active_installments'] = (int)true;
                $result['monthly_installments'] = $months;
            } else {
                $isInstallmentsAvilable = (int)false;
            }
        } else {
            $isInstallmentsAvilable = (int)false;
        }
        if ($isInstallmentsAvilable == false) {
            $result['active_installments'] = (int)false;
            $result['monthly_installments'] = [];
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getExpiredAt()
    {
        $datetime = new \Datetime();
        $datetime->add(new \DateInterval('P3D'));
        return $datetime->format('U');
    }

    /**
     * Get active quote
     *
     * @return \Magento\Quote\Model\Quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuote()
    {
        if (null === $this->quote) {
            $this->quote = $this->_checkoutSession->getQuote();
        }
        return $this->quote;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuoteId()
    {
        $quote = $this->getQuote();
        $quoteId = $quote->getId();
        $response = ['quote_id' => $quoteId];
        return $response;
    }


    public function getMetadataOrder($orderItems){
        return array_merge(
            $this->_conektaHelper->getMagentoMetadata(),
            ['quote_id' => $this->getQuote()->getId()],
            $this->_conektaHelper->getMetadataAttributesConketa($orderItems)
        );
    }
}

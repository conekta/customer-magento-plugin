<?php

namespace Conekta\Payments\Helper;

use Conekta\Customer as ConektaCustomer;
use Conekta\Order as ConektaOrderApi;
use Conekta\Payments\Exception\ConektaException;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\Ui\CreditCard\ConfigProvider;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\Context;

class ConektaOrder extends Util
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
     * @var ConfigProvider
     */
    protected $conektaConfigProvider;

    /**
     * ConektaOrder constructor.
     * @param Context $context
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     * @param ConektaCustomer $conektaCustomer
     * @param ConektaOrderApi $conektaOrderApi
     * @param CustomerSession $customerSession
     * @param Session $_checkoutSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param \ConfigProvider $conektaConfigProvider
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
        ConfigProvider $conektaConfigProvider
    ) {
        parent::__construct($context);
        $this->conektaLogger = $conektaLogger;
        $this->conektaCustomer = $conektaCustomer;
        $this->conektaOrderApi = $conektaOrderApi;
        $this->customerSession = $customerSession;
        $this->_conektaHelper = $conektaHelper;
        $this->_checkoutSession = $_checkoutSession;
        $this->customerRepository = $customerRepository;
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
    public function generateOrderParams($guestEmail)
    {
        $this->conektaLogger->info('ConektaOrder.generateOrderParams init');

        \Conekta\Conekta::setApiKey($this->_conektaHelper->getPrivateKey());
        \Conekta\Conekta::setApiVersion("2.0.0");
        $customerRequest = [];
        try {
            $customer = $this->customerSession->getCustomer();
            $customerApi = null;
            $conektaCustomerId = $customer->getConektaCustomerId();
            
            try {
                $customerApi = $this->conektaCustomer->find($conektaCustomerId);
            } catch (Exception $error) {
                $this->conektaLogger->info('Create Order. Find Customer: ' . $error->getMessage());
                $conektaCustomerId = '';
            }

            //Customer Info for API
            $billingAddress = $this->getQuote()->getBillingAddress();
            $customerId = $customer->getId();
            $customerRequest = [];
            if ($customerId) {
                //name without numbers
                $customerRequest['name'] = $customer->getName();
                $customerRequest['email'] = $customer->getEmail();
            } else {
                //name without numbers
                $customerRequest['name'] = $billingAddress->getName();
                $customerRequest['email'] = $guestEmail;
            }
            $customerRequest['name'] = $this->removeNameSpecialCharacter($customerRequest['name']);
            $customerRequest['phone'] = $this->removePhoneSpecialCharacter(
                $billingAddress->getTelephone()
            );
            
            if (strlen($customerRequest['phone']) < 10) {
                $this->conektaLogger->info('Helper.CreateOrder phone validation error', $customerRequest);
                throw new ConektaException(__('Télefono no válido. 
                    El télefono debe tener al menos 10 carácteres. 
                    Los caracteres especiales se desestimaran, solo se puede ingresar como 
                    primer carácter especial: +'));
            }
            
            if (empty($conektaCustomerId)) {
                $conektaAPI = $this->conektaCustomer->create($customerRequest);
                $conektaCustomerId = $conektaAPI->id;
                if ($customerId) {
                    $customer = $this->customerRepository->getById($customerId);
                    $customer->setCustomAttribute('conekta_customer_id', $conektaCustomerId);
                    $this->customerRepository->save($customer);
                }
                
            } else {
                //If cutomer API exists, always update error
                $customerApi->update($customerRequest);
            }
        } catch (\Conekta\Handler $error) {
            $this->conektaLogger->info($error->getMessage(), $customerRequest);
            throw new ConektaException(__($error->getMessage()));
        }
        $orderItems = $this->getQuote()->getAllItems();

        $validOrderWithCheckout = [];
        $validOrderWithCheckout['line_items'] = $this->_conektaHelper->getLineItems($orderItems);
        $validOrderWithCheckout['discount_lines'] = $this->_conektaHelper->getDiscountLines();
        $validOrderWithCheckout['tax_lines'] = $this->_conektaHelper->getTaxLines($orderItems);
        $validOrderWithCheckout['shipping_lines'] = $this->_conektaHelper->getShippingLines(
            $this->getQuote()->getId()
        );

        //always needs shipping due to api does not provide info about merchant type (dropshipping, virtual)
        $needsShippingContact = !$this->getQuote()->getIsVirtual() || true;
        if ($needsShippingContact) {
            $validOrderWithCheckout['shipping_contact'] = $this->_conektaHelper->getShippingContact(
                $this->getQuote()->getId()
            );
        }
        
        $validOrderWithCheckout['customer_info'] = [
            'customer_id' => $conektaCustomerId
        ];
        
        $threeDsEnabled =  $this->_conektaHelper->is3DSEnabled();
        $saveCardEnabled = $this->_conektaHelper->isSaveCardEnabled() &&
            $customerId;
        $installments = $this->getMonthlyInstallments();
        $validOrderWithCheckout['checkout']    = [
            'allowed_payment_methods'      => $this->getAllowedPaymentMethods(),
            'monthly_installments_enabled' => $installments['active_installments'] ? true : false,
            'monthly_installments_options' => $installments['monthly_installments'],
            'on_demand_enabled'            => $saveCardEnabled,
            'force_3ds_flow'               => $threeDsEnabled,
            'expires_at'                   => $this->_conektaHelper->getExpiredAt(),
            'needs_shipping_contact'       => $needsShippingContact
        ];
        $validOrderWithCheckout['currency']= $this->_conektaHelper->getCurrencyCode();
        $validOrderWithCheckout['metadata'] = $this->getMetadataOrder($orderItems);
        
        return $validOrderWithCheckout;
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
                $result['active_installments'] = (int)!empty($months);
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

    public function getAllowedPaymentMethods()
    {
        $methods = [];

        if ($this->_conektaHelper->isCreditCardEnabled()) {
            $methods[] = 'card';
        }

        $total = $this->getQuote()->getSubtotal();
        if ($this->_conektaHelper->isOxxoEnabled() &&
            $total <= 10000
        ) {
            $methods[] = 'cash';
        }
        if ($this->_conektaHelper->isSpeiEnabled()) {
            $methods[] = 'bank_transfer';
        }
        return $methods;
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
        return $this->_checkoutSession->getQuote();
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

    public function getMetadataOrder($orderItems)
    {
        return array_merge(
            $this->_conektaHelper->getMagentoMetadata(),
            ['quote_id' => $this->getQuote()->getId()],
            $this->_conektaHelper->getMetadataAttributesConekta($orderItems)
        );
    }
}

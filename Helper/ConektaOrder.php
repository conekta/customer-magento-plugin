<?php

namespace Conekta\Payments\Helper;

use Conekta\ApiException;
use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Exception\ConektaException;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\Ui\CreditCard\ConfigProvider;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\InputMismatchException;
use Magento\Quote\Model\Quote;

class ConektaOrder extends Util
{
    public const CURRENCY_CODE = 'mxn';
    public const STREET = 'Conekta Street';
    /**
     * @var ConektaLogger
     */
    protected $conektaLogger;

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
     * @var Quote|null
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
     * @var ConektaApiClient
     */
    private $conektaApiClient;

    /**
     * ConektaOrder constructor.
     *
     * @param Context $context
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     * @param ConektaApiClient $conektaApiClient
     * @param CustomerSession $customerSession
     * @param Session $_checkoutSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param ConfigProvider $conektaConfigProvider
     */
    public function __construct(
        Context $context,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        ConektaApiClient $conektaApiClient,
        CustomerSession $customerSession,
        Session $_checkoutSession,
        CustomerRepositoryInterface $customerRepository,
        ConfigProvider $conektaConfigProvider
    ) {
        parent::__construct($context);
        $this->conektaApiClient = $conektaApiClient;
        $this->conektaLogger = $conektaLogger;
        $this->customerSession = $customerSession;
        $this->_conektaHelper = $conektaHelper;
        $this->_checkoutSession = $_checkoutSession;
        $this->customerRepository = $customerRepository;
        $this->conektaConfigProvider = $conektaConfigProvider;
    }

    /**
     * Generate Order Params
     *
     * @param mixed $guestEmail
     * @return mixed|string
     * @throws ConektaException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws InputMismatchException
     */
    public function generateOrderParams($guestEmail)
    {
        $this->conektaLogger->info('ConektaOrder.generateOrderParams init');

        $customerRequest = [];
        try {
            $customer = $this->customerSession->getCustomer();
            $conektaCustomer = null;
            $conektaCustomerId = $customer->getConektaCustomerId();

            if(!empty($conektaCustomerId)) {
                try {
                    $conektaCustomer = $this->conektaApiClient->findCustomerByID($conektaCustomerId);
                } catch (Exception $error) {
                    $this->conektaLogger->info('Create Order. Find Customer: ' . $error->getMessage());
                    $conektaCustomerId = '';
                }
            }

            //Customer Info for API
            $billingAddress = $this->getQuote()->getBillingAddress();
            $customerId = $customer->getId();
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
                $conektaCustomer = $this->conektaApiClient->createCustomer($customerRequest);
                $conektaCustomerId = $conektaCustomer->getId();
                if ($customerId) {
                    $customer = $this->customerRepository->getById($customerId);
                    $customer->setCustomAttribute('conekta_customer_id', $conektaCustomerId);
                    $this->customerRepository->save($customer);
                }
            } else {
                //If customer API exists, always update error
                $this->conektaApiClient->updateCustomer($conektaCustomerId, $customerRequest);
            }
        } catch (ApiException $e) {
            $this->conektaLogger->info($e->getMessage(), $customerRequest);
            throw new ConektaException(__($e->getMessage()));
        }
        $orderItems = $this->getQuote()->getAllItems();

        $validOrderWithCheckout = [];
        $validOrderWithCheckout['line_items'] = $this->_conektaHelper->getLineItems($orderItems);
        $validOrderWithCheckout['discount_lines'] = $this->_conektaHelper->getDiscountLines();
        $validOrderWithCheckout['tax_lines'] = $this->_conektaHelper->getTaxLines($orderItems);
        $validOrderWithCheckout['shipping_lines'] = $this->_conektaHelper->getShippingLines(
            $this->getQuote()->getId()
        );

        //always needs shipping due to api does not provide info about merchant type (drop_shipping, virtual)
        $validOrderWithCheckout['shipping_contact'] = $this->_conektaHelper->getShippingContact(
            $this->getQuote()->getId()
        );
        $validOrderWithCheckout['fiscal_info'] = $this->_conektaHelper->getBillingAddress(
            $this->getQuote()->getId()
        );

        $validOrderWithCheckout['customer_info'] = [
            'customer_id' => $conektaCustomerId
        ];
        
        $threeDsEnabled =  $this->_conektaHelper->is3DSEnabled();
        $saveCardEnabled = $this->_conektaHelper->isSaveCardEnabled() &&
            $customerId;
        $installments = $this->getMonthlyInstallments();
        $validOrderWithCheckout['checkout']    = [
            'allowed_payment_methods'      => $this->getAllowedPaymentMethods(),
            'monthly_installments_enabled' => (bool)$installments['active_installments'],
            'monthly_installments_options' => $installments['monthly_installments'],
            'on_demand_enabled'            => $saveCardEnabled,
            'force_3ds_flow'               => $threeDsEnabled,
            'expires_at'                   => $this->_conektaHelper->getExpiredAt(),
            'needs_shipping_contact'       => true
        ];
        $validOrderWithCheckout['currency']= $this->_conektaHelper->getCurrencyCode();
        $validOrderWithCheckout['metadata'] = $this->getMetadataOrder($orderItems);
        
        return $validOrderWithCheckout;
    }

    /**
     * Get montly installments
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getMonthlyInstallments(): array
    {
        $result = [];
        $isInstallmentsAvailable = (int)true;
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
                $isInstallmentsAvailable = (int)false;
            }
        } else {
            $isInstallmentsAvailable = (int)false;
        }
        if (!$isInstallmentsAvailable) {
            $result['active_installments'] = (int)false;
            $result['monthly_installments'] = [];
        }
        return $result;
    }

    /**
     * Get allowed payments methods
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAllowedPaymentMethods(): array
    {
        $methods = [];

        if ($this->_conektaHelper->isCreditCardEnabled()) {
            $methods[] = 'card';
        }

        $total = $this->getQuote()->getSubtotal();
        if ($this->_conektaHelper->isCashEnabled() &&
            $total <= 10000
        ) {
            $methods[] = 'cash';
        }
        if ($this->_conektaHelper->isBankTransferEnabled()) {
            $methods[] = 'bank_transfer';
        }
        return $methods;
    }

    /**
     * Get active quote
     *
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->_checkoutSession->getQuote();
    }

    /**
     * Get quote ID
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuoteId(): array
    {
        $quote = $this->getQuote();
        $quoteId = $quote->getId();
        return ['quote_id' => $quoteId];
    }

    /**
     * Get Metadata Order
     *
     * @param mixed $orderItems
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getMetadataOrder($orderItems): array
    {
        return array_merge(
            $this->_conektaHelper->getMagentoMetadata(),
            ['quote_id' => $this->getQuote()->getId()],
            $this->_conektaHelper->getMetadataAttributesConekta($orderItems)
        );
    }
}

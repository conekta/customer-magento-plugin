<?php

namespace Conekta\Payments\Helper;

use Conekta\Customer as ConektaCustomer;
use Conekta\Order as ConektaOrderApi;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\Ui\CreditCard\ConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Escaper;

/**
 * Class ConektaOrder
 * @package Conekta\Payments\Helper
 */
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
        if ($this->conektaSession->getConektaCheckoutId()) {
//            return $this->conektaSession->getConektaCheckoutId();
        }

        $this->conektaLogger->info('Create Blank Order :: createOrder');

        \Conekta\Conekta::setApiKey($this->_conektaHelper->getPrivateKey());
        \Conekta\Conekta::setApiVersion("2.0.0");
        try {
            $customer = $this->customerSession->getCustomer();
            $customerApi = null;
            $conektaCustomerId = $customer->getConektaCustomerId();
            $this->conektaLogger->info('Create Blank Order :: customer', ['customer'=>$customer->getConektaCustomerId()]);
            try {
                $customerApi = $this->conektaCustomer->find($conektaCustomerId);
            } catch (\Conekta\ProcessingError $error) {
                $this->conektaLogger->info('Create Order. Find Customer: ' . $error->getMessage());
                $conektaCustomerId = '';
            } catch (\Conekta\ParameterValidationError $error) {
                $this->conektaLogger->info('Create Order. Find Customer: ' . $error->getMessage());
                $conektaCustomerId = '';
            } catch (\Conekta\Handler $error) {
                $this->conektaLogger->info('Create Order. Find Customer: ' . $error->getMessage());
                $conektaCustomerId = '';
            }

            //Customer Info for API
            $shippingAddress = $this->getQuote()->getShippingAddress();
            $customerId = $customer->getId();
            $customerRequest = [];
            if ($customerId) {
                $customerRequest['name'] = $customer->getName();
                $customerRequest['email'] = $customer->getEmail();
                $customerRequest['phone'] = $shippingAddress->getTelephone();
            } else {
                $customerRequest['name'] = $shippingAddress->getName();
                $customerRequest['email'] = $guestEmail;
                $customerRequest['phone'] = $shippingAddress->getTelephone();
            }

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
                    $this->conektaLogger->info($error->getMessage());
                }
            } else {
                //If cutomer API exists, always update info
                $customerApi->update($customerRequest);
            }
        } catch (\Conekta\ProcessingError $error) {
            $this->conektaLogger->info($error->getMessage());
        } catch (\Conekta\ParameterValidationError $error) {
            $this->conektaLogger->info($error->getMessage());
        } catch (\Conekta\Handler $error) {
            $this->conektaLogger->info($error->getMessage());
        }

        $validOrderWithCheckout = [];
        $validOrderWithCheckout['line_items'] = $this->getLineItems();
        $validOrderWithCheckout['shipping_lines'] = $this->getShippingLines();
        $validOrderWithCheckout['shipping_contact'] = $this->getShippingContact($guestEmail);
        $validOrderWithCheckout['customer_info'] = [
            'customer_id' => $conektaCustomerId
        ];

        $installments = $this->getMonthlyInstallments();
        $validOrderWithCheckout['checkout']    = [
            'allowed_payment_methods' => ["cash", "card", "bank_transfer"],
            'monthly_installments_enabled' => $installments['active_installments'] ? true : false,
            'monthly_installments_options' => $installments['monthly_installments'],
            'on_demand_enabled' => true, //agregar deteccion
            'force_3ds_flow' => true, 
        ];
        $validOrderWithCheckout['currency']= self::CURRENCY_CODE;
        $validOrderWithCheckout['checkout']['expires_at'] = $this->getExpiredAt();
        $validOrderWithCheckout['metadata'] = $this->getQuoteId();
        $iframeEnabled =  $this->_conektaHelper->getConfigData('conekta_cc', 'iframe_enabled') ? true : false;
        $validOrderWithCheckout['force_3ds_flow'] = true;//$iframeEnabled;
        $checkoutId = '';

        try {
            $order = $this->conektaOrderApi->create($validOrderWithCheckout);
            $this->conektaLogger->info('The Order is created');
            $order = (array) $order;
            $checkoutId =  $order['checkout']['id'];
            $this->conektaSession->setConektaCheckoutId($checkoutId);
        } catch (\Conekta\ProcessingError $error) {
            $this->conektaLogger->info($error->getMessage());
        } catch (\Conekta\ParameterValidationError $error) {
            $this->conektaLogger->info($error->getMessage());
        } catch (\Conekta\Handler $error) {
            $this->conektaLogger->info($error->getMessage());
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
        $isInstallmentsAvilable = intval(true);
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
                $result['active_installments'] = intval(true);
                $result['monthly_installments'] = $months;
            } else {
                $isInstallmentsAvilable = intval(false);
            }
        } else {
            $isInstallmentsAvilable = intval(false);
        }
        if ($isInstallmentsAvilable == false) {
            $result['active_installments'] = intval(false);
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
    public function getLineItems()
    {
        $version = (int)str_replace('.', '', $this->_conektaHelper->getMageVersion());
        $request = [];
        $items = $this->getQuote()->getItems();
        foreach ($items as $itemId => $item) {
            if ($version > 233) {
                if ($item->getProductType() != 'bundle' && $item->getProductType() != 'configurable') {
                    $request[] = [
                        'name' => $item->getName(),
                        'sku' => $item->getSku(),
                        'unit_price' => (int)($item->getPrice() * 100),
                        'description' => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity' => (int)($item->getQty()),
                        'tags' => [
                            $item->getProductType()
                        ]
                    ];
                }
            } else {
                if ($item->getProductType() != 'bundle' && $item->getPrice() > 0) {
                    $request[] = [
                        'name' => $item->getName(),
                        'sku' => $item->getSku(),
                        'unit_price' => (int)($item->getPrice() * 100),
                        'description' => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity' => (int)($item->getQtyOrdered()),
                        'tags' => [
                            $item->getProductType()
                        ]
                    ];
                }
            }
        }
        return $request;
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

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getShippingLines()
    {
        $quote = $this->getQuote();
        $amount = $quote->getShippingAddress()->getShippingAmount();

        if (!empty($amount)) {
            $shipping_lines['amount'] = (int)($amount * 100);
            $request[] = $shipping_lines;
        } else {
            $request = [];
        }
        return $request;
    }

    /**
     * @param $guestEmail
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getShippingContact($guestEmail)
    {
        $shipping = $this->getQuote()->getShippingAddress();
        $request = [];
        if ($shipping) {
            $street = $shipping->getStreet();
            $request = [
                'receiver' => $this->getCustomerName($shipping),
                'phone' => $shipping->getTelephone(),
                'address' => [
                    'city' => $shipping->getCity(),
                    'state' => $shipping->getRegionCode(),
                    'country' => $shipping->getCountryId(),
                    'postal_code' => $shipping->getPostcode(),
                    'phone' => $shipping->getTelephone(),
                    'email' => $shipping->getEmail() ? $shipping->getEmail() : $guestEmail
                ]
            ];
            $request['address']['street1'] = isset($street[0]) ? $street[0] : self::STREET;
            if (isset($street[1])) {
                $request['address']['street2'] = $street[1];
            }
        }
        return $request;
    }

    /**
     * @param $shipping
     * @return string
     */
    public function getCustomerName($shipping)
    {
        $customerName = sprintf(
            '%s %s %s',
            $shipping->getFirstname(),
            $shipping->getMiddlename(),
            $shipping->getLastname()
        );

        return $customerName;
    }
}

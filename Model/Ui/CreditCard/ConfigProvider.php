<?php
namespace Conekta\Payments\Model\Ui\CreditCard;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Model\CcConfig;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'conekta_cc';
    /**
     * @var Repository
     */
    protected $_assetRepository;
    /**
     * @var CcConfig
     */
    protected $_ccCongig;
    /**
     * @var ConektaHelper
     */
    protected $_conektaHelper;
    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @param Repository $assetRepository
     * @param CcConfig $ccCongig
     * @param ConektaHelper $conektaHelper
     * @param Session $checkoutSession
     */
    public function __construct(
        Repository $assetRepository,
        CcConfig $ccCongig,
        ConektaHelper $conektaHelper,
        Session $checkoutSession
    ) {
        $this->_assetRepository = $assetRepository;
        $this->_ccCongig = $ccCongig;
        $this->_conektaHelper = $conektaHelper;
        $this->_checkoutSession = $checkoutSession;
    }

    /**
     * Get config
     *
     * @return \array[][]
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'availableTypes' => $this->getCcAvalaibleTypes(),
                    'months' => $this->_getMonths(),
                    'years' => $this->_getYears(),
                    'hasVerification' => true,
                    'cvvImageUrl' => $this->getCvvImageUrl(),
                    'monthly_installments' => $this->getMonthlyInstallments(),
                    'active_monthly_installments' => $this->getActiveMonthlyInstallments(),
                    'minimum_amount_monthly_installments' => $this->getMinimumAmountMonthlyInstallments(),
                    'total' => $this->getQuote()->getGrandTotal()
                ]
            ]
        ];
    }

    /**
     * Get CC avaliable types
     *
     * @return array
     */
    public function getCcAvalaibleTypes()
    {
        $result = [];
        $cardTypes = $this->_ccCongig->getCcAvailableTypes();
        $cc_types = explode(',', $this->_conektaHelper->getConfigData('conekta_cc', 'cctypes'));
        if (!empty($cc_types)) {
            foreach ($cc_types as $key) {
                if (isset($cardTypes[$key])) {
                    $result[$key] = $cardTypes[$key];
                }
            }
        }
        return $result;
    }

    /**
     * Get monthly installments
     *
     * @return false|int[]|string[]
     */
    public function getMonthlyInstallments()
    {
        $total = $this->getQuote()->getGrandTotal();
        $months = [1];
        if ((int)$this->getMinimumAmountMonthlyInstallments() < (int)$total) {
            $months = explode(
                ',',
                $this->_conektaHelper->getConfigData('conekta_cc', 'monthly_installments')
            );

            if (!in_array("1", $months)) {
                array_push($months, "1");
            }

            asort($months);

            foreach ($months as $k => $v) {
                if ((int)$total < ($v * 100)) {
                    unset($months[$k]);
                }
            }
        }
        return $months;
    }

    /**
     * Get minimum amount monthly installments
     *
     * @return mixed
     */
    public function getMinimumAmountMonthlyInstallments()
    {
        return $this->_conektaHelper->getConfigData('conekta_cc', 'minimum_amount_monthly_installments');
    }

    /**
     * Get active monthly installments
     *
     * @return bool
     */
    public function getActiveMonthlyInstallments()
    {
        $isActive = $this->_conektaHelper->getConfigData('conekta/conekta_creditcard', 'active_monthly_installments');
        if ($isActive == "0") {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Get cvv image url
     *
     * @return string
     */
    public function getCvvImageUrl()
    {
        return $this->_assetRepository->getUrl('Conekta_Payments::images/cvv.png');
    }

    /**
     * Get months
     *
     * @return string[]
     */
    private function _getMonths()
    {
        return [
            "1" => "01 - Enero",
            "2" => "02 - Febrero",
            "3" => "03 - Marzo",
            "4" => "04 - Abril",
            "5" => "05 - Mayo",
            "6" => "06 - Junio",
            "7" => "07 - Julio",
            "8" => "08 - Augosto",
            "9" => "09 - Septiembre",
            "10" => "10 - Octubre",
            "11" => "11 - Noviembre",
            "12" => "12 - Diciembre"
        ];
    }

    /**
     * Get Years
     *
     * @return array
     */
    private function _getYears()
    {
        $years = [];
        $cYear = (integer) date("Y");
        $cYear = --$cYear;
        for ($i=1; $i <= 8; $i++) {
            $year = (string) ($cYear + $i);
            $years[$year] = $year;
        }

        return $years;
    }

    /**
     * Get start years
     *
     * @return array
     */
    private function _getStartYears()
    {
        $years = [];
        $cYear = (integer) date("Y");

        for ($i=5; $i>=0; $i--) {
            $year = (string)($cYear - $i);
            $years[$year] = $year;
        }

        return $years;
    }

    /**
     * Get quote
     *
     * @return CartInterface|Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->_checkoutSession->getQuote();
    }
}

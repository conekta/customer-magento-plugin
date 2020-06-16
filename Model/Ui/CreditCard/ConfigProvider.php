<?php
namespace Conekta\Payments\Model\Ui\CreditCard;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Model\CcConfig;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'conekta_cc';

    protected $_assetRepository;

    protected $_ccCongig;

    protected $_conektaHelper;

    protected $_checkoutSession;

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
                    'active_monthly_installments' => $this->getMonthlyInstallments(),
                    'minimum_amount_monthly_installments' => $this->getMinimumAmountMonthlyInstallments(),
                    'total' => $this->getQuote()->getGrandTotal()
                ]
            ]
        ];
    }

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

    public function getMonthlyInstallments()
    {
        $total = $this->getQuote()->getGrandTotal();
        $months = [1];
        if ((int)$this->getMinimumAmountMonthlyInstallments() < (int)$total) {
            $months = explode(',', $this->_conektaHelper->getConfigData('conekta_cc', 'monthly_installments'));
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

    public function getMinimumAmountMonthlyInstallments()
    {
        return $this->_conektaHelper->getConfigData('conekta_cc', 'minimum_amount_monthly_installments');
    }

    public function getCvvImageUrl()
    {
        return $this->_assetRepository->getUrl('Conekta_Payments::images/cvv.png');
    }

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

    public function getQuote()
    {
        return $this->_checkoutSession->getQuote();
    }
}

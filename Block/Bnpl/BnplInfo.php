<?php
namespace Conekta\Payments\Block\Bnpl;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;
use Conekta\Payments\Gateway\Response\Bnpl\TxnIdHandler;

class BnplInfo extends ConfigurableInfo
{
    /**
     * Returns label
     *
     * @param string $field
     * @return Phrase
     */
    protected function getLabel($field)
    {
        return __($field);
    }

    /**
     * Returns value view
     *
     * @param string $field
     * @param string $value
     * @return string | Phrase
     */
    protected function getValueView($field, $value)
    {
        switch ($field) {
            case TxnIdHandler::TXN_ID:
                return sprintf('#%s', $value);
        }
        return parent::getValueView($field, $value);
    }
}

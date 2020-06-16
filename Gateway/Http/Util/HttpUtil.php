<?php
namespace Conekta\Payments\Gateway\Http\Util;

use Conekta\Payments\Helper\Data as ConektaHelper;

class HttpUtil
{
    protected $_conektaHelper;

    public function __construct(
        ConektaHelper $conektaHelper
    ) {
        $this->_conektaHelper = $conektaHelper;
    }
    public function setupConektaClient($config)
    {
        try {
            $locale = $config['locale'];
            $privateKey = $this->_conektaHelper->getPrivateKey();
            $apiVersion = $this->_conektaHelper->getApiVersion();
            $pluginType = $this->_conektaHelper->pluginType();
            $pluginVersion = $this->_conektaHelper->pluginVersion();

            if (empty($privateKey) && !empty($locale)) {
                throw new \Magento\Framework\Validator\Exception(
                    __("Please check your conekta config.")
                );
            }
            \Conekta\Conekta::setApiKey($privateKey);
            \Conekta\Conekta::setApiVersion($apiVersion);
            \Conekta\Conekta::setPlugin($pluginType);
            \Conekta\Conekta::setPluginVersion($pluginVersion);
            \Conekta\Conekta::setLocale($locale);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(
                __($e->getMessage())
            );
        }
    }
}

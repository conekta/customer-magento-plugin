<?php
namespace Conekta\Payments\Model;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Webhook;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Locale\Resolver;

class Config
{
    protected $_encryptor;

    protected $_conektaHelper;

    private $_conektaLogger;

    protected $_storeManager;

    protected $_resolver;

    protected $_conektaWebhook;

    public function __construct(
        EncryptorInterface $encryptor,
        ConektaHelper $conektaHelper,
        StoreManagerInterface $storeManager,
        Resolver $resolver,
        ConektaLogger $conektaLogger,
        Webhook $conektaWebhook
    ) {
        $this->_encryptor = $encryptor;
        $this->_conektaHelper = $conektaHelper;
        $this->_storeManager = $storeManager;
        $this->_resolver = $resolver;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaWebhook = $conektaWebhook;
    }

    public function createWebhook()
    {
        $sandboxMode = $this->_conektaHelper->getConfigData('conekta/conekta_global', 'sandbox_mode');

        $this->initializeConektaLibrary();

        $urlWebhook = $this->_conektaHelper->getConfigData('conekta/conekta_global', 'conekta_webhook');

        if (empty($urlWebhook)) {
            $baseUrl = $this->_storeManager->getStore()->getBaseUrl();
            $urlWebhook = $baseUrl . "conekta/webhook/listener";
        }
        $events = ["events" => ["charge.paid"]];
        $errorMessage = null;
        try {
            $different = true;
            $webhooks = $this->_conektaWebhook->where();
            foreach ($webhooks as $webhook) {
                if (strpos($webhook->webhook_url, $urlWebhook) !== false) {
                    $different = false;
                }
            }
            if ($different) {
                if (!$sandboxMode) {
                    $mode = [
                        "production_enabled" => 1
                    ];
                } else {
                    $mode = [
                        "development_enabled" => 1
                    ];
                }
                $this->_conektaWebhook->create(
                    array_merge(["url" => $urlWebhook], $mode, $events)
                );
            } else {
                throw new \Magento\Framework\Validator\Exception(
                    __('Webhook was already registered in Conekta!<br>URL: ' . $urlWebhook)
                );
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->_conektaLogger->info('[Conekta]: Webhook error, Message: ' . $errorMessage . ' URL: ' . $urlWebhook);

            throw new \Magento\Framework\Validator\Exception(
                __('Can not register this webhook ' . $urlWebhook . '<br>'
                    . 'Message: ' . (string) $errorMessage)
            );
        }
    }

    public function initializeConektaLibrary()
    {
        try {
            $lang = explode('_', $this->_resolver->getLocale());
            $locale = $lang[0] == 'es' ? 'es' : 'en';
            $privateKey = $this->_conektaHelper->getPrivateKey();
            $apiVersion = $this->_conektaHelper->getApiVersion();
            $pluginType = $this->_conektaHelper->pluginType();
            $pluginVersion = $this->_conektaHelper->pluginVersion();

            if (empty($privateKey)) {
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

<?php

namespace Conekta\Payments\Model\Source;

use Magento\Framework\App\ObjectManager;

class Webhook
{
    public static function getUrl(){
        $baseUrl = ObjectManager::getInstance()
                    ->get('\Magento\Store\Model\StoreManagerInterface')
                    ->getStore()
                    ->getBaseUrl();
        
        return $baseUrl . "conekta/webhook/listener";
    }
}
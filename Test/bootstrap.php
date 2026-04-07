<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Magento auto-generated factory classes that don't exist in vendor
if (!class_exists(\Magento\Framework\Controller\Result\RawFactory::class)) {
    require_once __DIR__ . '/Stub/RawFactory.php';
}
if (!class_exists(\Magento\Sales\Model\OrderFactory::class)) {
    require_once __DIR__ . '/Stub/OrderFactory.php';
}

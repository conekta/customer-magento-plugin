<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Magento auto-generated factory classes that don't exist in vendor
if (!class_exists(\Magento\Framework\Controller\Result\RawFactory::class)) {
    class_alias(\stdClass::class, \Magento\Framework\Controller\Result\RawFactory::class);
}

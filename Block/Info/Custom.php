<?php

namespace Conekta\Payments\Block\Info;
use Magento\Payment\Block\Info;

class Custom extends Info {
    protected $_template = 'Conekta_Payments::info/custom.phtml';
    
    public function getOfflineInfo(){
        return $this->getMethod()
                    ->getInfoInstance()
                    ->getAdditionalInformation("offline_info");
    }
    
    public function getInstructions(){
        return $this->getMethod()->getInstructions();
    }
}


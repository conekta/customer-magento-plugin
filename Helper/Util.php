<?php
namespace Conekta\Payments\Helper;

class Util
{
    public function removeSpecialCharacter($param)
    {
        return preg_replace("/[^0-9a-zA-ZáéíóúüÁÉÍÓÚÜ ]/", "", $param);
    }
}

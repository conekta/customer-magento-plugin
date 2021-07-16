<?php
namespace Conekta\Payments\Helper;

class Util
{
    public static function removeSpecialCharacter($param)
    {
        return preg_replace("/[^0-9a-zA-Z ]/", "", $param);
    }
}

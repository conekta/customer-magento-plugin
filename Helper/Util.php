<?php
namespace Conekta\Payments\Helper;

abstract class Util extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Function that sanitizes a string, leaving it free
     * of unwanted characteres.
     */
    public function removeSpecialCharacter($param)
    {
        return trim(preg_replace("/[^0-9a-zA-ZáéíóúüÁÉÍÓÚÜñÑ ]/", "", $param));
    }

    /**
     * Function that sanitizes a phone string, leaving it free
     * of unwanted characteres
     */
    public function removePhoneSpecialCharacter($param)
    {
        $firstChar = preg_match("/^([+]).*$/", $param)? '+' : '';

        return $firstChar . preg_replace("/[^0-9]/", "", $param);
    }

    /**
     * Function that sanitizes a phone string, leaving it free
     * of unwanted characteres
     */
    public function removeNameSpecialCharacter($param)
    {
        return preg_replace("/[^a-zA-ZáéíóúüÁÉÍÓÚÜñÑ ]/", "", $param);
    }

    /**
     * Function that sanitizes a string, leaving it just with numbers
     */
    public function onlyNumbers($param)
    {
        return preg_replace("/[^0-9]/", "", $param);
    }

    /**
     * Convert $value into price for api (e.g: 40.8 => 4080)
     * avoiding float cast to int error
     * https://www.php.net/manual/es/language.types.float.php
     */
    public function convertToApiPrice($value)
    {
        return (int)number_format($value*100, 0, '.', '');
    }
}

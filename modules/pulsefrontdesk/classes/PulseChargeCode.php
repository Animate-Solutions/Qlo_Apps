<?php
class PulseChargeCode extends ObjectModel
{
    public $code; public $name; public $department; public $default_price = 0; public $tax_rate = 0; public $is_payment = 0; public $active = 1;
    public static $definition = array(
        'table' => 'pulse_charge_code', 'primary' => 'id_pulse_charge_code',
        'fields' => array(
            'code' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 16),
            'name' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 64),
            'department' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true),
            'default_price' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'tax_rate' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'is_payment' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'active' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
        ),
    );
    public static function byCode($code)
    {
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_charge_code` WHERE code="'.pSQL($code).'" AND active=1');
    }
    public static function all($paymentsOnly = null)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_charge_code` WHERE active=1'.($paymentsOnly === null ? '' : ' AND is_payment='.(int) $paymentsOnly).' ORDER BY department, name');
    }
}

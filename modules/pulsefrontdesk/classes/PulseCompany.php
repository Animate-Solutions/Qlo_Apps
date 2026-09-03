<?php
/** Corporate / travel-agent accounts with city-ledger balance and a standing company folio. */
class PulseCompany extends ObjectModel
{
    public $name; public $type = 'corporate'; public $contact_name; public $email; public $phone; public $address; public $tin;
    public $credit_limit = 0; public $ledger_balance = 0; public $discount_pct = 0; public $active = 1; public $date_add; public $date_upd;
    public static $definition = array(
        'table' => 'pulse_company', 'primary' => 'id_pulse_company',
        'fields' => array(
            'name' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 128),
            'type' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName'),
            'contact_name' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128),
            'email' => array('type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 128),
            'phone' => array('type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 32),
            'address' => array('type' => self::TYPE_STRING, 'validate' => 'isAddress', 'size' => 255),
            'tin' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32),
            'credit_limit' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'ledger_balance' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'discount_pct' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'active' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );
    /** The company's open folio (created on demand). */
    public function folio()
    {
        $id = (int) Db::getInstance()->getValue('SELECT id_pulse_folio FROM `'._DB_PREFIX_.'pulse_folio` WHERE id_pulse_company='.(int) $this->id.' AND type="company" AND status="open"');
        if ($id) { return new PulseFolio($id); }
        $f = new PulseFolio();
        $f->folio_no = PulseFolio::nextFolioNo('company');
        $f->id_pulse_company = (int) $this->id;
        $f->type = 'company';
        $f->add();
        return $f;
    }
    /** Record a payment received against the ledger. */
    public function receivePayment($amount, $method, $ref)
    {
        $f = $this->folio();
        $f->post($method, 'Ledger payment '.$ref, 1, $amount, 0, true, $method, 'frontdesk', $ref);
        $this->ledger_balance -= $amount;
        $this->update();
    }
}

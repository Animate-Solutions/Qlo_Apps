<?php
class PulseGroupBlock extends ObjectModel
{
    public $code; public $name; public $id_pulse_company; public $id_pulse_folio; public $date_from; public $date_to; public $cutoff_date; public $rate_per_night;
    public $billing = 'individual'; public $status = 'tentative'; public $contact_name; public $contact_phone; public $contact_email; public $notes; public $date_add; public $date_upd;
    public static $definition = array('table' => 'pulse_group_block', 'primary' => 'id_pulse_group_block', 'fields' => array(
        'code' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 16), 'name' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 128),
        'id_pulse_company' => array('type' => self::TYPE_INT), 'id_pulse_folio' => array('type' => self::TYPE_INT),
        'date_from' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true), 'date_to' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true), 'cutoff_date' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
        'rate_per_night' => array('type' => self::TYPE_FLOAT), 'billing' => array('type' => self::TYPE_STRING), 'status' => array('type' => self::TYPE_STRING),
        'contact_name' => array('type' => self::TYPE_STRING, 'size' => 128), 'contact_phone' => array('type' => self::TYPE_STRING, 'size' => 32), 'contact_email' => array('type' => self::TYPE_STRING, 'size' => 128), 'notes' => array('type' => self::TYPE_HTML),
        'date_add' => array('type' => self::TYPE_DATE), 'date_upd' => array('type' => self::TYPE_DATE),
    ));

    public function masterFolio()
    {
        if ($this->id_pulse_folio) { return new PulseFolio($this->id_pulse_folio); }
        $f = new PulseFolio(); $f->folio_no = PulseFolio::nextFolioNo('master'); $f->type = 'master'; $f->id_pulse_company = (int) $this->id_pulse_company; $f->add();
        $this->id_pulse_folio = $f->id; $this->update();
        if ($this->billing !== 'individual') { PulseRouting::add('group', $this->id, $this->billing === 'master' ? '*' : 'rooms', 'master', $f->id); }
        return $f;
    }

    public function setAllotment($idProduct, $blocked)
    {
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_group_block_allot` (id_pulse_group_block,id_product,blocked) VALUES ('.(int) $this->id.','.(int) $idProduct.','.(int) $blocked.') ON DUPLICATE KEY UPDATE blocked=VALUES(blocked)');
    }

    public function allotments()
    {
        return Db::getInstance()->executeS('SELECT a.*, pl.name room_type FROM `'._DB_PREFIX_.'pulse_group_block_allot` a INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=a.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' WHERE a.id_pulse_group_block='.(int) $this->id);
    }

    public function bookings()
    {
        return Db::getInstance()->executeS('SELECT b.id, b.room_num, b.room_type_name, b.date_from, b.date_to, b.id_status, CONCAT(c.firstname," ",c.lastname) guest FROM `'._DB_PREFIX_.'pulse_booking_ext` x INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=x.id_htl_booking LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer WHERE x.id_pulse_group_block='.(int) $this->id.' AND b.is_cancelled=0 ORDER BY b.room_num');
    }

    /**
     * Rooming list import. Rows: firstname,lastname,email,phone,room_type_id,adults,children,arrival(optional),departure(optional)
     * Returns [created => n, errors => [...]]
     */
    public function importRoomingList(array $rows)
    {
        $this->masterFolio(); $n = 0; $errors = array();
        foreach ($rows as $i => $r) {
            try {
                if (count($r) < 5 || !trim($r[0])) { continue; }
                $from = !empty($r[7]) ? $r[7] : $this->date_from; $to = !empty($r[8]) ? $r[8] : $this->date_to;
                PulseReservation::create(array('firstname' => trim($r[0]), 'lastname' => trim($r[1]), 'email' => trim($r[2]), 'phone' => trim($r[3])), $from, $to,
                    array(array('id_product' => (int) $r[4], 'adults' => (int) ($r[5] ?: 1), 'children' => (int) $r[6], 'rate_override' => $this->rate_per_night)),
                    array('source' => 'group', 'id_group_block' => $this->id, 'comment' => 'Group '.$this->code));
                $n++;
            } catch (Exception $e) { $errors[] = 'Row '.($i + 1).': '.$e->getMessage(); }
        }
        if ($n && $this->status === 'tentative') { $this->status = 'definite'; $this->update(); }
        return array('created' => $n, 'errors' => $errors);
    }

    /** Night audit: release un-picked-up allotments past cut-off. */
    public static function releaseExpired($businessDate)
    {
        $ids = Db::getInstance()->executeS('SELECT id_pulse_group_block FROM `'._DB_PREFIX_.'pulse_group_block` WHERE status IN ("tentative","definite") AND cutoff_date<"'.pSQL($businessDate).'"');
        foreach ($ids as $r) {
            Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_group_block_allot` SET blocked=picked_up WHERE id_pulse_group_block='.(int) $r['id_pulse_group_block']);
            Db::getInstance()->update('pulse_group_block', array('status' => 'released'), 'id_pulse_group_block='.(int) $r['id_pulse_group_block']);
        }
        return count($ids);
    }
}

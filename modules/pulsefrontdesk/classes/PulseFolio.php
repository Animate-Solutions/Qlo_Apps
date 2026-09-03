<?php
/**
 * Folio engine — the accounting heart of Front Desk.
 * Every charge/payment in the hotel ends up as a pulse_folio_line via post().
 */
class PulseFolio extends ObjectModel
{
    public $folio_no; public $id_order; public $id_htl_booking; public $id_customer; public $id_pulse_company; public $id_room;
    public $type = 'guest'; public $status = 'open';
    public $total_charges = 0; public $total_payments = 0; public $balance = 0;
    public $closed_by; public $date_closed; public $date_add; public $date_upd;

    public static $definition = array(
        'table' => 'pulse_folio', 'primary' => 'id_pulse_folio',
        'fields' => array(
            'folio_no'         => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32, 'required' => true),
            'id_order'         => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'id_htl_booking'   => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'id_customer'      => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'id_pulse_company' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'id_room'          => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'type'             => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName'),
            'status'           => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName'),
            'total_charges'    => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'total_payments'   => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'balance'          => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'closed_by'        => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'date_closed'      => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_add'         => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd'         => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );

    /* ---------- factories ---------- */

    public static function nextFolioNo($type = 'guest')
    {
        $prefix = array('guest' => 'F', 'company' => 'C', 'group' => 'G', 'master' => 'M', 'house' => 'H');
        $seq = (int) PulseCoreService::setting('pulsefrontdesk', 'folio_seq') + 1;
        PulseCoreService::setting('pulsefrontdesk', 'folio_seq', $seq);
        return $prefix[$type].date('y').str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    public static function openForBooking($idHtlBooking)
    {
        $id = (int) Db::getInstance()->getValue('SELECT id_pulse_folio FROM `'._DB_PREFIX_.'pulse_folio` WHERE id_htl_booking='.(int) $idHtlBooking.' AND status="open" AND type="guest"');
        return $id ? new self($id) : null;
    }

    /** Create (or return) the guest folio for a booking row of htl_booking_detail. */
    public static function ensureForBooking(array $booking)
    {
        if ($f = self::openForBooking($booking['id'])) {
            return $f;
        }
        $f = new self();
        $f->folio_no = self::nextFolioNo('guest');
        $f->id_order = (int) $booking['id_order'];
        $f->id_htl_booking = (int) $booking['id'];
        $f->id_customer = (int) $booking['id_customer'];
        $f->id_room = (int) $booking['id_room'];
        $f->type = 'guest';
        $f->add();
        // Apply any online prepayment already recorded on the order as a deposit
        $paid = (float) Db::getInstance()->getValue('SELECT SUM(amount) FROM `'._DB_PREFIX_.'order_payment` op INNER JOIN `'._DB_PREFIX_.'orders` o ON o.reference=op.order_reference WHERE o.id_order='.(int) $booking['id_order']);
        if ($paid > 0 && !Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE id_pulse_folio_line IN (SELECT id_pulse_folio_line FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE source="order_prepaid" AND source_ref="'.(int) $booking['id_order'].'")')) {
            // one order may cover several rooms: apportion by room share
            $orderTotal = (float) Db::getInstance()->getValue('SELECT SUM(total_price_tax_incl) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_order='.(int) $booking['id_order']);
            $share = $orderTotal > 0 ? $booking['total_price_tax_incl'] / $orderTotal : 1;
            $f->post('DEP', 'Online prepayment applied', 1, round($paid * $share, 2), 0, true, 'online', 'order_prepaid', (int) $booking['id_order']);
        }
        return $f;
    }

    /* ---------- posting ---------- */

    /**
     * Post a charge or payment.
     * @param string $code   charge code (ROOM, REST, CASH ...) — department & default tax resolved from pulse_charge_code
     */
    public function post($code, $description, $qty, $unitPriceTaxExcl, $taxRate = null, $isPayment = null, $paymentMethod = null, $source = 'frontdesk', $sourceRef = null)
    {
        if ($this->status !== 'open') {
            throw new PrestaShopException('Folio '.$this->folio_no.' is '.$this->status);
        }
        $cc = PulseChargeCode::byCode($code);
        if (!$cc) {
            throw new PrestaShopException('Unknown charge code '.$code);
        }
        // routing: charges (not payments) may be redirected to a company / group master folio
        if (!$cc['is_payment'] && $source !== 'transfer' && $source !== 'routed') {
            $dest = PulseRouting::resolve($this, $cc['department']);
            if ($dest->id != $this->id) {
                return $dest->post($code, $description.' (Rm folio '.$this->folio_no.')', $qty, $unitPriceTaxExcl, $taxRate, $isPayment, $paymentMethod, 'routed', $this->folio_no);
            }
        }
        if ($taxRate === null) { $taxRate = (float) $cc['tax_rate']; }
        if ($isPayment === null) { $isPayment = (bool) $cc['is_payment']; }
        $amount = round($qty * $unitPriceTaxExcl * (1 + $taxRate / 100), 2);
        $ctx = Context::getContext();
        $emp = isset($ctx->employee) ? (int) $ctx->employee->id : 0;
        Db::getInstance()->insert('pulse_folio_line', array(
            'id_pulse_folio'       => (int) $this->id,
            'id_pulse_charge_code' => (int) $cc['id_pulse_charge_code'],
            'department'           => pSQL($cc['department']),
            'source'               => pSQL($source),
            'source_ref'           => pSQL($sourceRef),
            'description'          => pSQL($description),
            'qty'                  => (float) $qty,
            'unit_price_tax_excl'  => (float) $unitPriceTaxExcl,
            'tax_rate'             => (float) $taxRate,
            'amount_tax_incl'      => (float) $amount,
            'is_payment'           => (int) $isPayment,
            'payment_method'       => pSQL($paymentMethod),
            'id_employee'          => $emp,
            'id_cashier_session'   => $isPayment ? (int) PulseCashierSession::currentId($emp) : null,
            'business_date'        => PulseCoreService::businessDate(),
            'date_add'             => date('Y-m-d H:i:s'),
        ));
        $idLine = (int) Db::getInstance()->Insert_ID();
        $this->recalc();
        PulseCoreService::audit('pulsefrontdesk', $isPayment ? 'payment' : 'charge', array('code' => $code, 'amount' => $amount, 'line' => $idLine), 'pulse_folio', $this->id);
        PulseCoreService::event('actionPulseFolioPost', array('folio' => $this, 'id_line' => $idLine, 'code' => $code, 'amount' => $amount, 'is_payment' => $isPayment));
        return $idLine;
    }

    /** Record a payment taken in a foreign currency; stored in shop currency with the original amount/rate on the line. */
    public function postForeignPayment($code, $description, $amountForeign, $isoCode, $paymentMethod)
    {
        $cur = Currency::getIdByIsoCode($isoCode);
        if (!$cur) { throw new PrestaShopException('Currency '.$isoCode.' not enabled in QloApps'); }
        $c = new Currency($cur); $rate = (float) $c->conversion_rate; // shop currency = 1
        $amountShop = round($amountForeign / $rate, 2);
        $id = $this->post($code, $description.' ['.$isoCode.' '.number_format($amountForeign, 2).' @ '.$rate.']', 1, $amountShop, 0, true, $paymentMethod);
        Db::getInstance()->update('pulse_folio_line', array('currency_iso' => pSQL($isoCode), 'amount_foreign' => (float) $amountForeign, 'conversion_rate' => $rate), 'id_pulse_folio_line='.(int) $id);
        return $id;
    }

    public function voidLine($idLine, $reason)
    {
        Db::getInstance()->update('pulse_folio_line', array('voided' => 1, 'void_reason' => pSQL($reason)), 'id_pulse_folio_line='.(int) $idLine.' AND id_pulse_folio='.(int) $this->id);
        $this->recalc();
        PulseCoreService::audit('pulsefrontdesk', 'void_line', array('line' => $idLine, 'reason' => $reason), 'pulse_folio', $this->id);
    }

    /** Move a line to another folio (routing to company / group master). */
    public function transferLine($idLine, PulseFolio $to)
    {
        $line = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE id_pulse_folio_line='.(int) $idLine.' AND id_pulse_folio='.(int) $this->id.' AND voided=0');
        if (!$line || $to->status !== 'open') { return false; }
        Db::getInstance()->update('pulse_folio_line', array('voided' => 1, 'void_reason' => 'Transferred to '.pSQL($to->folio_no), 'transferred_to' => (int) $to->id), 'id_pulse_folio_line='.(int) $idLine);
        unset($line['id_pulse_folio_line'], $line['voided'], $line['void_reason'], $line['transferred_to']);
        $line['id_pulse_folio'] = (int) $to->id;
        $line['source'] = 'transfer';
        $line['source_ref'] = $this->folio_no;
        $line['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_folio_line', array_map('pSQL', $line));
        $this->recalc(); $to->recalc();
        return true;
    }

    public function recalc()
    {
        $r = Db::getInstance()->getRow('SELECT COALESCE(SUM(IF(is_payment=0,amount_tax_incl,0)),0) c, COALESCE(SUM(IF(is_payment=1,amount_tax_incl,0)),0) p FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND id_pulse_folio='.(int) $this->id);
        $this->total_charges = (float) $r['c'];
        $this->total_payments = (float) $r['p'];
        $this->balance = round($this->total_charges - $this->total_payments, 2);
        $this->update();
    }

    public function close()
    {
        if (abs($this->balance) > 0.009) {
            throw new PrestaShopException('Folio '.$this->folio_no.' has a balance of '.$this->balance.'. Settle or transfer to city ledger first.');
        }
        $this->status = 'closed';
        $this->closed_by = (int) Context::getContext()->employee->id;
        $this->date_closed = date('Y-m-d H:i:s');
        $this->update();
        PulseCoreService::audit('pulsefrontdesk', 'folio_close', null, 'pulse_folio', $this->id);
    }

    /** Settle outstanding balance to a company account (city ledger). */
    public function settleToCityLedger(PulseCompany $company)
    {
        if ($this->balance <= 0) { return false; }
        if ($company->credit_limit > 0 && ($company->ledger_balance + $this->balance) > $company->credit_limit) {
            throw new PrestaShopException('Credit limit exceeded for '.$company->name);
        }
        $amt = $this->balance;
        $this->post('CL', 'To city ledger: '.$company->name, 1, $amt, 0, true, 'city_ledger', 'frontdesk', 'company:'.$company->id);
        $company->ledger_balance += $amt;
        $company->update();
        // mirror onto the company's folio
        $cf = $company->folio();
        $cf->post('MISC', 'From folio '.$this->folio_no, 1, $amt, 0, false, null, 'transfer', $this->folio_no);
        return true;
    }

    public function lines($includeVoided = false)
    {
        return Db::getInstance()->executeS('SELECT l.*, e.firstname, e.lastname FROM `'._DB_PREFIX_.'pulse_folio_line` l LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=l.id_employee WHERE l.id_pulse_folio='.(int) $this->id.($includeVoided ? '' : ' AND l.voided=0').' ORDER BY l.date_add');
    }
}

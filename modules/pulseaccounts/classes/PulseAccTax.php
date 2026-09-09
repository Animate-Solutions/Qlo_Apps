<?php
/**
 * Nigerian tax: the VAT output/input register and monthly return, the Rivers State consumption tax on
 * F&B, the withholding-tax register with certificate numbers, and the FIRS/NRS e-invoicing hand-off.
 *
 * E-invoicing note: the payload here follows the BIS Billing 3.0 / UBL shape the FIRS Merchant-Buyer
 * Solution (MBS) and the NRS e-invoicing pilot are built on. The transmission endpoint, business id,
 * service id, API key and secret are settings — nothing is hard-coded and nothing is invented. With the
 * endpoint blank the queue still builds, stores and validates every payload (a dry run), so the property
 * is ready the day its credentials arrive. See README for exactly what a live connection needs.
 */
class PulseAccTax
{
    /* ---------------- VAT ---------------- */

    /** Record a VAT movement in the register. Idempotent on (direction, source, source_ref). */
    public static function recordVat($direction, $source, $ref, array $d)
    {
        return Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_acc_vat`
            (`direction`,`source`,`source_ref`,`doc_no`,`party_name`,`tin`,`department`,`net_amount`,`vat_rate`,`vat_amount`,`consumption_tax`,`business_date`,`period`,`id_pulse_acc_journal`,`date_add`) VALUES
            ("'.pSQL($direction).'","'.pSQL($source).'","'.pSQL(Tools::substr($ref, 0, 96)).'","'.pSQL(Tools::substr((string) (isset($d['doc_no']) ? $d['doc_no'] : ''), 0, 64)).'","'.pSQL(Tools::substr((string) (isset($d['party_name']) ? $d['party_name'] : ''), 0, 128)).'","'.pSQL(Tools::substr((string) (isset($d['tin']) ? $d['tin'] : ''), 0, 32)).'","'.pSQL(Tools::substr((string) (isset($d['department']) ? $d['department'] : ''), 0, 32)).'",
            '.round((float) (isset($d['net_amount']) ? $d['net_amount'] : 0), 2).','.round((float) (isset($d['vat_rate']) ? $d['vat_rate'] : PulseAccService::vatPct()), 3).','.round((float) (isset($d['vat_amount']) ? $d['vat_amount'] : 0), 2).','.round((float) (isset($d['consumption_tax']) ? $d['consumption_tax'] : 0), 2).',
            "'.pSQL(isset($d['business_date']) ? $d['business_date'] : PulseAccService::bd()).'","'.pSQL(PulseAccService::period(isset($d['business_date']) ? $d['business_date'] : PulseAccService::bd())).'",'.(int) (isset($d['id_journal']) ? $d['id_journal'] : 0).',NOW())');
    }

    public static function vatRegister($period, $direction = null, $limit = 2000)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_vat` WHERE period="'.pSQL($period).'"'.($direction ? ' AND direction="'.pSQL($direction).'"' : '').' ORDER BY business_date, id_pulse_acc_vat LIMIT '.(int) $limit);
    }

    /** The month's VAT return in the shape FIRS asks for: output, input, net payable. */
    public static function vatReturn($period)
    {
        $r = Db::getInstance()->getRow('SELECT
            ROUND(COALESCE(SUM(IF(direction="output",net_amount,0)),0),2) output_net, ROUND(COALESCE(SUM(IF(direction="output",vat_amount,0)),0),2) output_vat,
            ROUND(COALESCE(SUM(IF(direction="input",net_amount,0)),0),2) input_net, ROUND(COALESCE(SUM(IF(direction="input",vat_amount,0)),0),2) input_vat,
            ROUND(COALESCE(SUM(consumption_tax),0),2) consumption_tax, COUNT(*) rows_total
            FROM `'._DB_PREFIX_.'pulse_acc_vat` WHERE period="'.pSQL($period).'"');
        $r = $r ? $r : array('output_net' => 0, 'output_vat' => 0, 'input_net' => 0, 'input_vat' => 0, 'consumption_tax' => 0, 'rows_total' => 0);
        $r['period'] = $period;
        $r['net_payable'] = round((float) $r['output_vat'] - (float) $r['input_vat'], 2);
        $r['gl_output'] = self::glBalance('2210', $period);
        $r['gl_input'] = self::glBalance('1270', $period);
        $r['gl_consumption'] = self::glBalance('2240', $period);
        $r['due_date'] = date('Y-m-21', strtotime($period.'-01 +1 month'));
        return $r;
    }

    public static function vatDue($period)
    {
        $v = self::vatReturn($period);
        return $v['net_payable'];
    }

    protected static function glBalance($code, $period)
    {
        return round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(credit-debit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE posted=1 AND account_code="'.pSQL($code).'" AND period="'.pSQL($period).'"'), 2);
    }

    /**
     * File the month: mark the register rows as returned and move the net VAT from the output/input
     * control accounts into 2220 VAT payable to FIRS, ready for the transfer.
     */
    public static function fileVatReturn($period, $reference, $date = null)
    {
        $v = self::vatReturn($period);
        if ((float) $v['rows_total'] === 0.0) { throw new PrestaShopException('No VAT transactions in '.$period); }
        if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_vat` WHERE period="'.pSQL($period).'" AND returned=1')) { throw new PrestaShopException('Period '.$period.' has already been filed'); }
        $date = $date ? $date : date('Y-m-t', strtotime($period.'-01'));
        $lines = array();
        if ((float) $v['output_vat'] > 0.004) { $lines[] = array('account' => '2210', 'debit' => (float) $v['output_vat'], 'memo' => 'VAT output '.$period, 'tax_code' => 'VAT'); }
        if ((float) $v['input_vat'] > 0.004) { $lines[] = array('account' => '1270', 'credit' => (float) $v['input_vat'], 'memo' => 'VAT input claimed '.$period, 'tax_code' => 'VAT'); }
        $net = round((float) $v['output_vat'] - (float) $v['input_vat'], 2);
        $lines[] = array('account' => '2220', 'debit' => $net < 0 ? abs($net) : 0, 'credit' => $net > 0 ? $net : 0, 'memo' => 'VAT return '.$period.' — '.$reference);
        $idJ = PulseAccJournal::post(array('type' => 'general', 'source' => 'tax', 'source_ref' => 'vat:'.$period, 'business_date' => $date, 'reference' => $reference, 'memo' => 'VAT return '.$period, 'lines' => $lines));
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_acc_vat` SET returned=1, return_ref="'.pSQL(Tools::substr($reference, 0, 32)).'" WHERE period="'.pSQL($period).'"');
        PulseCoreService::audit('pulseaccounts', 'vat_return', array('period' => $period, 'net' => $net, 'ref' => $reference), 'pulse_acc_journal', (int) $idJ);
        return $idJ;
    }

    /* ---------------- withholding tax ---------------- */

    /** Certificate numbers are sequential per direction so the file the FIRS office asks for is contiguous. */
    public static function nextCertNo($direction)
    {
        $prefix = $direction === 'suffered' ? 'WHTR' : 'WHT';
        return PulseAccService::nextNo($prefix, 5);
    }

    public static function recordWht(array $d)
    {
        $direction = isset($d['direction']) ? $d['direction'] : 'deducted';
        if (!empty($d['source']) && !empty($d['source_ref'])) {
            $ex = Db::getInstance()->getValue('SELECT id_pulse_acc_wht FROM `'._DB_PREFIX_.'pulse_acc_wht` WHERE source="'.pSQL($d['source']).'" AND source_ref="'.pSQL($d['source_ref']).'" AND direction="'.pSQL($direction).'"');
            if ($ex) { return (int) $ex; }
        }
        $date = isset($d['business_date']) ? $d['business_date'] : PulseAccService::bd();
        Db::getInstance()->insert('pulse_acc_wht', array(
            'cert_no' => pSQL(self::nextCertNo($direction)), 'direction' => pSQL($direction), 'party_type' => pSQL(isset($d['party_type']) ? $d['party_type'] : 'supplier'),
            'party_name' => pSQL(Tools::substr((string) (isset($d['party_name']) ? $d['party_name'] : 'Unknown'), 0, 128)), 'tin' => pSQL(Tools::substr((string) (isset($d['tin']) ? $d['tin'] : ''), 0, 32)),
            'id_party' => !empty($d['id_party']) ? (int) $d['id_party'] : null, 'wht_type' => pSQL(isset($d['wht_type']) ? $d['wht_type'] : 'services'),
            'base_amount' => round((float) (isset($d['base_amount']) ? $d['base_amount'] : 0), 2), 'rate_pct' => round((float) (isset($d['rate_pct']) ? $d['rate_pct'] : 5), 3),
            'amount' => round((float) (isset($d['amount']) ? $d['amount'] : 0), 2), 'source' => pSQL(isset($d['source']) ? $d['source'] : 'manual'),
            'source_ref' => pSQL(Tools::substr((string) (isset($d['source_ref']) ? $d['source_ref'] : ''), 0, 96)), 'doc_no' => pSQL(Tools::substr((string) (isset($d['doc_no']) ? $d['doc_no'] : ''), 0, 64)),
            'business_date' => pSQL($date), 'period' => pSQL(PulseAccService::period($date)), 'id_pulse_acc_journal' => !empty($d['id_journal']) ? (int) $d['id_journal'] : null,
            'id_employee' => PulseAccService::emp(), 'date_add' => date('Y-m-d H:i:s'),
        ), true);
        return (int) Db::getInstance()->Insert_ID();
    }

    public static function whtRegister($period = null, $direction = null, $remitted = null, $limit = 1000)
    {
        $w = array('1');
        if ($period) { $w[] = 'period="'.pSQL($period).'"'; }
        if ($direction) { $w[] = 'direction="'.pSQL($direction).'"'; }
        if ($remitted !== null) { $w[] = 'remitted='.(int) $remitted; }
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_wht` WHERE '.implode(' AND ', $w).' ORDER BY business_date DESC, id_pulse_acc_wht DESC LIMIT '.(int) $limit);
    }

    public static function whtCert($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_wht` WHERE id_pulse_acc_wht='.(int) $id); }

    public static function whtSummary($period)
    {
        $r = Db::getInstance()->getRow('SELECT ROUND(COALESCE(SUM(IF(direction="deducted",amount,0)),0),2) deducted, ROUND(COALESCE(SUM(IF(direction="suffered",amount,0)),0),2) suffered,
            ROUND(COALESCE(SUM(IF(direction="deducted" AND remitted=0,amount,0)),0),2) unremitted, COUNT(*) certificates FROM `'._DB_PREFIX_.'pulse_acc_wht` WHERE period="'.pSQL($period).'"');
        return $r ? $r : array('deducted' => 0, 'suffered' => 0, 'unremitted' => 0, 'certificates' => 0);
    }

    /** Remit the WHT withheld in a period: 2230 Dr, bank Cr, certificates flagged. */
    public static function remitWht($period, $reference, $idBankAccount = null, $date = null)
    {
        $rows = self::whtRegister($period, 'deducted', 0, 5000);
        if (!$rows) { throw new PrestaShopException('Nothing to remit for '.$period); }
        $total = 0; $ids = array();
        foreach ($rows as $r) { $total += (float) $r['amount']; $ids[] = (int) $r['id_pulse_acc_wht']; }
        $total = round($total, 2);
        $date = $date ? $date : date('Y-m-21', strtotime($period.'-01 +1 month'));
        $bank = $idBankAccount ? PulseAccService::bankAccount((int) $idBankAccount) : null;
        $cash = $bank ? $bank['account_code'] : Configuration::get('PULSE_ACC_DEFAULT_BANK');
        $idJ = PulseAccJournal::post(array('type' => 'payment', 'source' => 'tax', 'source_ref' => 'wht:'.$period, 'business_date' => $date, 'reference' => $reference, 'memo' => 'WHT remittance '.$period, 'lines' => array(
            array('account' => '2230', 'debit' => $total, 'memo' => 'WHT remitted '.$period.' — '.$reference, 'tax_code' => 'WHT'),
            array('account' => $cash, 'credit' => $total, 'memo' => 'WHT remittance '.$period),
        )));
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_acc_wht` SET remitted=1, remit_ref="'.pSQL(Tools::substr($reference, 0, 64)).'", remit_date="'.pSQL($date).'" WHERE id_pulse_acc_wht IN ('.implode(',', $ids).')');
        PulseCoreService::audit('pulseaccounts', 'wht_remit', array('period' => $period, 'total' => $total, 'certificates' => count($ids)), 'pulse_acc_journal', (int) $idJ);
        return array('id_journal' => $idJ, 'total' => $total, 'certificates' => count($ids));
    }

    /** Printable WHT certificate in the layout a Nigerian supplier expects to attach to its own return. */
    public static function whtCertificate($id)
    {
        $c = self::whtCert($id);
        if (!$c) { throw new PrestaShopException('Unknown certificate'); }
        return array(
            'cert' => $c,
            'hotel' => Configuration::get('PULSE_ACC_HOTEL_NAME'), 'hotel_tin' => Configuration::get('PULSE_ACC_HOTEL_TIN'), 'hotel_address' => Configuration::get('PULSE_ACC_HOTEL_ADDRESS'),
            'types' => array('services' => 'Professional / technical services', 'contracts' => 'Contract of supply or construction', 'rent' => 'Rent of property or equipment', 'dividends' => 'Dividends, interest', 'royalties' => 'Royalties', 'commission' => 'Commission / consultancy', 'other' => 'Other'),
        );
    }

    /* ---------------- FIRS / NRS e-invoicing ---------------- */

    /** Build the payload and put it on the queue. Never blocks the invoice — the queue is drained by cron. */
    public static function queueEinvoice($idInvoice)
    {
        $inv = PulseAccAr::invoice($idInvoice);
        if (!$inv) { throw new PrestaShopException('Unknown invoice'); }
        $ex = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_einvoice` WHERE id_pulse_acc_invoice='.(int) $idInvoice);
        if ($ex && in_array($ex['status'], array('accepted', 'sending'))) { return (int) $ex['id_pulse_acc_einvoice']; }
        $payload = self::einvoicePayload($inv);
        if ($ex) {
            Db::getInstance()->update('pulse_acc_einvoice', array('payload' => pSQL(json_encode($payload), true), 'status' => 'queued', 'last_error' => null, 'next_retry_at' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_einvoice='.(int) $ex['id_pulse_acc_einvoice'], 0, true);
            return (int) $ex['id_pulse_acc_einvoice'];
        }
        Db::getInstance()->insert('pulse_acc_einvoice', array(
            'doc_type' => pSQL($inv['type'] === 'credit_note' ? 'credit_note' : 'invoice'), 'id_pulse_acc_invoice' => (int) $idInvoice, 'invoice_no' => pSQL($inv['invoice_no']),
            'irn' => pSQL($payload['irn']), 'payload' => pSQL(json_encode($payload), true), 'status' => 'queued', 'business_date' => pSQL($inv['invoice_date']),
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        return (int) Db::getInstance()->Insert_ID();
    }

    /**
     * The document itself. Field names follow the UBL/BIS Billing 3.0 vocabulary the FIRS MBS validation
     * service uses; the IRN is the documented "<invoice number>-<service id>-<YYYYMMDD>" form.
     */
    public static function einvoicePayload(array $inv)
    {
        $date = Tools::substr($inv['invoice_date'], 0, 10);
        $serviceId = (string) Configuration::get('PULSE_ACC_EINV_SERVICE_ID');
        $vatPct = PulseAccService::vatPct();
        $lines = array(); $i = 0;
        foreach ($inv['lines'] as $l) {
            $net = round((float) $l['line_total'] - (float) $l['tax_amount'], 2);
            $lines[] = array(
                'hsn_code' => '', 'id' => ++$i, 'invoiced_quantity' => (float) $l['qty'], 'line_extension_amount' => $net,
                'item' => array('name' => $l['description'], 'description' => $l['description'], 'sellers_item_identification' => array('id' => $l['account_code'])),
                'price' => array('price_amount' => round((float) $l['unit_price'], 2), 'base_quantity' => 1, 'price_unit' => 'NGN per unit'),
                'tax_total' => array(array('tax_amount' => round((float) $l['tax_amount'], 2), 'tax_subtotal' => array(array('taxable_amount' => $net, 'tax_amount' => round((float) $l['tax_amount'], 2), 'tax_category' => array('id' => 'VAT', 'percent' => (float) $l['tax_rate']))))),
            );
        }
        $net = round((float) $inv['subtotal'], 2); $tax = round((float) $inv['tax_amount'], 2); $total = round((float) $inv['total'], 2);
        return array(
            'business_id' => (string) Configuration::get('PULSE_ACC_EINV_BUSINESS_ID'),
            'irn' => preg_replace('/[^A-Za-z0-9]/', '', $inv['invoice_no']).'-'.($serviceId !== '' ? $serviceId : 'PENDING').'-'.date('Ymd', strtotime($date)),
            'issue_date' => $date, 'due_date' => Tools::substr($inv['due_date'], 0, 10),
            'invoice_type_code' => $inv['type'] === 'credit_note' ? '381' : '380',
            'document_currency_code' => $inv['currency'] ? $inv['currency'] : 'NGN', 'tax_currency_code' => 'NGN',
            'note' => (string) $inv['note'],
            'accounting_supplier_party' => array(
                'party_name' => (string) Configuration::get('PULSE_ACC_HOTEL_NAME'), 'tin' => (string) Configuration::get('PULSE_ACC_HOTEL_TIN'),
                'email' => (string) Configuration::get('PULSE_ACC_HOTEL_EMAIL'), 'business_description' => 'Hotel and hospitality services',
                'postal_address' => array('street_name' => (string) Configuration::get('PULSE_ACC_HOTEL_ADDRESS'), 'city_name' => 'Port Harcourt', 'country' => 'NG'),
            ),
            'accounting_customer_party' => array(
                'party_name' => (string) $inv['company_name'], 'tin' => (string) $inv['tin'], 'email' => (string) $inv['email'],
                'postal_address' => array('street_name' => (string) $inv['address'], 'country' => 'NG'),
            ),
            'legal_monetary_total' => array('line_extension_amount' => $net, 'tax_exclusive_amount' => $net, 'tax_inclusive_amount' => $total, 'payable_amount' => $total),
            'tax_total' => array(array('tax_amount' => $tax, 'tax_subtotal' => array(array('taxable_amount' => $net, 'tax_amount' => $tax, 'tax_category' => array('id' => 'VAT', 'percent' => $vatPct))))),
            'invoice_line' => $lines,
            'payment_means' => array(array('payment_means_code' => 30, 'payment_due_date' => Tools::substr($inv['due_date'], 0, 10))),
            'billing_reference' => $inv['id_credited_invoice'] ? array(array('irn' => (string) Db::getInstance()->getValue('SELECT invoice_no FROM `'._DB_PREFIX_.'pulse_acc_invoice` WHERE id_pulse_acc_invoice='.(int) $inv['id_credited_invoice']))) : array(),
        );
    }

    public static function einvoiceQueue($status = null, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT e.*, i.company_name, i.total FROM `'._DB_PREFIX_.'pulse_acc_einvoice` e LEFT JOIN `'._DB_PREFIX_.'pulse_acc_invoice` i ON i.id_pulse_acc_invoice=e.id_pulse_acc_invoice WHERE 1'.($status ? ' AND e.status="'.pSQL($status).'"' : '').' ORDER BY e.id_pulse_acc_einvoice DESC LIMIT '.(int) $limit);
    }

    public static function einvoiceRow($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_einvoice` WHERE id_pulse_acc_einvoice='.(int) $id); }

    /**
     * Try to transmit one queued document. With no endpoint configured this validates the payload and
     * leaves the row queued (a dry run) rather than pretending it was filed. With an endpoint it POSTs
     * JSON with the documented key/secret headers, a short timeout and exponential backoff on failure.
     */
    public static function einvoiceSend($id)
    {
        $e = self::einvoiceRow($id);
        if (!$e) { throw new PrestaShopException('Unknown e-invoice row'); }
        if ($e['status'] === 'accepted') { return array('ok' => true, 'status' => 'accepted'); }
        $payload = json_decode($e['payload'], true);
        $problems = self::einvoiceValidate(is_array($payload) ? $payload : array());
        if ($problems) {
            Db::getInstance()->update('pulse_acc_einvoice', array('status' => 'rejected', 'last_error' => pSQL(Tools::substr(implode('; ', $problems), 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_einvoice='.(int) $id);
            return array('ok' => false, 'status' => 'rejected', 'error' => implode('; ', $problems));
        }
        $endpoint = trim((string) Configuration::get('PULSE_ACC_EINV_ENDPOINT'));
        if ($endpoint === '' || !Configuration::get('PULSE_ACC_EINV_ENABLED')) {
            Db::getInstance()->update('pulse_acc_einvoice', array('status' => 'queued', 'last_error' => pSQL('Validated — no transmission endpoint configured (dry run)'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_einvoice='.(int) $id);
            return array('ok' => true, 'status' => 'queued', 'error' => 'Dry run: payload validated, endpoint not configured');
        }
        $attempts = (int) $e['attempts'] + 1;
        Db::getInstance()->update('pulse_acc_einvoice', array('status' => 'sending', 'attempts' => $attempts, 'submitted_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_einvoice='.(int) $id);
        $key = (string) Configuration::get('PULSE_ACC_EINV_KEY');
        $secret = (string) Configuration::get('PULSE_ACC_EINV_SECRET');
        if ($secret !== '' && class_exists('PulseCoreService')) { $plain = PulseCoreService::decrypt($secret); if ($plain) { $secret = $plain; } }
        $timeout = (int) Configuration::get('PULSE_ACC_EINV_TIMEOUT'); $timeout = $timeout > 0 ? $timeout : 20;
        $body = json_encode($payload);
        $ch = curl_init(rtrim($endpoint, '/').'/api/v1/invoice/signing');
        curl_setopt_array($ch, array(
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Accept: application/json', 'x-api-key: '.$key, 'x-api-secret: '.$secret),
        ));
        $resp = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        $json = $resp ? json_decode($resp, true) : null;
        if ($code >= 200 && $code < 300) {
            Db::getInstance()->update('pulse_acc_einvoice', array(
                'status' => 'accepted', 'http_code' => $code, 'response' => pSQL(Tools::substr((string) $resp, 0, 60000), true), 'last_error' => null,
                'irn' => pSQL(Tools::substr(isset($json['data']['irn']) ? $json['data']['irn'] : $e['irn'], 0, 96)),
                'qr_data' => pSQL(Tools::substr(isset($json['data']['qr_code']) ? $json['data']['qr_code'] : '', 0, 512)),
                'csid' => pSQL(Tools::substr(isset($json['data']['csid']) ? $json['data']['csid'] : (isset($json['data']['signature']) ? $json['data']['signature'] : ''), 0, 255)),
                'accepted_at' => date('Y-m-d H:i:s'), 'next_retry_at' => null, 'date_upd' => date('Y-m-d H:i:s'),
            ), 'id_pulse_acc_einvoice='.(int) $id, 0, true);
            PulseCoreService::audit('pulseaccounts', 'einvoice_accepted', array('invoice' => $e['invoice_no'], 'http' => $code), 'pulse_acc_einvoice', (int) $id);
            return array('ok' => true, 'status' => 'accepted');
        }
        $message = $err ? $err : (isset($json['message']) ? $json['message'] : 'HTTP '.$code);
        $fatal = $code >= 400 && $code < 500 && $code !== 408 && $code !== 429;
        $max = (int) Configuration::get('PULSE_ACC_RETRY_MAX'); $max = $max > 0 ? $max : 5;
        $status = $fatal ? 'rejected' : ($attempts >= $max ? 'failed' : 'queued');
        Db::getInstance()->update('pulse_acc_einvoice', array(
            'status' => $status, 'http_code' => $code, 'last_error' => pSQL(Tools::substr($message, 0, 255)), 'response' => pSQL(Tools::substr((string) $resp, 0, 60000), true),
            'next_retry_at' => $status === 'queued' ? date('Y-m-d H:i:s', time() + min(3600, 60 * pow(2, $attempts))) : null, 'date_upd' => date('Y-m-d H:i:s'),
        ), 'id_pulse_acc_einvoice='.(int) $id, 0, true);
        return array('ok' => false, 'status' => $status, 'error' => $message);
    }

    /** Everything the service will reject the document for, checked before we spend a request on it. */
    public static function einvoiceValidate(array $p)
    {
        $out = array();
        if (empty($p['business_id'])) { $out[] = 'Business ID is not set in Settings'; }
        if (empty($p['accounting_supplier_party']['tin'])) { $out[] = 'The hotel TIN is not set in Settings'; }
        if (empty($p['accounting_customer_party']['party_name'])) { $out[] = 'Customer name missing'; }
        if (empty($p['invoice_line'])) { $out[] = 'Invoice has no lines'; }
        if (!isset($p['legal_monetary_total']['payable_amount']) || (float) $p['legal_monetary_total']['payable_amount'] === 0.0) { $out[] = 'Payable amount is zero'; }
        if (isset($p['legal_monetary_total'])) {
            $lines = 0;
            foreach ((isset($p['invoice_line']) ? $p['invoice_line'] : array()) as $l) { $lines += (float) $l['line_extension_amount']; }
            if (abs(round($lines, 2) - round((float) $p['legal_monetary_total']['line_extension_amount'], 2)) > 0.05) { $out[] = 'Line totals do not add up to the invoice net'; }
        }
        return $out;
    }

    /** Drain the e-invoice queue (cron). Respects the retry backoff so a dead link is not hammered. */
    public static function einvoiceDrain($limit = 50)
    {
        $rows = Db::getInstance()->executeS('SELECT id_pulse_acc_einvoice FROM `'._DB_PREFIX_.'pulse_acc_einvoice` WHERE status IN ("queued","sending") AND (next_retry_at IS NULL OR next_retry_at<=NOW()) ORDER BY id_pulse_acc_einvoice LIMIT '.(int) $limit);
        $ok = 0; $bad = 0;
        foreach ($rows as $r) {
            try { $x = self::einvoiceSend((int) $r['id_pulse_acc_einvoice']); if (!empty($x['ok']) && $x['status'] === 'accepted') { $ok++; } else { $bad++; } }
            catch (Exception $e) { $bad++; }
        }
        return array('sent' => $ok, 'pending' => $bad);
    }
}

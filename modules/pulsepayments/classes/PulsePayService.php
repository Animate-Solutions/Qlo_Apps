<?php
/**
 * Payment engine: gateway registry, transaction ledger, pre-authorisations, captures, refunds and folio/POS posting.
 * Works standalone (transactions are still recorded and reconciled); Front Desk and POS postings are optional.
 */
class PulsePayService
{
    /* ---------- environment ---------- */
    public static function fd() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFolio'); }
    public static function pos() { return Module::isEnabled('pulsepos') && class_exists('PulsePosPayment'); }
    public static function rpt() { return Module::isEnabled('pulsereports') && class_exists('PulseExpense'); }
    public static function bd() { return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    public static function emp() { $c = Context::getContext(); return isset($c->employee) && $c->employee ? (int) $c->employee->id : 0; }
    protected static function now() { return date('Y-m-d H:i:s'); }
    public static function currency() { $c = Context::getContext(); return isset($c->currency) && $c->currency->iso_code ? $c->currency->iso_code : 'NGN'; }

    /** PAY250908-0007 — short enough for a bank narration, unique enough for a gateway reference. */
    public static function nextRef($prefix = 'PAY')
    {
        $n = (int) PulseCoreService::setting('pulsepayments', 'seq_'.$prefix) + 1;
        PulseCoreService::setting('pulsepayments', 'seq_'.$prefix, $n);
        return $prefix.date('ymd').'-'.str_pad($n % 100000, 5, '0', STR_PAD_LEFT);
    }

    /* ---------- gateways ---------- */
    public static function gateways($activeOnly = false) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_gateway`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY sort, code'); }
    public static function gatewayRow($code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_gateway` WHERE code="'.pSQL($code).'"'); }

    /** Decrypt the stored credentials and hand back a live adapter, or null when the gateway is unknown/unusable. */
    public static function adapter($code)
    {
        $g = self::gatewayRow($code);
        if (!$g || !class_exists($g['adapter'])) { return null; }
        foreach (array('secret_key', 'webhook_secret', 'extra') as $k) { $g[$k] = self::dec($g[$k]); }
        $a = new $g['adapter']();
        return $a->init($g);
    }

    public static function enc($plain) { if ($plain === null || $plain === '') { return ''; } return PulseCoreService::encrypt($plain); }
    public static function dec($cipher) { if ($cipher === null || $cipher === '') { return ''; } $v = PulseCoreService::decrypt($cipher); return $v === false ? '' : $v; }
    /** Never echo a secret back into a form — show its shape only. */
    public static function masked($cipher) { $v = self::dec($cipher); if ($v === '') { return ''; } return Tools::strlen($v) <= 8 ? str_repeat('•', Tools::strlen($v)) : Tools::substr($v, 0, 4).str_repeat('•', 8).Tools::substr($v, -4); }

    /** Save gateway credentials; a blank secret field means "leave what is stored alone". */
    public static function saveGateway($code, array $d)
    {
        $g = self::gatewayRow($code);
        if (!$g) { throw new PrestaShopException('Unknown gateway '.$code); }
        $u = array('active' => (int) !empty($d['active']), 'test_mode' => (int) !empty($d['test_mode']), 'date_upd' => self::now());
        foreach (array('name', 'endpoint', 'public_key', 'merchant_id', 'currency', 'channels') as $k) { if (isset($d[$k])) { $u[$k] = pSQL($d[$k]); } }
        foreach (array('fee_percent', 'fee_flat', 'fee_cap', 'fee_flat_waive_below') as $k) { if (isset($d[$k])) { $u[$k] = (float) $d[$k]; } }
        foreach (array('secret_key', 'webhook_secret') as $k) { if (isset($d[$k]) && trim($d[$k]) !== '') { $u[$k] = pSQL(self::enc(trim($d[$k])), true); } }
        if (isset($d['extra']) && is_array($d['extra'])) { $cur = json_decode(self::dec($g['extra']), true); if (!is_array($cur)) { $cur = array(); } foreach ($d['extra'] as $k => $v) { if ($v !== '' || array_key_exists($k, $cur)) { $cur[$k] = $v; } } $u['extra'] = pSQL(self::enc(json_encode($cur)), true); }
        Db::getInstance()->update('pulse_pay_gateway', $u, 'code="'.pSQL($code).'"');
        PulseCoreService::audit('pulsepayments', 'gateway_save', array('gateway' => $code, 'active' => !empty($d['active']), 'test' => !empty($d['test_mode'])), 'pulse_pay_gateway', (int) $g['id_pulse_pay_gateway']);
        return true;
    }

    /** First active gateway that serves this channel, honouring the configured preference. */
    public static function defaultGateway($channel = 'web')
    {
        $pref = Configuration::get('PULSE_PAY_DEFAULT_GATEWAY');
        if ($pref && ($g = self::gatewayRow($pref)) && $g['active'] && in_array($channel, explode(',', $g['channels']))) { return $g['code']; }
        foreach (self::gateways(true) as $g) { if (in_array($channel, explode(',', $g['channels']))) { return $g['code']; } }
        return 'manual';
    }

    /* ---------- transactions ---------- */
    public static function tx($ref)
    {
        if (is_numeric($ref)) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE id_pulse_pay_transaction='.(int) $ref); }
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE reference="'.pSQL($ref).'" OR gateway_ref="'.pSQL($ref).'" ORDER BY id_pulse_pay_transaction LIMIT 1');
    }

    /** Insert an intent. An identical idempotency key returns the existing row instead of a second charge. */
    public static function createTx(array $d)
    {
        $idem = isset($d['idempotency_key']) && $d['idempotency_key'] ? $d['idempotency_key'] : Tools::substr(sha1(json_encode(array(isset($d['gateway']) ? $d['gateway'] : '', isset($d['amount']) ? round((float) $d['amount'], 2) : 0, isset($d['purpose']) ? $d['purpose'] : '', isset($d['id_htl_booking']) ? (int) $d['id_htl_booking'] : 0, isset($d['id_pulse_pos_check']) ? (int) $d['id_pulse_pos_check'] : 0, microtime(true), mt_rand()))), 0, 64);
        if ($x = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE idempotency_key="'.pSQL($idem).'"')) { return $x; }
        $ref = isset($d['reference']) && $d['reference'] ? $d['reference'] : self::nextRef(isset($d['ref_prefix']) ? $d['ref_prefix'] : 'PAY');
        $row = array(
            'reference' => pSQL($ref), 'idempotency_key' => pSQL($idem),
            'gateway' => pSQL(isset($d['gateway']) ? $d['gateway'] : 'manual'), 'gateway_ref' => isset($d['gateway_ref']) ? pSQL($d['gateway_ref']) : null,
            'type' => pSQL(isset($d['type']) ? $d['type'] : 'charge'), 'purpose' => pSQL(isset($d['purpose']) ? $d['purpose'] : 'folio'),
            'channel' => pSQL(isset($d['channel']) ? $d['channel'] : 'desk'), 'method' => pSQL(isset($d['method']) ? $d['method'] : 'card'),
            'state' => pSQL(isset($d['state']) ? $d['state'] : 'intent'), 'amount' => round((float) $d['amount'], 2),
            'surcharge' => round((float) (isset($d['surcharge']) ? $d['surcharge'] : 0), 2), 'surcharge_tax' => round((float) (isset($d['surcharge_tax']) ? $d['surcharge_tax'] : 0), 2),
            'currency' => pSQL(isset($d['currency']) ? $d['currency'] : self::currency()),
            'id_customer' => isset($d['id_customer']) ? (int) $d['id_customer'] : null, 'id_htl_booking' => isset($d['id_htl_booking']) ? (int) $d['id_htl_booking'] : null,
            'id_pulse_folio' => isset($d['id_pulse_folio']) ? (int) $d['id_pulse_folio'] : null, 'id_pulse_pos_check' => isset($d['id_pulse_pos_check']) ? (int) $d['id_pulse_pos_check'] : null,
            'id_pulse_pay_link' => isset($d['id_pulse_pay_link']) ? (int) $d['id_pulse_pay_link'] : null, 'id_order' => isset($d['id_order']) ? (int) $d['id_order'] : null,
            'id_parent' => isset($d['id_parent']) ? (int) $d['id_parent'] : null, 'hold_type' => pSQL(isset($d['hold_type']) ? $d['hold_type'] : 'none'),
            'customer_name' => pSQL(isset($d['customer_name']) ? $d['customer_name'] : ''), 'customer_email' => pSQL(isset($d['customer_email']) ? $d['customer_email'] : ''), 'customer_phone' => pSQL(isset($d['customer_phone']) ? $d['customer_phone'] : ''),
            'description' => pSQL(isset($d['description']) ? Tools::substr($d['description'], 0, 255) : ''), 'expires_at' => isset($d['expires_at']) ? pSQL($d['expires_at']) : null,
            'id_employee' => self::emp(), 'business_date' => pSQL(self::bd()), 'date_add' => self::now(), 'date_upd' => self::now(),
        );
        if (!$row['id_customer'] && $row['id_htl_booking'] && ($b = self::bookingInfo($row['id_htl_booking']))) { $row['id_customer'] = (int) $b['id_customer']; if (!$row['customer_name']) { $row['customer_name'] = pSQL($b['guest']); } if (!$row['customer_email']) { $row['customer_email'] = pSQL($b['email']); } }
        if ($row['id_customer'] && (!$row['customer_email'] || !$row['customer_name'])) { $c = new Customer((int) $row['id_customer']); if (Validate::isLoadedObject($c)) { $row['customer_email'] = $row['customer_email'] ?: pSQL($c->email); $row['customer_name'] = $row['customer_name'] ?: pSQL($c->firstname.' '.$c->lastname); } }
        Db::getInstance()->insert('pulse_pay_transaction', $row, true);
        $row['id_pulse_pay_transaction'] = (int) Db::getInstance()->Insert_ID();
        // a lost race on the reference/idempotency sequence must never leave us posting money against transaction 0
        if (!$row['id_pulse_pay_transaction']) { throw new PrestaShopException('Payment record '.$ref.' could not be written — try again'); }
        return $row;
    }

    protected static function bookingInfo($idBooking)
    {
        if (!$idBooking) { return null; }
        return Db::getInstance()->getRow('SELECT b.id_customer, b.id_room, CONCAT(c.firstname," ",c.lastname) guest, c.email, r.room_num FROM `'._DB_PREFIX_.'htl_booking_detail` b LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room WHERE b.id='.(int) $idBooking);
    }

    /** Fold an adapter result into the transaction row (state, gateway ref, card details, fee, raw payload). */
    public static function applyResult(array $tx, array $r)
    {
        $u = array('date_upd' => self::now());
        if (!empty($r['gateway_ref'])) { $u['gateway_ref'] = pSQL($r['gateway_ref']); }
        if (!empty($r['auth_code'])) { $u['auth_code'] = pSQL($r['auth_code']); }
        if (!empty($r['rrn'])) { $u['rrn'] = pSQL($r['rrn']); }
        if (!empty($r['card_last4'])) { $u['card_last4'] = pSQL($r['card_last4']); }
        if (!empty($r['card_brand'])) { $u['card_brand'] = pSQL(Tools::substr($r['card_brand'], 0, 24)); }
        if (!empty($r['bank'])) { $u['bank'] = pSQL(Tools::substr($r['bank'], 0, 64)); }
        if (!empty($r['method'])) { $u['method'] = pSQL($r['method']); }
        if (!empty($r['hold_type'])) { $u['hold_type'] = pSQL($r['hold_type']); }
        if (!empty($r['auth_token'])) { $u['auth_token'] = pSQL(self::enc($r['auth_token']), true); }
        if (isset($r['raw'])) { $u['raw'] = pSQL(PulsePayAdapter::redact(json_encode($r['raw'])), true); }
        if (!empty($r['state'])) { $u['state'] = pSQL($r['state']); }
        if (!empty($r['error'])) { $u['failed_reason'] = pSQL(Tools::substr($r['error'], 0, 255)); }
        if (!empty($r['state']) && $r['state'] === 'authorized') { $u['authorized_at'] = self::now(); }
        if (!empty($r['state']) && in_array($r['state'], array('captured', 'settled'))) {
            $u['captured_at'] = self::now();
            $u['amount_captured'] = round(isset($r['amount']) && $r['amount'] > 0 ? (float) $r['amount'] : (float) $tx['amount'], 2);
            $fee = isset($r['fee']) && $r['fee'] !== null ? (float) $r['fee'] : self::estimateFee($tx['gateway'], $u['amount_captured']);
            $u['fee'] = round($fee, 2); $u['net'] = round($u['amount_captured'] - $fee, 2);
        }
        Db::getInstance()->update('pulse_pay_transaction', $u, 'id_pulse_pay_transaction='.(int) $tx['id_pulse_pay_transaction']);
        return array_merge($tx, $u);
    }

    public static function estimateFee($gateway, $amount) { $a = self::adapter($gateway); return $a ? $a->fee($amount) : 0; }

    /* ---------- surcharge policy ---------- */
    /** Card surcharge (and VAT on it) for a channel, as configured. Returns array(surcharge, tax, total). */
    public static function surcharge($amount, $channel = 'web', $method = 'card')
    {
        $pct = (float) Configuration::get('PULSE_PAY_SURCHARGE_PCT');
        $chans = array_filter(array_map('trim', explode(',', (string) Configuration::get('PULSE_PAY_SURCHARGE_CHANNELS'))));
        if ($pct <= 0 || !in_array($channel, $chans) || in_array($method, array('cash', 'transfer'))) { return array('surcharge' => 0, 'tax' => 0, 'total' => round((float) $amount, 2)); }
        $s = round((float) $amount * $pct / 100, 2);
        $t = round($s * (float) Configuration::get('PULSE_PAY_SURCHARGE_VAT_PCT') / 100, 2);
        return array('surcharge' => $s, 'tax' => $t, 'total' => round((float) $amount + $s + $t, 2));
    }

    /* ---------- charging ---------- */
    /**
     * Start (and where possible finish) a payment.
     * $d: amount, gateway, channel, method, purpose, token, id_htl_booking, id_pulse_folio, id_pulse_pos_check, description, return_url.
     */
    public static function charge(array $d)
    {
        $amount = round((float) $d['amount'], 2);
        if ($amount <= 0) { throw new PrestaShopException('Amount must be greater than zero'); }
        $channel = isset($d['channel']) ? $d['channel'] : 'desk';
        $gateway = isset($d['gateway']) && $d['gateway'] ? $d['gateway'] : self::defaultGateway($channel);
        $sur = self::surcharge($amount, $channel, isset($d['method']) ? $d['method'] : 'card');
        $tx = self::createTx(array_merge($d, array('gateway' => $gateway, 'channel' => $channel, 'amount' => $sur['total'], 'surcharge' => $sur['surcharge'], 'surcharge_tax' => $sur['tax'], 'type' => 'charge')));
        if (!in_array($tx['state'], array('intent', 'awaiting_confirmation'))) { return $tx; }
        $a = self::adapter($gateway);
        if (!$a) { return self::applyResult($tx, array('ok' => false, 'state' => 'failed', 'error' => 'Gateway '.$gateway.' is not available')); }
        $r = $a->charge($tx, $d);
        $tx = self::applyResult($tx, $r);
        if (!empty($r['ok']) && in_array($tx['state'], array('captured', 'settled'))) { self::postToLedger($tx, 'capture', (float) $tx['amount_captured']); }
        if (empty($r['ok'])) { PulseCoreService::event('actionPulsePaymentFailed', array('reference' => $tx['reference'], 'gateway' => $gateway, 'error' => isset($r['error']) ? $r['error'] : '')); }
        $tx['redirect_url'] = isset($r['redirect_url']) ? $r['redirect_url'] : null;
        $tx['form_action'] = isset($r['form_action']) ? $r['form_action'] : null;
        $tx['form_fields'] = isset($r['form_fields']) ? $r['form_fields'] : null;
        $tx['instructions'] = isset($r['instructions']) ? $r['instructions'] : null;
        $tx['error'] = isset($r['error']) ? $r['error'] : null;
        return $tx;
    }

    /* ---------- pre-authorisation ---------- */
    /**
     * Hold an amount against the guest's card at check-in.
     * When the gateway cannot hold (no saved card, gateway down, Interswitch), fall back to a recorded manual hold
     * so the desk is never blocked — that is exactly what PulsePaymentBridge expects.
     */
    public static function authorize($idCustomer, $amount, $token = null, array $context = array())
    {
        $amount = round((float) $amount, 2);
        $days = (int) Configuration::get('PULSE_PAY_PREAUTH_DAYS') ?: 7;
        $gateway = isset($context['gateway']) && $context['gateway'] ? $context['gateway'] : self::defaultGateway('desk');
        $tx = self::createTx(array(
            'gateway' => $gateway, 'type' => 'preauth', 'purpose' => 'preauth', 'channel' => isset($context['channel']) ? $context['channel'] : 'desk', 'method' => 'card',
            'amount' => $amount, 'id_customer' => (int) $idCustomer, 'id_htl_booking' => isset($context['id_htl_booking']) ? (int) $context['id_htl_booking'] : null,
            'id_pulse_folio' => isset($context['id_pulse_folio']) ? (int) $context['id_pulse_folio'] : null,
            'description' => isset($context['description']) ? $context['description'] : 'Pre-authorisation at check-in',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+'.$days.' days')), 'ref_prefix' => 'PRE',
        ));
        $a = self::adapter($gateway);
        $r = $a ? $a->authorize($tx, array_merge($context, array('token' => $token))) : array('ok' => false, 'error' => 'Gateway unavailable');
        if (empty($r['ok']) && $gateway !== 'manual') {
            $m = self::adapter('manual');
            if ($m) { $r = $m->authorize($tx, $context); Db::getInstance()->update('pulse_pay_transaction', array('gateway' => 'manual', 'failed_reason' => pSQL('Fell back to manual hold: '.(isset($r['error']) ? $r['error'] : $gateway.' could not hold the card'))), 'id_pulse_pay_transaction='.(int) $tx['id_pulse_pay_transaction']); $tx['gateway'] = 'manual'; }
        }
        $tx = self::applyResult($tx, $r);
        PulseCoreService::audit('pulsepayments', 'preauth', array('reference' => $tx['reference'], 'amount' => $amount, 'gateway' => $tx['gateway'], 'hold' => $tx['hold_type']), 'pulse_pay_transaction', (int) $tx['id_pulse_pay_transaction']);
        PulseCoreService::event('actionPulsePaymentAuthorized', array('reference' => $tx['reference'], 'amount' => $amount, 'gateway' => $tx['gateway'], 'id_htl_booking' => $tx['id_htl_booking']));
        return array('reference' => $tx['reference'], 'amount' => $amount, 'gateway' => $tx['gateway'], 'hold_type' => $tx['hold_type'], 'expires_at' => $tx['expires_at'], 'ok' => !empty($r['ok']));
    }

    /** Increase an existing hold (stay extended, incidentals blown past the original estimate). */
    public static function topUp($reference, $extra, $note = '')
    {
        $tx = self::tx($reference);
        if (!$tx || $tx['state'] !== 'authorized') { throw new PrestaShopException('No open pre-authorisation '.$reference); }
        $extra = round((float) $extra, 2);
        if ($extra <= 0) { throw new PrestaShopException('Top-up must be greater than zero'); }
        $new = round((float) $tx['amount'] + $extra, 2);
        $days = (int) Configuration::get('PULSE_PAY_PREAUTH_DAYS') ?: 7;
        Db::getInstance()->update('pulse_pay_transaction', array('amount' => $new, 'expires_at' => date('Y-m-d H:i:s', strtotime('+'.$days.' days')), 'description' => pSQL(Tools::substr($tx['description'].' | top-up '.number_format($extra, 2).($note ? ' '.$note : ''), 0, 255)), 'date_upd' => self::now()), 'id_pulse_pay_transaction='.(int) $tx['id_pulse_pay_transaction']);
        PulseCoreService::audit('pulsepayments', 'preauth_topup', array('reference' => $reference, 'extra' => $extra, 'new_total' => $new), 'pulse_pay_transaction', (int) $tx['id_pulse_pay_transaction']);
        return array('ok' => true, 'amount' => $new);
    }

    /**
     * Capture against a hold (or settle an intent). Partial captures are allowed and leave the hold open
     * until the full amount is taken or the remainder is voided.
     * $opts: rrn / auth_code for a manual terminal capture, allow_over, no_post (caller posts the folio line itself).
     */
    public static function capture($reference, $amount, array $opts = array())
    {
        $tx = self::tx($reference);
        if (!$tx) { return array('ok' => false, 'error' => 'Unknown payment reference '.$reference); }
        $amount = round((float) $amount, 2);
        if ($amount <= 0) { return array('ok' => false, 'error' => 'Capture amount must be greater than zero'); }
        $remaining = round((float) $tx['amount'] - (float) $tx['amount_captured'], 2);
        if (!in_array($tx['state'], array('authorized', 'partially_captured', 'intent', 'awaiting_confirmation'))) { return array('ok' => false, 'error' => 'Payment '.$reference.' is '.$tx['state']); }
        if ($amount > $remaining + 0.009 && empty($opts['allow_over'])) { return array('ok' => false, 'error' => 'Capture of '.number_format($amount, 2).' exceeds the hold of '.number_format($remaining, 2)); }
        $a = self::adapter($tx['gateway']);
        if (!$a) { return array('ok' => false, 'error' => 'Gateway '.$tx['gateway'].' is not available'); }
        $child = self::createTx(array(
            'gateway' => $tx['gateway'], 'type' => 'capture', 'purpose' => $tx['purpose'] === 'preauth' ? 'folio' : $tx['purpose'], 'channel' => $tx['channel'], 'method' => $tx['method'],
            'amount' => $amount, 'id_customer' => $tx['id_customer'], 'id_htl_booking' => $tx['id_htl_booking'], 'id_pulse_folio' => $tx['id_pulse_folio'], 'id_pulse_pos_check' => $tx['id_pulse_pos_check'],
            'id_parent' => (int) $tx['id_pulse_pay_transaction'], 'customer_name' => $tx['customer_name'], 'customer_email' => $tx['customer_email'],
            'description' => 'Capture of '.$tx['reference'], 'ref_prefix' => 'CAP',
        ));
        $r = $a->capture(array_merge($tx, array('auth_token' => self::dec($tx['auth_token']))), $amount, array_merge($opts, array('reference' => $child['reference'])));
        $child = self::applyResult($child, $r);
        if (empty($r['ok'])) {
            Db::getInstance()->update('pulse_pay_transaction', array('state' => 'failed', 'failed_reason' => pSQL(Tools::substr(isset($r['error']) ? $r['error'] : 'Capture failed', 0, 255)), 'date_upd' => self::now()), 'id_pulse_pay_transaction='.(int) $child['id_pulse_pay_transaction']);
            PulseCoreService::event('actionPulsePaymentFailed', array('reference' => $child['reference'], 'gateway' => $tx['gateway'], 'error' => isset($r['error']) ? $r['error'] : ''));
            return array('ok' => false, 'error' => isset($r['error']) ? $r['error'] : 'Capture failed', 'reference' => $child['reference']);
        }
        // money only counts against the hold once the gateway (or the RRN off the slip) confirms it —
        // an awaiting_confirmation capture must not eat the remaining hold before the money exists.
        $confirmed = in_array($child['state'], array('captured', 'settled'));
        $captured = round((float) $tx['amount_captured'] + ($confirmed ? $amount : 0), 2);
        $state = $confirmed ? ($captured + 0.009 >= (float) $tx['amount'] ? 'captured' : 'partially_captured') : $tx['state'];
        $u = array('amount_captured' => $captured, 'state' => pSQL($state), 'date_upd' => self::now());
        if ($confirmed) { $u['captured_at'] = self::now(); }
        Db::getInstance()->update('pulse_pay_transaction', $u, 'id_pulse_pay_transaction='.(int) $tx['id_pulse_pay_transaction']);
        // $opts['no_post'] is set by callers that write the folio line themselves (PulsePaymentBridge at check-out).
        if ($confirmed && empty($opts['no_post'])) { self::postToLedger($child, 'capture', $amount); }
        PulseCoreService::audit('pulsepayments', 'capture', array('reference' => $tx['reference'], 'capture' => $child['reference'], 'amount' => $amount), 'pulse_pay_transaction', (int) $tx['id_pulse_pay_transaction']);
        return array('ok' => true, 'reference' => $child['reference'], 'parent' => $tx['reference'], 'amount' => $amount, 'state' => $child['state'], 'captured_total' => $captured);
    }

    /** Release an uncaptured hold (early departure, cancelled stay). */
    public static function voidTx($reference, $reason = '')
    {
        $tx = self::tx($reference);
        if (!$tx) { return array('ok' => false, 'error' => 'Unknown payment reference '.$reference); }
        if (!in_array($tx['state'], array('authorized', 'partially_captured', 'intent', 'awaiting_confirmation'))) { return array('ok' => false, 'error' => 'Payment '.$reference.' is '.$tx['state']); }
        $a = self::adapter($tx['gateway']);
        $r = $a ? $a->void($tx, $reason) : array('ok' => true, 'state' => 'voided');
        Db::getInstance()->update('pulse_pay_transaction', array('state' => 'voided', 'failed_reason' => pSQL(Tools::substr($reason, 0, 255)), 'date_upd' => self::now()), 'id_pulse_pay_transaction='.(int) $tx['id_pulse_pay_transaction']);
        PulseCoreService::audit('pulsepayments', 'void', array('reference' => $reference, 'reason' => $reason), 'pulse_pay_transaction', (int) $tx['id_pulse_pay_transaction']);
        return array('ok' => true, 'state' => 'voided', 'gateway' => isset($r['raw']) ? $r['raw'] : null);
    }

    /* ---------- refunds ---------- */
    public static function refund($reference, $amount, $reason = '', $approver = null)
    {
        $tx = self::tx($reference);
        if (!$tx) { throw new PrestaShopException('Unknown payment reference '.$reference); }
        $amount = round((float) $amount, 2);
        $refundable = round((float) $tx['amount_captured'] - (float) $tx['amount_refunded'], 2);
        if ($amount <= 0 || $amount > $refundable + 0.009) { throw new PrestaShopException('Refundable amount on '.$reference.' is '.number_format($refundable, 2)); }
        $ref = self::nextRef('RFD');
        Db::getInstance()->insert('pulse_pay_refund', array('id_pulse_pay_transaction' => (int) $tx['id_pulse_pay_transaction'], 'reference' => pSQL($ref), 'gateway' => pSQL($tx['gateway']), 'amount' => $amount, 'reason' => pSQL(Tools::substr($reason, 0, 255)), 'status' => 'processing', 'requested_by' => self::emp(), 'approved_by' => (int) $approver, 'business_date' => pSQL(self::bd()), 'date_add' => self::now(), 'date_upd' => self::now()), true);
        $idR = (int) Db::getInstance()->Insert_ID();
        $a = self::adapter($tx['gateway']);
        $r = $a ? $a->refund($tx, $amount, $reason) : array('ok' => false, 'error' => 'Gateway unavailable');
        Db::getInstance()->update('pulse_pay_refund', array('status' => !empty($r['ok']) ? 'done' : 'failed', 'gateway_ref' => pSQL(isset($r['gateway_ref']) ? $r['gateway_ref'] : ''), 'failed_reason' => pSQL(Tools::substr(isset($r['error']) ? $r['error'] : '', 0, 255)), 'raw' => pSQL(PulsePayAdapter::redact(json_encode(isset($r['raw']) ? $r['raw'] : array())), true), 'date_upd' => self::now()), 'id_pulse_pay_refund='.$idR);
        if (empty($r['ok'])) { return array('ok' => false, 'error' => isset($r['error']) ? $r['error'] : 'Refund failed', 'reference' => $ref); }
        $refunded = round((float) $tx['amount_refunded'] + $amount, 2);
        Db::getInstance()->update('pulse_pay_transaction', array('amount_refunded' => $refunded, 'state' => pSQL($refunded + 0.009 >= (float) $tx['amount_captured'] ? 'refunded' : 'partially_refunded'), 'date_upd' => self::now()), 'id_pulse_pay_transaction='.(int) $tx['id_pulse_pay_transaction']);
        self::postToLedger(array_merge($tx, array('gateway_ref' => isset($r['gateway_ref']) && $r['gateway_ref'] ? $r['gateway_ref'] : $ref, 'reference' => $ref, 'description' => 'Refund — '.$reason)), 'refund', -$amount);
        PulseCoreService::audit('pulsepayments', 'refund', array('reference' => $reference, 'refund' => $ref, 'amount' => $amount, 'reason' => $reason), 'pulse_pay_transaction', (int) $tx['id_pulse_pay_transaction']);
        PulseCoreService::event('actionPulsePaymentRefunded', array('reference' => $reference, 'refund' => $ref, 'amount' => $amount));
        return array('ok' => true, 'reference' => $ref, 'amount' => $amount);
    }

    /* ---------- verification ---------- */
    /** Ask the gateway what happened and reconcile our row with the answer. Safe to call repeatedly. */
    public static function verify($reference)
    {
        $tx = self::tx($reference);
        if (!$tx) { return array('ok' => false, 'error' => 'Unknown payment reference '.$reference); }
        $a = self::adapter($tx['gateway']);
        if (!$a) { return array('ok' => false, 'error' => 'Gateway '.$tx['gateway'].' is not available'); }
        $r = $a->verify($tx['reference'], $tx);
        $attempts = (int) $tx['verify_attempts'] + 1;
        Db::getInstance()->update('pulse_pay_transaction', array('verify_attempts' => $attempts, 'next_verify_at' => date('Y-m-d H:i:s', strtotime('+'.min(720, (int) pow(3, min(6, $attempts))).' minutes')), 'date_upd' => self::now()), 'id_pulse_pay_transaction='.(int) $tx['id_pulse_pay_transaction']);
        if (empty($r['ok']) && (empty($r['state']) || $r['state'] === 'intent')) { return array('ok' => false, 'state' => $tx['state'], 'error' => isset($r['error']) ? $r['error'] : 'Still pending'); }
        $was = $tx['state'];
        $tx = self::applyResult($tx, $r);
        if (in_array($tx['state'], array('captured', 'settled')) && !in_array($was, array('captured', 'settled'))) { self::postToLedger($tx, 'capture', (float) $tx['amount_captured']); }
        return array('ok' => !empty($r['ok']), 'state' => $tx['state'], 'reference' => $tx['reference'], 'amount' => (float) $tx['amount_captured'], 'error' => isset($r['error']) ? $r['error'] : null);
    }

    /* ---------- ledger posting ---------- */
    /** Charge code for a settled payment: deposits DEP, POS card POS, transfers TRF, online ONL, card captures CARD. */
    public static function chargeCodeFor(array $tx, $purpose)
    {
        if ($purpose === 'refund') { return 'ADJ'; }
        if ($tx['purpose'] === 'deposit') { return 'DEP'; }
        if (in_array($tx['method'], array('transfer', 'mobile_money'))) { return 'TRF'; }
        if ($tx['channel'] === 'terminal' || $tx['channel'] === 'pos' || $tx['gateway'] === 'manual') { return 'POS'; }
        if (in_array($tx['channel'], array('web', 'portal', 'link'))) { return 'ONL'; }
        return 'CARD';
    }

    /**
     * Write the money into the guest folio (or settle the POS check) exactly once.
     * The unique key on pulse_pay_posting(gateway_ref, purpose) is the guard: a webhook, a cron verify and a
     * cashier clicking twice all try to post, and only the first one wins.
     */
    public static function postToLedger(array $tx, $purpose, $amount)
    {
        $key = !empty($tx['gateway_ref']) ? $tx['gateway_ref'] : $tx['reference'];
        Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_pay_posting` (id_pulse_pay_transaction, gateway_ref, purpose, target, amount, date_add) VALUES ('.(int) $tx['id_pulse_pay_transaction'].',"'.pSQL($key).'","'.pSQL($purpose).'","folio",'.(float) $amount.',"'.self::now().'")');
        if (!Db::getInstance()->Affected_Rows()) { return false; }
        $idPosting = (int) Db::getInstance()->Insert_ID();
        $line = null; $target = 'folio'; $idTarget = null;
        try {
            if ($tx['id_pulse_pos_check'] && self::pos() && $purpose === 'capture') {
                PulsePosPayment::pay((int) $tx['id_pulse_pos_check'], self::posMethod($tx), $amount, self::emp() ?: (int) Configuration::get('PS_CRON_EMPLOYEE_ID'), array('reference' => $tx['reference'].($tx['rrn'] ? ' RRN '.$tx['rrn'] : '')));
                $target = 'pos'; $idTarget = (int) $tx['id_pulse_pos_check'];
            } elseif (self::fd() && ($f = self::folioFor($tx))) {
                $code = self::chargeCodeFor($tx, $purpose);
                if (!PulseChargeCode::byCode($code)) { $code = $purpose === 'refund' ? 'ADJ' : 'CARD'; }
                $desc = ($purpose === 'refund' ? 'Refund ' : ucfirst($tx['gateway']).' ').$tx['reference'].($tx['rrn'] ? ' · RRN '.$tx['rrn'] : '').($tx['card_last4'] ? ' · ****'.$tx['card_last4'] : '');
                $line = (int) $f->post($code, Tools::substr($desc, 0, 250), 1, round((float) $amount, 2), 0, true, $tx['method'], 'payments', $tx['reference']);
                $idTarget = (int) $f->id;
                if ($purpose === 'capture' && (float) $tx['surcharge'] > 0 && PulseChargeCode::byCode('SURCH')) { $f->post('SURCH', 'Card processing surcharge', 1, (float) $tx['surcharge'], (float) Configuration::get('PULSE_PAY_SURCHARGE_VAT_PCT'), false, null, 'payments', $tx['reference']); }
            }
        } catch (Exception $e) {
            Db::getInstance()->update('pulse_pay_posting', array('purpose' => pSQL($purpose.':failed'.$idPosting)), 'id_pulse_pay_posting='.$idPosting);
            PulseCoreService::audit('pulsepayments', 'post_failed', array('reference' => $tx['reference'], 'error' => $e->getMessage()), 'pulse_pay_transaction', (int) $tx['id_pulse_pay_transaction']);
            return false;
        }
        // nothing was actually written (no Front Desk, or the folio is already closed): drop the guard row
        // rather than burning the key, or the money could never be posted once the folio is reopened
        if ($target === 'folio' && $line === null) { Db::getInstance()->delete('pulse_pay_posting', 'id_pulse_pay_posting='.$idPosting); }
        else { Db::getInstance()->update('pulse_pay_posting', array('target' => pSQL($target), 'id_target' => (int) $idTarget, 'id_line' => (int) $line), 'id_pulse_pay_posting='.$idPosting); }
        if ($purpose === 'capture') { PulseCoreService::event('actionPulsePaymentCaptured', array('reference' => $tx['reference'], 'amount' => $amount, 'gateway' => $tx['gateway'], 'id_htl_booking' => $tx['id_htl_booking'], 'id_pulse_folio' => $idTarget)); }
        return $line;
    }

    protected static function posMethod(array $tx) { $m = array('transfer' => 'transfer', 'mobile_money' => 'mobile_money', 'card' => 'card', 'ussd' => 'transfer', 'online' => 'online'); return isset($m[$tx['method']]) ? $m[$tx['method']] : 'card'; }

    protected static function folioFor(array $tx)
    {
        if (!self::fd()) { return null; }
        if (!empty($tx['id_pulse_folio'])) { $f = new PulseFolio((int) $tx['id_pulse_folio']); if (Validate::isLoadedObject($f) && $f->status === 'open') { return $f; } }
        if (!empty($tx['id_htl_booking'])) { return PulseFolio::openForBooking((int) $tx['id_htl_booking']); }
        return null;
    }

    /* ---------- pre-auth housekeeping ---------- */
    public static function openPreauths()
    {
        return Db::getInstance()->executeS('SELECT t.*, r.room_num, ROUND(t.amount-t.amount_captured,2) remaining, TIMESTAMPDIFF(HOUR,NOW(),t.expires_at) hours_left, f.balance folio_balance, f.folio_no
            FROM `'._DB_PREFIX_.'pulse_pay_transaction` t
            LEFT JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=t.id_htl_booking
            LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room
            LEFT JOIN `'._DB_PREFIX_.'pulse_folio` f ON f.id_htl_booking=t.id_htl_booking AND f.status="open" AND f.type="guest"
            WHERE t.type="preauth" AND t.state IN ("authorized","partially_captured") ORDER BY t.expires_at');
    }

    public static function preauthForBooking($idBooking)
    {
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE type="preauth" AND state IN ("authorized","partially_captured") AND id_htl_booking='.(int) $idBooking.' ORDER BY id_pulse_pay_transaction DESC');
    }

    /** Expire holds the bank has already dropped so the desk stops trusting them. */
    public static function expirePreauths()
    {
        $rows = Db::getInstance()->executeS('SELECT id_pulse_pay_transaction, reference FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE type="preauth" AND state IN ("authorized","partially_captured") AND expires_at IS NOT NULL AND expires_at<NOW()');
        foreach ($rows as $r) { Db::getInstance()->update('pulse_pay_transaction', array('state' => 'expired', 'date_upd' => self::now()), 'id_pulse_pay_transaction='.(int) $r['id_pulse_pay_transaction']); PulseCoreService::audit('pulsepayments', 'preauth_expired', array('reference' => $r['reference'])); }
        return count($rows);
    }

    /* ---------- reporting ---------- */
    public static function dashboard($date)
    {
        $d = pSQL($date);
        return array(
            'by_gateway' => Db::getInstance()->executeS('SELECT gateway, COUNT(*) n, ROUND(SUM(amount_captured),2) gross, ROUND(SUM(fee),2) fee, ROUND(SUM(net),2) net FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE business_date="'.$d.'" AND state IN ("captured","settled","partially_refunded","refunded") AND type<>"preauth" GROUP BY gateway ORDER BY gross DESC'),
            'by_channel' => Db::getInstance()->executeS('SELECT channel, method, COUNT(*) n, ROUND(SUM(amount_captured),2) gross FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE business_date="'.$d.'" AND state IN ("captured","settled","partially_refunded","refunded") AND type<>"preauth" GROUP BY channel, method ORDER BY gross DESC'),
            'totals' => Db::getInstance()->getRow('SELECT COUNT(*) n, ROUND(COALESCE(SUM(amount_captured),0),2) gross, ROUND(COALESCE(SUM(fee),0),2) fee, ROUND(COALESCE(SUM(net),0),2) net, ROUND(COALESCE(SUM(amount_refunded),0),2) refunds FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE business_date="'.$d.'" AND state IN ("captured","settled","partially_refunded","refunded") AND type<>"preauth"'),
            'failures' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE business_date="'.$d.'" AND state IN ("failed","awaiting_confirmation") ORDER BY date_add DESC LIMIT 40'),
            'preauths' => self::openPreauths(),
            'pending' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE state="intent" AND type<>"preauth" AND date_add>DATE_SUB(NOW(), INTERVAL 3 DAY) ORDER BY date_add DESC LIMIT 40'),
            'disputes' => Db::getInstance()->executeS('SELECT d.*, t.reference FROM `'._DB_PREFIX_.'pulse_pay_dispute` d LEFT JOIN `'._DB_PREFIX_.'pulse_pay_transaction` t ON t.id_pulse_pay_transaction=d.id_pulse_pay_transaction WHERE d.status IN ("open","evidence_sent") ORDER BY d.due_at'),
            'terminal' => PulsePayTerminal::queue(),
        );
    }

    public static function transactions(array $f = array())
    {
        $w = array('1');
        if (!empty($f['from'])) { $w[] = 't.business_date>="'.pSQL($f['from']).'"'; }
        if (!empty($f['to'])) { $w[] = 't.business_date<="'.pSQL($f['to']).'"'; }
        if (!empty($f['gateway'])) { $w[] = 't.gateway="'.pSQL($f['gateway']).'"'; }
        if (!empty($f['state'])) { $w[] = 't.state="'.pSQL($f['state']).'"'; }
        if (!empty($f['channel'])) { $w[] = 't.channel="'.pSQL($f['channel']).'"'; }
        if (!empty($f['type'])) { $w[] = 't.type="'.pSQL($f['type']).'"'; }
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w[] = '(t.reference LIKE "%'.$q.'%" OR t.gateway_ref LIKE "%'.$q.'%" OR t.rrn LIKE "%'.$q.'%" OR t.customer_name LIKE "%'.$q.'%" OR t.customer_email LIKE "%'.$q.'%")'; }
        return Db::getInstance()->executeS('SELECT t.*, r.room_num FROM `'._DB_PREFIX_.'pulse_pay_transaction` t LEFT JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=t.id_htl_booking LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room WHERE '.implode(' AND ', $w).' ORDER BY t.id_pulse_pay_transaction DESC LIMIT '.(int) (isset($f['limit']) ? $f['limit'] : 200));
    }

    public static function logs($reference) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_log` WHERE reference="'.pSQL($reference).'" ORDER BY id_pulse_pay_log DESC LIMIT 50'); }
    public static function refunds($reference) { return Db::getInstance()->executeS('SELECT r.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_pay_refund` r LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=r.requested_by WHERE r.id_pulse_pay_transaction IN (SELECT id_pulse_pay_transaction FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE reference="'.pSQL($reference).'") ORDER BY r.id_pulse_pay_refund DESC'); }

    /** Log a chargeback / dispute raised by the gateway or the bank. */
    public static function dispute(array $d)
    {
        $tx = !empty($d['reference']) ? self::tx($d['reference']) : null;
        Db::getInstance()->insert('pulse_pay_dispute', array(
            'id_pulse_pay_transaction' => $tx ? (int) $tx['id_pulse_pay_transaction'] : null, 'gateway' => pSQL(isset($d['gateway']) ? $d['gateway'] : ($tx ? $tx['gateway'] : 'manual')),
            'gateway_ref' => pSQL(isset($d['gateway_ref']) ? $d['gateway_ref'] : ($tx ? $tx['gateway_ref'] : '')), 'dispute_ref' => pSQL(isset($d['dispute_ref']) ? $d['dispute_ref'] : ''),
            'amount' => round((float) (isset($d['amount']) ? $d['amount'] : ($tx ? $tx['amount_captured'] : 0)), 2), 'category' => pSQL(isset($d['category']) ? $d['category'] : 'chargeback'),
            'status' => pSQL(isset($d['status']) ? $d['status'] : 'open'), 'reason' => pSQL(Tools::substr(isset($d['reason']) ? $d['reason'] : '', 0, 255)), 'evidence' => pSQL(isset($d['evidence']) ? $d['evidence'] : '', true),
            'due_at' => !empty($d['due_at']) ? pSQL($d['due_at']) : null, 'id_employee' => self::emp(), 'business_date' => pSQL(self::bd()), 'date_add' => self::now(), 'date_upd' => self::now(),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::audit('pulsepayments', 'dispute', $d, 'pulse_pay_dispute', $id);
        return $id;
    }

    /** Freeze the day's takings into pulse_pay_daily so night audit and Reports agree tomorrow. */
    public static function rollDaily($date)
    {
        $d = pSQL($date); $n = 0;
        $rows = Db::getInstance()->executeS('SELECT gateway, channel, COUNT(*) n, ROUND(SUM(amount_captured),2) gross, ROUND(SUM(amount_refunded),2) refunds, ROUND(SUM(fee),2) fee, ROUND(SUM(net),2) net FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE business_date="'.$d.'" AND type<>"preauth" AND state IN ("captured","settled","partially_refunded","refunded") GROUP BY gateway, channel');
        foreach ($rows as $r) {
            $fail = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE business_date="'.$d.'" AND gateway="'.pSQL($r['gateway']).'" AND channel="'.pSQL($r['channel']).'" AND state="failed"');
            $hold = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(amount-amount_captured),0) FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE type="preauth" AND state IN ("authorized","partially_captured") AND gateway="'.pSQL($r['gateway']).'"');
            Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_pay_daily` (business_date, gateway, channel, txn_count, gross, refunds, fee, net, failures, open_preauth, date_add) VALUES ("'.$d.'","'.pSQL($r['gateway']).'","'.pSQL($r['channel']).'",'.(int) $r['n'].','.(float) $r['gross'].','.(float) $r['refunds'].','.(float) $r['fee'].','.(float) $r['net'].','.$fail.','.$hold.',"'.self::now().'") ON DUPLICATE KEY UPDATE txn_count=VALUES(txn_count), gross=VALUES(gross), refunds=VALUES(refunds), fee=VALUES(fee), net=VALUES(net), failures=VALUES(failures), open_preauth=VALUES(open_preauth)');
            $n++;
        }
        return $n;
    }

    /* ---------- payment links ---------- */
    /** Front Desk bridge entry point: mint a link for $amount and hand back the URL the guest can open. */
    public static function paymentLink($amount, array $context = array())
    {
        $l = PulsePayLink::create(array_merge($context, array('amount' => round((float) $amount, 2), 'purpose' => isset($context['purpose']) ? $context['purpose'] : (!empty($context['id_pulse_folio']) ? 'folio' : 'deposit'))));
        return PulsePayLink::url($l);
    }

    /**
     * The guest pressed "Pay" on the link page: create the transaction for this attempt and ask the gateway
     * where to send them. Called from the front controller, so it must never throw at the guest.
     */
    public static function startLinkPayment(array $link, $gateway = null, $method = 'card', $returnUrl = null, $amount = null)
    {
        $outstanding = round((float) $link['amount'] - (float) $link['amount_paid'], 2);
        $amount = round((float) ($amount !== null && !$link['amount_locked'] ? $amount : $outstanding), 2);
        if ($amount <= 0) { return array('ok' => false, 'error' => 'Nothing to pay'); }
        if ((float) $link['min_amount'] > 0 && $amount + 0.009 < (float) $link['min_amount']) { return array('ok' => false, 'error' => 'The minimum payment on this link is '.number_format($link['min_amount'], 2)); }
        if (!PulsePayLink::usable($link)) { return array('ok' => false, 'error' => 'This payment link is '.$link['status']); }
        $gateway = $gateway ? $gateway : ($link['gateway'] ? $link['gateway'] : self::defaultGateway('link'));
        $tx = self::charge(array(
            'amount' => $amount, 'gateway' => $gateway, 'channel' => 'link', 'method' => $method, 'purpose' => $link['purpose'],
            'id_customer' => $link['id_customer'], 'id_htl_booking' => $link['id_htl_booking'], 'id_pulse_folio' => $link['id_pulse_folio'], 'id_pulse_pos_check' => $link['id_pulse_pos_check'],
            'id_pulse_pay_link' => (int) $link['id_pulse_pay_link'], 'customer_name' => $link['customer_name'], 'customer_email' => $link['customer_email'], 'customer_phone' => $link['customer_phone'],
            'description' => $link['title'], 'return_url' => $returnUrl, 'ref_prefix' => 'LNK',
        ));
        if (in_array($tx['state'], array('captured', 'settled'))) { PulsePayLink::credit($link, (float) $tx['amount_captured'], $tx); }
        return array('ok' => empty($tx['error']), 'tx' => $tx, 'error' => isset($tx['error']) ? $tx['error'] : null);
    }

    /* ---------- unattended recovery ---------- */
    /**
     * Everything the cron needs to do when nobody is watching: chase transactions the webhook never delivered,
     * expire stale links, pre-auths and terminal requests, and retry captures that were queued while the link was down.
     */
    public static function sweep($maxAgeHours = 72)
    {
        $out = array('verified' => 0, 'settled' => 0, 'links_expired' => 0, 'preauths_expired' => 0, 'terminal_expired' => 0, 'captures_retried' => 0);
        $rows = Db::getInstance()->executeS('SELECT reference FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE state IN ("intent","awaiting_confirmation") AND type<>"preauth" AND gateway<>"manual" AND date_add>DATE_SUB(NOW(), INTERVAL '.(int) $maxAgeHours.' HOUR) AND (next_verify_at IS NULL OR next_verify_at<=NOW()) ORDER BY date_add LIMIT 100');
        foreach ($rows as $r) {
            $res = self::verify($r['reference']); $out['verified']++;
            if (!empty($res['ok']) && in_array($res['state'], array('captured', 'settled'))) { $out['settled']++; $tx = self::tx($r['reference']); if ($tx && $tx['id_pulse_pay_link'] && ($l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_link` WHERE id_pulse_pay_link='.(int) $tx['id_pulse_pay_link']))) { PulsePayLink::credit($l, (float) $tx['amount_captured'], $tx); } }
        }
        foreach (Db::getInstance()->executeS('SELECT reference, amount, amount_captured FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE type="capture" AND state="failed" AND date_add>DATE_SUB(NOW(), INTERVAL 24 HOUR) AND verify_attempts<3 LIMIT 25') as $r) {
            Db::getInstance()->update('pulse_pay_transaction', array('verify_attempts' => 99), 'reference="'.pSQL($r['reference']).'"');
            $p = Db::getInstance()->getRow('SELECT t.reference, t.amount, t.amount_captured FROM `'._DB_PREFIX_.'pulse_pay_transaction` c INNER JOIN `'._DB_PREFIX_.'pulse_pay_transaction` t ON t.id_pulse_pay_transaction=c.id_parent WHERE c.reference="'.pSQL($r['reference']).'"');
            if ($p) { $left = round((float) $p['amount'] - (float) $p['amount_captured'], 2); if ($left > 0.009) { self::capture($p['reference'], min($left, (float) $r['amount'])); $out['captures_retried']++; } }
        }
        $out['links_expired'] = PulsePayLink::expireStale();
        $out['preauths_expired'] = self::expirePreauths();
        $out['terminal_expired'] = PulsePayTerminal::expireStale();
        return $out;
    }

    /** Webhook / pay-page / API base URL for this shop, shown in Settings for copy-paste into the gateway dashboard. */
    public static function baseUrl()
    {
        $ssl = Configuration::get('PS_SSL_ENABLED');
        return ($ssl ? 'https://' : 'http://').Tools::getShopDomainSsl().__PS_BASE_URI__;
    }
    public static function webhookUrl($gateway) { return self::baseUrl().'pulse/pay/hook/'.$gateway; }
}

<?php
/**
 * Demo data for Pulse Payments — a 52-room property in Port Harcourt.
 * Two gateways in test mode with obviously fake keys, a day of takings across cash/card/transfer,
 * one open card hold, one payment link and a settlement CSV to try the reconciliation screen with.
 * Idempotent: every row is keyed on a SEED reference and skipped if it already exists.
 * Run: php modules/pulsepayments/seed/seed.php  (or in the browser with ?token=<PULSE_PAY_CRON_TOKEN>)
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
if (php_sapi_name() !== 'cli') { $t = Tools::getValue('token'); if ($t !== Configuration::get('PULSE_PAY_CRON_TOKEN')) { die('Invalid token'); } }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);

$D = Db::getInstance();
$bd = PulsePayService::bd();
$made = array('gateways' => 0, 'transactions' => 0, 'preauth' => 0, 'links' => 0, 'terminal' => 0, 'csv' => 0);

/* ---------- 1. two gateways in test mode, obviously fake keys ---------- */
$fake = array(
    'paystack' => array('public_key' => 'pk_test_0000pulsedemo0000paystack0000', 'secret_key' => 'sk_test_0000pulsedemo0000paystack0000', 'webhook_secret' => 'sk_test_0000pulsedemo0000paystack0000'),
    'flutterwave' => array('public_key' => 'FLWPUBK_TEST-0000PULSEDEMO0000-X', 'secret_key' => 'FLWSECK_TEST-0000PULSEDEMO0000-X', 'webhook_secret' => 'pulse-demo-verif-hash'),
);
foreach ($fake as $code => $keys) {
    $g = PulsePayService::gatewayRow($code);
    if (!$g) { continue; }
    if (!$g['active'] || !PulsePayService::dec($g['secret_key'])) {
        PulsePayService::saveGateway($code, array_merge($keys, array('active' => 1, 'test_mode' => 1)));
        $made['gateways']++;
    }
}
$manual = PulsePayService::gatewayRow('manual');
if ($manual && !PulsePayService::dec($manual['extra'])) {
    PulsePayService::saveGateway('manual', array('active' => 1, 'test_mode' => 0, 'extra' => array('bank_name' => 'Zenith Bank', 'account_name' => 'Rivers Crest Hotel Ltd', 'account_number' => '1012345678')));
}
foreach (array('PULSE_PAY_BANK_NAME' => 'Zenith Bank', 'PULSE_PAY_BANK_ACCOUNT_NAME' => 'Rivers Crest Hotel Ltd', 'PULSE_PAY_BANK_ACCOUNT' => '1012345678', 'PULSE_PAY_FALLBACK_EMAIL' => 'frontdesk@riverscrest.ng') as $k => $v) { if (!Configuration::get($k)) { Configuration::updateValue($k, $v); } }

/* ---------- 2. an in-house guest to hang the demo off, when Front Desk is installed ---------- */
$guest = $D->getRow('SELECT b.id id_htl_booking, b.id_customer, CONCAT(c.firstname," ",c.lastname) guest, c.email, r.room_num
    FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room
    WHERE b.is_cancelled=0 AND b.is_refunded=0 ORDER BY b.id DESC');
$idBooking = $guest ? (int) $guest['id_htl_booking'] : null;
$idCustomer = $guest ? (int) $guest['id_customer'] : null;

/** Create one demo transaction unless its reference already exists. */
function seedTx($ref, array $d, array $after = array())
{
    global $made;
    if (Db::getInstance()->getValue('SELECT id_pulse_pay_transaction FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE reference="'.pSQL($ref).'"')) { return null; }
    $tx = PulsePayService::createTx(array_merge($d, array('reference' => $ref, 'idempotency_key' => 'seed-'.$ref)));
    if ($after) { Db::getInstance()->update('pulse_pay_transaction', $after, 'id_pulse_pay_transaction='.(int) $tx['id_pulse_pay_transaction']); }
    $made['transactions']++;
    return $tx;
}

$names = array('Chidinma Okonkwo', 'Tamunotonye Briggs', 'Emeka Nwachukwu', 'Aisha Bello', 'Ibiso Amadi', 'Segun Adeyemi');
/** Shape a "money actually arrived" update for a demo transaction. */
function seedCaptured($amount, $fee) { return array('state' => 'captured', 'amount_captured' => $amount, 'fee' => $fee, 'net' => round($amount - $fee, 2), 'captured_at' => date('Y-m-d H:i:s'), 'authorized_at' => date('Y-m-d H:i:s')); }

seedTx('SEEDPAY-0001', array('gateway' => 'paystack', 'channel' => 'link', 'method' => 'card', 'purpose' => 'deposit', 'amount' => 85000, 'gateway_ref' => 'seed_ps_1001', 'customer_name' => $names[0], 'customer_email' => 'chidinma.okonkwo@example.ng', 'description' => 'Pre-arrival deposit — 2 nights Deluxe', 'id_customer' => $idCustomer), array_merge(seedCaptured(85000, 1375), array('card_brand' => 'visa', 'card_last4' => '4081', 'bank' => 'GTBank')));
seedTx('SEEDPAY-0002', array('gateway' => 'paystack', 'channel' => 'web', 'method' => 'card', 'purpose' => 'folio', 'amount' => 145000, 'gateway_ref' => 'seed_ps_1002', 'customer_name' => $names[1], 'customer_email' => 'tt.briggs@example.ng', 'description' => 'Room charge settlement', 'id_htl_booking' => $idBooking), array_merge(seedCaptured(145000, 2000), array('card_brand' => 'mastercard', 'card_last4' => '5399', 'bank' => 'Access Bank')));
seedTx('SEEDPAY-0003', array('gateway' => 'flutterwave', 'channel' => 'portal', 'method' => 'transfer', 'purpose' => 'folio', 'amount' => 60000, 'gateway_ref' => '8801234', 'customer_name' => $names[2], 'customer_email' => 'emeka.n@example.ng', 'description' => 'Bank transfer via portal'), array_merge(seedCaptured(60000, 840), array('bank' => 'Moniepoint MFB')));
seedTx('SEEDPAY-0004', array('gateway' => 'manual', 'channel' => 'terminal', 'method' => 'card', 'purpose' => 'folio', 'amount' => 32500, 'gateway_ref' => '000123456789', 'customer_name' => $names[3], 'description' => 'Bank POS terminal — reception'), array_merge(seedCaptured(32500, 0), array('rrn' => '000123456789', 'auth_code' => '004512', 'card_last4' => '7712', 'card_brand' => 'verve', 'bank' => 'GTBank')));
seedTx('SEEDPAY-0005', array('gateway' => 'manual', 'channel' => 'desk', 'method' => 'cash', 'purpose' => 'folio', 'amount' => 18000, 'gateway_ref' => 'CASH-SEED-0005', 'customer_name' => $names[4], 'description' => 'Cash at reception'), array_merge(seedCaptured(18000, 0), array('method' => 'cash')));
seedTx('SEEDPAY-0006', array('gateway' => 'manual', 'channel' => 'desk', 'method' => 'transfer', 'purpose' => 'folio', 'amount' => 120000, 'state' => 'awaiting_confirmation', 'customer_name' => $names[5], 'description' => 'Transfer promised — receipt not shown yet'));
seedTx('SEEDPAY-0007', array('gateway' => 'paystack', 'channel' => 'web', 'method' => 'card', 'purpose' => 'folio', 'amount' => 42000, 'gateway_ref' => 'seed_ps_1003', 'customer_name' => 'Ngozi Eze', 'customer_email' => 'ngozi.eze@example.ng', 'description' => 'Restaurant bill paid online'), array_merge(seedCaptured(42000, 730), array('card_brand' => 'visa', 'card_last4' => '1902')));
seedTx('SEEDPAY-0008', array('gateway' => 'paystack', 'channel' => 'link', 'method' => 'card', 'purpose' => 'invoice', 'amount' => 22000, 'gateway_ref' => 'seed_ps_1004', 'customer_name' => 'Shell Contractors Ltd', 'customer_email' => 'accounts@example.ng', 'description' => 'City ledger invoice — not yet settled by the gateway'), array_merge(seedCaptured(22000, 430), array('card_brand' => 'visa', 'card_last4' => '2244')));
seedTx('SEEDPAY-0009', array('gateway' => 'paystack', 'channel' => 'web', 'method' => 'card', 'purpose' => 'folio', 'amount' => 25000, 'customer_name' => 'Walk-in guest', 'description' => 'Card declined — insufficient funds'), array('state' => 'failed', 'failed_reason' => 'Declined by the issuing bank (insufficient funds)'));

/* ---------- 3. one open pre-authorisation ---------- */
if (!$D->getValue('SELECT id_pulse_pay_transaction FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE reference="SEEDPRE-0001"')) {
    seedTx('SEEDPRE-0001', array('gateway' => 'manual', 'type' => 'preauth', 'purpose' => 'preauth', 'channel' => 'desk', 'method' => 'card', 'amount' => 150000,
        'customer_name' => $guest ? $guest['guest'] : 'Tamunotonye Briggs', 'customer_email' => $guest ? $guest['email'] : 'tt.briggs@example.ng',
        'id_customer' => $idCustomer, 'id_htl_booking' => $idBooking, 'description' => 'Incidentals hold at check-in', 'expires_at' => date('Y-m-d H:i:s', strtotime('+2 days'))),
        array('state' => 'authorized', 'hold_type' => 'manual', 'gateway_ref' => 'MANUAL-SEEDHOLD', 'card_last4' => '5399', 'card_brand' => 'mastercard', 'authorized_at' => date('Y-m-d H:i:s')));
    $made['preauth']++;
}

/* ---------- 4. one payment link ---------- */
if (!$D->getValue('SELECT id_pulse_pay_link FROM `'._DB_PREFIX_.'pulse_pay_link` WHERE short_code="PHCDEM"')) {
    $D->insert('pulse_pay_link', array(
        'token' => pSQL(sha1('pulse-seed-link')), 'short_code' => 'PHCDEM', 'purpose' => 'deposit', 'gateway' => '', 'amount' => 96500, 'amount_locked' => 1,
        'currency' => pSQL(PulsePayService::currency()), 'max_uses' => 1, 'uses' => 0, 'status' => 'open', 'id_customer' => $idCustomer, 'id_htl_booking' => $idBooking,
        'customer_name' => 'Ibiso Amadi', 'customer_email' => 'ibiso.amadi@example.ng', 'customer_phone' => '+2348030001234',
        'title' => 'Deposit — 3 nights, Rivers Crest Hotel Port Harcourt', 'note' => 'Covers room and breakfast. Balance payable on arrival.',
        'expires_at' => date('Y-m-d H:i:s', strtotime('+3 days')), 'id_employee' => PulsePayService::emp(), 'business_date' => pSQL($bd), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
    ), true);
    $made['links']++;
}

/* ---------- 5. a terminal request waiting for an RRN ---------- */
if (!$D->getValue('SELECT id_pulse_pay_terminal_request FROM `'._DB_PREFIX_.'pulse_pay_terminal_request` WHERE reference="SEEDTRM-0001"')) {
    $tx = seedTx('SEEDTRM-0001', array('gateway' => 'manual', 'channel' => 'terminal', 'method' => 'card', 'purpose' => 'folio', 'amount' => 74250, 'state' => 'awaiting_confirmation', 'customer_name' => 'Restaurant check 1184', 'description' => 'Sent to the restaurant terminal'));
    $idT = (int) $D->getValue('SELECT id_pulse_pay_terminal FROM `'._DB_PREFIX_.'pulse_pay_terminal` WHERE code="RESTAURANT"');
    $D->insert('pulse_pay_terminal_request', array('id_pulse_pay_terminal' => $idT ?: null, 'id_pulse_pay_transaction' => $tx ? (int) $tx['id_pulse_pay_transaction'] : null, 'reference' => 'SEEDTRM-0001', 'amount' => 74250,
        'station' => 'restaurant', 'requested_by' => PulsePayService::emp(), 'source' => 'pos', 'auto_settle' => 0, 'status' => 'queued',
        'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')), 'business_date' => pSQL($bd), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), true);
    $made['terminal']++;
}

/* ---------- 6. a Paystack settlement CSV to reconcile ---------- */
$csv = dirname(__FILE__).'/settlement_sample.csv';
$paid = $bd.' 14:20:00';
$rows = array(
    array('Transaction Reference', 'Merchant Reference', 'Paid At', 'Amount', 'Fee', 'Net', 'Currency', 'Status'),
    array('seed_ps_1001', 'SEEDPAY-0001', $paid, '85000.00', '1375.00', '83625.00', 'NGN', 'success'),
    array('seed_ps_1002', 'SEEDPAY-0002', $bd.' 16:05:00', '145000.00', '2000.00', '143000.00', 'NGN', 'success'),
    array('seed_ps_1003', 'SEEDPAY-0007', $bd.' 19:41:00', '42000.00', '900.00', '41100.00', 'NGN', 'success'),
    array('seed_ps_8888', 'PSTK-OFFLINE-88', $bd.' 21:02:00', '15000.00', '325.00', '14675.00', 'NGN', 'success'),
);
$fh = fopen($csv, 'w');
foreach ($rows as $r) { fputcsv($fh, $r); }
fclose($fh);
$made['csv'] = count($rows) - 1;

PulsePayService::rollDaily($bd);
PulseCoreService::audit('pulsepayments', 'seed', $made);

echo "Pulse Payments demo data for ".$bd." (Rivers Crest Hotel, Port Harcourt)\n";
echo "  gateways configured in test mode : ".$made['gateways']."\n";
echo "  transactions created             : ".$made['transactions']." (card, transfer, cash, terminal, one failure, one awaiting confirmation)\n";
echo "  open pre-authorisation           : ".$made['preauth']." (₦150,000 manual hold, expires in 2 days)\n";
echo "  payment link                     : ".$made['links']." (short code PHCDEM, ₦96,500 deposit)\n";
echo "  terminal request queued          : ".$made['terminal']." (₦74,250 on the restaurant terminal)\n";
echo "  settlement CSV rows              : ".$made['csv']." at ".$csv."\n";
echo "Import that CSV under Reconciliation as gateway 'paystack': 2 rows match, 1 shows a fee variance, 1 is unmatched, and SEEDPAY-0008 shows as captured-but-not-settled.\n";

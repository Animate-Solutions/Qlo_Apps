<?php
/**
 * Demo data for Pulse Accounts — Rivers Crest Hotel, a 52-room property in Port Harcourt.
 *
 * Creates: opening balances, a full month of posted daily revenue and expense journals derived from
 * plausible folio / POS / purchase activity, three city-ledger companies with real ageing, a supplier
 * bill run with WHT, a fixed-asset register (generators, chillers, lift, kitchen, vehicles, furniture
 * per room) with the prior month's depreciation already run, a petty-cash book, and a bank statement
 * CSV under seed/ that actually matches the seeded journals so the reconciliation screen has something
 * honest to chew on.
 *
 * Idempotent: every journal carries a seed source_ref and the UNIQUE(source, source_ref) key means a
 * second run changes nothing. Never deletes anything.
 * Run: php modules/pulseaccounts/seed/seed.php  (or in the browser with ?token=<PULSE_ACC_CRON_TOKEN>)
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
if (php_sapi_name() !== 'cli') { $t = Tools::getValue('token'); if ($t !== Configuration::get('PULSE_ACC_CRON_TOKEN')) { die('Invalid token'); } }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);

$D = Db::getInstance();
$made = array('periods' => 0, 'assets' => 0, 'journals' => 0, 'companies' => 0, 'invoices' => 0, 'receipts' => 0, 'bills' => 0, 'payments' => 0, 'petty' => 0, 'depreciation' => 0);

/* ---------- 0. hotel identity and periods ---------- */
foreach (array(
    'PULSE_ACC_HOTEL_NAME' => 'Rivers Crest Hotel Limited',
    'PULSE_ACC_HOTEL_TIN' => '01234567-0001',
    'PULSE_ACC_HOTEL_ADDRESS' => '14 Aba Road, GRA Phase II, Port Harcourt, Rivers State',
    'PULSE_ACC_HOTEL_EMAIL' => 'accounts@riverscrest.ng',
    'PULSE_ACC_EINV_BUSINESS_ID' => 'RC-PHC-0001',
    'PULSE_ACC_EINV_SERVICE_ID' => '94ND90NR',
) as $k => $v) { if (!Configuration::get($k)) { Configuration::updateValue($k, $v); } }

$bd = PulseAccService::bd();
$monthStart = date('Y-m-01', strtotime($bd));
$prevMonth = date('Y-m', strtotime($monthStart.' -1 month'));
$prevStart = $prevMonth.'-01';
$prevEnd = date('Y-m-t', strtotime($prevStart));
$openingDate = date('Y-m-d', strtotime($prevStart.' -1 day'));
PulseAccService::ensurePeriods($openingDate, 4);
$made['periods'] = count(PulseAccService::periods(6));
PulseAccService::ensureBankAccounts();

/* ---------- 1. bank accounts ---------- */
foreach (array(
    array('code' => 'ZEN-CUR', 'name' => 'Zenith Bank — current', 'type' => 'bank', 'bank_name' => 'Zenith Bank', 'account_no' => '1012345678', 'account_name' => 'Rivers Crest Hotel Ltd', 'branch' => 'Aba Road, Port Harcourt', 'account_code' => '1120', 'opening_balance' => 0, 'sort' => 4),
    array('code' => 'GTB-CUR', 'name' => 'GTBank — current', 'type' => 'bank', 'bank_name' => 'Guaranty Trust Bank', 'account_no' => '0234567891', 'account_name' => 'Rivers Crest Hotel Ltd', 'branch' => 'Olu Obasanjo, Port Harcourt', 'account_code' => '1121', 'opening_balance' => 0, 'sort' => 5),
    array('code' => 'ACC-COL', 'name' => 'Access Bank — collections', 'type' => 'bank', 'bank_name' => 'Access Bank', 'account_no' => '0087654321', 'account_name' => 'Rivers Crest Hotel Ltd', 'branch' => 'Trans Amadi', 'account_code' => '1122', 'opening_balance' => 0, 'sort' => 6),
) as $b) {
    $id = (int) $D->getValue('SELECT id_pulse_acc_bank_account FROM `'._DB_PREFIX_.'pulse_acc_bank_account` WHERE code="'.pSQL($b['code']).'"');
    if ($id) { $b['id_pulse_acc_bank_account'] = $id; }
    PulseAccService::saveBankAccount($b);
}
$petty = $D->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_bank_account` WHERE type="petty_cash" LIMIT 1');
if ($petty && (float) $petty['imprest_float'] <= 0) { $D->update('pulse_acc_bank_account', array('imprest_float' => 250000, 'account_name' => pSQL('Front office imprest'), 'name' => pSQL('Petty cash / imprest — front office')), 'id_pulse_acc_bank_account='.(int) $petty['id_pulse_acc_bank_account']); $petty = PulseAccService::bankAccount((int) $petty['id_pulse_acc_bank_account']); }
$gtb = PulseAccService::bankByAccountCode('1121');
$zen = PulseAccService::bankByAccountCode('1120');

/* ---------- 2. fixed asset register ---------- */
/* cost, class, months already in service before the prior month, cost centre */
$assetPlan = array(
    array('BLDG', 'Hotel building — 52 rooms, GRA Phase II', 780000000, 96, 'general', 'Main building'),
    array('IMPR', 'Lobby and restaurant refurbishment 2024', 42000000, 20, 'general', 'Ground floor'),
    array('GEN', '250 kVA Perkins generator (primary)', 38500000, 34, 'maintenance', 'Generator house'),
    array('GEN', '100 kVA Mikano generator (standby)', 16800000, 46, 'maintenance', 'Generator house'),
    array('HVAC', 'Chiller plant — 2 x 60 TR', 54000000, 40, 'maintenance', 'Plant room, roof'),
    array('HVAC', 'Passenger lift — 8 person', 28500000, 52, 'maintenance', 'Lift shaft'),
    array('KITCH', 'Kitchen line — ranges, ovens, extraction', 22400000, 30, 'fnb', 'Main kitchen'),
    array('KITCH', 'Cold room and blast chiller', 9800000, 30, 'fnb', 'Main kitchen'),
    array('KITCH', 'Laundry — 2 washers, 2 dryers, roller iron', 14600000, 28, 'laundry', 'Laundry'),
    array('VEH', 'Toyota Hiace 14-seat shuttle', 31500000, 22, 'rooms', 'Car park'),
    array('VEH', 'Toyota Corolla — management', 18900000, 16, 'admin', 'Car park'),
    array('IT', 'Server, network and CCTV', 7400000, 14, 'admin', 'Back office'),
    array('IT', 'Front office and POS terminals', 4650000, 12, 'rooms', 'Front office'),
    array('FFE', 'Restaurant and bar furniture', 12800000, 26, 'fnb', 'Restaurant'),
    array('FFE', 'Conference hall seating and AV', 9600000, 24, 'general', 'Conference hall'),
);
$assetTotals = array();
foreach ($assetPlan as $i => $p) {
    list($cls, $name, $cost, $monthsInUse, $cc, $location) = $p;
    $code = 'SEED-'.$cls.'-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT);
    if ($D->getValue('SELECT id_pulse_acc_asset FROM `'._DB_PREFIX_.'pulse_acc_asset` WHERE code="'.pSQL($code).'"')) { continue; }
    $c = PulseAccAssets::assetClassByCode($cls);
    $inService = date('Y-m-01', strtotime($prevStart.' -'.$monthsInUse.' month'));
    $residual = round($cost * (float) $c['residual_pct'] / 100, 2);
    // accumulated depreciation up to the month before the prior month, on the class's own method
    $accum = 0;
    if ($c['method'] === 'straight_line' && (int) $c['life_months'] > 0) { $accum = round(min($cost - $residual, ($cost - $residual) / (int) $c['life_months'] * $monthsInUse), 2); }
    elseif ($c['method'] === 'reducing_balance' && (float) $c['rate_pct'] > 0) {
        $nbv = $cost;
        for ($m = 0; $m < $monthsInUse; $m++) { $charge = round($nbv * ((float) $c['rate_pct'] / 100) / 12, 2); if ($nbv - $charge < $residual) { $charge = max(0, $nbv - $residual); } $nbv -= $charge; }
        $accum = round($cost - $nbv, 2);
    }
    PulseAccAssets::addAsset(array(
        'class_code' => $cls, 'name' => $name, 'code' => $code, 'cost' => $cost, 'residual_value' => $residual,
        'acquisition_date' => $inService, 'in_service_date' => $inService, 'cost_centre' => $cc, 'department' => $cc, 'location' => $location,
        'accum_depreciation' => $accum, 'last_period' => date('Y-m', strtotime($prevStart.' -1 month')),
        'supplier' => 'Seeded historical register', 'no_journal' => true, 'capex_budget_line' => 'capex:'.$cls,
        'note' => 'Opening register loaded from the fixed-asset schedule at takeover.',
    ));
    $made['assets']++;
    if (!isset($assetTotals[$cls])) { $assetTotals[$cls] = array('cost' => 0, 'accum' => 0); }
    $assetTotals[$cls]['cost'] += $cost; $assetTotals[$cls]['accum'] += $accum;
}
/* guest room furniture: one FF&E record per room so the register ties to the room list */
$rooms = PulseAccService::fd() ? $D->executeS('SELECT id id_room, room_num FROM `'._DB_PREFIX_.'htl_room_information` ORDER BY room_num LIMIT 52') : array();
if (!$rooms) { $rooms = array(); for ($i = 1; $i <= 52; $i++) { $rooms[] = array('id_room' => null, 'room_num' => (100 + $i)); } }
foreach ($rooms as $i => $r) {
    $code = 'SEED-FFE-RM'.$r['room_num'];
    if ($D->getValue('SELECT id_pulse_acc_asset FROM `'._DB_PREFIX_.'pulse_acc_asset` WHERE code="'.pSQL($code).'"')) { continue; }
    $cost = 1850000 + ($i % 4) * 220000;
    $months = 18 + ($i % 12);
    $c = PulseAccAssets::assetClassByCode('FFE');
    $residual = round($cost * (float) $c['residual_pct'] / 100, 2);
    $accum = round(min($cost - $residual, ($cost - $residual) / (int) $c['life_months'] * $months), 2);
    PulseAccAssets::addAsset(array(
        'class_code' => 'FFE', 'name' => 'Guest room furniture and fittings — room '.$r['room_num'], 'code' => $code, 'cost' => $cost, 'residual_value' => $residual,
        'acquisition_date' => date('Y-m-01', strtotime($prevStart.' -'.$months.' month')), 'in_service_date' => date('Y-m-01', strtotime($prevStart.' -'.$months.' month')),
        'cost_centre' => 'rooms', 'department' => 'rooms', 'id_room' => $r['id_room'], 'location' => 'Room '.$r['room_num'],
        'accum_depreciation' => $accum, 'last_period' => date('Y-m', strtotime($prevStart.' -1 month')), 'no_journal' => true, 'capex_budget_line' => 'capex:FFE',
    ));
    $made['assets']++;
    if (!isset($assetTotals['FFE'])) { $assetTotals['FFE'] = array('cost' => 0, 'accum' => 0); }
    $assetTotals['FFE']['cost'] += $cost; $assetTotals['FFE']['accum'] += $accum;
}

/* ---------- 3. opening balances ---------- */
$opening = array(
    '1110' => 480000, '1111' => 210000, '1115' => 250000, '1120' => 18600000, '1121' => 9450000, '1122' => 2340000, '1130' => 3120000,
    '1210' => 4180000, '1220' => 11750000, '1240' => 620000, '1256' => 1800000, '1270' => 0,
    '1310' => 3850000, '1320' => 5240000, '1330' => 1960000, '1340' => 740000, '1350' => 2180000, '1360' => 410000, '1370' => 3300000,
    '2110' => -8420000, '2120' => -1560000, '2130' => -900000, '2140' => -6200000, '2150' => -1180000, '2155' => -940000,
    '2210' => -2360000, '2230' => -410000, '2240' => -530000, '2310' => -5600000, '2340' => -320000,
    '2430' => -46000000, '3100' => -250000000,
);
foreach ($assetTotals as $cls => $t) {
    $c = PulseAccAssets::assetClassByCode($cls);
    if (!isset($opening[$c['asset_account']])) { $opening[$c['asset_account']] = 0; }
    if (!isset($opening[$c['accum_account']])) { $opening[$c['accum_account']] = 0; }
    $opening[$c['asset_account']] += $t['cost'];
    $opening[$c['accum_account']] -= $t['accum'];
}
if (!PulseAccJournal::bySource('opening', 'ob:'.$openingDate)) { PulseAccPosting::opening($opening, $openingDate, 'Opening balances at takeover'); $made['journals']++; }

/* ---------- 4. a month of trading ---------- */
/* Plausible for a 52-room Port Harcourt hotel: mid-week corporate, weekend leisure dip, ADR around N70,000 net. */
$vat = PulseAccService::vatPct(); $cons = PulseAccService::consumptionPct();
$soldByDow = array(1 => 38, 2 => 40, 3 => 39, 4 => 36, 5 => 30, 6 => 24, 7 => 27);
$days = (int) date('t', strtotime($prevStart));
$transferDays = array();
for ($d = 1; $d <= $days; $d++) {
    $date = date('Y-m-d', strtotime($prevStart.' +'.($d - 1).' day'));
    $ref = 'seed:rev:'.$date;
    if (PulseAccJournal::bySource('manual', $ref)) { continue; }
    $dow = (int) date('N', strtotime($date));
    $sold = $soldByDow[$dow] + (($d * 7) % 5) - 2;
    $sold = max(18, min(50, $sold));
    $adr = 68000 + (($d * 13) % 9) * 1000;
    $roomsNet = $sold * $adr;
    $foodNet = round($sold * 8600 + ($dow >= 5 ? 240000 : 0));
    $bevSoftNet = round($sold * 2100);
    $bevLiquorNet = round($sold * 3400 + ($dow >= 5 ? 180000 : 0));
    $laundryNet = round($sold * 850);
    $spaNet = in_array($dow, array(3, 5, 6)) ? 145000 : 0;
    $teleNet = round($sold * 120);
    $minibarNet = round($sold * 430);
    $hallNet = ($d % 9 === 0) ? 850000 : 0;
    $roomsTax = round($roomsNet * $vat / 100, 2);
    $otherNet = $laundryNet + $spaNet + $teleNet + $minibarNet + $hallNet;
    $otherTax = round($otherNet * $vat / 100, 2);
    $fnbNet = $foodNet + $bevSoftNet + $bevLiquorNet;
    $fnbVat = round($fnbNet * $vat / 100, 2);
    $fnbCons = round($fnbNet * $cons / 100, 2);
    $gross = round($roomsNet + $roomsTax + $fnbNet + $fnbVat + $fnbCons + $otherNet + $otherTax, 2);

    $cash = round($gross * 0.17, 2);
    $card = round($gross * 0.34, 2);
    $transfer = round($gross * 0.24, 2);
    $city = round($gross * 0.17, 2);
    $guest = round($gross - $cash - $card - $transfer - $city, 2);
    $transferDays[$date] = $transfer;

    $lines = array(
        array('account' => '1110', 'debit' => $cash, 'memo' => 'Cash takings '.$date, 'cost_centre' => 'rooms'),
        array('account' => '1130', 'debit' => $card, 'memo' => 'Card settlement '.$date, 'cost_centre' => 'rooms'),
        array('account' => '1121', 'debit' => $transfer, 'memo' => 'Transfer takings TRF-'.$date, 'cost_centre' => 'rooms'),
        array('account' => '1220', 'debit' => $city, 'memo' => 'To city ledger '.$date, 'cost_centre' => 'rooms'),
        array('account' => '1210', 'debit' => $guest, 'memo' => 'Guest ledger movement '.$date, 'cost_centre' => 'rooms'),
        array('account' => '4110', 'credit' => round($roomsNet * 0.62, 2), 'memo' => $sold.' rooms sold', 'department' => 'rooms', 'cost_centre' => 'rooms'),
        array('account' => '4120', 'credit' => round($roomsNet * 0.28, 2), 'memo' => 'Corporate contract rate', 'department' => 'rooms', 'cost_centre' => 'rooms'),
        array('account' => '4140', 'credit' => round($roomsNet - round($roomsNet * 0.62, 2) - round($roomsNet * 0.28, 2), 2), 'memo' => 'OTA bookings', 'department' => 'rooms', 'cost_centre' => 'rooms'),
        array('account' => '4210', 'credit' => round($foodNet * 0.78, 2), 'memo' => 'Restaurant food', 'department' => 'fnb', 'cost_centre' => 'fnb'),
        array('account' => '4215', 'credit' => round($foodNet - round($foodNet * 0.78, 2), 2), 'memo' => 'Room service', 'department' => 'fnb', 'cost_centre' => 'fnb'),
        array('account' => '4230', 'credit' => $bevSoftNet, 'memo' => 'Soft drinks and water', 'department' => 'fnb', 'cost_centre' => 'fnb'),
        array('account' => '4235', 'credit' => $bevLiquorNet, 'memo' => 'Beer, wine and spirits', 'department' => 'fnb', 'cost_centre' => 'fnb'),
        array('account' => '4310', 'credit' => $laundryNet, 'memo' => 'Guest laundry', 'department' => 'laundry', 'cost_centre' => 'laundry'),
        array('account' => '4330', 'credit' => $teleNet, 'memo' => 'Telephone and internet', 'department' => 'telephone', 'cost_centre' => 'telephone'),
        array('account' => '4340', 'credit' => $minibarNet, 'memo' => 'Minibar', 'department' => 'rooms', 'cost_centre' => 'rooms'),
        array('account' => '2210', 'credit' => round($roomsTax + $fnbVat + $otherTax, 2), 'memo' => 'VAT on the day', 'tax_code' => 'VAT'),
        array('account' => '2240', 'credit' => $fnbCons, 'memo' => 'Rivers State consumption tax', 'tax_code' => 'CONS'),
    );
    if ($spaNet > 0) { $lines[] = array('account' => '4320', 'credit' => $spaNet, 'memo' => 'Spa treatments', 'department' => 'spa', 'cost_centre' => 'spa'); }
    if ($hallNet > 0) { $lines[] = array('account' => '4360', 'credit' => $hallNet, 'memo' => 'Conference hall hire', 'department' => 'general', 'cost_centre' => 'general'); }
    PulseAccJournal::post(array('type' => 'sales', 'source' => 'manual', 'source_ref' => $ref, 'business_date' => $date, 'reference' => 'TRF-'.$date, 'memo' => 'Daily revenue journal '.$date.' — '.$sold.' rooms sold', 'lines' => $lines));
    $made['journals']++;

    // cost of sales relieved from stock, three times a week
    if ($d % 2 === 1) {
        $foodCost = round($foodNet * 0.34, 2); $bevCost = round(($bevSoftNet + $bevLiquorNet) * 0.31, 2); $amenityCost = round($sold * 640, 2);
        PulseAccJournal::post(array('type' => 'general', 'source' => 'manual', 'source_ref' => 'seed:cos:'.$date, 'business_date' => $date, 'reference' => 'Stores issue', 'memo' => 'Stores issued to kitchen, bar and housekeeping '.$date, 'lines' => array(
            array('account' => '5100', 'debit' => $foodCost, 'memo' => 'Food cost', 'department' => 'fnb', 'cost_centre' => 'fnb'),
            array('account' => '5200', 'debit' => $bevCost, 'memo' => 'Beverage cost', 'department' => 'fnb', 'cost_centre' => 'fnb'),
            array('account' => '5400', 'debit' => $amenityCost, 'memo' => 'Guest supplies consumed', 'department' => 'rooms', 'cost_centre' => 'rooms'),
            array('account' => '1310', 'credit' => $foodCost, 'memo' => 'Food store'),
            array('account' => '1320', 'credit' => $bevCost, 'memo' => 'Beverage store'),
            array('account' => '1330', 'credit' => $amenityCost, 'memo' => 'Amenity store'),
        )));
        $made['journals']++;
    }
}

/* diesel twice a week, paid from GTBank — these become debits on the bank statement */
$dieselDays = array();
for ($d = 3; $d <= $days; $d += 4) {
    $date = date('Y-m-d', strtotime($prevStart.' +'.($d - 1).' day'));
    $ref = 'seed:diesel:'.$date;
    if (PulseAccJournal::bySource('manual', $ref)) { continue; }
    $litres = 2200 + (($d * 17) % 600);
    $net = round($litres * 1180, 2); $vatAmt = round($net * $vat / 100, 2);
    $dieselDays[$date] = round($net + $vatAmt, 2);
    PulseAccJournal::post(array('type' => 'payment', 'source' => 'manual', 'source_ref' => $ref, 'business_date' => $date, 'reference' => 'DIESEL-'.$date, 'memo' => 'Diesel '.$litres.' litres — Bonny Fuels Ltd DIESEL-'.$date, 'lines' => array(
        array('account' => '7420', 'debit' => $net, 'memo' => 'Diesel '.$litres.' litres', 'department' => 'maintenance', 'cost_centre' => 'maintenance'),
        array('account' => '1270', 'debit' => $vatAmt, 'memo' => 'Input VAT on diesel', 'tax_code' => 'VAT'),
        array('account' => '1121', 'credit' => round($net + $vatAmt, 2), 'memo' => 'Bonny Fuels Ltd DIESEL-'.$date),
    )));
    PulseAccTax::recordVat('input', 'manual', $ref, array('doc_no' => 'DIESEL-'.$date, 'party_name' => 'Bonny Fuels Ltd', 'tin' => '20481122-0001', 'department' => 'maintenance', 'net_amount' => $net, 'vat_rate' => $vat, 'vat_amount' => $vatAmt, 'business_date' => $date));
    $made['journals']++;
}

/* utilities, security, marketing and other overheads, mid-month */
$mid = date('Y-m-15', strtotime($prevStart));
if (!PulseAccJournal::bySource('manual', 'seed:overheads:'.$prevMonth)) {
    PulseAccJournal::post(array('type' => 'purchase', 'source' => 'manual', 'source_ref' => 'seed:overheads:'.$prevMonth, 'business_date' => $mid, 'reference' => 'Overheads '.$prevMonth, 'memo' => 'Monthly overheads '.$prevMonth, 'lines' => array(
        array('account' => '7410', 'debit' => 1840000, 'memo' => 'PHED electricity', 'department' => 'maintenance', 'cost_centre' => 'maintenance'),
        array('account' => '7440', 'debit' => 260000, 'memo' => 'Borehole and water treatment', 'department' => 'maintenance', 'cost_centre' => 'maintenance'),
        array('account' => '7450', 'debit' => 720000, 'memo' => 'Cooking gas', 'department' => 'fnb', 'cost_centre' => 'fnb'),
        array('account' => '7160', 'debit' => 480000, 'memo' => 'Internet, DStv and phones', 'department' => 'admin', 'cost_centre' => 'admin'),
        array('account' => '7195', 'debit' => 1250000, 'memo' => 'Security — Halogen Guards', 'department' => 'security', 'cost_centre' => 'security'),
        array('account' => '7230', 'debit' => 1680000, 'memo' => 'OTA commissions', 'department' => 'sales', 'cost_centre' => 'sales'),
        array('account' => '7320', 'debit' => 640000, 'memo' => 'Building repairs', 'department' => 'maintenance', 'cost_centre' => 'maintenance'),
        array('account' => '7130', 'debit' => 310000, 'memo' => 'Bank charges and card fees', 'department' => 'admin', 'cost_centre' => 'admin'),
        array('account' => '8100', 'debit' => 1500000, 'memo' => 'Ground rent', 'department' => 'admin', 'cost_centre' => 'admin'),
        array('account' => '8300', 'debit' => 300000, 'memo' => 'Insurance', 'department' => 'admin', 'cost_centre' => 'admin'),
        array('account' => '1270', 'debit' => 517500, 'memo' => 'Input VAT on overheads', 'tax_code' => 'VAT'),
        array('account' => '2230', 'credit' => 297500, 'memo' => 'WHT withheld on services and rent', 'tax_code' => 'WHT'),
        array('account' => '2110', 'credit' => 9200000, 'memo' => 'Overheads on credit'),
    )));
    $made['journals']++;
    PulseAccTax::recordWht(array('direction' => 'deducted', 'party_type' => 'supplier', 'party_name' => 'Halogen Guards Ltd', 'tin' => '18822440-0001', 'wht_type' => 'services', 'base_amount' => 1250000, 'rate_pct' => 5, 'amount' => 62500, 'source' => 'manual', 'source_ref' => 'seed:wht:security:'.$prevMonth, 'doc_no' => 'HG/'.$prevMonth, 'business_date' => $mid));
    PulseAccTax::recordWht(array('direction' => 'deducted', 'party_type' => 'supplier', 'party_name' => 'Aba Road Properties Ltd', 'tin' => '30991177-0001', 'wht_type' => 'rent', 'base_amount' => 1500000, 'rate_pct' => 10, 'amount' => 150000, 'source' => 'manual', 'source_ref' => 'seed:wht:rent:'.$prevMonth, 'doc_no' => 'RENT/'.$prevMonth, 'business_date' => $mid));
    PulseAccTax::recordWht(array('direction' => 'deducted', 'party_type' => 'supplier', 'party_name' => 'Bright Media Nigeria Ltd', 'tin' => '45120099-0001', 'wht_type' => 'commission', 'base_amount' => 1680000, 'rate_pct' => 5, 'amount' => 84000, 'source' => 'manual', 'source_ref' => 'seed:wht:ota:'.$prevMonth, 'doc_no' => 'BM/'.$prevMonth, 'business_date' => $mid));
}

/* payroll on the 28th */
if (!PulseAccJournal::bySource('payroll', 'payroll:'.$prevMonth)) {
    PulseAccPosting::payroll($prevMonth, array('rooms' => 4850000, 'housekeeping' => 2960000, 'fnb' => 5240000, 'laundry' => 980000, 'maintenance' => 1780000, 'sales' => 1120000, 'security' => 640000, 'admin' => 3480000),
        array('paye' => 1640000, 'pension' => 1420000, 'nsitf' => 210000), date('Y-m-28', strtotime($prevStart)));
    $made['journals']++;
}

/* salary payment out of Zenith, so the bank statement has a big debit */
$payDate = date('Y-m-28', strtotime($prevStart));
if (!PulseAccJournal::bySource('manual', 'seed:salarypay:'.$prevMonth)) {
    PulseAccJournal::post(array('type' => 'payment', 'source' => 'manual', 'source_ref' => 'seed:salarypay:'.$prevMonth, 'business_date' => $payDate, 'reference' => 'SAL-'.$prevMonth, 'memo' => 'Net salaries paid SAL-'.$prevMonth, 'lines' => array(
        array('account' => '2140', 'debit' => 17780000, 'memo' => 'Net salaries '.$prevMonth),
        array('account' => '1120', 'credit' => 17780000, 'memo' => 'Zenith transfer SAL-'.$prevMonth),
    )));
    $made['journals']++;
}

/* ---------- 5. city ledger companies, invoices and receipts ---------- */
$companyPlan = array(
    array('name' => 'Shoreline Energy Services Ltd', 'contact' => 'Ngozi Adeyemi', 'email' => 'accounts@shorelineenergy.ng', 'phone' => '08033445566', 'address' => 'Plot 12 Trans Amadi Industrial Layout, Port Harcourt', 'tin' => '10233445-0001', 'limit' => 15000000, 'terms' => 30, 'age' => 12, 'amount' => 4260000),
    array('name' => 'Delta Wells Nigeria Ltd', 'contact' => 'Emeka Okonkwo', 'email' => 'finance@deltawells.com.ng', 'phone' => '08122334455', 'address' => '5 Olu Obasanjo Road, Port Harcourt', 'tin' => '11556677-0001', 'limit' => 9000000, 'terms' => 30, 'age' => 47, 'amount' => 6840000),
    array('name' => 'Rivers State Ministry of Works', 'contact' => 'Ibiso Briggs', 'email' => 'accounts@rsworks.gov.ng', 'phone' => '08099887766', 'address' => 'Secretariat Complex, Moscow Road, Port Harcourt', 'tin' => '99001122-0001', 'limit' => 6000000, 'terms' => 45, 'age' => 104, 'amount' => 3920000),
);
foreach ($companyPlan as $cp) {
    $idCompany = null;
    if (PulseAccService::fd() && PulseAccService::tableExists('pulse_company')) {
        $idCompany = (int) $D->getValue('SELECT id_pulse_company FROM `'._DB_PREFIX_.'pulse_company` WHERE name="'.pSQL($cp['name']).'"');
        if (!$idCompany) {
            $co = new PulseCompany();
            $co->name = $cp['name']; $co->type = strpos($cp['name'], 'Ministry') !== false ? 'government' : 'corporate';
            $co->contact_name = $cp['contact']; $co->email = $cp['email']; $co->phone = $cp['phone']; $co->address = $cp['address']; $co->tin = $cp['tin'];
            $co->credit_limit = $cp['limit']; $co->ledger_balance = 0; $co->payment_terms_days = $cp['terms']; $co->active = 1;
            $co->add();
            $idCompany = (int) $co->id;
            $made['companies']++;
        }
    }
    $invDate = date('Y-m-d', strtotime($bd.' -'.($cp['age'] + $cp['terms']).' day'));
    $exists = (int) $D->getValue('SELECT id_pulse_acc_invoice FROM `'._DB_PREFIX_.'pulse_acc_invoice` WHERE company_name="'.pSQL($cp['name']).'" AND invoice_date="'.pSQL($invDate).'"');
    if ($exists) { continue; }
    $net = round($cp['amount'] / (1 + $vat / 100), 2);
    $id = PulseAccAr::invoiceManual($idCompany, array(
        array('description' => 'Accommodation and meetings — '.date('F Y', strtotime($invDate)), 'qty' => 1, 'unit_price' => round($net * 0.72, 2), 'tax_rate' => $vat, 'account_code' => '4120', 'department' => 'rooms'),
        array('description' => 'Conference hall and catering', 'qty' => 1, 'unit_price' => round($net * 0.28, 2), 'tax_rate' => $vat, 'account_code' => '4360', 'department' => 'general'),
    ), $invDate, 'Monthly account — '.$cp['contact'], $cp['name']);
    $made['invoices']++;
    if ($idCompany && PulseAccService::fd()) { $co = new PulseCompany($idCompany); if (Validate::isLoadedObject($co)) { $inv = PulseAccAr::invoice($id); $co->ledger_balance = round((float) $co->ledger_balance + (float) $inv['total'], 2); $co->update(); } }
    // Shoreline part-pays with WHT deducted, the way a Nigerian oil-services client actually settles
    if (strpos($cp['name'], 'Shoreline') === 0) {
        $inv = PulseAccAr::invoice($id);
        $part = round((float) $inv['total'] * 0.6, 2);
        $wht = round($part / (1 + $vat / 100) * 5 / 100, 2);
        PulseAccAr::receipt(array('id_pulse_company' => $idCompany, 'company_name' => $cp['name'], 'amount' => round($part - $wht, 2), 'wht_amount' => $wht,
            'wht_cert_no' => 'SES/WHT/'.date('Ymd', strtotime($invDate.' +20 day')), 'method' => 'transfer', 'id_pulse_acc_bank_account' => $gtb ? $gtb['id_pulse_acc_bank_account'] : null,
            'receipt_date' => date('Y-m-d', strtotime($invDate.' +20 day')), 'reference' => 'SHORELINE/'.date('ymd', strtotime($invDate)), 'auto_allocate' => 1));
        $made['receipts']++;
    }
}

/* ---------- 6. supplier bills and a payment run ---------- */
$supplierPlan = array(
    array('name' => 'Bonny Fuels Ltd', 'tin' => '20481122-0001', 'inv' => 'BF/'.date('Ym', strtotime($prevStart)).'/118', 'age' => 8, 'wht' => 0, 'lines' => array(array('description' => 'Diesel supply — month end top-up', 'account_code' => '7420', 'department' => 'maintenance', 'qty' => 1, 'unit_price' => 2640000, 'tax_rate' => 7.5))),
    array('name' => 'Garden City Foods Ltd', 'tin' => '33445566-0001', 'inv' => 'GCF-4471', 'age' => 22, 'wht' => 0, 'lines' => array(array('description' => 'Fresh produce, protein and dry goods', 'account_code' => '1310', 'department' => 'fnb', 'qty' => 1, 'unit_price' => 3180000, 'tax_rate' => 7.5))),
    array('name' => 'Halogen Guards Ltd', 'tin' => '18822440-0001', 'inv' => 'HG/'.date('Ym', strtotime($prevStart)), 'age' => 34, 'wht' => 5, 'lines' => array(array('description' => 'Manned guarding — monthly', 'account_code' => '7195', 'department' => 'security', 'qty' => 1, 'unit_price' => 1250000, 'tax_rate' => 7.5))),
    array('name' => 'Cool Breeze Engineering', 'tin' => '77220011-0001', 'inv' => 'CBE/2291', 'age' => 52, 'wht' => 5, 'lines' => array(array('description' => 'Chiller service and compressor overhaul', 'account_code' => '7350', 'department' => 'maintenance', 'qty' => 1, 'unit_price' => 1860000, 'tax_rate' => 7.5))),
    array('name' => 'Aba Road Properties Ltd', 'tin' => '30991177-0001', 'inv' => 'RENT/Q'.ceil((int) date('n', strtotime($prevStart)) / 3), 'age' => 15, 'wht' => 10, 'lines' => array(array('description' => 'Ground rent — quarter', 'account_code' => '8100', 'department' => 'admin', 'qty' => 1, 'unit_price' => 4500000, 'tax_rate' => 0))),
);
$billIds = array();
foreach ($supplierPlan as $sp) {
    if ($D->getValue('SELECT id_pulse_acc_bill FROM `'._DB_PREFIX_.'pulse_acc_bill` WHERE supplier_invoice_no="'.pSQL($sp['inv']).'"')) { continue; }
    $idSupplier = null;
    if (PulseAccService::inv()) {
        $idSupplier = (int) $D->getValue('SELECT id_pulse_inv_supplier FROM `'._DB_PREFIX_.'pulse_inv_supplier` WHERE name="'.pSQL($sp['name']).'"');
        if (!$idSupplier) { $D->insert('pulse_inv_supplier', array('name' => pSQL($sp['name']), 'tin' => pSQL($sp['tin']), 'payment_terms_days' => 30, 'active' => 1, 'date_add' => date('Y-m-d H:i:s')), true); $idSupplier = (int) $D->Insert_ID(); }
    }
    $billIds[] = PulseAccAp::bill(array(
        'id_pulse_inv_supplier' => $idSupplier, 'supplier_name' => $sp['name'], 'tin' => $sp['tin'], 'supplier_invoice_no' => $sp['inv'],
        'bill_date' => date('Y-m-d', strtotime($bd.' -'.$sp['age'].' day')), 'terms_days' => 30, 'wht_rate_pct' => $sp['wht'],
        'wht_type' => $sp['wht'] == 10 ? 'rent' : 'services', 'lines' => $sp['lines'], 'note' => 'Seeded demo bill',
    ));
    $made['bills']++;
}
/* pay the two oldest bills out of GTBank so the statement has matching debits */
$toPay = array();
foreach (PulseAccAp::bills(array('open' => 1), 50) as $b) { if ((int) $b['days_overdue'] > 20) { $toPay[] = (int) $b['id_pulse_acc_bill']; } }
if ($toPay && !$D->getValue('SELECT id_pulse_acc_payment FROM `'._DB_PREFIX_.'pulse_acc_payment` WHERE reference="SEED-RUN"')) {
    $r = PulseAccAp::paymentRun($toPay, array('payment_date' => date('Y-m-d', strtotime($bd.' -3 day')), 'method' => 'transfer', 'id_pulse_acc_bank_account' => $gtb ? $gtb['id_pulse_acc_bank_account'] : null, 'reference' => 'SEED-RUN'));
    $made['payments'] = count($r['payments']);
}

/* ---------- 7. petty cash ---------- */
if ($petty && !$D->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_petty_cash` WHERE id_pulse_acc_bank_account='.(int) $petty['id_pulse_acc_bank_account'])) {
    $idPetty = (int) $petty['id_pulse_acc_bank_account'];
    PulseAccBank::pettyMove($idPetty, 'float_in', 250000, 'Imprest float from GTBank', array('business_date' => date('Y-m-02', strtotime($prevStart)), 'account_code' => '1121', 'reference' => 'PC-FLOAT'));
    foreach (array(
        array(4, 'expense', 18500, 'Okada and taxi for guest airport run', '6150', 'rooms', 'PV-001'),
        array(6, 'expense', 42000, 'Plumbing fittings from Mile 3 market', '7340', 'maintenance', 'PV-002'),
        array(9, 'expense', 26500, 'Printer cartridges and A4 paper', '7120', 'admin', 'PV-003'),
        array(13, 'expense', 15000, 'Casual labour — garden clearing', '7360', 'maintenance', 'PV-004'),
        array(18, 'expense', 33000, 'Bread, eggs and milk for breakfast top-up', '5100', 'fnb', 'PV-005'),
        array(22, 'expense', 12000, 'Courier to Lagos head office', '7180', 'admin', 'PV-006'),
    ) as $p) {
        PulseAccBank::pettyMove($idPetty, $p[1], $p[2], $p[3], array('business_date' => date('Y-m-'.str_pad($p[0], 2, '0', STR_PAD_LEFT), strtotime($prevStart)), 'account_code' => $p[4], 'cost_centre' => $p[5], 'reference' => $p[6]));
        $made['petty']++;
    }
    PulseAccBank::pettyMove($idPetty, 'reimburse', 147000, 'Imprest reimbursed to the float', array('business_date' => date('Y-m-25', strtotime($prevStart)), 'account_code' => '1121', 'reference' => 'PC-REIMB'));
    $made['petty']++;
}

/* ---------- 8. depreciation for the prior month ---------- */
if (!$D->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_depreciation` WHERE period="'.pSQL($prevMonth).'"')) {
    $dep = PulseAccAssets::runDepreciation($prevMonth);
    $made['depreciation'] = $dep['assets'];
}

/* ---------- 9. bank statement CSV that actually matches ---------- */
$csv = dirname(__FILE__).'/bank_statement_sample.csv';
$rows = array();
$balance = 9450000;
$stmtFrom = date('Y-m-d', strtotime($prevStart));
foreach ($D->executeS('SELECT l.business_date, l.debit, l.credit, l.memo, j.reference FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_journal` j ON j.id_pulse_acc_journal=l.id_pulse_acc_journal WHERE l.posted=1 AND l.account_code="1121" AND l.business_date BETWEEN "'.pSQL($stmtFrom).'" AND "'.pSQL($prevEnd).'" ORDER BY l.business_date, l.id_pulse_acc_journal_line') as $l) {
    $in = round((float) $l['debit'], 2); $out = round((float) $l['credit'], 2);
    $balance = round($balance + $in - $out, 2);
    $rows[] = array(date('d/m/Y', strtotime($l['business_date'])), Tools::substr($l['memo'], 0, 80), $l['reference'], $out > 0 ? number_format($out, 2, '.', '') : '', $in > 0 ? number_format($in, 2, '.', '') : '', number_format($balance, 2, '.', ''));
}
/* three lines the ledger has never seen — exactly what makes a reconciliation worth doing */
foreach (array(
    array(date('Y-m-11', strtotime($prevStart)), 'COMMISSION ON TURNOVER', 'COT/'.date('ym', strtotime($prevStart)), 18750, 0),
    array(date('Y-m-19', strtotime($prevStart)), 'NIP TRANSFER FROM OKPARA V. — WEDDING DEPOSIT', 'NIP'.date('ymd', strtotime($prevStart)).'0091', 0, 450000),
    array(date('Y-m-26', strtotime($prevStart)), 'SMS ALERT AND ACCOUNT MAINTENANCE FEE', 'CHG/'.date('ym', strtotime($prevStart)), 4200, 0),
) as $x) {
    $balance = round($balance + $x[4] - $x[3], 2);
    $rows[] = array(date('d/m/Y', strtotime($x[0])), $x[1], $x[2], $x[3] > 0 ? number_format($x[3], 2, '.', '') : '', $x[4] > 0 ? number_format($x[4], 2, '.', '') : '', number_format($balance, 2, '.', ''));
}
usort($rows, function ($a, $b) { $da = substr($a[0], 6, 4).substr($a[0], 3, 2).substr($a[0], 0, 2); $db = substr($b[0], 6, 4).substr($b[0], 3, 2).substr($b[0], 0, 2); return strcmp($da, $db); });
$fh = fopen($csv, 'w');
fputcsv($fh, array('Value Date', 'Narration', 'Reference', 'Debit', 'Credit', 'Balance'));
foreach ($rows as $r) { fputcsv($fh, $r); }
fclose($fh);

/* ---------- 10. budget lines so Budget vs Actual has something to compare ---------- */
if (PulseAccService::tableExists('pulse_budget')) {
    $y = (int) date('Y', strtotime($prevStart)); $m = (int) date('n', strtotime($prevStart));
    foreach (array('room_revenue' => 74000000, 'fnb_revenue' => 21000000, 'other_revenue' => 3600000,
        'expense:DIESEL' => 11500000, 'expense:SAL' => 21000000, 'expense:POWER' => 2000000, 'expense:MKT' => 1500000, 'expense:SEC' => 1250000,
        'capex:FFE' => 6000000, 'capex:GEN' => 4000000) as $line => $amount) {
        $D->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_budget` (`year`,`month`,`line`,`amount`) VALUES ('.$y.','.$m.',"'.pSQL($line).'",'.(float) $amount.')');
    }
}

/* ---------- 11. queue the e-invoice payloads so the FIRS screen is not empty ---------- */
foreach (PulseAccAr::invoices(array('from' => date('Y-m-d', strtotime($bd.' -180 day'))), 20) as $i) {
    if ($i['type'] !== 'invoice') { continue; }
    try { PulseAccTax::queueEinvoice((int) $i['id_pulse_acc_invoice']); } catch (Exception $e) { /* payload build is best effort in the seeder */ }
}

$tb = PulseAccReport::trialBalance($openingDate, $bd);
echo "Pulse Accounts demo data — Rivers Crest Hotel, Port Harcourt\n";
echo '  periods open/closed ......... '.$made['periods']."\n";
echo '  fixed assets capitalised .... '.$made['assets']."\n";
echo '  journals posted ............. '.$made['journals']."\n";
echo '  city-ledger companies ....... '.$made['companies']."\n";
echo '  sales invoices .............. '.$made['invoices'].' (1 part-paid with 5% WHT deducted)'."\n";
echo '  receipts .................... '.$made['receipts']."\n";
echo '  supplier bills .............. '.$made['bills'].', payments '.$made['payments']."\n";
echo '  petty cash movements ........ '.$made['petty']."\n";
echo '  assets depreciated for '.$prevMonth.' . '.$made['depreciation']."\n";
echo '  bank statement CSV .......... '.$csv.' ('.count($rows).' lines, 3 of them deliberately unmatched)'."\n";
echo '  trial balance '.($tb['balanced'] ? 'balances' : 'IS OUT by '.number_format($tb['totals']['closing_dr'] - $tb['totals']['closing_cr'], 2))
    .' — debits '.number_format($tb['totals']['closing_dr'], 2)."\n";
echo "Next: Accounts dashboard → Post now, then Reports → USALI departmental P&L for ".$prevMonth.".\n";

<?php
/** Expenditure ledger. Manual entries + automatic feeds from Maintenance (labour/parts/vendor), Laundry (vendor, chemicals), cashier paid-outs, meter costs. */
class PulseExpense
{
    protected static function emp() { $c = Context::getContext(); return isset($c->employee) ? (int) $c->employee->id : 0; }
    protected static function bd() { return class_exists('PulseCore') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    protected static function nextNo() { $n = (int) PulseCoreService::setting('pulsereports', 'exp_seq') + 1; PulseCoreService::setting('pulsereports', 'exp_seq', $n); return 'EX'.date('ym').str_pad($n % 100000, 5, '0', STR_PAD_LEFT); }
    public static function catId($code) { return (int) Db::getInstance()->getValue('SELECT id_pulse_expense_category FROM `'._DB_PREFIX_.'pulse_expense_category` WHERE code="'.pSQL($code).'"'); }

    public static function add(array $d)
    {
        if (!empty($d['source']) && !empty($d['source_ref']) && Db::getInstance()->getValue('SELECT id_pulse_expense FROM `'._DB_PREFIX_.'pulse_expense` WHERE source="'.pSQL($d['source']).'" AND source_ref="'.pSQL($d['source_ref']).'"')) { return false; } // idempotent feeds
        Db::getInstance()->insert('pulse_expense', array(
            'expense_no' => self::nextNo(), 'id_pulse_expense_category' => (int) (isset($d['id_category']) ? $d['id_category'] : self::catId($d['category'])), 'department' => pSQL(isset($d['department']) ? $d['department'] : 'general'),
            'description' => pSQL($d['description']), 'payee' => pSQL(isset($d['payee']) ? $d['payee'] : ''), 'amount' => (float) $d['amount'], 'tax_amount' => (float) (isset($d['tax_amount']) ? $d['tax_amount'] : 0),
            'payment_method' => pSQL(isset($d['payment_method']) ? $d['payment_method'] : 'cash'), 'reference' => pSQL(isset($d['reference']) ? $d['reference'] : ''), 'receipt_path' => pSQL(isset($d['receipt_path']) ? $d['receipt_path'] : ''),
            'status' => pSQL(isset($d['status']) ? $d['status'] : ((float) $d['amount'] > (float) Configuration::get('PULSE_RPT_APPROVAL_LIMIT') ? 'submitted' : 'approved')),
            'source' => pSQL(isset($d['source']) ? $d['source'] : 'manual'), 'source_ref' => pSQL(isset($d['source_ref']) ? $d['source_ref'] : ''), 'business_date' => pSQL(isset($d['business_date']) ? $d['business_date'] : self::bd()),
            'id_employee' => self::emp(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::audit('pulsereports', 'expense_add', array('amount' => $d['amount'], 'desc' => $d['description']), 'pulse_expense', $id);
        return $id;
    }
    public static function setStatus($id, $status) { Db::getInstance()->update('pulse_expense', array('status' => pSQL($status), 'approved_by' => in_array($status, array('approved', 'rejected', 'paid')) ? self::emp() : null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_expense='.(int) $id); PulseCoreService::audit('pulsereports', 'expense_'.$status, null, 'pulse_expense', $id); }

    /** Pull costs recorded elsewhere in Pulse for a business date into the ledger (idempotent). */
    public static function syncFeeds($date)
    {
        $n = 0;
        if (Module::isEnabled('pulsemaintenance')) {
            foreach (Db::getInstance()->executeS('SELECT wo_no, subject, labour_cost, parts_cost, vendor_cost, category, DATE(date_completed) d FROM `'._DB_PREFIX_.'pulse_work_order` WHERE status IN ("completed","verified") AND DATE(date_completed)="'.pSQL($date).'"') as $w) {
                if ($w['parts_cost'] > 0 && self::add(array('category' => 'PARTS', 'department' => 'maintenance', 'description' => 'Parts — '.$w['wo_no'].' '.$w['subject'], 'amount' => $w['parts_cost'], 'payment_method' => 'credit', 'source' => 'maintenance', 'source_ref' => $w['wo_no'].':parts', 'business_date' => $w['d'], 'status' => 'approved'))) { $n++; }
                if ($w['vendor_cost'] > 0 && self::add(array('category' => 'VENDOR', 'department' => 'maintenance', 'description' => 'Contractor — '.$w['wo_no'].' '.$w['subject'], 'amount' => $w['vendor_cost'], 'payment_method' => 'credit', 'source' => 'maintenance', 'source_ref' => $w['wo_no'].':vendor', 'business_date' => $w['d'], 'status' => 'approved'))) { $n++; }
            }
            foreach (Db::getInstance()->executeS('SELECT id_pulse_meter_reading, meter, cost FROM `'._DB_PREFIX_.'pulse_meter_reading` WHERE cost>0 AND DATE(read_at)="'.pSQL($date).'"') as $m) {
                $cat = array('diesel_litres' => 'DIESEL', 'electricity_kwh' => 'POWER', 'water_m3' => 'WATER', 'gas_kg' => 'GAS'); if (!isset($cat[$m['meter']])) { continue; }
                if (self::add(array('category' => $cat[$m['meter']], 'department' => 'general', 'description' => ucfirst(str_replace('_', ' ', $m['meter'])).' purchase', 'amount' => $m['cost'], 'source' => 'meter', 'source_ref' => 'MR'.$m['id_pulse_meter_reading'], 'business_date' => $date, 'status' => 'approved'))) { $n++; }
            }
        }
        if (Module::isEnabled('pulselaundry')) {
            foreach (Db::getInstance()->executeS('SELECT batch_no, chemicals_cost FROM `'._DB_PREFIX_.'pulse_laundry_batch` WHERE chemicals_cost>0 AND DATE(date_add)="'.pSQL($date).'"') as $b) { if (self::add(array('category' => 'CLEAN', 'department' => 'laundry', 'description' => 'Laundry chemicals — batch '.$b['batch_no'], 'amount' => $b['chemicals_cost'], 'source' => 'laundry', 'source_ref' => $b['batch_no'], 'business_date' => $date, 'status' => 'approved'))) { $n++; } }
            foreach (Db::getInstance()->executeS('SELECT o.order_no, o.pieces, v.name, v.rate_per_kg FROM `'._DB_PREFIX_.'pulse_laundry_order` o INNER JOIN `'._DB_PREFIX_.'pulse_laundry_vendor` v ON v.id_pulse_laundry_vendor=o.id_vendor WHERE o.status="delivered" AND DATE(o.delivered_at)="'.pSQL($date).'" AND v.rate_per_kg>0') as $o) { if (self::add(array('category' => 'VENDOR', 'department' => 'laundry', 'description' => 'Outsourced laundry '.$o['order_no'].' — '.$o['name'].' (est. '.$o['pieces'].' pc)', 'amount' => round($o['pieces'] * 0.4 * $o['rate_per_kg'], 2), 'payment_method' => 'credit', 'source' => 'laundry', 'source_ref' => $o['order_no'], 'business_date' => $date, 'status' => 'submitted'))) { $n++; } }
        }
        if (Module::isEnabled('pulsefrontdesk')) {
            $tbl = Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_drawer_movement"') ? 'pulse_drawer_movement' : (Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_cashier_movement"') ? 'pulse_cashier_movement' : null);
            if ($tbl) { foreach (Db::getInstance()->executeS('SELECT m.*, s.business_date bd FROM `'._DB_PREFIX_.$tbl.'` m INNER JOIN `'._DB_PREFIX_.'pulse_cashier_session` s ON s.id_pulse_cashier_session=m.id_pulse_cashier_session WHERE m.type="paid_out" AND s.business_date="'.pSQL($date).'"') as $m) { if (self::add(array('category' => 'MISC', 'department' => 'rooms', 'description' => 'Front-desk paid-out: '.(isset($m['note']) ? $m['note'] : (isset($m['reason']) ? $m['reason'] : '')), 'amount' => $m['amount'], 'payment_method' => 'petty_cash', 'source' => 'cashier', 'source_ref' => 'PO'.$m[array_keys($m)[0]], 'business_date' => $date, 'status' => 'approved'))) { $n++; } } }
        }
        return $n;
    }

    public static function byCategory($from, $to, $approvedOnly = true)
    {
        return Db::getInstance()->executeS('SELECT c.code, c.name, c.group_name, COUNT(e.id_pulse_expense) n, ROUND(SUM(e.amount),2) total FROM `'._DB_PREFIX_.'pulse_expense` e INNER JOIN `'._DB_PREFIX_.'pulse_expense_category` c ON c.id_pulse_expense_category=e.id_pulse_expense_category WHERE e.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'.($approvedOnly ? ' AND e.status IN ("approved","paid")' : ' AND e.status<>"rejected"').' GROUP BY c.id_pulse_expense_category ORDER BY c.group_name, total DESC');
    }
    public static function byGroup($from, $to) { return Db::getInstance()->executeS('SELECT c.group_name, ROUND(SUM(e.amount),2) total FROM `'._DB_PREFIX_.'pulse_expense` e INNER JOIN `'._DB_PREFIX_.'pulse_expense_category` c ON c.id_pulse_expense_category=e.id_pulse_expense_category WHERE e.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND e.status IN ("approved","paid") GROUP BY c.group_name'); }
    public static function total($from, $to) { return (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(amount),0) FROM `'._DB_PREFIX_.'pulse_expense` WHERE business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND status IN ("approved","paid")'); }
    public static function pending() { return Db::getInstance()->executeS('SELECT e.*, c.name category, CONCAT(emp.firstname," ",emp.lastname) who FROM `'._DB_PREFIX_.'pulse_expense` e INNER JOIN `'._DB_PREFIX_.'pulse_expense_category` c ON c.id_pulse_expense_category=e.id_pulse_expense_category LEFT JOIN `'._DB_PREFIX_.'employee` emp ON emp.id_employee=e.id_employee WHERE e.status="submitted" ORDER BY e.date_add'); }
    public static function list_($from, $to, $status = null) { return Db::getInstance()->executeS('SELECT e.*, c.name category, c.code, CONCAT(emp.firstname," ",emp.lastname) who FROM `'._DB_PREFIX_.'pulse_expense` e INNER JOIN `'._DB_PREFIX_.'pulse_expense_category` c ON c.id_pulse_expense_category=e.id_pulse_expense_category LEFT JOIN `'._DB_PREFIX_.'employee` emp ON emp.id_employee=e.id_employee WHERE e.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'.($status ? ' AND e.status="'.pSQL($status).'"' : '').' ORDER BY e.business_date DESC, e.id_pulse_expense DESC'); }
}

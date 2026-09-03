<?php
/** Builds and sends the scheduled reports (owner daily, manager daily, weekly, monthly, alerts). HTML email + SMS headline + report log. */
class PulseOwnerSnapshot
{
    public static function money($v) { $c = Context::getContext()->currency; return ($c ? $c->sign : '₦').number_format((float) $v, 0); }
    protected static function arrow($now, $before) { $p = PulseReportData::pct($now, $before); if ($p === null) { return ''; } $col = $p >= 0 ? '#1e8449' : '#c0392b'; return ' <span style="color:'.$col.';font-size:12px">'.($p >= 0 ? '▲' : '▼').' '.abs($p).'%</span>'; }

    /** Ranges for a report type ending on $bd (business date just closed). */
    public static function ranges($report, $bd)
    {
        switch ($report) {
            case 'weekly': $from = date('Y-m-d', strtotime($bd.' -6 days')); $pf = date('Y-m-d', strtotime($from.' -7 days')); $pt = date('Y-m-d', strtotime($bd.' -7 days')); break;
            case 'monthly': $from = date('Y-m-01', strtotime($bd)); $pf = date('Y-m-01', strtotime($from.' -1 month')); $pt = date('Y-m-t', strtotime($pf)); break;
            default: $from = $bd; $pf = $pt = date('Y-m-d', strtotime($bd.' -7 days'));
        }
        return array('from' => $from, 'to' => $bd, 'prev_from' => $pf, 'prev_to' => $pt, 'mtd_from' => date('Y-m-01', strtotime($bd)), 'ytd_from' => date('Y-01-01', strtotime($bd)));
    }

    public static function build($report, $bd)
    {
        $r = self::ranges($report, $bd); $now = PulseReportData::period($r['from'], $r['to']); $prev = PulseReportData::period($r['prev_from'], $r['prev_to']);
        $mtd = PulseReportData::period($r['mtd_from'], $r['to']); $ytd = $report === 'monthly' ? PulseReportData::period($r['ytd_from'], $r['to']) : null;
        $hotel = Configuration::get('PS_SHOP_NAME'); $title = array('owner_daily' => 'Daily Owner Snapshot', 'manager_daily' => 'Daily Operations Report', 'weekly' => 'Weekly Owner Summary', 'monthly' => 'Monthly Owner Report', 'alerts' => 'Alerts')[$report];
        $label = $report === 'weekly' ? date('d M', strtotime($r['from'])).' – '.date('d M Y', strtotime($bd)) : ($report === 'monthly' ? date('F Y', strtotime($bd)) : date('l, d F Y', strtotime($bd)));
        $M = function ($v) { return self::money($v); };
        $h = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:720px;margin:0 auto;color:#222">';
        $h .= '<div style="background:#1f3b57;color:#fff;padding:18px 22px"><div style="font-size:12px;opacity:.8">'.$hotel.'</div><div style="font-size:22px;font-weight:600">'.$title.'</div><div style="font-size:14px;opacity:.9">'.$label.'</div></div>';
        // headline tiles
        $tile = function ($lbl, $val, $sub = '') { return '<td style="padding:10px;border:1px solid #e3e8ee;text-align:center;width:25%"><div style="font-size:11px;color:#666;text-transform:uppercase">'.$lbl.'</div><div style="font-size:20px;font-weight:700">'.$val.'</div><div style="font-size:11px;color:#666">'.$sub.'</div></td>'; };
        $h .= '<table style="width:100%;border-collapse:collapse;margin-top:12px"><tr>'.$tile('Total revenue (net)', $M($now['revenue']['net']).self::arrow($now['revenue']['net'], $prev['revenue']['net']), 'MTD '.$M($mtd['revenue']['net'])).$tile('Expenses', $M($now['expenses']['total']).self::arrow($now['expenses']['total'], $prev['expenses']['total']), 'MTD '.$M($mtd['expenses']['total'])).$tile('Operating profit', $M($now['pl']['gop']), $now['pl']['gop_pct'].'% margin · MTD '.$M($mtd['pl']['gop'])).$tile('Cash collected', $M($now['payments']['total']), 'net cash '.$M($now['pl']['net_cash'])).'</tr>';
        $h .= '<tr>'.$tile('Occupancy', $now['occupancy']['occupancy_pct'].'%'.self::arrow($now['occupancy']['occupancy_pct'], $prev['occupancy']['occupancy_pct']), $now['occupancy']['room_nights_sold'].' of '.$now['occupancy']['room_nights_available'].' room nights · MTD '.$mtd['occupancy']['occupancy_pct'].'%').$tile('ADR', $M($now['occupancy']['adr']).self::arrow($now['occupancy']['adr'], $prev['occupancy']['adr']), 'MTD '.$M($mtd['occupancy']['adr'])).$tile('RevPAR', $M($now['occupancy']['revpar']).self::arrow($now['occupancy']['revpar'], $prev['occupancy']['revpar']), 'MTD '.$M($mtd['occupancy']['revpar'])).$tile('In house now', $now['occupancy']['in_house_now'], $now['occupancy']['ooo_now'].' rooms OOO · '.$now['guests']['vip_in_house'].' VIP').'</tr></table>';
        // alerts
        if ($now['alerts']) { $h .= '<h3 style="margin:18px 0 6px;font-size:15px;color:#c0392b">Needs attention</h3><ul style="margin:0;padding-left:18px;font-size:13px">'; foreach ($now['alerts'] as $a) { $h .= '<li style="color:'.($a['level'] === 'danger' ? '#c0392b' : ($a['level'] === 'warning' ? '#b9770e' : '#555')).'">'.$a['text'].'</li>'; } $h .= '</ul>'; }
        $sec = function ($t) { return '<h3 style="margin:18px 0 6px;font-size:15px;color:#1f3b57;border-bottom:2px solid #e3e8ee;padding-bottom:3px">'.$t.'</h3>'; };
        $tbl = function ($rows, $cols) { $s = '<table style="width:100%;border-collapse:collapse;font-size:13px">'; foreach ($rows as $row) { $s .= '<tr>'; foreach ($cols as $i => $c) { $s .= '<td style="padding:4px 6px;border-bottom:1px solid #eee;'.($i > 0 ? 'text-align:right' : '').'">'.$row[$i].'</td>'; } $s .= '</tr>'; } return $s.'</table>'; };
        // revenue
        $rv = $now['revenue']; $rows = array(); foreach ($rv['by_department'] as $d) { $rows[] = array(ucfirst($d['department']), $M($d['net']), $M($d['tax']), $M($d['gross'])); } $rows[] = array('<b>Total</b>', '<b>'.$M($rv['net']).'</b>', '<b>'.$M($rv['tax']).'</b>', '<b>'.$M($rv['gross']).'</b>');
        $h .= $sec('Revenue by department').$tbl(array_merge(array(array('<i>Department</i>', '<i>Net</i>', '<i>Tax</i>', '<i>Gross</i>')), $rows), array(0, 1, 2, 3));
        if ($rv['voids'] > 0) { $h .= '<div style="font-size:12px;color:#666">Voided charges: '.$M($rv['voids']).' · Adjustments: '.$M($rv['adjustments'] ?? 0).'</div>'; }
        // expenses + P&L
        $ex = $now['expenses']; if ($ex['by_category']) { $rows = array(); foreach ($ex['by_category'] as $c) { $rows[] = array($c['name'].' <span style="color:#999">('.str_replace('_', ' ', $c['group_name']).')</span>', $M($c['total'])); } $rows[] = array('<b>Total expenses</b>', '<b>'.$M($ex['total']).'</b>'); $h .= $sec('Expenditure').$tbl($rows, array(0, 1)); if ($ex['pending']) { $h .= '<div style="font-size:12px;color:#b9770e">'.$ex['pending'].' expense(s) totalling '.$M($ex['pending_amount']).' awaiting approval (not included)</div>'; } }
        $pl = $now['pl']; $h .= $sec('Operating statement').$tbl(array(array('Revenue (net of tax)', $M($pl['revenue'])), array('Cost of sales', '('.$M($pl['cost_of_sales']).')'), array('<b>Gross profit</b>', '<b>'.$M($pl['gross_profit']).'</b>'), array('Payroll', '('.$M($pl['payroll']).')'), array('Utilities & energy', '('.$M($pl['utilities']).')'), array('Repairs & maintenance', '('.$M($pl['repairs']).')'), array('Other operating', '('.$M($pl['other_opex']).')'), array('<b>Gross operating profit</b>', '<b>'.$M($pl['gop']).' ('.$pl['gop_pct'].'%)</b>'), array('Cash collected', $M($pl['cash_in'])), array('Cash paid out', '('.$M($pl['cash_out']).')'), array('<b>Net cash movement</b>', '<b>'.$M($pl['net_cash']).'</b>')), array(0, 1));
        // payments & ledgers
        $rows = array(); foreach ($now['payments']['by_method'] as $p) { $rows[] = array(ucfirst(str_replace('_', ' ', $p['method'])), $p['n'], $M($p['total'])); } $rows[] = array('<b>Total collected</b>', '', '<b>'.$M($now['payments']['total']).'</b>');
        $lg = $now['ledgers']; $h .= $sec('Collections and ledgers').$tbl($rows, array(0, 1, 2)).'<div style="font-size:13px;margin-top:6px">Guest ledger (owed by in-house guests): <b>'.$M($lg['guest_ledger']).'</b> · City ledger (owed by companies): <b>'.$M($lg['city_ledger']).'</b> · Deposits held for future stays: <b>'.$M($lg['deposits_held']).'</b></div>';
        if ($lg['city_ledger_top']) { $rows = array(); foreach ($lg['city_ledger_top'] as $c) { $rows[] = array($c['name'], $M($c['ledger_balance']).($c['credit_limit'] > 0 ? ' / '.$M($c['credit_limit']) : '')); } $h .= $tbl($rows, array(0, 1)); }
        $cs = $now['cash']; if ($cs) { $h .= '<div style="font-size:12px;color:#666;margin-top:4px">Cashier shifts: '.$cs['shifts'].' · cash taken '.$M($cs['cash_taken']).' · variances '.$cs['shifts_with_variance'].' shift(s), net '.$M($cs['variance_total']).' · voids '.$cs['voids_count'].'</div>'; }
        // front office
        $fo = $now['frontoffice']; $h .= $sec('Front office').'<div style="font-size:13px">Arrivals <b>'.$fo['arrivals'].'</b> · Departures <b>'.$fo['departures'].'</b> · No-shows <b>'.$fo['no_shows'].'</b> · Cancellations <b>'.$fo['cancellations'].'</b> · Walk-ins <b>'.$fo['walk_ins'].'</b> · Avg length of stay <b>'.$now['guests']['avg_los'].' nights</b> · Repeat guests <b>'.$now['guests']['repeat_pct'].'%</b><br>Upsells sold <b>'.$fo['upsell_count'].'</b> worth <b>'.$M($fo['upsell_revenue']).'</b> · Guest requests raised <b>'.$fo['tickets_raised'].'</b> (complaints '.$fo['complaints'].', SLA breached '.$fo['tickets_sla_breached'].') · open now <b>'.$fo['tickets_open'].'</b></div>';
        if ($fo['source_mix']) { $s = array(); foreach ($fo['source_mix'] as $x) { $s[] = ucfirst($x['source']).' '.$x['n']; } $h .= '<div style="font-size:12px;color:#666">Booking sources: '.implode(' · ', $s).'</div>'; }
        if ($now['occupancy']['by_type']) { $rows = array(); foreach ($now['occupancy']['by_type'] as $t) { $rows[] = array($t['type'], $t['nights'].' nights', $M($t['avg_rate'])); } $h .= $tbl($rows, array(0, 1, 2)); }
        // forecast
        $f = $now['occupancy']['forecast']; if ($f) { $rows = array(); foreach ($f as $x) { $rows[] = array(date('D d M', strtotime($x['d'])), $x['occupied'].' rooms', $x['pct'].'%', $x['arrivals'].' arr'); } $h .= $sec('Next 7 days').$tbl($rows, array(0, 1, 2, 3)); }
        // housekeeping / laundry / maintenance
        $hk = $now['housekeeping']; if ($hk) { $st = array(); foreach ($hk['status_now'] as $x) { $st[] = str_replace('_', ' ', $x['hk_status']).' '.$x['n']; } $h .= $sec('Housekeeping').'<div style="font-size:13px">Tasks '.$hk['tasks'].' · done '.$hk['done'].' · skipped '.$hk['skipped'].' · avg '.$hk['avg_min'].' min/room · open now '.$hk['open_now'].'<br><span style="color:#666">'.implode(' · ', $st).'</span></div>'; if ($hk['ooo_rooms']) { $rows = array(); foreach ($hk['ooo_rooms'] as $o) { $rows[] = array('Room '.$o['room_num'], str_replace('_', ' ', $o['hk_status']), $o['ooo_reason'].($o['ooo_until'] ? ' until '.$o['ooo_until'] : '')); } $h .= $tbl($rows, array(0, 1, 2)); } }
        $ld = $now['laundry']; if ($ld) { $h .= $sec('Laundry').'<div style="font-size:13px">Orders '.$ld['orders'].' · pieces '.$ld['pieces'].' · guest revenue <b>'.$M($ld['revenue']).'</b> · express '.$ld['express'].' · late '.$ld['late'].' · turnaround '.$ld['turnaround_h'].'h · in process '.$ld['in_process'].' · claims '.$ld['claims'].' (open '.$ld['claims_open'].') · linen discarded '.$ld['linen_discarded'].'</div>'; if ($ld['linen_shortfall']) { $s = array(); foreach ($ld['linen_shortfall'] as $l) { $s[] = $l['name'].' short '.$l['shortfall']; } $h .= '<div style="font-size:12px;color:#b9770e">Linen below par: '.implode(', ', $s).'</div>'; } }
        $mt = $now['maintenance']; if ($mt) { $h .= $sec('Maintenance').'<div style="font-size:13px">Raised '.($mt['total'] ?? 0).' · closed '.($mt['closed'] ?? 0).' · open now <b>'.$mt['open_now'].'</b> (overdue '.$mt['overdue_now'].') · emergencies '.$mt['emergencies'].' · MTTR '.($mt['mttr_hours'] ?? '—').'h · cost <b>'.$M($mt['cost'] ?? 0).'</b></div>'; if ($mt['open_list']) { $rows = array(); foreach (array_slice($mt['open_list'], 0, 6) as $w) { $rows[] = array($w['wo_no'].' '.$w['subject'].($w['room_num'] ? ' (Rm '.$w['room_num'].')' : ''), $w['priority'], str_replace('_', ' ', $w['status'])); } $h .= $tbl($rows, array(0, 1, 2)); } if ($mt['assets_oos']) { $s = array(); foreach ($mt['assets_oos'] as $a) { $s[] = $a['code']; } $h .= '<div style="font-size:12px;color:#c0392b">Assets out of service: '.implode(', ', $s).'</div>'; } if ($mt['meters']) { $s = array(); foreach ($mt['meters'] as $m) { $s[] = str_replace('_', ' ', $m['meter']).' +'.number_format($m['delta'], 1); } $h .= '<div style="font-size:12px;color:#666">Meters: '.implode(' · ', $s).'</div>'; } }
        // YTD for monthly
        if ($ytd) { $h .= $sec('Year to date').'<div style="font-size:13px">Revenue <b>'.$M($ytd['revenue']['net']).'</b> · Expenses <b>'.$M($ytd['expenses']['total']).'</b> · GOP <b>'.$M($ytd['pl']['gop']).' ('.$ytd['pl']['gop_pct'].'%)</b> · Occupancy <b>'.$ytd['occupancy']['occupancy_pct'].'%</b> · ADR <b>'.$M($ytd['occupancy']['adr']).'</b></div>'; }
        $h .= '<div style="margin-top:18px;font-size:11px;color:#999;border-top:1px solid #eee;padding-top:8px">Generated by Pulse for '.$hotel.' on '.date('d M Y H:i').'. Figures are for business date(s) '.$r['from'].($r['from'] !== $r['to'] ? ' to '.$r['to'] : '').', net of tax unless stated. Comparison arrows are against '.($report === 'weekly' ? 'the previous week' : ($report === 'monthly' ? 'the previous month' : 'the same day last week')).'.</div></div>';
        $sms = $hotel.' '.date('d/m', strtotime($bd)).': Rev '.$M($now['revenue']['net']).' Exp '.$M($now['expenses']['total']).' GOP '.$M($now['pl']['gop']).' Occ '.$now['occupancy']['occupancy_pct'].'% ADR '.$M($now['occupancy']['adr']).' Cash '.$M($now['payments']['total']).($now['alerts'] ? ' | '.count($now['alerts']).' alert(s)' : '');
        return array('subject' => $hotel.' — '.$title.' — '.$label, 'html' => $h, 'sms' => $sms, 'data' => $now);
    }

    public static function send($idSchedule, $bd = null, $force = false)
    {
        $s = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_report_schedule` WHERE id_pulse_report_schedule='.(int) $idSchedule);
        if (!$s || (!$s['active'] && !$force)) { return false; }
        $bd = $bd ?: date('Y-m-d', strtotime(PulseCoreService::businessDate().' -1 day'));
        if (!$force && $s['last_business_date'] === $bd) { return false; }
        $r = self::build($s['report'], $bd);
        $emails = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $s['recipients_email']))); $phones = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $s['recipients_sms'])));
        $ok = true; $err = '';
        foreach ($emails as $e) { if (!Validate::isEmail($e)) { continue; } try { $sent = Mail::Send((int) Configuration::get('PS_LANG_DEFAULT'), 'pulse_report', $r['subject'], array('{content}' => $r['html'], '{subject}' => $r['subject']), $e, null, null, null, null, null, _PS_MODULE_DIR_.'pulsereports/mails/', true); if (!$sent) { $ok = false; $err .= 'mail fail '.$e.'; '; } } catch (Exception $x) { $ok = false; $err .= $x->getMessage().'; '; } }
        if ($phones && class_exists('PulseComms')) { foreach ($phones as $p) { try { PulseComms::sendRaw(null, $p, 'owner_snapshot', array('text' => $r['sms'])); } catch (Exception $x) { $err .= 'sms '.$x->getMessage().'; '; } } }
        Db::getInstance()->insert('pulse_report_log', array('id_pulse_report_schedule' => (int) $idSchedule, 'report' => pSQL($s['report']), 'business_date' => pSQL($bd), 'recipients' => pSQL(implode(',', array_merge($emails, $phones))), 'status' => $ok ? 'sent' : 'failed', 'error' => pSQL(substr($err, 0, 250)), 'html' => pSQL($r['html'], true), 'date_add' => date('Y-m-d H:i:s')));
        Db::getInstance()->update('pulse_report_schedule', array('last_sent' => date('Y-m-d H:i:s'), 'last_business_date' => pSQL($bd)), 'id_pulse_report_schedule='.(int) $idSchedule);
        return $ok;
    }

    /** Called by cron every 15 min and by the night-audit hook. Decides which schedules are due. */
    public static function runDue($afterAudit = false)
    {
        $bd = date('Y-m-d', strtotime(PulseCoreService::businessDate().' -1 day')); $sent = array();
        if (class_exists('PulseExpense')) { PulseExpense::syncFeeds($bd); }
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_report_schedule` WHERE active=1') as $s) {
            if ($s['last_business_date'] === $bd) { continue; }
            $due = false; $now = date('H:i:s'); $dow = (int) date('N'); $dom = (int) date('j');
            if ($s['report'] === 'weekly' && $dow !== (int) $s['weekday']) { continue; }
            if ($s['report'] === 'monthly' && $dom !== (int) $s['month_day']) { continue; }
            if ($afterAudit && $s['send_after_audit'] && in_array($s['report'], array('owner_daily', 'manager_daily', 'alerts'))) { $due = true; }
            if ($now >= $s['send_time']) { $due = true; }
            if ($due && self::send($s['id_pulse_report_schedule'], $bd)) { $sent[] = $s['name']; }
        }
        return $sent;
    }
}

<?php
/** ESC/POS receipts and kitchen tickets over raw TCP (port 9100). Also returns plain text for browser printing. */
class PulsePosPrinter
{
    const W = 42;
    public static function esc($text, $cut = true) { return "\x1b@".$text."\n\n\n".($cut ? "\x1dV\x00" : ''); }
    public static function raw($host, $port, $data) { $fp = @fsockopen($host, (int) ($port ?: 9100), $e, $es, 3); if (!$fp) { PulseCoreService::audit('pulsepos', 'print_fail', $host.':'.$port.' '.$es); return false; } fwrite($fp, $data); fclose($fp); return true; }
    protected static function lr($l, $r) { $w = self::W - strlen($r); return str_pad(mb_substr($l, 0, $w), $w).$r."\n"; }
    protected static function c($s) { return str_pad($s, self::W, ' ', STR_PAD_BOTH)."\n"; }
    public static function receipt(array $c, $final = true)
    {
        $cur = Context::getContext()->currency ? Context::getContext()->currency->sign : 'N'; $m = function ($v) use ($cur) { return $cur.number_format((float) $v, 2); };
        $t = self::c(Configuration::get('PS_SHOP_NAME')).($c['receipt_header'] ? self::c($c['receipt_header']) : '').self::c($c['outlet_name']).str_repeat('-', self::W)."\n";
        $t .= self::lr(($final ? 'RECEIPT ' : 'CHECK ').$c['check_no'], date('d/m/y H:i')).self::lr(($c['table_code'] ? 'Table '.$c['table_code'] : ucfirst(str_replace('_', ' ', $c['order_type']))).' Cov '.$c['covers'], 'Srv '.$c['server']);
        if ($c['room_num']) { $t .= self::lr('Room '.$c['room_num'].' '.$c['guest_name'], ''); }
        $t .= str_repeat('-', self::W)."\n";
        foreach ($c['lines'] as $l) { if ($l['voided']) { continue; } $t .= self::lr($l['qty'] * 1 .' '.$l['name'], $l['comp'] ? 'COMP' : $m($l['line_total'])); foreach ((array) $l['modifiers'] as $mo) { $t .= '   + '.$mo['name'].($mo['price'] > 0 ? ' '.$m($mo['price'] * $mo['qty']) : '')."\n"; } if ($l['line_discount'] > 0) { $t .= self::lr('   discount', '-'.$m($l['line_discount'])); } }
        $t .= str_repeat('-', self::W)."\n".self::lr('Subtotal', $m($c['subtotal'])); if ($c['discount_total'] > 0) { $t .= self::lr('Discount'.($c['discount_reason'] ? ' ('.$c['discount_reason'].')' : ''), '-'.$m($c['discount_total'])); } if ($c['service_charge'] > 0) { $t .= self::lr('Service charge '.$c['service_charge_pct'].'%', $m($c['service_charge'])); } $t .= self::lr('TOTAL', $m($c['total'])).self::lr('  incl. tax', $m($c['tax_total']));
        if ($final) { foreach ($c['payments'] as $p) { $t .= self::lr(ucfirst(str_replace('_', ' ', $p['method'])).($p['reference'] ? ' '.$p['reference'] : ''), $m($p['amount'])); if ($p['tip'] > 0) { $t .= self::lr('  tip', $m($p['tip'])); } } if ($c['change_due'] > 0) { $t .= self::lr('Change', $m($c['change_due'])); } }
        $t .= str_repeat('-', self::W)."\n".($c['receipt_footer'] ? self::c($c['receipt_footer']) : '').($final ? '' : self::c('Not a receipt — please settle at the desk'));
        return $t;
    }
    public static function kot(array $c, $kot, array $lines, array $st) { $t = self::c('** '.$st['name'].' **').self::lr('KOT '.$kot, date('H:i')).self::lr($c['check_no'], ($c['table_code'] ? 'Table '.$c['table_code'] : ucfirst(str_replace('_', ' ', $c['order_type']))).($c['room_num'] ? ' Rm '.$c['room_num'] : '')).self::lr('Srv '.$c['server'], 'Cov '.$c['covers']).str_repeat('=', self::W)."\n".(!empty($c['allergy_note']) ? "\x1b!\x10!! ".$c['allergy_note']." !!\x1b!\x00\n" : ''); $course = null; foreach ($lines as $l) { if ($l['course'] !== $course) { $course = $l['course']; if ($course) { $t .= '-- COURSE '.$course." --\n"; } } $t .= "\x1b!\x10".$l['qty'] * 1 .' x '.$l['name']."\x1b!\x00\n"; foreach ((array) (is_array($l['modifiers']) ? $l['modifiers'] : json_decode($l['modifiers'], true)) as $mo) { $t .= '     > '.$mo['name']."\n"; } if ($l['note']) { $t .= '     ! '.$l['note']."\n"; } if ($l['seat'] > 1) { $t .= '     seat '.$l['seat']."\n"; } } return self::raw($st['printer_host'], $st['printer_port'], self::esc($t)); }
    public static function cashDrawer($host, $port) { return self::raw($host, $port, "\x1bp\x00\x19\xfa"); }
}

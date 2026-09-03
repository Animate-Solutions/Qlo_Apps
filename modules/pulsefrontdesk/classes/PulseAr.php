<?php
/** Accounts receivable: company statements, ageing, invoice numbering. */
class PulseAr
{
    public static function statement($idCompany, $from, $to)
    {
        $c = new PulseCompany((int) $idCompany); $f = $c->folio();
        $opening = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(IF(is_payment=1,-amount_tax_incl,amount_tax_incl)),0) FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND id_pulse_folio='.(int) $f->id.' AND business_date<"'.pSQL($from).'"');
        $lines = Db::getInstance()->executeS('SELECT l.*, b.room_num, CONCAT(cu.firstname," ",cu.lastname) guest FROM `'._DB_PREFIX_.'pulse_folio_line` l LEFT JOIN `'._DB_PREFIX_.'pulse_folio` gf ON gf.folio_no=l.source_ref LEFT JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=gf.id_htl_booking LEFT JOIN `'._DB_PREFIX_.'customer` cu ON cu.id_customer=gf.id_customer WHERE l.voided=0 AND l.id_pulse_folio='.(int) $f->id.' AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY l.business_date, l.date_add');
        $run = $opening; foreach ($lines as &$l) { $run += $l['is_payment'] ? -$l['amount_tax_incl'] : $l['amount_tax_incl']; $l['running'] = round($run, 2); }
        return array('company' => $c, 'folio' => $f, 'opening' => round($opening, 2), 'lines' => $lines, 'closing' => round($run, 2), 'from' => $from, 'to' => $to, 'statement_no' => 'ST'.date('ym').str_pad($idCompany, 4, '0', STR_PAD_LEFT).'-'.date('d', strtotime($to)));
    }

    /** Ageing of open city-ledger charges by days since business date (FIFO against payments). */
    public static function ageing()
    {
        $out = array(); $today = strtotime(PulseCoreService::businessDate());
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_company` WHERE ledger_balance<>0') as $c) {
            $f = (new PulseCompany((int) $c['id_pulse_company']))->folio();
            $paid = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(amount_tax_incl),0) FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND is_payment=1 AND id_pulse_folio='.(int) $f->id);
            $b = array('company' => $c['name'], 'credit_limit' => $c['credit_limit'], 'current' => 0, 'd31_60' => 0, 'd61_90' => 0, 'd90_plus' => 0, 'total' => 0);
            foreach (Db::getInstance()->executeS('SELECT amount_tax_incl, business_date FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND is_payment=0 AND id_pulse_folio='.(int) $f->id.' ORDER BY business_date') as $l) {
                $amt = (float) $l['amount_tax_incl']; $apply = min($amt, $paid); $paid -= $apply; $open = $amt - $apply; if ($open <= 0.009) { continue; }
                $age = (int) (($today - strtotime($l['business_date'])) / 86400);
                $k = $age <= 30 ? 'current' : ($age <= 60 ? 'd31_60' : ($age <= 90 ? 'd61_90' : 'd90_plus')); $b[$k] += $open; $b['total'] += $open;
            }
            foreach (array('current', 'd31_60', 'd61_90', 'd90_plus', 'total') as $k) { $b[$k] = round($b[$k], 2); }
            $out[] = $b;
        }
        return $out;
    }
}

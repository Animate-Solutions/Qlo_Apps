<?php
/**
 * Payslips: retrieval, year-to-date columns, the printable/PDF document, the tokenised download and the
 * email that carries the link.
 *
 * A payslip is confidential. Three things follow from that and none of them are optional:
 *  - the URL carries a 48-character token derived from the shop cookie key and a nonce, so it cannot be
 *    guessed and cannot be enumerated from an employee id;
 *  - the token alone is not enough — the page asks for the employee's payslip PIN before it renders
 *    anything, and the PIN is stored hashed with _COOKIE_KEY_ exactly as POS PINs are;
 *  - a token expires (90 days by default) and every view is stamped, so an employee can be told when
 *    their payslip was last opened.
 */
class PulsePrPayslip
{
    public static function get($id)
    {
        $p = Db::getInstance()->getRow('SELECT p.*, r.run_no, r.run_type, r.pay_date, r.status run_status, r.currency, r.country FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run WHERE p.id_pulse_pr_payslip='.(int) $id);
        if ($p) { $p['lines'] = self::lines((int) $id); }
        return $p;
    }

    public static function byToken($token)
    {
        if (!preg_match('/^[a-f0-9]{48}$/', (string) $token)) { return null; }
        $p = Db::getInstance()->getRow('SELECT p.*, r.run_no, r.run_type, r.pay_date, r.status run_status, r.currency, r.country FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run WHERE p.token="'.pSQL($token).'"');
        if (!$p) { return null; }
        $days = (int) PulsePrService::cfg('PAYSLIP_TOKEN_DAYS', 90);
        if ($days > 0 && strtotime($p['date_add']) < strtotime('-'.$days.' day')) { return null; }
        if (!in_array($p['run_status'], array('approved', 'paid', 'posted'))) { return null; }
        return $p;
    }

    public static function lines($id)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_payslip_line` WHERE id_pulse_pr_payslip='.(int) $id.' ORDER BY sequence, element_code');
    }

    /** Every payslip an employee can see: only from runs that have actually been approved. */
    public static function forEmployee($idEmployee, $limit = 24)
    {
        return Db::getInstance()->executeS('SELECT p.id_pulse_pr_payslip, p.period, p.gross, p.total_deductions, p.net_pay, p.token, p.date_add, r.run_no, r.pay_date, r.status run_status
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.id_pulse_pr_employee='.(int) $idEmployee.' AND r.status IN ("approved","paid","posted") ORDER BY p.period DESC LIMIT '.(int) $limit);
    }

    /** Year-to-date, per element, for the payslip's YTD column and the employee YTD report. */
    public static function ytdLines($idEmployee, $year)
    {
        return Db::getInstance()->executeS('SELECT l.element_code, l.element_name, l.type, ROUND(SUM(l.amount),2) amount
            FROM `'._DB_PREFIX_.'pulse_pr_payslip_line` l INNER JOIN `'._DB_PREFIX_.'pulse_pr_payslip` p ON p.id_pulse_pr_payslip=l.id_pulse_pr_payslip INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.id_pulse_pr_employee='.(int) $idEmployee.' AND p.period LIKE "'.pSQL((string) (int) $year).'-%" AND r.status IN ("approved","paid","posted")
            GROUP BY l.element_code, l.element_name, l.type ORDER BY l.type, l.element_code');
    }

    public static function ytdSummary($idEmployee, $year)
    {
        $r = Db::getInstance()->getRow('SELECT COUNT(*) periods, ROUND(SUM(p.gross),2) gross, ROUND(SUM(p.taxable_gross),2) taxable, ROUND(SUM(p.paye),2) paye, ROUND(SUM(p.pension_ee),2) pension_ee, ROUND(SUM(p.pension_er),2) pension_er, ROUND(SUM(p.nhf),2) nhf, ROUND(SUM(p.total_deductions),2) deductions, ROUND(SUM(p.net_pay),2) net
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.id_pulse_pr_employee='.(int) $idEmployee.' AND p.period LIKE "'.pSQL((string) (int) $year).'-%" AND r.status IN ("approved","paid","posted")');
        return $r ? $r : array('periods' => 0, 'gross' => 0, 'taxable' => 0, 'paye' => 0, 'pension_ee' => 0, 'pension_er' => 0, 'nhf' => 0, 'deductions' => 0, 'net' => 0);
    }

    /** Everything the payslip document needs, in one call, so the front and back office render identically. */
    public static function document($id)
    {
        $p = self::get($id);
        if (!$p) { return null; }
        $emp = PulsePrService::employee((int) $p['id_pulse_pr_employee']);
        $year = (int) Tools::substr($p['period'], 0, 4);
        $earnings = array(); $deductions = array(); $employer = array(); $information = array();
        foreach ($p['lines'] as $l) {
            if ($l['type'] === 'earning') { $earnings[] = $l; }
            elseif ($l['type'] === 'deduction') { $deductions[] = $l; }
            elseif ($l['type'] === 'employer') { $employer[] = $l; }
            else { $information[] = $l; }
        }
        $ytdByCode = array();
        foreach (self::ytdLines((int) $p['id_pulse_pr_employee'], $year) as $y) { $ytdByCode[$y['element_code']] = (float) $y['amount']; }
        return array(
            'payslip' => $p, 'employee' => $emp, 'earnings' => $earnings, 'deductions' => $deductions, 'employer' => $employer, 'information' => $information,
            'ytd' => self::ytdSummary((int) $p['id_pulse_pr_employee'], $year), 'ytd_by_code' => $ytdByCode, 'year' => $year,
            'hotel' => Configuration::get('PS_SHOP_NAME'), 'address' => Configuration::get('PULSE_ACC_HOTEL_ADDRESS'),
            'currency' => $p['currency'] ? $p['currency'] : PulsePrService::currency(),
            'tronc' => PulsePrTronc::statement((int) $p['id_pulse_pr_employee'], $p['period'], $p['period']),
            'loans' => PulsePrLoan::balanceFor((int) $p['id_pulse_pr_employee']),
        );
    }

    /** Stamp a view so the employee (and an auditor) can see when a payslip was actually opened. */
    public static function stampViewed($id)
    {
        Db::getInstance()->update('pulse_pr_payslip', array('viewed_at' => date('Y-m-d H:i:s')), 'id_pulse_pr_payslip='.(int) $id);
        return true;
    }

    /** The public, tokenised URL. Never build a payslip link any other way. */
    public static function url($token)
    {
        return Context::getContext()->link->getModuleLink('pulsepayroll', 'payslip', array('t' => $token), true);
    }

    /**
     * Email the payslip link. Routed through Pulse Comms when Front Desk is installed so the send lands
     * in the suite's communications log; falls back to the module's own mail template when it is not.
     * The email never carries the figures — only the link and the reminder that a PIN is needed.
     */
    public static function email($id)
    {
        $p = self::get($id);
        if (!$p) { throw new PrestaShopException('Unknown payslip'); }
        if (!in_array($p['run_status'], array('approved', 'paid', 'posted'))) { throw new PrestaShopException('Payslips are only sent from an approved run'); }
        $emp = PulsePrService::employee((int) $p['id_pulse_pr_employee']);
        if (!$emp || !$emp['email'] || !Validate::isEmail($emp['email'])) { throw new PrestaShopException('No valid email address on file for '.$p['employee_name']); }
        $url = self::url($p['token']);
        $hotel = Configuration::get('PS_SHOP_NAME');
        $subject = 'Your payslip for '.$p['period'].' — '.$hotel;
        $text = 'Dear '.$emp['firstname'].', your payslip for '.$p['period'].' is ready. Open it here: '.$url.' — you will be asked for your payslip PIN. Do not forward this link.';
        $sent = false;
        if (PulsePrService::comms()) {
            $sent = (bool) PulseComms::sendRaw($emp['email'], $emp['phone'], 'payslip', array('name' => $emp['firstname'], 'period' => $p['period'], 'url' => $url, 'html' => self::emailHtml($p, $emp, $url)));
        }
        if (!$sent) {
            $sent = (bool) Mail::Send((int) Context::getContext()->language->id, 'pulse_payslip', $subject,
                array('{firstname}' => $emp['firstname'], '{lastname}' => $emp['lastname'], '{period}' => $p['period'], '{hotel}' => $hotel, '{url}' => $url, '{message}' => $text),
                $emp['email'], trim($emp['firstname'].' '.$emp['lastname']), null, null, null, null, dirname(__FILE__).'/../mails/');
        }
        if ($sent) {
            Db::getInstance()->update('pulse_pr_payslip', array('emailed_at' => date('Y-m-d H:i:s')), 'id_pulse_pr_payslip='.(int) $id);
            self::logComms($emp, $p, 'sent');
        } else {
            self::logComms($emp, $p, 'failed');
        }
        return $sent;
    }

    protected static function emailHtml(array $p, array $emp, $url)
    {
        return '<p>Dear '.htmlspecialchars($emp['firstname']).',</p><p>Your payslip for <strong>'.htmlspecialchars($p['period']).'</strong> is ready.</p>'
            .'<p><a href="'.htmlspecialchars($url).'">Open your payslip</a></p>'
            .'<p>You will be asked for your payslip PIN before it opens. This link is personal to you — please do not forward it.</p>';
    }

    /** Mirror the send into the suite communications log when the table is there, so nothing is invisible. */
    protected static function logComms(array $emp, array $p, $status)
    {
        if (!PulsePrService::tableExists('pulse_comms_log')) { return false; }
        return Db::getInstance()->insert('pulse_comms_log', array(
            'channel' => 'email', 'template' => 'payslip', 'to_addr' => pSQL($emp['email']), 'status' => pSQL($status),
            'error' => $status === 'failed' ? 'Mail::Send failed' : null, 'date_add' => date('Y-m-d H:i:s'),
            'date_sent' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
        ), true);
    }

    /** Email every payslip in an approved run that has not gone out yet. Safe to run repeatedly. */
    public static function emailRun($idRun, $limit = 200)
    {
        $run = PulsePrRun::get($idRun);
        if (!$run) { throw new PrestaShopException('Unknown run'); }
        if (!in_array($run['status'], array('approved', 'paid', 'posted'))) { throw new PrestaShopException('Run '.$run['run_no'].' is '.$run['status'].' — payslips only go out from an approved run'); }
        $sent = 0; $failed = 0;
        foreach (Db::getInstance()->executeS('SELECT id_pulse_pr_payslip FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $idRun.' AND emailed_at IS NULL LIMIT '.(int) $limit) as $r) {
            try { if (self::email((int) $r['id_pulse_pr_payslip'])) { $sent++; } else { $failed++; } } catch (Exception $e) { $failed++; }
        }
        PulsePrService::log($idRun, 'payslip_email_run', 'run', array('sent' => $sent, 'failed' => $failed), $idRun);
        return array('sent' => $sent, 'failed' => $failed);
    }

    /** Re-issue a token — used when an employee says a link has leaked. The old link stops working at once. */
    public static function reissueToken($id)
    {
        $p = self::get($id);
        if (!$p) { throw new PrestaShopException('Unknown payslip'); }
        $token = PulsePrRun::payslipToken((int) $p['id_pulse_pr_run'], (int) $p['id_pulse_pr_employee']);
        Db::getInstance()->update('pulse_pr_payslip', array('token' => pSQL($token), 'emailed_at' => null, 'viewed_at' => null), 'id_pulse_pr_payslip='.(int) $id, 0, true);
        PulsePrService::log((int) $p['id_pulse_pr_run'], 'payslip_reissue', 'payslip', null, (int) $id);
        return $token;
    }
}

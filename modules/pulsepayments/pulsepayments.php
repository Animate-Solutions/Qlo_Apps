<?php
/** Pulse Payments — gateways, pre-authorisations, payment links, bank POS terminals, refunds and settlement reconciliation. Benchmarks: eZee iPay, OPERA Payment Interface (OPI), Shift4/Adyen tokenised pre-auth, Paystack, Flutterwave, Interswitch WebPAY. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulsePayments extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulsePayments' => 'Payments', 'AdminPulsePayTransactions' => 'Transactions', 'AdminPulsePayLinks' => 'Payment Links', 'AdminPulsePayRecon' => 'Reconciliation', 'AdminPulsePaySettings' => 'Payment Settings');
    protected $hooks = array('displayBackOfficeHeader', 'moduleRoutes', 'actionPulseBeforeCheckOut', 'actionPulseNightAuditClosed', 'actionPulseCheckOut', 'actionPulsePaymentAuthorized', 'actionPulsePaymentCaptured', 'actionPulsePaymentRefunded', 'actionPulsePaymentFailed', 'actionPulsePayTerminalRequest');

    public function __construct()
    {
        $this->name = 'pulsepayments'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Payments'); $this->description = $this->l('Card, transfer and terminal payments: gateway adapters (Paystack, Flutterwave, Interswitch, manual bank POS), pre-authorisations, payment links, webhooks, refunds, disputes and settlement reconciliation.');
        $this->confirmUninstall = $this->l('Uninstall Payments? Transactions, links, settlements and gateway credentials will be dropped. Folio postings already made stay where they are.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 130;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach ($this->defaults() as $k => $v) { Configuration::updateValue($k, $v); }
        $this->addChargeCodes();
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array_keys($this->defaults()) as $k) { Configuration::deleteByName($k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    /** Nigerian defaults: naira, VAT 7.5% on any surcharge, 7-day card holds, 10-minute terminal timeout. */
    protected function defaults()
    {
        return array(
            'PULSE_PAY_DEFAULT_GATEWAY' => 'paystack', 'PULSE_PAY_SURCHARGE_PCT' => 0, 'PULSE_PAY_SURCHARGE_VAT_PCT' => 7.5, 'PULSE_PAY_SURCHARGE_CHANNELS' => 'web,link,portal',
            'PULSE_PAY_PREAUTH_DAYS' => 7, 'PULSE_PAY_PREAUTH_BUFFER_PCT' => 20, 'PULSE_PAY_PREAUTH_WARN_HOURS' => 24, 'PULSE_PAY_AUTOCAPTURE' => 'warn',
            'PULSE_PAY_LINK_HOURS' => 72, 'PULSE_PAY_TERMINAL_TIMEOUT_MIN' => 10, 'PULSE_PAY_FEE_TOLERANCE' => 1,
            'PULSE_PAY_BANK_NAME' => '', 'PULSE_PAY_BANK_ACCOUNT_NAME' => '', 'PULSE_PAY_BANK_ACCOUNT' => '', 'PULSE_PAY_FALLBACK_EMAIL' => '',
            'PULSE_PAY_CRON_TOKEN' => Tools::passwdGen(32),
        );
    }

    /** SURCH exists only when Front Desk is installed; adding it is optional so we install standalone. */
    protected function addChargeCodes()
    {
        if (!Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_charge_code"')) { return false; }
        Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_charge_code` (`code`,`name`,`department`,`default_price`,`tax_rate`,`is_payment`) VALUES ("SURCH","Card processing surcharge","misc",0,7.5,0)');
        Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_charge_code` (`code`,`name`,`department`,`default_price`,`tax_rate`,`is_payment`) VALUES ("MOMO","Mobile money","payment",0,0,1)');
        return true;
    }

    protected function runSql($f)
    {
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/'.$f.'.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) { return false; } }
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulsePayments')); }
    public function hookDisplayBackOfficeHeader() { if (strpos($this->context->controller->controller_name, 'AdminPulsePay') === 0) { $this->context->controller->addCSS($this->_path.'views/css/payments.css'); $this->context->controller->addJS($this->_path.'views/js/payments.js'); } }
    public function hookModuleRoutes()
    {
        return array(
            'pulsepayments-api' => array('controller' => 'api', 'rule' => 'pulse/api/payments{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsepayments-pay' => array('controller' => 'pay', 'rule' => 'pulse/pay', 'keywords' => array(), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsepayments-hook' => array('controller' => 'webhook', 'rule' => 'pulse/pay/hook{/:gateway}', 'keywords' => array('gateway' => array('regexp' => '[a-z_]+', 'param' => 'gateway')), 'params' => array('fc' => 'module', 'module' => $this->name)),
        );
    }

    /* ---------------- static bridge API (PulsePaymentBridge probes method_exists on this class) ---------------- */

    /** Pre-authorise a card at check-in. Returns array('reference'=>…) — falls back to a recorded manual hold. */
    public static function authorize($idCustomer, $amount, $token, array $context = array()) { return PulsePayService::authorize($idCustomer, $amount, $token, $context); }

    /**
     * Capture against a hold at check-out. Returns array('ok'=>bool,'error'=>…).
     * PulsePaymentBridge writes the folio CARD line itself, so this path must not post a second one — and it
     * must only report success once the money really moved, or the desk records a payment the terminal never approved.
     */
    public static function capture($reference, $amount)
    {
        $r = PulsePayService::capture($reference, $amount, array('no_post' => true));
        if (!empty($r['ok']) && !in_array(isset($r['state']) ? $r['state'] : '', array('captured', 'settled'))) {
            return array('ok' => false, 'error' => 'Capture is awaiting confirmation — key the terminal RRN into Payments, then settle', 'reference' => isset($r['reference']) ? $r['reference'] : $reference);
        }
        return $r;
    }

    /** Tokenised pay-page URL for a deposit, self check-out or invoice. */
    public static function paymentLink($amount, array $context = array()) { return PulsePayService::paymentLink($amount, $context); }

    /** Release an uncaptured hold (early departure, cancelled stay). */
    public static function voidAuthorization($reference, $reason = '') { return PulsePayService::voidTx($reference, $reason); }

    /** Ask a physical bank terminal for an amount; poll with PulsePayments::terminalResult(). */
    public static function terminalRequest(array $d) { return PulsePayTerminal::request($d); }
    public static function terminalResult($reference) { return PulsePayTerminal::poll($reference); }

    /* ---------------- hooks ---------------- */

    /**
     * Check-out is about to close the folio: settle the balance from an open pre-auth when auto-capture is on,
     * otherwise leave a loud trace so the cashier deals with the hold before the guest walks.
     */
    public function hookActionPulseBeforeCheckOut($p)
    {
        if (empty($p['booking']['id'])) { return; }
        $pre = PulsePayService::preauthForBooking((int) $p['booking']['id']);
        if (!$pre) { return; }
        $folio = isset($p['folio']) ? $p['folio'] : null;
        $balance = $folio ? round((float) $folio->balance, 2) : 0;
        $mode = Configuration::get('PULSE_PAY_AUTOCAPTURE');
        if ($mode === 'checkout' && $balance > 0.009) {
            $take = min($balance, round((float) $pre['amount'] - (float) $pre['amount_captured'], 2));
            if ($take > 0.009) { $r = PulsePayService::capture($pre['reference'], $take); if (empty($r['ok']) && class_exists('PulseTrace')) { PulseTrace::add('alert', 'Auto-capture of '.number_format($take, 2).' on hold '.$pre['reference'].' failed: '.(isset($r['error']) ? $r['error'] : 'unknown'), date('Y-m-d H:i:s'), (int) $p['booking']['id'], (int) $p['id_room'], null, 'payments'); } }
        } elseif (class_exists('PulseTrace')) {
            PulseTrace::add('alert', 'Open card hold '.$pre['reference'].' for '.number_format((float) $pre['amount'] - (float) $pre['amount_captured'], 2).' — capture or release it before departure', date('Y-m-d H:i:s'), (int) $p['booking']['id'], (int) $p['id_room'], null, 'payments');
        }
    }

    /** Guest has gone: any hold still open is now money nobody is watching. */
    public function hookActionPulseCheckOut($p)
    {
        if (empty($p['booking']['id']) || !empty($p['room_move'])) { return; }
        $pre = PulsePayService::preauthForBooking((int) $p['booking']['id']);
        if ($pre && Configuration::get('PULSE_PAY_AUTOCAPTURE') === 'release') { PulsePayService::voidTx($pre['reference'], 'Released at check-out'); }
    }

    /** Night audit closed the day: freeze the settlement summary for that business date. */
    public function hookActionPulseNightAuditClosed($p)
    {
        $d = isset($p['business_date']) ? $p['business_date'] : PulsePayService::bd();
        PulsePayService::rollDaily($d);
        PulsePayService::expirePreauths();
    }

    public function hookActionPulsePaymentAuthorized($p) {}
    public function hookActionPulsePaymentCaptured($p) {}
    public function hookActionPulsePaymentRefunded($p) {}
    public function hookActionPulsePaymentFailed($p) {}
    public function hookActionPulsePayTerminalRequest($p) {}
}

<?php
/** /pulse/api/payments/{resource}/{id} — POS terminal charges, portal deposits, desk captures and link status. */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulsepayments/classes/autoload.php';

class PulsePaymentsApiModuleFrontController extends PulseApiController
{
    protected $resources = array(
        'ping' => 'ping', 'gateways' => 'gatewaysList', 'link_create' => 'linkCreate', 'link_status' => 'linkStatus',
        'terminal_request' => 'terminalRequest', 'terminal_claim' => 'terminalClaim', 'terminal_result' => 'terminalResult', 'terminal_poll' => 'terminalPoll',
        'verify' => 'verifyTx', 'preauth' => 'preauth', 'capture' => 'capture', 'refund' => 'refund', 'transaction' => 'transaction',
    );

    protected function ping() { return array('module' => 'pulsepayments', 'version' => PulsePayments::VERSION, 'business_date' => PulsePayService::bd(), 'gateways' => count(PulsePayService::gateways(true))); }

    /** Public-safe: codes, labels and capabilities only — never a key. */
    protected function gatewaysList()
    {
        $out = array();
        foreach (PulsePayService::gateways(true) as $g) { $out[] = array('code' => $g['code'], 'name' => $g['name'], 'test_mode' => (int) $g['test_mode'], 'currency' => $g['currency'], 'channels' => explode(',', $g['channels']), 'capabilities' => explode(',', $g['capabilities'])); }
        return $out;
    }

    /** Portal / desk: mint a payment link. */
    protected function linkCreate($id, $body)
    {
        $this->requireScope('portal');
        $l = PulsePayLink::create(array(
            'amount' => isset($body['amount']) ? $body['amount'] : 0, 'purpose' => isset($body['purpose']) ? $body['purpose'] : 'folio',
            'id_htl_booking' => isset($body['id_htl_booking']) ? $body['id_htl_booking'] : null, 'id_pulse_folio' => isset($body['id_pulse_folio']) ? $body['id_pulse_folio'] : null,
            'id_pulse_pos_check' => isset($body['id_pulse_pos_check']) ? $body['id_pulse_pos_check'] : null, 'id_customer' => isset($body['id_customer']) ? $body['id_customer'] : null,
            'customer_email' => isset($body['email']) ? $body['email'] : '', 'customer_phone' => isset($body['phone']) ? $body['phone'] : '',
            'title' => isset($body['title']) ? $body['title'] : '', 'expires_hours' => isset($body['expires_hours']) ? $body['expires_hours'] : 0,
            'max_uses' => isset($body['max_uses']) ? $body['max_uses'] : 1, 'amount_locked' => isset($body['amount_locked']) ? $body['amount_locked'] : 1,
        ));
        if (!empty($body['send'])) { PulsePayLink::send($l); }
        return array('short_code' => $l['short_code'], 'url' => PulsePayLink::url($l), 'short_url' => PulsePayLink::shortUrl($l), 'expires_at' => $l['expires_at'], 'amount' => (float) $l['amount']);
    }

    protected function linkStatus($id, $body) { $t = isset($body['token']) ? $body['token'] : Tools::getValue('token_ref'); return PulsePayLink::status($t); }

    /** POS or desk pushes an amount to a physical bank terminal. */
    protected function terminalRequest($id, $body)
    {
        $this->requireScope('pos');
        return PulsePayTerminal::request(array(
            'amount' => isset($body['amount']) ? $body['amount'] : 0, 'terminal' => isset($body['terminal']) ? $body['terminal'] : null, 'station' => isset($body['station']) ? $body['station'] : '',
            'id_pulse_pos_check' => isset($body['id_pulse_pos_check']) ? $body['id_pulse_pos_check'] : null, 'id_htl_booking' => isset($body['id_htl_booking']) ? $body['id_htl_booking'] : null,
            'id_pulse_folio' => isset($body['id_pulse_folio']) ? $body['id_pulse_folio'] : null, 'auto_settle' => !empty($body['auto_settle']), 'source' => isset($body['source']) ? $body['source'] : 'pos',
            'description' => isset($body['description']) ? $body['description'] : 'Bank POS terminal charge',
        ));
    }

    /** A terminal app takes the next job for its device. */
    protected function terminalClaim($id, $body) { $this->requireScope('pos'); $j = PulsePayTerminal::claim(isset($body['terminal']) ? $body['terminal'] : Tools::getValue('terminal')); return $j ? $j : array('job' => null); }

    /** The terminal app (or a cashier via the desk) posts the slip back. */
    protected function terminalResult($id, $body) { $this->requireScope('pos'); return PulsePayTerminal::result(isset($body['reference']) ? $body['reference'] : Tools::getValue('reference'), $body); }

    protected function terminalPoll($id, $body) { $this->requireScope('pos'); return PulsePayTerminal::poll(isset($body['reference']) ? $body['reference'] : Tools::getValue('reference')); }

    protected function verifyTx($id, $body) { $this->requireScope('desk'); return PulsePayService::verify(isset($body['reference']) ? $body['reference'] : Tools::getValue('reference')); }
    protected function transaction($id, $body) { $this->requireScope('desk'); $t = PulsePayService::tx($id ? $id : (isset($body['reference']) ? $body['reference'] : Tools::getValue('reference'))); if (!$t) { throw new PrestaShopException('Not found', 404); } unset($t['auth_token'], $t['raw']); return $t; }

    protected function preauth($id, $body)
    {
        $this->requireScope('desk');
        return PulsePayService::authorize((int) (isset($body['id_customer']) ? $body['id_customer'] : $id), isset($body['amount']) ? $body['amount'] : 0, isset($body['token']) ? $body['token'] : null, array('id_htl_booking' => isset($body['id_htl_booking']) ? $body['id_htl_booking'] : null, 'channel' => 'desk', 'description' => isset($body['description']) ? $body['description'] : 'Pre-authorisation'));
    }

    protected function capture($id, $body)
    {
        $this->requireScope('desk');
        $r = PulsePayService::capture(isset($body['reference']) ? $body['reference'] : Tools::getValue('reference'), isset($body['amount']) ? $body['amount'] : 0, isset($body['rrn']) ? array('rrn' => $body['rrn'], 'auth_code' => isset($body['auth_code']) ? $body['auth_code'] : '') : array());
        if (empty($r['ok'])) { throw new PrestaShopException(isset($r['error']) ? $r['error'] : 'Capture failed'); }
        return $r;
    }

    protected function refund($id, $body)
    {
        $this->requireScope('desk');
        return PulsePayService::refund(isset($body['reference']) ? $body['reference'] : Tools::getValue('reference'), isset($body['amount']) ? $body['amount'] : 0, isset($body['reason']) ? $body['reason'] : '', isset($body['approver']) ? $body['approver'] : null);
    }
}

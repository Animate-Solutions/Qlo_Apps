<?php
/** /pulse/pay/hook/<gateway> — signed gateway callbacks. Always answers fast; the guest's browser is irrelevant here. */
require_once _PS_MODULE_DIR_.'pulsepayments/classes/autoload.php';

class PulsePaymentsWebhookModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function init() { $this->ajax = true; parent::init(); }

    public function postProcess()
    {
        $gateway = preg_replace('/[^a-z_]/', '', Tools::getValue('gateway'));
        $raw = Tools::file_get_contents('php://input');
        if ($raw === false) { $raw = ''; }
        // Which property, before the gateway row is even read — the adapter, its keys and its webhook
        // secret are all per-property. A callback we cannot place is refused with a 400 the gateway will
        // retry, rather than a 200 that would file the payment against a guessed hotel or against none.
        if (!PulsePayService::enterHotelForWebhook($gateway, $raw)) {
            // Not audited: the audit trail is per-property too, so there is nowhere to write this yet.
            // A 400 is deliberate — the gateway retries it, and a retry to the per-property callback URL
            // lands correctly, where a 200 would have dropped a real payment in silence.
            $this->answer(400, 'Which property is this for? Register the per-property callback URL shown in Pulse Payments, Settings.');
        }
        try {
            list($status, $message) = PulsePayWebhook::handle($gateway, $raw, PulsePayWebhook::incomingHeaders(), Tools::getRemoteAddr());
        } catch (Exception $e) {
            // Never hand a gateway a 500 it will retry forever over a bad row; record it and move on.
            PulseCoreService::audit('pulsepayments', 'webhook_error', array('gateway' => $gateway, 'error' => $e->getMessage()));
            $status = 200; $message = 'Recorded with error: '.$e->getMessage();
        }
        $this->answer((int) $status, $message);
    }

    protected function answer($status, $message)
    {
        header('Content-Type: application/json');
        http_response_code((int) $status);
        die(json_encode(array('ok' => $status === 200, 'message' => $message)));
    }

    public function initContent() { $this->postProcess(); }
}

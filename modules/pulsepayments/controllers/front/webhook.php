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
        try {
            list($status, $message) = PulsePayWebhook::handle($gateway, $raw, PulsePayWebhook::incomingHeaders(), Tools::getRemoteAddr());
        } catch (Exception $e) {
            // Never hand a gateway a 500 it will retry forever over a bad row; record it and move on.
            PulseCoreService::audit('pulsepayments', 'webhook_error', array('gateway' => $gateway, 'error' => $e->getMessage()));
            $status = 200; $message = 'Recorded with error: '.$e->getMessage();
        }
        header('Content-Type: application/json');
        http_response_code((int) $status);
        die(json_encode(array('ok' => $status === 200, 'message' => $message)));
    }

    public function initContent() { $this->postProcess(); }
}

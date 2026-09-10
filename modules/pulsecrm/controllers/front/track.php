<?php
/**
 * /pulse/crm/open, /pulse/crm/click, /pulse/crm/unsub — campaign tracking.
 * The open pixel and the click redirect answer even when the token is unknown (a forwarded email must
 * not show a broken image or a dead link), and the click target is HMAC-signed so the redirect can
 * never be turned into an open one.
 */
require_once _PS_MODULE_DIR_.'pulsecrm/classes/autoload.php';

class PulseCrmTrackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function init()
    {
        $action = preg_replace('/[^a-z]/', '', Tools::getValue('a', 'open'));
        $token = preg_replace('/[^a-f0-9]/', '', Tools::getValue('t'));
        if ($action === 'open') { PulseCrmCampaign::markOpen($token); $this->pixel(); }
        if ($action === 'click') {
            $url = base64_decode(Tools::getValue('u'), true);
            $ok = $url && PulseCrmService::verify($token.'|'.$url, Tools::getValue('s')) && preg_match('#^https?://#i', $url);
            PulseCrmCampaign::markClick($token);
            Tools::redirect($ok ? $url : PulseCrmService::baseUrl());
        }
        parent::init();
    }

    public function postProcess()
    {
        if (!Tools::isSubmit('submitUnsub')) { return; }
        $token = preg_replace('/[^a-f0-9]/', '', Tools::getValue('t'));
        $r = PulseCrmCampaign::unsubscribe($token, Tools::getValue('reason'));
        $this->context->smarty->assign(array('done' => (bool) $r, 'reason' => Tools::getValue('reason')));
    }

    public function initContent()
    {
        parent::initContent();
        $token = preg_replace('/[^a-f0-9]/', '', Tools::getValue('t'));
        $r = PulseCrmCampaign::byToken($token);
        $this->context->smarty->assign(array('r' => $r, 'token' => $token, 'hotel' => Configuration::get('PS_SHOP_NAME'),
            'already' => $r && $r['status'] === 'unsubscribed', 'action' => PulseCrmService::link('track', array('a' => 'unsub', 't' => $token))));
        $this->setTemplate('unsubscribe.tpl');
    }

    /** A 1x1 transparent GIF, uncacheable so a second read counts as a second open. */
    protected function pixel()
    {
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        die(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
    }
}

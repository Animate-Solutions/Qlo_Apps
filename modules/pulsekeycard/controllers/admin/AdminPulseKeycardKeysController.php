<?php
/** Keys register — every credential ever cut, filterable, with cancel / retry / blacklist from the list. */
class AdminPulseKeycardKeysController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Keys'); }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to change keys')); }
    }

    public function initContent()
    {
        parent::initContent();
        $f = array('status' => Tools::getValue('status', ''), 'type' => Tools::getValue('type', ''), 'q' => Tools::getValue('q', ''),
            'from' => Tools::getValue('from', date('Y-m-d', strtotime('-7 day'))), 'to' => Tools::getValue('to', PulseKcService::bd()));
        if ($id = (int) Tools::getValue('id_key')) {
            $k = PulseKcKey::get($id);
            $this->context->smarty->assign(array('k' => $k, 'audit' => $k ? PulseKcAudit::search(array('q' => $k['card_serial']), 100) : array(),
                'mobile' => $k ? Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_kc_mobile_key` WHERE id_pulse_kc_key='.(int) $id) : array(),
                'self_url' => self::$currentIndex.'&token='.$this->token));
            return $this->setTemplate('key.tpl');
        }
        $this->context->smarty->assign(array(
            'f' => $f, 'keys' => PulseKcKey::search($f, 400), 'mobile_keys' => PulseKcMobileKey::active(), 'jobs' => PulseKcService::jobs('queued,failed'),
            'types' => array('guest', 'duplicate', 'one_shot', 'staff', 'master', 'common', 'emergency'),
            'statuses' => array('pending', 'issued', 'cancelled', 'expired', 'failed', 'lost'),
            'self_url' => self::$currentIndex.'&token='.$this->token, 'business_date' => PulseKcService::bd(),
        ));
        $this->setTemplate('keys.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('cancelKey')) { $this->assertEdit(); PulseKcKey::cancel((int) Tools::getValue('id_key'), Tools::getValue('reason', 'Cancelled from the register')); $this->confirmations[] = $this->l('Key cancelled'); }
            if (Tools::isSubmit('markLost')) { $this->assertEdit(); $id = (int) Tools::getValue('id_key'); $k = PulseKcKey::get($id); PulseKcKey::cancel($id, Tools::getValue('reason', 'Reported lost'), false, 'lost'); if ($k && $k['card_serial']) { PulseKcKey::blacklistSerial($k['card_serial']); } $this->confirmations[] = $this->l('Card marked lost and blacklisted where the lock system supports it'); }
            if (Tools::isSubmit('retryKey')) { $this->assertEdit(); PulseKcKey::retryEncode((int) Tools::getValue('id_key'), (int) Tools::getValue('id_encoder') ?: null); $this->confirmations[] = $this->l('Key encoded'); }
            if (Tools::isSubmit('extendKey')) { $this->assertEdit(); PulseKcKey::extend((int) Tools::getValue('id_key'), Tools::getValue('valid_to')); $this->confirmations[] = $this->l('Key extended'); }
            if (Tools::isSubmit('runQueue')) { $this->assertEdit(); $r = PulseKcService::runQueue(); $this->confirmations[] = sprintf($this->l('Queue run: %d done, %d given up'), $r['done'], $r['failed']); }
            if (Tools::isSubmit('revokeMobile')) { $this->assertEdit(); PulseKcMobileKey::revoke((int) Tools::getValue('id_mobile'), Tools::getValue('reason', 'Revoked from the register')); $this->confirmations[] = $this->l('Mobile key revoked'); }
            if (Tools::isSubmit('resendMobile')) { $this->assertEdit(); $r = PulseKcMobileKey::deliver((int) Tools::getValue('id_mobile')); $this->confirmations[] = sprintf($this->l('Mobile key link re-sent (%s)'), $r ? $r['via'] : 'failed'); }
        } catch (PulseKcEncoderException $e) { $this->errors[] = $e->userMessage(); }
        catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}

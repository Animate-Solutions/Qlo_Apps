<?php
class AdminPulseNightAuditController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Night Audit'); }

    public function initContent()
    {
        parent::initContent();
        $bd = PulseCoreService::businessDate(); $na = new PulseNightAudit($bd);
        $this->context->smarty->assign(array(
            'business_date' => $bd, 'issues' => $na->preChecks(), 'in_house' => PulseFdService::inHouse(), 'no_shows' => PulseFdService::noShowCandidates($bd),
            'history' => Db::getInstance()->executeS('SELECT a.*, CONCAT(e.firstname," ",e.lastname) auditor FROM `'._DB_PREFIX_.'pulse_night_audit` a LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=a.id_employee ORDER BY business_date DESC LIMIT 30'),
            'self_url' => self::$currentIndex.'&token='.$this->token, 'cron_url' => Tools::getShopDomainSsl(true).__PS_BASE_URI__.'modules/pulsefrontdesk/cron/night_audit.php?token='.Configuration::get('PULSE_FD_CRON_TOKEN'),
        ));
        $this->setTemplate('audit.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('runAudit')) {
            try { $s = (new PulseNightAudit())->run((bool) Tools::getValue('force')); $this->confirmations[] = sprintf($this->l('Night audit closed. Occupied %d, room revenue %s'), $s['rooms_occupied'], Tools::displayPrice($s['room_revenue'])); }
            catch (Exception $e) { $this->errors[] = nl2br($e->getMessage()); }
        }
        return parent::postProcess();
    }
}

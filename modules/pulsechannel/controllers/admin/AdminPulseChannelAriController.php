<?php
/** ARI calendar grid (date x room type, editable) plus bulk rate/restriction updates and the parity check. */
class AdminPulseChannelAriController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('ARI Calendar'); }

    public function initContent()
    {
        parent::initContent();
        $channels = PulseChService::channels();
        $idChannel = (int) Tools::getValue('id_channel');
        if (!$idChannel) { foreach ($channels as $c) { if ($c['enabled']) { $idChannel = (int) $c['id_pulse_ch_channel']; break; } } }
        if (!$idChannel && $channels) { $idChannel = (int) $channels[0]['id_pulse_ch_channel']; }
        $from = Tools::getValue('from', PulseChService::businessDate());
        $days = min(60, max(7, (int) Tools::getValue('days', 30)));
        $grid = $idChannel ? PulseChAri::grid($idChannel, $from, $days, (int) Tools::getValue('id_rate_plan')) : array('dates' => array(), 'rows' => array());
        $this->context->smarty->assign(array(
            'channels' => $channels, 'id_channel' => $idChannel, 'rate_plans' => PulseChService::ratePlans(), 'id_rate_plan' => (int) Tools::getValue('id_rate_plan'),
            'room_types' => PulseChService::roomTypes(), 'dates' => $grid['dates'], 'rows' => $grid['rows'],
            'from' => $from, 'days' => $days, 'business_date' => PulseChService::businessDate(),
            'parity' => PulseChAri::parity(min(30, $days), $idChannel), 'tolerance' => Configuration::get('PULSE_CH_PARITY_TOLERANCE'),
            'queue' => PulseChAri::queue('pending,failed,poison', $idChannel, 25),
            'dows' => array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('ari.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveCells')) {
                $n = 0;
                foreach ((array) Tools::getValue('cell') as $idAri => $v) {
                    $idAri = (int) $idAri; if (!$idAri) { continue; }
                    $upd = array('date_upd' => date('Y-m-d H:i:s'), 'cell_hash' => '');
                    if (isset($v['rate']) && $v['rate'] !== '') { $upd['manual_rate'] = round((float) $v['rate'], 2); $upd['rate'] = $upd['manual_rate']; }
                    if (isset($v['min_los']) && $v['min_los'] !== '') { $upd['min_los'] = max(1, (int) $v['min_los']); $upd['manual_min_los'] = $upd['min_los']; }
                    if (isset($v['max_los']) && $v['max_los'] !== '') { $upd['max_los'] = max(0, (int) $v['max_los']); $upd['manual_max_los'] = $upd['max_los']; }
                    $upd['cta'] = !empty($v['cta']) ? 1 : 0; $upd['ctd'] = !empty($v['ctd']) ? 1 : 0;
                    $upd['manual_stop_sell'] = !empty($v['stop_sell']) ? 1 : 0; $upd['stop_sell'] = $upd['manual_stop_sell'];
                    Db::getInstance()->update('pulse_ch_ari', $upd, 'id_pulse_ch_ari='.$idAri); $n++;
                }
                foreach ((array) Tools::getValue('dirty_product') as $pid) { PulseChAri::markDirty((int) $pid, Tools::getValue('from', PulseChService::businessDate()), date('Y-m-d', strtotime(Tools::getValue('from', PulseChService::businessDate()).' +'.(int) Tools::getValue('days', 30).' day')), 'manual', (int) Tools::getValue('id_channel')); }
                $this->confirmations[] = sprintf($this->l('%d cell(s) saved and queued for push.'), $n);
            }
            if (Tools::isSubmit('bulkUpdate')) {
                $n = PulseChAri::bulkUpdate((array) Tools::getValue('b_channels'), array_filter((array) Tools::getValue('b_products')), array_filter((array) Tools::getValue('b_plans')),
                    Tools::getValue('b_from'), Tools::getValue('b_to'), array(
                        'rate' => Tools::getValue('b_rate'), 'min_los' => Tools::getValue('b_min_los'), 'max_los' => Tools::getValue('b_max_los'),
                        'cta' => Tools::getValue('b_cta') === '' ? null : Tools::getValue('b_cta'), 'ctd' => Tools::getValue('b_ctd') === '' ? null : Tools::getValue('b_ctd'),
                        'stop_sell' => Tools::getValue('b_stop') === '' ? null : Tools::getValue('b_stop'), 'release_days' => Tools::getValue('b_release'),
                        'clear_manual' => (int) Tools::getValue('b_clear'), 'dow' => array_filter((array) Tools::getValue('b_dow'), 'strlen'),
                    ));
                $this->confirmations[] = sprintf($this->l('%d cell(s) updated and queued.'), $n);
            }
            if (Tools::isSubmit('closeOut')) { $n = PulseChAri::closeOut(Tools::getValue('co_from'), Tools::getValue('co_to'), (int) Tools::getValue('co_channel'), (bool) Tools::getValue('co_open')); $this->confirmations[] = sprintf($this->l('%d cell(s) %s.'), $n, Tools::getValue('co_open') ? $this->l('re-opened') : $this->l('stop-sold')); }
            if (Tools::isSubmit('pushNow')) { $r = PulseChAri::drainQueue((int) Tools::getValue('id_channel')); $this->confirmations[] = sprintf($this->l('%d batch(es) pushed (%d cells), %d failed.'), $r['sent'], $r['cells'], $r['errors']); }
            if (Tools::isSubmit('recomputeNow')) { $r = PulseChAri::rebuild((int) Tools::getValue('id_channel')); $this->confirmations[] = sprintf($this->l('%d cell(s) recomputed.'), $r['cells_changed']); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}

<?php
/** Room type + rate plan to channel code mapping. Unmapped inventory is shown first, loudly. */
class AdminPulseChannelMappingController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Channel Mappings'); }

    public function initContent()
    {
        parent::initContent();
        $idChannel = (int) Tools::getValue('id_channel');
        $this->context->smarty->assign(array(
            'channels' => PulseChService::channels(), 'id_channel' => $idChannel,
            'mappings' => PulseChMapping::all($idChannel), 'unmapped' => PulseChMapping::unmapped(), 'broken' => PulseChMapping::broken(),
            'room_types' => PulseChService::roomTypes(), 'rate_plans' => PulseChService::ratePlans(false),
            'edit' => Tools::getValue('id_mapping') ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_mapping` WHERE id_pulse_ch_mapping='.(int) Tools::getValue('id_mapping')) : null,
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('mapping.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveMapping')) {
                PulseChMapping::save(array(
                    'id_pulse_ch_mapping' => (int) Tools::getValue('id_pulse_ch_mapping'), 'id_pulse_ch_channel' => (int) Tools::getValue('m_channel'),
                    'id_product' => (int) Tools::getValue('m_product'), 'id_pulse_ch_rate_plan' => (int) Tools::getValue('m_plan'),
                    'channel_room_code' => Tools::getValue('m_room_code'), 'channel_rate_code' => Tools::getValue('m_rate_code'),
                    'base_occupancy' => Tools::getValue('m_base_occ'), 'max_occupancy' => Tools::getValue('m_max_occ'),
                    'single_adj' => Tools::getValue('m_single'), 'extra_adult_adj' => Tools::getValue('m_extra'), 'child_adj' => Tools::getValue('m_child'),
                    'rate_adjust_type' => Tools::getValue('m_adj_type'), 'rate_adjust_value' => Tools::getValue('m_adj_value'),
                    'allotment' => Tools::getValue('m_allot'), 'min_los' => Tools::getValue('m_min_los'), 'active' => Tools::getValue('m_active', 1),
                ));
                $this->confirmations[] = $this->l('Mapping saved — the affected dates are queued for push.');
            }
            if (Tools::isSubmit('deleteMapping')) { PulseChMapping::delete((int) Tools::getValue('id_mapping_del')); $this->confirmations[] = $this->l('Mapping removed with its ARI cells.'); }
            if (Tools::isSubmit('copyMappings')) { $n = PulseChMapping::copyChannel((int) Tools::getValue('copy_from'), (int) Tools::getValue('copy_to')); $this->confirmations[] = sprintf($this->l('%d mapping(s) copied.'), $n); }
            if (Tools::isSubmit('quickMap')) {
                $n = 0;
                foreach ((array) Tools::getValue('q_code') as $key => $code) {
                    if (trim($code) === '') { continue; }
                    list($idChan, $idProd) = array_map('intval', explode('-', $key));
                    PulseChMapping::save(array('id_pulse_ch_channel' => $idChan, 'id_product' => $idProd, 'id_pulse_ch_rate_plan' => (int) Tools::getValue('q_plan'),
                        'channel_room_code' => $code, 'channel_rate_code' => Tools::getValue('q_rate_code_'.$key) ?: Tools::getValue('q_rate_code') ?: $code,
                        'base_occupancy' => 2, 'max_occupancy' => 3, 'single_adj' => Tools::getValue('q_single'), 'active' => 1));
                    $n++;
                }
                $this->confirmations[] = sprintf($this->l('%d room type(s) mapped.'), $n);
            }
            if (Tools::isSubmit('saveRatePlan')) {
                $id = (int) Tools::getValue('rp_id');
                $d = array('code' => pSQL(Tools::strtoupper(Tools::getValue('rp_code'))), 'name' => pSQL(Tools::getValue('rp_name')), 'meal_plan' => pSQL(Tools::getValue('rp_meal')),
                    'derive_from' => (int) Tools::getValue('rp_parent') ?: null, 'adjust_type' => pSQL(Tools::getValue('rp_adj_type')), 'adjust_value' => round((float) Tools::getValue('rp_adj_value'), 2),
                    'refundable' => (int) Tools::getValue('rp_refundable'), 'min_los' => (int) Tools::getValue('rp_min_los'), 'max_los' => (int) Tools::getValue('rp_max_los'),
                    'release_days' => (int) Tools::getValue('rp_release'), 'active' => (int) Tools::getValue('rp_active', 1), 'sort' => (int) Tools::getValue('rp_sort'));
                if (!$d['code'] || !$d['name']) { throw new PrestaShopException($this->l('Rate plan code and name are required')); }
                $id ? Db::getInstance()->update('pulse_ch_rate_plan', $d, 'id_pulse_ch_rate_plan='.$id) : Db::getInstance()->insert('pulse_ch_rate_plan', $d);
                $this->confirmations[] = $this->l('Rate plan saved');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}

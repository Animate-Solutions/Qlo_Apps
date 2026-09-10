<?php
/** Channel line-up from the IP headend, the VOD catalogue, radio streams and the games/apps launcher. */
class AdminPulseGuestPortalChannelsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Channels & VOD'); }

    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-m-01')); $to = Tools::getValue('to', PulseGpService::bd());
        $this->context->smarty->assign(array(
            'channels' => PulseGpEntertainment::allChannels(), 'channel' => (int) Tools::getValue('id_channel') ? PulseGpEntertainment::channel((int) Tools::getValue('id_channel')) : null,
            'vods' => PulseGpEntertainment::allVod(), 'vod' => (int) Tools::getValue('id_vod') ? PulseGpEntertainment::vodItem((int) Tools::getValue('id_vod')) : null,
            'radio' => PulseGpEntertainment::radio(), 'apps' => PulseGpEntertainment::apps(),
            'plays' => PulseGpEntertainment::plays($from, $to), 'from' => $from, 'to' => $to,
            'revenue' => Db::getInstance()->getRow('SELECT COUNT(*) n, ROUND(COALESCE(SUM(price),0),2) total FROM `'._DB_PREFIX_.'pulse_gp_vod_play` WHERE status="charged" AND business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'),
            'adult_pin_set' => PulseGpService::cfg('ADULT_PIN_HASH', '') ? 1 : 0, 'upload_base' => __PS_BASE_URI__, 'vod_code' => PulseGpService::cfg('VOD_CHARGE_CODE', 'VOD'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('channels.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveChannel')) {
                $logo = PulseGpService::uploadImage('logo', 'chan');
                $d = array('number' => Tools::getValue('number'), 'name' => Tools::getValue('name'), 'url' => Tools::getValue('url'), 'category' => Tools::getValue('category'),
                    'source' => Tools::getValue('source'), 'adult' => Tools::getValue('adult', 0), 'hd' => Tools::getValue('hd', 0), 'sort' => Tools::getValue('sort'), 'active' => Tools::getValue('active', 0));
                if ($logo) { $d['logo'] = $logo; }
                $id = PulseGpEntertainment::saveChannel((int) Tools::getValue('id_channel'), $d);
                if ($logo) { Db::getInstance()->update('pulse_gp_channel', array('logo' => pSQL($logo)), 'id_pulse_gp_channel='.(int) $id); }
                $this->confirmations[] = $this->l('Channel saved');
            }
            if (Tools::isSubmit('deleteChannel')) { PulseGpEntertainment::deleteChannel((int) Tools::getValue('id_channel')); $this->confirmations[] = $this->l('Channel removed'); }
            if (Tools::isSubmit('importChannels')) { $n = PulseGpEntertainment::importChannels(Tools::getValue('import')); $this->confirmations[] = sprintf($this->l('%d channel(s) imported'), $n); }
            if (Tools::isSubmit('saveVod')) {
                $poster = PulseGpService::uploadImage('poster', 'vod');
                PulseGpEntertainment::saveVod((int) Tools::getValue('id_vod'), array('title' => Tools::getValue('title'), 'synopsis' => Tools::getValue('synopsis'), 'stream_url' => Tools::getValue('stream_url'),
                    'category' => Tools::getValue('vcategory'), 'rating' => Tools::getValue('rating'), 'year' => Tools::getValue('year'), 'duration_min' => Tools::getValue('duration_min'),
                    'language' => Tools::getValue('language'), 'price' => Tools::getValue('price'), 'free' => Tools::getValue('free', 0), 'adult' => Tools::getValue('vadult', 0),
                    'sort' => Tools::getValue('vsort'), 'active' => Tools::getValue('vactive', 0)), $poster);
                $this->confirmations[] = $this->l('Title saved');
            }
            if (Tools::isSubmit('deleteVod')) { PulseGpEntertainment::deleteVod((int) Tools::getValue('id_vod')); $this->confirmations[] = $this->l('Title removed'); }
            if (Tools::isSubmit('saveRadio')) {
                $list = array(); $names = (array) Tools::getValue('rname'); $urls = (array) Tools::getValue('rurl'); $genres = (array) Tools::getValue('rgenre');
                foreach ($names as $i => $n) { if (trim((string) $n) !== '') { $list[] = array('name' => $n, 'url' => isset($urls[$i]) ? $urls[$i] : '', 'genre' => isset($genres[$i]) ? $genres[$i] : ''); } }
                $this->confirmations[] = sprintf($this->l('%d radio stream(s) saved'), PulseGpEntertainment::saveRadio($list));
            }
            if (Tools::isSubmit('saveApps')) {
                $list = array(); $names = (array) Tools::getValue('aname'); $urls = (array) Tools::getValue('aurl'); $types = (array) Tools::getValue('atype');
                foreach ($names as $i => $n) { if (trim((string) $n) !== '') { $list[] = array('name' => $n, 'url' => isset($urls[$i]) ? $urls[$i] : '', 'type' => isset($types[$i]) ? $types[$i] : 'app'); } }
                $this->confirmations[] = sprintf($this->l('%d app(s) saved'), PulseGpEntertainment::saveApps($list));
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}

<?php
/** Portal content: directory pages, dayparted promotions and the welcome greeting, each per language. */
class AdminPulseGuestPortalContentController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Portal Content'); }

    public function initContent()
    {
        parent::initContent();
        $langs = PulseGpService::langs();
        $welcome = array();
        foreach ($langs as $code => $name) { $welcome[$code] = PulseCoreService::setting('pulseguestportal', 'welcome_'.$code); if (!$welcome[$code]) { $welcome[$code] = PulseGpContent::defaultWelcome($code); } }
        $this->context->smarty->assign(array(
            'pages' => PulseGpContent::pages(), 'page' => (int) Tools::getValue('id_page') ? PulseGpContent::page((int) Tools::getValue('id_page')) : null,
            'promos' => PulseGpContent::allPromos(), 'promo' => (int) Tools::getValue('id_promo') ? PulseGpContent::promo((int) Tools::getValue('id_promo')) : null,
            'categories' => PulseGpContent::categories(), 'langs' => $langs, 'default_lang' => PulseGpService::defaultLang(),
            'room_types' => PulseGpContent::roomTypes(), 'welcome' => $welcome, 'upload_base' => __PS_BASE_URI__,
            'allergens' => PulseGpDining::allergens(), 'pos_items' => PulseGpService::pos() ? Db::getInstance()->executeS('SELECT id_pulse_pos_item id, name FROM `'._DB_PREFIX_.'pulse_pos_item` WHERE active=1 ORDER BY name LIMIT 300') : array(),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('content.tpl');
    }

    public function postProcess()
    {
        try {
            $langs = array_keys(PulseGpService::langs());
            if (Tools::isSubmit('savePage')) {
                $texts = array();
                foreach ($langs as $l) { $texts[$l] = array('title' => Tools::getValue('title_'.$l), 'summary' => Tools::getValue('summary_'.$l), 'body' => Tools::getValue('body_'.$l)); }
                $img = PulseGpService::uploadImage('image', 'page');
                $id = PulseGpContent::savePage((int) Tools::getValue('id_page'), array('code' => Tools::getValue('code'), 'category' => Tools::getValue('category'), 'icon' => Tools::getValue('icon'),
                    'phone' => Tools::getValue('phone'), 'extension' => Tools::getValue('extension'), 'opens' => Tools::getValue('opens'), 'location' => Tools::getValue('location'),
                    'room_types' => Tools::getValue('room_types'), 'sort' => Tools::getValue('sort'), 'active' => Tools::getValue('active', 0)), $texts, $img);
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_page='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('deletePage')) { PulseGpContent::deletePage((int) Tools::getValue('id_page')); $this->confirmations[] = $this->l('Page deleted'); }
            if (Tools::isSubmit('savePromo')) {
                $texts = array();
                foreach ($langs as $l) { $texts[$l] = array('title' => Tools::getValue('ptitle_'.$l), 'body' => Tools::getValue('pbody_'.$l), 'cta' => Tools::getValue('pcta_'.$l)); }
                $img = PulseGpService::uploadImage('pimage', 'promo');
                $id = PulseGpContent::savePromo((int) Tools::getValue('id_promo'), array('code' => Tools::getValue('pcode'), 'placement' => Tools::getValue('placement'), 'target' => Tools::getValue('target'),
                    'day_start' => Tools::getValue('day_start'), 'day_end' => Tools::getValue('day_end'), 'date_from' => Tools::getValue('date_from'), 'date_to' => Tools::getValue('date_to'),
                    'room_types' => Tools::getValue('proom_types'), 'sort' => Tools::getValue('psort'), 'active' => Tools::getValue('pactive', 0)), $texts, $img);
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_promo='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('deletePromo')) { PulseGpContent::deletePromo((int) Tools::getValue('id_promo')); $this->confirmations[] = $this->l('Promotion deleted'); }
            if (Tools::isSubmit('saveWelcome')) { foreach ($langs as $l) { PulseGpContent::saveWelcome($l, Tools::getValue('welcome_'.$l)); } $this->confirmations[] = $this->l('Welcome text saved'); }
            if (Tools::isSubmit('saveAllergens')) { $n = PulseGpDining::saveAllergens((array) Tools::getValue('allergen')); $this->confirmations[] = sprintf($this->l('%d allergen note(s) saved'), $n); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}

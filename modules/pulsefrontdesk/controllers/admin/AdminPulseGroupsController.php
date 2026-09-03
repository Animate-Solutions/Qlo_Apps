<?php
class AdminPulseGroupsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; $this->table = 'pulse_group_block'; $this->className = 'PulseGroupBlock'; $this->identifier = 'id_pulse_group_block'; $this->addRowAction('view'); $this->addRowAction('edit'); $this->addRowAction('delete');
        parent::__construct(); $this->meta_title = $this->l('Groups & Blocks');
        $this->_select = 'comp.name company, (SELECT SUM(blocked) FROM `'._DB_PREFIX_.'pulse_group_block_allot` x WHERE x.id_pulse_group_block=a.id_pulse_group_block) blocked, (SELECT SUM(picked_up) FROM `'._DB_PREFIX_.'pulse_group_block_allot` x WHERE x.id_pulse_group_block=a.id_pulse_group_block) picked_up';
        $this->_join = 'LEFT JOIN `'._DB_PREFIX_.'pulse_company` comp ON comp.id_pulse_company=a.id_pulse_company';
        $this->fields_list = array('code' => array('title' => 'Code'), 'name' => array('title' => $this->l('Group')), 'company' => array('title' => $this->l('Company'), 'havingFilter' => true), 'date_from' => array('title' => $this->l('From'), 'type' => 'date'), 'date_to' => array('title' => $this->l('To'), 'type' => 'date'), 'cutoff_date' => array('title' => $this->l('Cut-off'), 'type' => 'date'), 'blocked' => array('title' => $this->l('Blocked'), 'havingFilter' => true), 'picked_up' => array('title' => $this->l('Picked up'), 'havingFilter' => true), 'billing' => array('title' => $this->l('Billing')), 'status' => array('title' => $this->l('Status')));
        $companies = Db::getInstance()->executeS('SELECT id_pulse_company id, name FROM `'._DB_PREFIX_.'pulse_company` WHERE active=1 ORDER BY name'); array_unshift($companies, array('id' => 0, 'name' => '—'));
        $this->fields_form = array('legend' => array('title' => $this->l('Group block')), 'input' => array(
            array('type' => 'text', 'label' => 'Code', 'name' => 'code', 'required' => true), array('type' => 'text', 'label' => $this->l('Name'), 'name' => 'name', 'required' => true),
            array('type' => 'select', 'label' => $this->l('Company'), 'name' => 'id_pulse_company', 'options' => array('query' => $companies, 'id' => 'id', 'name' => 'name')),
            array('type' => 'date', 'label' => $this->l('Arrival'), 'name' => 'date_from', 'required' => true), array('type' => 'date', 'label' => $this->l('Departure'), 'name' => 'date_to', 'required' => true), array('type' => 'date', 'label' => $this->l('Cut-off date'), 'name' => 'cutoff_date', 'required' => true, 'desc' => $this->l('Un-picked-up rooms are released to general sale after this date (night audit).')),
            array('type' => 'text', 'label' => $this->l('Contracted rate / night (tax incl)'), 'name' => 'rate_per_night', 'desc' => $this->l('Leave empty for rack rate')),
            array('type' => 'select', 'label' => $this->l('Billing'), 'name' => 'billing', 'options' => array('query' => array(array('id' => 'individual', 'name' => 'Each guest pays own folio'), array('id' => 'room_to_master', 'name' => 'Rooms to master, incidentals to guest'), array('id' => 'master', 'name' => 'Everything to master folio')), 'id' => 'id', 'name' => 'name')),
            array('type' => 'select', 'label' => $this->l('Status'), 'name' => 'status', 'options' => array('query' => array(array('id' => 'tentative', 'name' => 'Tentative'), array('id' => 'definite', 'name' => 'Definite'), array('id' => 'cancelled', 'name' => 'Cancelled')), 'id' => 'id', 'name' => 'name')),
            array('type' => 'text', 'label' => $this->l('Contact'), 'name' => 'contact_name'), array('type' => 'text', 'label' => $this->l('Phone'), 'name' => 'contact_phone'), array('type' => 'text', 'label' => 'Email', 'name' => 'contact_email'), array('type' => 'textarea', 'label' => $this->l('Notes'), 'name' => 'notes'),
        ), 'submit' => array('title' => $this->l('Save')));
    }

    public function initContent()
    {
        if ($this->display === 'view') {
            $g = new PulseGroupBlock((int) Tools::getValue('id_pulse_group_block'));
            $types = Db::getInstance()->executeS('SELECT rt.id_product, pl.name FROM `'._DB_PREFIX_.'htl_room_type` rt INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=rt.id_product AND pl.id_lang='.(int) $this->context->language->id.' AND pl.id_shop='.(int) $this->context->shop->id);
            $allot = array(); foreach ($g->allotments() as $a) { $allot[$a['id_product']] = $a; }
            foreach ($types as &$t) { $t['blocked'] = isset($allot[$t['id_product']]) ? $allot[$t['id_product']]['blocked'] : 0; $t['picked_up'] = isset($allot[$t['id_product']]) ? $allot[$t['id_product']]['picked_up'] : 0; $t['avail'] = PulseReservation::availability($t['id_product'], $g->date_from, $g->date_to, $g->id); }
            $this->context->smarty->assign(array('g' => $g, 'types' => $types, 'bookings' => $g->bookings(), 'master' => $g->id_pulse_folio ? new PulseFolio($g->id_pulse_folio) : null, 'self_url' => self::$currentIndex.'&token='.$this->token.'&id_pulse_group_block='.$g->id.'&viewpulse_group_block', 'folio_url' => $this->context->link->getAdminLink('AdminPulseFolio')));
            $this->context->smarty->assign('content', $this->context->smarty->fetch($this->getTemplatePath().'pulse_groups/view.tpl'));
            return;
        }
        parent::initContent();
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveAllot')) { $g = new PulseGroupBlock((int) Tools::getValue('id_pulse_group_block')); foreach ((array) Tools::getValue('blocked') as $idp => $n) { $g->setAllotment((int) $idp, (int) $n); } $g->masterFolio(); $this->confirmations[] = $this->l('Allotments saved'); }
            if (Tools::isSubmit('importRooming') && isset($_FILES['rooming']) && $_FILES['rooming']['tmp_name']) {
                $g = new PulseGroupBlock((int) Tools::getValue('id_pulse_group_block')); $rows = array_map('str_getcsv', file($_FILES['rooming']['tmp_name'])); if (isset($rows[0][0]) && stripos($rows[0][0], 'first') !== false) { array_shift($rows); }
                $r = $g->importRoomingList($rows); $this->confirmations[] = sprintf($this->l('%d reservations created'), $r['created']); foreach ($r['errors'] as $e) { $this->errors[] = $e; }
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}

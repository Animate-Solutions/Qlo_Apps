<?php
/** Live Board — who is in, by department, right now. The screen a duty manager stands in front of at 6 a.m. */
class AdminPulseTaBoardController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Live Board'); }

    public function initContent()
    {
        parent::initContent();
        $dept = Tools::getValue('department', '');
        $board = PulseTaService::board($dept);
        $groups = array();
        foreach ($board as $r) { $groups[$r['department']][] = $r; }
        ksort($groups);
        $this->context->smarty->assign(array(
            'board' => $board, 'groups' => $groups, 'counters' => PulseTaService::dashboard(),
            'departments' => PulseTaService::departments(), 'department' => $dept,
            'devices' => PulseTaDevice::statusAll(), 'exceptions' => PulseTaExceptionQueue::search(array('status' => 'open'), 12),
            'staff' => PulseTaService::staffList($dept), 'hr' => PulseTaService::hr(), 'pos' => PulseTaService::pos(),
            'business_date' => PulseTaService::bd(), 'now' => date('Y-m-d H:i'),
            'labour' => PulseTaService::labourPerOccupiedRoom(date('Y-m-01'), PulseTaService::bd()),
            'exceptions_url' => $this->context->link->getAdminLink('AdminPulseTaExceptions'),
            'devices_url' => $this->context->link->getAdminLink('AdminPulseTaDevices'),
            'timesheets_url' => $this->context->link->getAdminLink('AdminPulseTaTimesheets'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('board.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('pollAll')) {
                $r = PulseTaDevice::pollAll(true);
                $this->confirmations[] = sprintf($this->l('Polled %d device(s): %d punch(es) read, %d new.'), $r['devices'], $r['pulled'], $r['stored']);
                foreach (array_slice($r['errors'], 0, 5) as $e) { $this->errors[] = $e; }
            }
            if (Tools::isSubmit('quickPunch')) {
                $this->assertEdit();
                $id = PulseTaPunch::manual((int) Tools::getValue('id_staff'), Tools::getValue('at') ?: date('Y-m-d H:i:s'), Tools::getValue('direction', 'unknown'), 'manual');
                if ($id) { PulseTaEngine::buildOne((int) Tools::getValue('id_staff'), date('Y-m-d', strtotime(Tools::getValue('at') ?: 'now'))); $this->confirmations[] = $this->l('Punch recorded and the timesheet rebuilt.'); }
                else { $this->errors[] = $this->l('That punch already exists.'); }
            }
            if (Tools::isSubmit('rebuildToday')) {
                $r = PulseTaEngine::buildDay(Tools::getValue('build_date') ?: PulseTaService::bd());
                $this->confirmations[] = sprintf($this->l('%d timesheet(s) rebuilt, %d exception(s) open.'), $r['timesheets'], $r['exceptions']);
            }
        } catch (PulseTaDeviceException $e) { $this->errors[] = $e->userMessage(); }
        catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to record punches')); }
    }

    protected function json($d) { die(json_encode($d)); }
    public function ajaxProcessBoard() { $this->json(array('ok' => true, 'counters' => PulseTaService::dashboard(), 'staff' => PulseTaService::board(Tools::getValue('department', '')))); }
}

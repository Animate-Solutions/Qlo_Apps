<?php
/** Segments: build a rule set, preview the count before you commit, materialise membership. */
class AdminPulseCrmSegmentsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Segments'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        $id = (int) Tools::getValue('id_segment');
        $s = $id ? PulseCrmSegment::get($id) : null;
        $parsed = $s ? json_decode($s['rules_json'], true) : null;
        $this->context->smarty->assign(array(
            'segments' => PulseCrmSegment::all(), 'fields' => PulseCrmSegment::fields(), 'ops' => PulseCrmSegment::ops(),
            's' => $s, 'match' => is_array($parsed) && isset($parsed['match']) ? $parsed['match'] : 'all',
            'rules' => is_array($parsed) && isset($parsed['rules']) ? $parsed['rules'] : array(),
            'members' => $id ? PulseCrmSegment::members($id, 100) : array(),
            'preview' => $this->context->smarty->getTemplateVars('preview'), 'self_url' => $self,
        ));
        $this->setTemplate('segments.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveSegment')) {
                $rules = $this->rulesFromRequest();
                $id = PulseCrmSegment::save(array('name' => Tools::getValue('name'), 'code' => Tools::getValue('code'), 'description' => Tools::getValue('description'),
                    'rules_json' => $rules, 'active' => (int) Tools::getValue('active', 1)), (int) Tools::getValue('id_segment'));
                PulseCrmSegment::refresh($id);
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_segment='.$id.'&conf=3');
            }
            if (Tools::isSubmit('previewSegment')) { $this->context->smarty->assign('preview', PulseCrmSegment::preview($this->rulesFromRequest(), 25)); }
            if (Tools::isSubmit('refreshSegment')) { $n = PulseCrmSegment::refresh((int) Tools::getValue('id_segment_a')); $this->confirmations[] = sprintf($this->l('%d guests in the segment'), $n); }
            if (Tools::isSubmit('refreshAll')) { $r = PulseCrmSegment::refreshAll(); $this->confirmations[] = sprintf($this->l('%1$d segments refreshed, %2$d memberships'), $r['segments'], $r['members']); }
            if (Tools::isSubmit('deleteSegment')) { PulseCrmSegment::remove((int) Tools::getValue('id_segment_a')); $this->confirmations[] = $this->l('Segment deleted'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    /** The rule builder posts parallel arrays; empty rows are dropped rather than compiled into nonsense. */
    protected function rulesFromRequest()
    {
        $f = (array) Tools::getValue('rule_field'); $o = (array) Tools::getValue('rule_op'); $v = (array) Tools::getValue('rule_value');
        $rules = array();
        foreach ($f as $i => $field) {
            if (!$field) { continue; }
            $op = isset($o[$i]) ? $o[$i] : 'eq'; $val = isset($v[$i]) ? $v[$i] : '';
            if ($val === '' && !in_array($op, array('is_set', 'is_null'))) { continue; }
            $rules[] = array('field' => $field, 'op' => $op, 'value' => $val);
        }
        return array('match' => Tools::getValue('match', 'all') === 'any' ? 'any' : 'all', 'rules' => $rules);
    }
}

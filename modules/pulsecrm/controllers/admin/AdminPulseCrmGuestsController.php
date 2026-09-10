<?php
/** Guest search and the CRM profile: preferences, occasions, relationships, consent, tags, loyalty, feedback, merge and erasure. */
class AdminPulseCrmGuestsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Guests');
    }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex . '&token=' . $this->token;
        if ($id = (int) Tools::getValue('id_customer')) {
            $this->context->smarty->assign(array(
                'p' => PulseCrmProfile::get($id),
                'options' => PulseCrmProfile::options(),
                'tags' => Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'pulse_crm_tag` ORDER BY name'),
                'programs' => Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'pulse_crm_loyalty_program` WHERE active=1'),
                'companies' => PulseCrmService::tableExists('pulse_company') ? Db::getInstance()->executeS('SELECT id_pulse_company, name FROM `' . _DB_PREFIX_ . 'pulse_company` WHERE active=1 ORDER BY name') : array(),
                'surveys' => PulseCrmSurvey::all(true),
                'self_url' => $self,
                'fd' => PulseCrmService::fd(),
            ));
            return $this->setTemplate('profile.tpl');
        }
        $q = Tools::getValue('q', '');
        $this->context->smarty->assign(array(
            'rows' => PulseCrmProfile::search($q, 100),
            'q' => $q,
            'self_url' => $self,
            'duplicates' => Tools::getValue('dupes') && class_exists('PulseGuestProfile') ? PulseGuestProfile::findDuplicates(50) : array(),
            'segments' => PulseCrmSegment::all(true)
        ));
        $this->setTemplate('guests.tpl');
    }

    public function postProcess()
    {
        $id = (int) Tools::getValue('id_customer');
        try {
            if (Tools::isSubmit('savePref')) {
                PulseCrmProfile::savePreference($id, Tools::getValue('category'), Tools::getValue('pvalue'), Tools::getValue('pcode') ?: null, 'desk');
                $this->confirmations[] = $this->l('Preference saved');
            }
            if (Tools::isSubmit('delPref')) {
                PulseCrmProfile::removePreference((int) Tools::getValue('id_pref'));
                $this->confirmations[] = $this->l('Preference removed');
            }
            if (Tools::isSubmit('saveOccasion')) {
                PulseCrmProfile::saveOccasion($id, Tools::getValue('otype'), Tools::getValue('odate'), Tools::getValue('onote'), (int) Tools::getValue('oremind', 7));
                $this->confirmations[] = $this->l('Occasion saved');
            }
            if (Tools::isSubmit('delOccasion')) {
                PulseCrmProfile::removeOccasion((int) Tools::getValue('id_occasion'));
                $this->confirmations[] = $this->l('Occasion removed');
            }
            if (Tools::isSubmit('saveRelation')) {
                PulseCrmProfile::relate($id, (int) Tools::getValue('id_related'), Tools::getValue('rtype'), Tools::getValue('rnote'));
                $this->confirmations[] = $this->l('Relationship saved');
            }
            if (Tools::isSubmit('delRelation')) {
                PulseCrmProfile::unrelate((int) Tools::getValue('id_relation'));
                $this->confirmations[] = $this->l('Relationship removed');
            }
            if (Tools::isSubmit('saveConsent')) {
                foreach (array('email', 'sms', 'whatsapp', 'phone', 'post', 'profiling') as $ch) {
                    $state = Tools::getValue('consent_' . $ch);
                    if ($state) {
                        PulseCrmProfile::setConsent($id, $ch, $state, Tools::getValue('consent_source', 'desk'), Tools::getValue('consent_evidence'), Tools::getValue('consent_reason'));
                    }
                }
                $this->confirmations[] = $this->l('Consent recorded');
            }
            if (Tools::isSubmit('saveExt')) {
                PulseCrmProfile::saveExt($id, array('source_of_business' => Tools::getValue('source_of_business'), 'market_segment' => Tools::getValue('market_segment'), 'guest_type' => Tools::getValue('guest_type'), 'preferred_language' => Tools::getValue('preferred_language'), 'preferred_channel' => Tools::getValue('preferred_channel')));
                $this->confirmations[] = $this->l('Profile saved');
            }
            if (Tools::isSubmit('saveFdProfile') && class_exists('PulseGuestProfile')) {
                PulseGuestProfile::save($id, array('vip_level' => (int) Tools::getValue('vip_level'), 'id_pulse_company' => (int) Tools::getValue('id_pulse_company'), 'nationality' => Tools::getValue('nationality'), 'phone' => Tools::getValue('phone'), 'notes' => Tools::getValue('notes')));
                $this->confirmations[] = $this->l('Guest profile saved');
            }
            if (Tools::isSubmit('addTag')) {
                PulseCrmProfile::tag($id, Tools::getValue('tag_code'), 'desk');
                $this->confirmations[] = $this->l('Tag added');
            }
            if (Tools::isSubmit('delTag')) {
                PulseCrmProfile::untag($id, Tools::getValue('tag_code'));
                $this->confirmations[] = $this->l('Tag removed');
            }
            if (Tools::isSubmit('enrolMember')) {
                $m = PulseCrmLoyalty::enrol($id, 'desk', (int) Tools::getValue('id_program'));
                $this->confirmations[] = $this->l('Enrolled as member ') . PulseCrmLoyalty::member($m)['member_no'];
            }
            if (Tools::isSubmit('sendSurvey')) {
                $r = PulseCrmSurvey::inviteAndSend((int) Tools::getValue('id_survey'), $id, (int) Tools::getValue('id_htl_booking') ?: null, 'email');
                $this->confirmations[] = $r['ok'] ? $this->l('Survey sent') : $this->l('Survey not sent: ') . $r['reason'];
            }
            if (Tools::isSubmit('mergeGuest') && class_exists('PulseGuestProfile')) {
                PulseGuestProfile::merge((int) Tools::getValue('id_keep'), (int) Tools::getValue('id_merge'));
                $this->confirmations[] = $this->l('Profiles merged');
            }
            if (Tools::isSubmit('forgetGuest')) {
                if (Tools::getValue('confirm_forget') !== 'FORGET') {
                    throw new PrestaShopException($this->l('Type FORGET to confirm — this cannot be undone'));
                }
                PulseCrmProfile::forget($id, Tools::getValue('forget_reason', 'Guest exercised the right to erasure'));
                $this->confirmations[] = $this->l('Marketing data erased. The accounting trail is untouched.');
            }
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }
        return parent::postProcess();
    }
}

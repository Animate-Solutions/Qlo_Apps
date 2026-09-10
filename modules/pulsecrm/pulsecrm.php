<?php
/** Pulse CRM — guest 360 enrichment, segmentation, campaigns, journeys, loyalty, surveys, service recovery, reputation and corporate sales. Benchmarks: Oracle OPERA Customer Management & OCIS, Revinate, Cendyn, TrustYou / ReviewPro, eZee guest feedback and loyalty. */
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once dirname(__FILE__) . '/classes/autoload.php';

class PulseCrm extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array(
        'AdminPulseCrm' => 'CRM',
        'AdminPulseCrmGuests' => 'Guests',
        'AdminPulseCrmSegments' => 'Segments',
        'AdminPulseCrmCampaigns' => 'Campaigns',
        'AdminPulseCrmJourneys' => 'Journeys',
        'AdminPulseCrmLoyalty' => 'Loyalty',
        'AdminPulseCrmSurveys' => 'Surveys',
        'AdminPulseCrmCases' => 'Service Recovery',
        'AdminPulseCrmReviews' => 'Reviews',
        'AdminPulseCrmCorporate' => 'Corporate',
        'AdminPulseCrmSettings' => 'CRM Settings'
    );
    protected $hooks = array(
        'displayBackOfficeHeader',
        'moduleRoutes',
        'actionPulseCheckIn',
        'actionPulseCheckOut',
        'actionPulseNoShow',
        'actionPulseFolioPost',
        'actionPulseNightAuditClosed',
        'actionPulseTicketCreated',
        'actionValidateOrder',
        'actionPulseCrmMemberEnrolled',
        'actionPulseCrmPointsRedeemed',
        'actionPulseCrmSurveyCompleted',
        'actionPulseCrmCaseOpened',
        'actionPulseCrmReviewAdded'
    );

    public function __construct()
    {
        $this->name = 'pulsecrm';
        $this->tab = 'administration';
        $this->version = self::VERSION;
        $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->dependencies = array('pulsecore');
        $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse CRM');
        $this->description = $this->l('Guest 360 enrichment, segmentation, email/SMS/WhatsApp campaigns, event-triggered journeys, a tiered loyalty programme posting to the folio, NPS surveys, service-recovery cases with cost tracking, review management and corporate sales.');
        $this->confirmUninstall = $this->l('Uninstall CRM? Preferences, consent records, segments, campaigns, journeys, loyalty members and their points, surveys, recovery cases, reviews and corporate accounts will all be dropped. Folio postings already made stay where they are.');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        foreach ($this->hooks as $h) {
            if (!$this->registerHook($h)) {
                return false;
            }
        }
        if (!$this->runSql('install')) {
            return false;
        }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore');
        $i = 120;
        foreach ($this->tabs as $c => $n) {
            $t = new Tab();
            $t->class_name = $c;
            $t->module = $this->name;
            $t->id_parent = $parent;
            $t->position = $i++;
            foreach (Language::getLanguages(true) as $l) {
                $t->name[$l['id_lang']] = $n;
            }
            if (!$t->add()) {
                return false;
            }
        }
        foreach (array(
            'PULSE_CRM_QUIET_FROM' => '21:00',
            'PULSE_CRM_QUIET_TO' => '08:00',
            'PULSE_CRM_SUPPRESS_DAYS' => 1,
            'PULSE_CRM_IMPLIED_CONSENT' => 0,
            'PULSE_CRM_THROTTLE' => 100,
            'PULSE_CRM_SEGMENT_CHUNK' => 20,
            'PULSE_CRM_JOURNEY_CHUNK' => 100,
            'PULSE_CRM_NPS_THRESHOLD' => 6,
            'PULSE_CRM_SURVEY_EXPIRY_DAYS' => 30,
            'PULSE_CRM_REVIEW_URL' => '',
            'PULSE_CRM_PORTAL_URL' => '',
            'PULSE_CRM_BASE_URL' => '',
            'PULSE_CRM_MANAGER_EMAIL' => Configuration::get('PS_SHOP_EMAIL'),
            'PULSE_CRM_SECRET' => Tools::passwdGen(48),
            'PULSE_CRM_CRON_TOKEN' => Tools::passwdGen(32)
        ) as $k => $v) {
            Configuration::updateValue($k, $v);
        }
        $this->seedProgram();
        $this->seedJourneys();
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) {
            if ($id = (int) Tab::getIdFromClassName($c)) {
                $t = new Tab($id);
                $t->delete();
            }
        }
        foreach (array(
            'QUIET_FROM',
            'QUIET_TO',
            'SUPPRESS_DAYS',
            'IMPLIED_CONSENT',
            'THROTTLE',
            'SEGMENT_CHUNK',
            'JOURNEY_CHUNK',
            'NPS_THRESHOLD',
            'SURVEY_EXPIRY_DAYS',
            'REVIEW_URL',
            'PORTAL_URL',
            'BASE_URL',
            'MANAGER_EMAIL',
            'SECRET',
            'CRON_TOKEN'
        ) as $k) {
            Configuration::deleteByName('PULSE_CRM_' . $k);
        }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    protected function runSql($f)
    {
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__) . '/sql/' . $f . '.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) {
            if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) {
                return false;
            }
        }
        return true;
    }

    /** A three-tier programme in naira, with earn rates that reflect what a Nigerian hotel actually makes money on. */
    protected function seedProgram()
    {
        if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_loyalty_program`')) {
            return;
        }
        $id = PulseCrmLoyalty::saveProgram(array(
            'code' => 'PULSE',
            'name' => 'Pulse Rewards',
            'point_value' => 1,
            'min_redeem_points' => 2000,
            'expiry_months' => 24,
            'qualify_window_months' => 12,
            'enrol_bonus' => 500,
            'terms' => 'Points are earned on net spend excluding VAT and consumption tax. Points expire 24 months after they are earned. 1 point = 1 naira on redemption.',
            'earn_rate_json' => array('rooms' => 100, 'fnb' => 60, 'minibar' => 60, 'spa' => 80, 'laundry' => 40, 'telephone' => 0, 'business_centre' => 40, 'misc' => 20, 'default' => 20)
        ));
        foreach (array(
            array('code' => 'CLASSIC', 'name' => 'Classic', 'sort' => 1, 'min_nights' => 0, 'min_stays' => 0, 'min_spend' => 0, 'earn_multiplier' => 1, 'colour' => 'default', 'benefits' => 'Members rate, late checkout to 13:00 subject to availability, free wifi.'),
            array('code' => 'SILVER', 'name' => 'Silver', 'sort' => 2, 'min_nights' => 10, 'min_stays' => 4, 'min_spend' => 600000, 'earn_multiplier' => 1.25, 'colour' => 'info', 'benefits' => 'Everything in Classic plus complimentary breakfast, late checkout to 15:00, welcome drink.'),
            array('code' => 'GOLD', 'name' => 'Gold', 'sort' => 3, 'min_nights' => 30, 'min_stays' => 10, 'min_spend' => 2000000, 'earn_multiplier' => 1.5, 'colour' => 'warning', 'benefits' => 'Everything in Silver plus a room upgrade when one is free, airport pick-up on stays of three nights, guaranteed room to 24h before arrival, dedicated line to the duty manager.'),
        ) as $t) {
            $t['id_pulse_crm_loyalty_program'] = $id;
            PulseCrmLoyalty::saveTier($t);
        }
    }

    /** The seven journeys the brief describes, wired to their triggers with sensible delays and conditions. */
    protected function seedJourneys()
    {
        if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_journey`')) {
            return;
        }
        $j = array(
            array(
                'code' => 'prearrival',
                'name' => 'Pre-arrival upsell',
                'trigger_event' => 'booking_confirmed',
                'description' => 'Three days before arrival, offer the upgrade while there is still a room to give.',
                'active' => 1,
                'steps' => array(
                    array(
                        'sort' => 1,
                        'name' => 'T-3 pre-arrival note',
                        'delay_minutes' => 0,
                        'action' => 'send_template',
                        'channel' => 'auto',
                        'template_code' => 'crm_prearrival',
                        'subject' => 'Getting ready for you — {hotel}',
                        'body' => "Dear {first_name},\n\nWe are looking forward to welcoming you on {from}.\n\nIf you would like a suite, an early check-in or an airport pick-up, reply to this message and we will hold it for you.\n\nWarm regards,\nThe front desk"
                    ),
                )
            ),
            array(
                'code' => 'welcome',
                'name' => 'Check-in welcome',
                'trigger_event' => 'check_in',
                'description' => 'Welcome note with the in-room portal link, fifteen minutes after the key is cut.',
                'active' => 1,
                'steps' => array(
                    array(
                        'sort' => 1,
                        'name' => 'Welcome',
                        'delay_minutes' => 15,
                        'action' => 'send_template',
                        'channel' => 'auto',
                        'template_code' => 'crm_welcome',
                        'subject' => 'Welcome to {hotel}, {first_name}',
                        'body' => "Welcome {first_name}.\n\nYou are in room {room}. Dial 0 for the front desk at any hour.\n\nOrder food, ask for housekeeping and see your bill here: {portal_url}\n\nEnjoy your stay."
                    ),
                )
            ),
            array(
                'code' => 'midstay',
                'name' => 'Mid-stay satisfaction ping',
                'trigger_event' => 'mid_stay',
                'description' => 'Night two: ask how it is going while it can still be fixed. A low score opens a ticket.',
                'active' => 1,
                'steps' => array(
                    array(
                        'sort' => 1,
                        'name' => 'How is everything?',
                        'delay_minutes' => 0,
                        'action' => 'send_template',
                        'channel' => 'auto',
                        'template_code' => 'crm_midstay',
                        'subject' => 'How is everything, {first_name}?',
                        'action_json' => array('survey' => 'in_stay'),
                        'body' => "Dear {first_name},\n\nYou are on night two with us. Is everything as it should be?\n\nThirty seconds here and the duty manager sees it tonight: {survey_url}"
                    ),
                    array(
                        'sort' => 2,
                        'name' => 'Chase a detractor',
                        'delay_minutes' => 1440,
                        'action' => 'create_ticket',
                        'channel' => 'auto',
                        'condition_json' => array('match' => 'all', 'rules' => array(array('field' => 'nps_band', 'op' => 'eq', 'value' => 'detractor'))),
                        'action_json' => array('category' => 'complaint', 'department' => 'frontdesk', 'priority' => 'high', 'title' => 'In-house guest scored us low — go and see them'),
                        'body' => "Guest {full_name} in room {room} scored {nps} on the in-stay survey. Visit the room before they check out."
                    ),
                )
            ),
            array(
                'code' => 'thankyou',
                'name' => 'Check-out thank you and survey',
                'trigger_event' => 'check_out',
                'description' => 'Thank you two hours after departure, with the post-stay survey attached.',
                'active' => 1,
                'steps' => array(
                    array(
                        'sort' => 1,
                        'name' => 'Thank you + survey',
                        'delay_minutes' => 120,
                        'action' => 'send_template',
                        'channel' => 'auto',
                        'template_code' => 'crm_thankyou',
                        'subject' => 'Thank you for staying with us — {hotel}',
                        'action_json' => array('survey' => 'post_stay'),
                        'body' => "Dear {first_name},\n\nThank you for staying with us. It was good to have you.\n\nWould you tell us how we did? It takes under a minute and the general manager reads every one: {survey_url}"
                    ),
                    array(
                        'sort' => 2,
                        'name' => 'Review request at T+7',
                        'delay_minutes' => 10080,
                        'action' => 'send_template',
                        'channel' => 'email',
                        'template_code' => 'crm_review_ask',
                        'subject' => 'Would you leave us a review?',
                        'condition_json' => array('match' => 'any', 'rules' => array(array('field' => 'nps_band', 'op' => 'eq', 'value' => 'promoter'), array('field' => 'nps_band', 'op' => 'eq', 'value' => 'unknown'))),
                        'body' => "Dear {first_name},\n\nIf we looked after you, would you post a short review? It genuinely changes which hotel the next visitor to Port Harcourt picks.\n\n{review_url}\n\nThank you."
                    ),
                )
            ),
            array(
                'code' => 'winback',
                'name' => 'Win-back at 90 days',
                'trigger_event' => 'lapsed',
                'description' => 'Ninety days after the last stay, a members rate and a reason to come back.',
                'active' => 1,
                'steps' => array(
                    array(
                        'sort' => 1,
                        'name' => 'Win-back offer',
                        'delay_minutes' => 0,
                        'action' => 'send_template',
                        'channel' => 'email',
                        'template_code' => 'crm_winback',
                        'subject' => 'We have missed you at {hotel}',
                        'body' => "Dear {first_name},\n\nIt has been three months since we last saw you, and the new wing is finished.\n\nBook direct this month and we will hold a members rate for you: {book_url}\n\nWe would be glad to have you back."
                    ),
                    array('sort' => 2, 'name' => 'Tag as a win-back target', 'delay_minutes' => 1, 'action' => 'add_tag', 'action_json' => array('tag' => 'winback')),
                )
            ),
            array(
                'code' => 'birthday',
                'name' => 'Birthday greeting',
                'trigger_event' => 'birthday',
                'description' => 'A greeting on the day, and 1,000 points for members.',
                'active' => 1,
                'steps' => array(
                    array(
                        'sort' => 1,
                        'name' => 'Greeting',
                        'delay_minutes' => 0,
                        'action' => 'send_template',
                        'channel' => 'auto',
                        'template_code' => 'crm_birthday',
                        'subject' => 'Happy birthday from all of us at {hotel}',
                        'body' => "Happy birthday {first_name}.\n\nEveryone here wishes you a wonderful day. Your next stay with us comes with a complimentary upgrade, subject to availability — just mention this note."
                    ),
                    array(
                        'sort' => 2,
                        'name' => 'Birthday points',
                        'delay_minutes' => 2,
                        'action' => 'add_points',
                        'condition_json' => array('match' => 'all', 'rules' => array(array('field' => 'is_member', 'op' => 'eq', 'value' => 1))),
                        'action_json' => array('points' => 1000, 'reason' => 'Birthday points')
                    ),
                )
            ),
            array(
                'code' => 'anniversary',
                'name' => 'Anniversary greeting',
                'trigger_event' => 'anniversary',
                'description' => 'A note on the anniversary with dinner for two on the house.',
                'active' => 1,
                'steps' => array(
                    array(
                        'sort' => 1,
                        'name' => 'Greeting',
                        'delay_minutes' => 0,
                        'action' => 'send_template',
                        'channel' => 'auto',
                        'template_code' => 'crm_anniversary',
                        'subject' => 'Happy anniversary — {hotel}',
                        'body' => "Dear {first_name},\n\nHappy anniversary from all of us.\n\nStay with us this month and dinner for two in the restaurant is on the house."
                    ),
                )
            ),
        );
        foreach ($j as $spec) {
            $steps = $spec['steps'];
            unset($spec['steps']);
            $id = PulseCrmJourney::save($spec);
            foreach ($steps as $s) {
                $s['id_pulse_crm_journey'] = $id;
                PulseCrmJourney::saveStep($s);
            }
        }
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseCrm'));
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (strpos($this->context->controller->controller_name, 'AdminPulseCrm') === 0) {
            $this->context->controller->addCSS($this->_path . 'views/css/crm.css');
            $this->context->controller->addJS($this->_path . 'views/js/crm.js');
        }
    }

    public function hookModuleRoutes()
    {
        return array(
            'pulsecrm-api' => array('controller' => 'api', 'rule' => 'pulse/api/crm{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsecrm-survey' => array('controller' => 'survey', 'rule' => 'pulse/survey', 'keywords' => array(), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsecrm-track' => array('controller' => 'track', 'rule' => 'pulse/crm/{:a}', 'keywords' => array('a' => array('regexp' => '[a-z]+', 'param' => 'a')), 'params' => array('fc' => 'module', 'module' => $this->name)),
        );
    }

    /* ---------- suite events ---------- */

    /** A confirmed booking starts the pre-arrival journey three days out, and stamps the source of business. */
    public function hookActionValidateOrder($p)
    {
        if (empty($p['customer']) || empty($p['order'])) {
            return;
        }
        try {
            $idc = (int) $p['customer']->id;
            PulseCrmProfile::touch($idc);
            $b = Db::getInstance()->getRow('SELECT id, id_room, date_from FROM `' . _DB_PREFIX_ . 'htl_booking_detail` WHERE id_order=' . (int) $p['order']->id . ' ORDER BY date_from LIMIT 1');
            if (!$b) {
                return;
            }
            $delay = max(0, (int) floor((strtotime($b['date_from'] . ' 10:00:00') - time() - 3 * 86400) / 60));
            $started = PulseCrmJourney::trigger('booking_confirmed', array('id_customer' => $idc, 'id_htl_booking' => (int) $b['id'], 'id_room' => (int) $b['id_room']));
            if ($started && $delay > 0) {
                Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'pulse_crm_journey_run` SET next_run_at=DATE_ADD(NOW(), INTERVAL ' . $delay . ' MINUTE) WHERE id_customer=' . $idc . ' AND id_htl_booking=' . (int) $b['id'] . ' AND status="active" AND step_index=0');
            }
        } catch (Exception $e) {
            PulseCoreService::audit('pulsecrm', 'order_hook_failed', array('error' => $e->getMessage()));
        }
    }

    public function hookActionPulseCheckIn($p)
    {
        if (empty($p['booking']['id_customer'])) {
            return;
        }
        try {
            PulseCrmJourney::trigger('check_in', array('id_customer' => (int) $p['booking']['id_customer'], 'id_htl_booking' => (int) $p['booking']['id'], 'id_room' => (int) $p['id_room']));
        } catch (Exception $e) {
            PulseCoreService::audit('pulsecrm', 'checkin_hook_failed', array('error' => $e->getMessage()));
        }
    }

    /** Check-out closes the stay: record it on the loyalty side, re-tier, and start the thank-you journey. */
    public function hookActionPulseCheckOut($p)
    {
        if (empty($p['booking']['id_customer']) || !empty($p['room_move'])) {
            return;
        }
        try {
            $idc = (int) $p['booking']['id_customer'];
            if ($m = PulseCrmLoyalty::memberOf($idc)) {
                PulseCrmLoyalty::recalcTier((int) $m['id_pulse_crm_member']);
            }
            PulseCrmJourney::trigger('check_out', array('id_customer' => $idc, 'id_htl_booking' => (int) $p['booking']['id'], 'id_room' => (int) $p['id_room']));
        } catch (Exception $e) {
            PulseCoreService::audit('pulsecrm', 'checkout_hook_failed', array('error' => $e->getMessage()));
        }
    }

    /** A no-show should never be thanked for a stay that did not happen. */
    public function hookActionPulseNoShow($p)
    {
        if (empty($p['booking']['id_customer'])) {
            return;
        }
        PulseCrmJourney::cancelFor((int) $p['booking']['id_customer'], (int) $p['booking']['id'], 'no show');
    }

    /** Every folio charge is a chance to earn points; the ledger decides whether it qualifies. */
    public function hookActionPulseFolioPost($p)
    {
        if (empty($p['id_line']) || !empty($p['is_payment'])) {
            return;
        }
        try {
            PulseCrmLoyalty::earnFromFolioLine((int) $p['id_line']);
        } catch (Exception $e) {
            PulseCoreService::audit('pulsecrm', 'earn_failed', array('line' => (int) $p['id_line'], 'error' => $e->getMessage()));
        }
    }

    /** After the night audit: bring qualifying nights up to date for everyone who slept here last night. */
    public function hookActionPulseNightAuditClosed($p)
    {
        try {
            if (!PulseCrmService::tableExists('htl_booking_detail')) {
                return;
            }
            $d = isset($p['business_date']) ? $p['business_date'] : PulseCrmService::bd();
            foreach (Db::getInstance()->executeS('SELECT DISTINCT m.id_pulse_crm_member FROM `' . _DB_PREFIX_ . 'htl_booking_detail` b
                INNER JOIN `' . _DB_PREFIX_ . 'pulse_crm_member` m ON m.id_customer=b.id_customer AND m.status="active"
                WHERE b.is_cancelled=0 AND b.is_refunded=0 AND "' . pSQL($d) . '" BETWEEN b.date_from AND b.date_to LIMIT 200') as $m) {
                PulseCrmLoyalty::recalcQualifiers((int) $m['id_pulse_crm_member']);
            }
        } catch (Exception $e) {
            PulseCoreService::audit('pulsecrm', 'night_audit_hook_failed', array('error' => $e->getMessage()));
        }
    }

    /** A complaint ticket raised anywhere in the suite becomes a service-recovery case here. */
    public function hookActionPulseTicketCreated($p)
    {
        if (empty($p['id_ticket']) || empty($p['department']) || !PulseCrmService::tableExists('pulse_ticket')) {
            return;
        }
        try {
            $t = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'pulse_ticket` WHERE id_pulse_ticket=' . (int) $p['id_ticket']);
            if (!$t || $t['category'] !== 'complaint' || $t['source'] === 'survey') {
                return;
            }
            if ((int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_case` WHERE id_pulse_ticket=' . (int) $p['id_ticket'])) {
                return;
            }
            PulseCrmCase::open(array(
                'source' => 'ticket',
                'severity' => in_array($t['priority'], array('urgent', 'high')) ? 'high' : 'medium',
                'department' => $t['department'],
                'id_customer' => $t['id_customer'],
                'id_htl_booking' => $t['id_htl_booking'],
                'id_room' => $t['id_room'],
                'id_pulse_ticket' => (int) $p['id_ticket'],
                'title' => $t['title'],
                'description' => $t['description']
            ));
        } catch (Exception $e) {
            PulseCoreService::audit('pulsecrm', 'ticket_hook_failed', array('error' => $e->getMessage()));
        }
    }

    public function hookActionPulseCrmMemberEnrolled($p)
    {
    }
    public function hookActionPulseCrmPointsRedeemed($p)
    {
    }
    public function hookActionPulseCrmSurveyCompleted($p)
    {
    }
    public function hookActionPulseCrmCaseOpened($p)
    {
    }
    public function hookActionPulseCrmReviewAdded($p)
    {
    }
}

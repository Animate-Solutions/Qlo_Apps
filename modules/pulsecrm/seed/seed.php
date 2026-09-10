<?php
/**
 * Demo data for Pulse CRM — a 52-room property in Port Harcourt.
 * Creates enriched guest profiles (preferences, occasions, relationships, consent, source of business),
 * loyalty members across three tiers with a real points ledger, four materialised segments, two past
 * campaigns with believable open and click rates, three live journeys with runs in flight, sixty survey
 * responses producing a plausible NPS, five service-recovery cases, twenty-five reviews across the
 * portals, and four corporate accounts with production history.
 * Idempotent: run it as often as you like. It never deletes anything.
 * Usage: php modules/pulsecrm/seed/seed.php   (or in a browser with ?token=<PULSE_CRM_CRON_TOKEN>)
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
if (php_sapi_name() !== 'cli') {
    $token = Tools::getValue('token');
    if (!hash_equals((string) Configuration::get('PULSE_CRM_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
}
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$db = Db::getInstance();
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$summary = array();
mt_srand(52); // a fixed seed so two runs tell the same story

/* ---------- 0. a pool of guests to enrich ---------- */
$names = array(
    array('Chinedu', 'Okafor', 'm'), array('Amaka', 'Nwosu', 'f'), array('Ibrahim', 'Danladi', 'm'), array('Folake', 'Adeyemi', 'f'),
    array('Tamunoemi', 'Briggs', 'm'), array('Ngozi', 'Eze', 'f'), array('Segun', 'Balogun', 'm'), array('Halima', 'Sani', 'f'),
    array('Emeka', 'Obi', 'm'), array('Blessing', 'Etim', 'f'), array('Kelechi', 'Amadi', 'm'), array('Zainab', 'Yusuf', 'f'),
    array('Preye', 'Ogbonna', 'm'), array('Chioma', 'Nnamdi', 'f'), array('Musa', 'Abdullahi', 'm'), array('Temitope', 'Ogundele', 'f'),
    array('Soibi', 'Wokoma', 'm'), array('Adaeze', 'Uche', 'f'), array('Bala', 'Mohammed', 'm'), array('Efe', 'Ovie', 'f'),
    array('Uche', 'Anyanwu', 'm'), array('Yetunde', 'Fashola', 'f'), array('Dumo', 'Georgewill', 'm'), array('Aisha', 'Bello', 'f'),
    array('Obinna', 'Chukwu', 'm'), array('Sarah', 'Peterside', 'f'), array('Gbenga', 'Alabi', 'm'), array('Rita', 'Iheanacho', 'f'),
    array('Nasir', 'Garba', 'm'), array('Ijeoma', 'Okonkwo', 'f'), array('Tonye', 'Amachree', 'm'), array('Funmi', 'Bakare', 'f'),
    array('Chika', 'Madu', 'm'), array('Grace', 'Effiong', 'f'), array('Yakubu', 'Idris', 'm'), array('Ebele', 'Nwachukwu', 'f'),
    array('Ledum', 'Nwiado', 'm'), array('Patience', 'Akpan', 'f'), array('Sadiq', 'Aliyu', 'm'), array('Onyinye', 'Kalu', 'f'),
);
$idLang = (int) Configuration::get('PS_LANG_DEFAULT');
$idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
$guests = array(); $newGuests = 0;
foreach ($names as $i => $n) {
    $email = Tools::strtolower($n[0].'.'.$n[1]).'@pulse-demo.ng';
    $id = (int) $db->getValue('SELECT id_customer FROM `'._DB_PREFIX_.'customer` WHERE email="'.pSQL($email).'"');
    if (!$id) {
        $c = new Customer();
        $c->firstname = $n[0]; $c->lastname = $n[1]; $c->email = $email; $c->passwd = Tools::encrypt(Tools::passwdGen(12));
        $c->id_lang = $idLang; $c->id_shop = $idShop; $c->newsletter = 0; $c->optin = 0; $c->active = 1;
        try { $c->add(); $id = (int) $c->id; $newGuests++; }
        catch (Exception $e) { echo 'Guest '.$email.' skipped: '.$e->getMessage()."\n"; continue; }
    }
    $guests[] = array('id' => $id, 'first' => $n[0], 'last' => $n[1], 'sex' => $n[2], 'email' => $email, 'i' => $i);
}
$summary[] = $newGuests.' guest account(s) created ('.count($guests).' in the demo set)';

/* ---------- 1. Front Desk profile: stays, revenue, VIP, nationality, phone ---------- */
$fd = PulseCrmService::tableExists('pulse_guest_profile');
$phonePrefix = array('0803', '0806', '0813', '0703', '0906', '0810', '0817');
if ($fd) {
    foreach ($guests as $g) {
        PulseGuestProfile::touch($g['id']);
        $stays = 1 + ($g['i'] % 9);
        $nights = $stays * (1 + ($g['i'] % 4));
        $revenue = round($nights * (65000 + ($g['i'] % 7) * 18000), 2);
        $lastStay = date('Y-m-d', strtotime('-'.(3 + $g['i'] * 11).' day'));
        $exists = (int) $db->getValue('SELECT stays FROM `'._DB_PREFIX_.'pulse_guest_profile` WHERE id_customer='.(int) $g['id']);
        if (!$exists) {
            $db->update('pulse_guest_profile', array('stays' => $stays, 'nights' => $nights, 'lifetime_revenue' => $revenue, 'last_stay' => $lastStay,
                'vip_level' => $stays >= 8 ? 2 : ($stays >= 5 ? 1 : 0), 'nationality' => $g['i'] % 11 === 0 ? 'GBR' : ($g['i'] % 7 === 0 ? 'GHA' : 'NGA'),
                'phone' => $phonePrefix[$g['i'] % 7].str_pad((string) (1000000 + $g['i'] * 13457), 7, '0', STR_PAD_LEFT),
                'date_upd' => $now), 'id_customer='.(int) $g['id']);
        }
    }
    $summary[] = 'stay history written onto the Front Desk guest 360 (stays, nights, lifetime revenue, VIP level, phone)';
} else {
    $summary[] = 'Front Desk is not installed — CRM data is seeded, but there is no stay history to hang it on';
}

/* ---------- 2. preferences, occasions, relationships, consent, source of business ---------- */
$prefSets = array(
    array(array('room_position', 'high_floor'), array('pillow', 'firm'), array('newspaper', 'punch')),
    array(array('room_position', 'quiet'), array('allergy', 'dust'), array('dietary', 'no_pepper')),
    array(array('floor', 'ground'), array('transport', 'airport_pickup'), array('amenity', 'extra_water')),
    array(array('room_position', 'away_lift'), array('bed', 'twin'), array('housekeeping', 'evening_clean')),
    array(array('dietary', 'halal'), array('newspaper', 'thisday'), array('amenity', 'kettle')),
    array(array('room_position', 'pool_view'), array('pillow', 'soft'), array('transport', 'own_car')),
    array(array('allergy', 'seafood'), array('housekeeping', 'turndown'), array('amenity', 'iron')),
    array(array('bed', 'king'), array('dietary', 'vegetarian'), array('newspaper', 'none')),
);
$freeform = array('Always asks for room 214 if it is free', 'Prefers the corridor lights off after 22:00', 'Travels with a CPAP machine — needs a socket by the bed',
    'Will not take a room above the third floor', 'Wants the fridge emptied of alcohol before arrival', 'Likes a jug of zobo instead of the welcome cocktail');
$sob = array('direct', 'booking.com', 'corporate', 'walk-in', 'referral', 'travel_agent', 'jumia_travel', 'repeat');
$segs = array('corporate', 'leisure', 'government', 'conference', 'crew');
$newPrefs = 0; $newOcc = 0; $newConsent = 0;
foreach ($guests as $g) {
    $set = $prefSets[$g['i'] % count($prefSets)];
    foreach ($set as $p) {
        if ((int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_preference` WHERE id_customer='.(int) $g['id'].' AND category="'.pSQL($p[0]).'" AND code="'.pSQL($p[1]).'"')) { continue; }
        PulseCrmProfile::savePreference($g['id'], $p[0], '', $p[1], 'registration_card');
        $newPrefs++;
    }
    if ($g['i'] % 5 === 0) {
        $note = $freeform[$g['i'] % count($freeform)];
        if (!(int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_preference` WHERE id_customer='.(int) $g['id'].' AND value="'.pSQL($note).'"')) { PulseCrmProfile::savePreference($g['id'], 'other', $note, null, 'desk'); $newPrefs++; }
    }
    // birthdays spread across the year, a handful of them inside the next fortnight so the arrivals board has something to show
    $bday = $g['i'] % 6 === 0 ? date('Y-m-d', strtotime('+'.($g['i'] % 14).' day')) : date('Y-m-d', strtotime('-'.(20 + $g['i'] * 8).' day'));
    $bday = (1965 + ($g['i'] % 30)).substr($bday, 4);
    if (!(int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_occasion` WHERE id_customer='.(int) $g['id'].' AND type="birthday"')) {
        PulseCrmProfile::saveOccasion($g['id'], 'birthday', $bday, '', 7); $newOcc++;
    }
    if ($g['i'] % 4 === 1 && !(int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_occasion` WHERE id_customer='.(int) $g['id'].' AND type="anniversary"')) {
        $ann = (2005 + ($g['i'] % 15)).substr(date('Y-m-d', strtotime('+'.(($g['i'] * 9) % 300).' day')), 4);
        PulseCrmProfile::saveOccasion($g['id'], 'anniversary', $ann, 'Wedding anniversary — dinner for two on the house', 10); $newOcc++;
    }
    PulseCrmProfile::saveExt($g['id'], array('source_of_business' => $sob[$g['i'] % count($sob)], 'market_segment' => $segs[$g['i'] % count($segs)],
        'guest_type' => $g['i'] % 3 === 0 ? 'oil & gas' : ($g['i'] % 3 === 1 ? 'banking' : 'NGO'), 'preferred_language' => 'en',
        'preferred_channel' => $g['i'] % 5 === 0 ? 'whatsapp' : 'email'));
    // consent: most opted in, a few opted out with a reason, a few never asked
    $state = $g['i'] % 9 === 0 ? 'opt_out' : ($g['i'] % 7 === 0 ? 'unknown' : 'opt_in');
    if (!(int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_consent` WHERE id_customer='.(int) $g['id'].' AND channel="email"')) {
        PulseCrmProfile::setConsent($g['id'], 'email', $state, 'registration_card', 'Signed registration card, box ticked',
            $state === 'opt_out' ? 'Too many emails' : '');
        PulseCrmProfile::setConsent($g['id'], 'sms', $state === 'opt_in' && $g['i'] % 3 !== 0 ? 'opt_in' : 'unknown', 'registration_card', 'Signed registration card');
        PulseCrmProfile::setConsent($g['id'], 'whatsapp', $g['i'] % 5 === 0 ? 'opt_in' : 'unknown', 'front_desk_verbal', 'Asked at check-in');
        $newConsent += 3;
    }
}
// two travelling pairs and one assistant relationship
$pairs = array(array(0, 1, 'travels_with'), array(4, 5, 'spouse'), array(8, 9, 'colleague'), array(12, 13, 'assistant_of'));
$newRel = 0;
foreach ($pairs as $p) {
    if (!isset($guests[$p[0]], $guests[$p[1]])) { continue; }
    if ((int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_relationship` WHERE id_customer='.(int) $guests[$p[0]]['id'].' AND id_related_customer='.(int) $guests[$p[1]]['id'])) { continue; }
    PulseCrmProfile::relate($guests[$p[0]]['id'], $guests[$p[1]]['id'], $p[2], 'Recorded at check-in');
    $newRel++;
}
$summary[] = $newPrefs.' preference(s), '.$newOcc.' occasion(s), '.$newRel.' relationship(s) and '.$newConsent.' consent record(s) written';

/* ---------- 3. loyalty: enrol two thirds, move their points, spread the tiers ---------- */
$program = PulseCrmLoyalty::program();
$newMembers = 0; $txns = 0;
if ($program) {
    $tiers = PulseCrmLoyalty::tiers((int) $program['id_pulse_crm_loyalty_program']);
    foreach ($guests as $g) {
        if ($g['i'] % 3 === 2) { continue; } // a third stay out of the programme
        $existing = PulseCrmLoyalty::memberOf($g['id']);
        $idm = $existing ? (int) $existing['id_pulse_crm_member'] : 0;
        if (!$idm) {
            try { $idm = PulseCrmLoyalty::enrol($g['id'], $g['i'] % 4 === 0 ? 'portal' : 'desk'); $newMembers++; }
            catch (Exception $e) { echo 'Enrolment for '.$g['email'].' skipped: '.$e->getMessage()."\n"; continue; }
            $db->update('pulse_crm_member', array('join_date' => date('Y-m-d', strtotime('-'.(60 + $g['i'] * 9).' day'))), 'id_pulse_crm_member='.(int) $idm);
            // a plausible earning history: a few stays' worth of room and restaurant spend
            $stays = 1 + ($g['i'] % 9);
            for ($s = 0; $s < min(6, $stays); $s++) {
                $when = date('Y-m-d', strtotime('-'.(20 + $s * 47 + $g['i'] * 3).' day'));
                $room = 78000 + (($g['i'] + $s) % 6) * 22000;
                PulseCrmLoyalty::award($idm, 'earn', (int) floor($room / 1000 * 100), 'folio', 'Room charge, stay of '.$when,
                    array('department' => 'rooms', 'amount_basis' => $room, 'reference' => 'ROOM', 'expires_on' => date('Y-m-d', strtotime($when.' +24 month'))));
                $txns++;
                if (($g['i'] + $s) % 2 === 0) {
                    $fnb = 9000 + (($g['i'] + $s) % 5) * 4500;
                    PulseCrmLoyalty::award($idm, 'earn', (int) floor($fnb / 1000 * 60), 'folio', 'Restaurant, stay of '.$when,
                        array('department' => 'fnb', 'amount_basis' => $fnb, 'reference' => 'REST', 'expires_on' => date('Y-m-d', strtotime($when.' +24 month'))));
                    $txns++;
                }
            }
            // one in five has redeemed something
            if ($g['i'] % 5 === 3) {
                $m = PulseCrmLoyalty::member($idm);
                $redeem = min(5000, (int) floor($m['points_balance'] / 1000) * 1000);
                if ($redeem >= (int) $m['min_redeem_points']) { PulseCrmLoyalty::award($idm, 'redeem', $redeem, 'redemption', 'Redeemed against the bill at the desk', array('reference' => 'seed')); $txns++; }
            }
            // a couple of lots already on their way out, so the expiry screen has something on it
            if ($g['i'] % 8 === 4) {
                PulseCrmLoyalty::award($idm, 'bonus', 1500, 'promotion', 'Rainy-season promotion', array('expires_on' => date('Y-m-d', strtotime('+21 day'))));
                $txns++;
            }
        }
        PulseCrmLoyalty::recalcTier($idm, true);
    }
    // make sure all three tiers are populated even if the stay history is thin
    $members = $db->executeS('SELECT id_pulse_crm_member FROM `'._DB_PREFIX_.'pulse_crm_member` ORDER BY points_balance DESC LIMIT 8');
    foreach ($members as $n => $m) {
        $t = $tiers[$n < 2 ? count($tiers) - 1 : ($n < 5 ? min(1, count($tiers) - 1) : 0)];
        $db->update('pulse_crm_member', array('id_pulse_crm_tier' => (int) $t['id_pulse_crm_tier'], 'tier_since' => date('Y-m-d', strtotime('-'.(30 + $n * 20).' day'))), 'id_pulse_crm_member='.(int) $m['id_pulse_crm_member']);
    }
    $summary[] = $newMembers.' loyalty member(s) enrolled across '.count($tiers).' tiers with '.$txns.' points transaction(s)';
} else {
    $summary[] = 'no loyalty programme found — reinstall the module to get Pulse Rewards';
}

/* ---------- 4. segments ---------- */
$segRefreshed = 0; $segNames = array();
foreach (array('first_timers', 'repeat', 'lapsed_12m', 'corporate', 'high_spenders', 'detractors', 'birthdays_30d', 'loyalty_members') as $code) {
    $s = PulseCrmSegment::byCode($code);
    if (!$s) { continue; }
    $n = PulseCrmSegment::refresh((int) $s['id_pulse_crm_segment']);
    $segRefreshed++; $segNames[] = $s['name'].' ('.$n.')';
}
$summary[] = $segRefreshed.' segment(s) materialised: '.implode(', ', array_slice($segNames, 0, 4)).(count($segNames) > 4 ? ' and more' : '');

/* ---------- 5. two past campaigns with realistic engagement ---------- */
$campaignSpecs = array(
    array('name' => 'Rainy season direct offer', 'segment' => 'repeat', 'subject' => 'Two nights, one rate — {first_name}, come back to us',
        'body' => "Dear {first_name},\n\nPort Harcourt in the rains is quieter, and so are we. Book two nights direct this month and the second is at half rate — no code, just mention this email when you call the desk.\n\nYou have stayed with us {stays} times. That means something to a hotel this size.\n\nWarm regards,\nThe front desk",
        'subject_b' => 'A quieter Port Harcourt — half price on your second night',
        'body_b' => "Dear {first_name},\n\nThe rains have thinned the traffic on Aba Road and the hotel with it. Two nights direct this month, the second at half rate.\n\nCall the desk and say you had this note.\n\nWarm regards,\nThe front desk",
        'ab' => 30, 'days_ago' => 34, 'open' => 41, 'click' => 12, 'unsub' => 2),
    array('name' => 'We have missed you', 'segment' => 'lapsed_12m', 'subject' => 'It has been a while, {first_name}',
        'body' => "Dear {first_name},\n\nIt has been a while since we last had you with us — the new wing opened in between, and the generator that used to wake the third floor is gone.\n\nIf work brings you back to Port Harcourt, we would be glad to have you.\n\nWarm regards,\nThe general manager",
        'subject_b' => '', 'body_b' => '', 'ab' => 0, 'days_ago' => 12, 'open' => 28, 'click' => 6, 'unsub' => 3),
);
$newCampaigns = 0;
foreach ($campaignSpecs as $spec) {
    if ((int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_campaign` WHERE name="'.pSQL($spec['name']).'"')) { continue; }
    $seg = PulseCrmSegment::byCode($spec['segment']);
    $idc = PulseCrmCampaign::save(array('name' => $spec['name'], 'channel' => 'email', 'id_pulse_crm_segment' => $seg ? $seg['id_pulse_crm_segment'] : 0,
        'subject' => $spec['subject'], 'body' => $spec['body'], 'subject_b' => $spec['subject_b'], 'body_b' => $spec['body_b'], 'ab_split_pct' => $spec['ab'],
        'schedule_type' => 'manual', 'throttle_per_run' => 100, 'quiet_from' => '21:00', 'quiet_to' => '08:00'));
    $sentAt = date('Y-m-d H:i:s', strtotime('-'.$spec['days_ago'].' day'));
    // Build the recipient rows directly: this is history, not a send we want to perform now.
    $pool = $seg ? PulseCrmSegment::members((int) $seg['id_pulse_crm_segment'], 200) : array();
    if (!$pool) { $pool = array_slice($guests, 0, 14); foreach ($pool as $k => $v) { $pool[$k] = array('id_customer' => $v['id'], 'email' => $v['email']); } }
    $n = 0; $opened = 0; $clicked = 0; $unsub = 0;
    foreach ($pool as $r) {
        $idcust = (int) $r['id_customer'];
        if ($db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_campaign_recipient` WHERE id_pulse_crm_campaign='.(int) $idc.' AND id_customer='.$idcust)) { continue; }
        $may = PulseCrmProfile::mayContact($idcust, 'email');
        $status = 'sent'; $skip = ''; $opens = 0; $clicks = 0;
        $dOpen = null; $dClick = null;
        if ($may !== true) { $status = 'skipped'; $skip = $may; }
        else {
            $roll = ($n * 37) % 100;
            if ($roll < $spec['unsub']) { $status = 'unsubscribed'; $opens = 1; $clicks = 1; $unsub++; }
            elseif ($roll < $spec['click']) { $status = 'clicked'; $opens = 1 + ($n % 3); $clicks = 1; $clicked++; $opened++; }
            elseif ($roll < $spec['open']) { $status = 'opened'; $opens = 1 + ($n % 2); $opened++; }
            if ($opens) { $dOpen = date('Y-m-d H:i:s', strtotime($sentAt.' +'.(2 + $n % 40).' hour')); }
            if ($clicks) { $dClick = date('Y-m-d H:i:s', strtotime($sentAt.' +'.(3 + $n % 40).' hour')); }
        }
        $db->insert('pulse_crm_campaign_recipient', array('id_pulse_crm_campaign' => (int) $idc, 'id_customer' => $idcust,
            'variant' => ($spec['ab'] > 0 && ($n % 100) < $spec['ab']) ? 'b' : 'a', 'to_addr' => pSQL($r['email']), 'token' => PulseCrmService::token(16),
            'status' => $status, 'skip_reason' => pSQL($skip), 'open_count' => $opens, 'click_count' => $clicks,
            'date_queued' => $sentAt, 'date_sent' => $status === 'skipped' ? null : $sentAt, 'date_opened' => $dOpen, 'date_clicked' => $dClick));
        $n++;
    }
    $db->update('pulse_crm_campaign', array('status' => 'sent', 'last_run_at' => $sentAt, 'date_add' => date('Y-m-d H:i:s', strtotime($sentAt.' -1 day')), 'date_upd' => $sentAt), 'id_pulse_crm_campaign='.(int) $idc);
    PulseCrmCampaign::recount($idc);
    $newCampaigns++;
    $summary[] = 'campaign "'.$spec['name'].'": '.$n.' recipients, '.$opened.' opened, '.$clicked.' clicked, '.$unsub.' unsubscribed';
}
if (!$newCampaigns) { $summary[] = 'campaigns already seeded'; }

/* ---------- 6. journeys: make sure three are live and put runs in flight ---------- */
$live = array('welcome', 'thankyou', 'midstay');
$activated = 0;
foreach ($live as $code) {
    $j = PulseCrmJourney::byCode($code);
    if (!$j) { continue; }
    if (!$j['active']) { $db->update('pulse_crm_journey', array('active' => 1, 'date_upd' => $now), 'id_pulse_crm_journey='.(int) $j['id_pulse_crm_journey']); }
    $activated++;
}
$runs = 0;
foreach ($guests as $g) {
    if ($g['i'] % 6 !== 0) { continue; }
    $j = PulseCrmJourney::byCode($live[$g['i'] % 3]);
    if (!$j) { continue; }
    $id = PulseCrmJourney::start((int) $j['id_pulse_crm_journey'], array('id_customer' => $g['id']));
    if ($id) {
        // stagger them so the Journeys screen shows work due over the next few days rather than a wall of "now"
        $db->update('pulse_crm_journey_run', array('next_run_at' => date('Y-m-d H:i:s', strtotime('+'.(($g['i'] % 5) + 1).' day')), 'date_upd' => $now), 'id_pulse_crm_journey_run='.(int) $id);
        $runs++;
    }
}
$summary[] = $activated.' journey(s) live, '.$runs.' run(s) in flight';

/* ---------- 7. sixty survey responses producing a believable NPS ---------- */
$survey = PulseCrmSurvey::byCode('post_stay');
$inStay = PulseCrmSurvey::byCode('in_stay');
$comments = array(
    9 => array('Very comfortable, the staff at reception were excellent. Will be back.', 'Clean room, prompt service, good breakfast. Kudos to the team.', 'Best stay I have had in Port Harcourt. Power never went off once.'),
    8 => array('Good stay overall. Breakfast could be more varied.', 'Comfortable and quiet. Wifi was slow in the evenings.', 'Solid. Nothing wrong, nothing special.'),
    6 => array('Room was fine but the air conditioner was noisy all night.', 'Waited forty minutes for room service. Food was cold when it came.', 'The generator woke me at 2am. Otherwise the room was clean.'),
    4 => array('Bathroom was dirty on arrival and it took two calls to get it cleaned.', 'No water in the morning. Nobody at the desk could tell me when it would come back.', 'Reception was rude when I asked about the late checkout I had been promised.'),
    2 => array('Terrible. No power for three hours, no apology, and the room smelled of damp.', 'Booked a king, given a twin, and told there was nothing they could do.'),
);
$newResponses = 0;
if ($survey) {
    $questions = PulseCrmSurvey::questions((int) $survey['id_pulse_crm_survey']);
    for ($i = 0; $i < 60; $i++) {
        $g = $guests[$i % count($guests)];
        $when = date('Y-m-d', strtotime('-'.(2 + $i * 5).' day'));
        if ((int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_survey_response` WHERE id_customer='.(int) $g['id'].' AND business_date="'.pSQL($when).'"')) { continue; }
        // a distribution that gives roughly NPS +30: about half promoters, a third passives, the rest detractors
        $roll = ($i * 17) % 100;
        $nps = $roll < 48 ? (9 + $i % 2) : ($roll < 80 ? (7 + $i % 2) : ($roll < 94 ? (4 + $i % 3) : (1 + $i % 3)));
        $band = $nps >= 9 ? 9 : ($nps >= 7 ? 8 : ($nps >= 4 ? 6 : ($nps >= 2 ? 4 : 2)));
        $comment = $comments[$band][$i % count($comments[$band])];
        $useSurvey = ($i % 5 === 0 && $inStay) ? $inStay : $survey;
        $qs = PulseCrmSurvey::questions((int) $useSurvey['id_pulse_crm_survey']);
        $inv = PulseCrmSurvey::invite((int) $useSurvey['id_pulse_crm_survey'], $g['id'], null, $i % 7 === 0 ? 'sms' : 'email');
        $answers = array();
        foreach ($qs as $q) {
            if ($q['type'] === 'nps') { $answers[$q['code']] = $nps; }
            elseif ($q['type'] === 'scale5') {
                $base = (int) max(1, min(5, round($nps / 2)));
                // engineering carries the pain in Port Harcourt: power and water score a point lower
                $answers[$q['code']] = $q['department'] === 'engineering' ? max(1, $base - 1) : min(5, $base + (($i + strlen($q['code'])) % 2));
            } elseif ($q['type'] === 'text') { $answers[$q['code']] = $comment; }
            elseif ($q['type'] === 'single' && $q['options']) { $answers[$q['code']] = $q['options'][$i % count($q['options'])]; }
        }
        try {
            PulseCrmSurvey::submit($inv['token'], $answers, '105.112.'.($i % 250).'.'.(($i * 7) % 250));
            $db->update('pulse_crm_survey_response', array('business_date' => pSQL($when), 'completed_at' => pSQL($when.' '.str_pad((string) (8 + $i % 12), 2, '0', STR_PAD_LEFT).':'.str_pad((string) (($i * 13) % 60), 2, '0', STR_PAD_LEFT).':00'),
                'sent_at' => pSQL(date('Y-m-d H:i:s', strtotime($when.' -1 day')))), 'id_pulse_crm_survey_response='.(int) $inv['id_pulse_crm_survey_response']);
            $newResponses++;
        } catch (Exception $e) { /* an already-answered invite is fine to skip */ }
    }
    $r = PulseCrmService::npsFor(date('Y-m-d', strtotime('-400 day')), $today);
    $summary[] = $newResponses.' survey response(s) written — NPS '.$r['nps'].' from '.$r['responses'].' completed ('.$r['promoters'].' promoters, '.$r['detractors'].' detractors)';
}
if (!$survey) { $summary[] = 'no post-stay survey found — reinstall the module'; }

/* ---------- 8. service recovery: five cases, two still open ---------- */
$caseSpecs = array(
    array('dept' => 'engineering', 'sev' => 'high', 'title' => 'No water on the fourth floor from 05:00 to 09:00',
        'desc' => 'Booster pump tripped overnight. Four rooms affected, two guests complained at the desk before breakfast.',
        'root' => 'Booster pump has no auto-restart after a power dip', 'action' => 'comp', 'detail' => 'One night comped for room 407, breakfast comped for the other three rooms',
        'cost' => 78000, 'status' => 'closed', 'note' => 'Auto-restart relay fitted 12 days later. Night porter now checks pressure at 04:00 and logs it.', 'days' => 26),
    array('dept' => 'housekeeping', 'sev' => 'medium', 'title' => 'Room handed over dirty — bathroom not cleaned',
        'desc' => 'Guest arrived to an uncleaned bathroom at 22:40. Room was marked clean in the system.',
        'root' => 'Room marked clean before inspection because the supervisor had gone off shift', 'action' => 'upgrade', 'detail' => 'Moved to a suite for the two nights, fruit basket',
        'cost' => 45000, 'status' => 'closed', 'note' => 'Inspection now required before a room can go to clean; the checklist is on the housekeeping tablet.', 'days' => 18),
    array('dept' => 'fnb', 'sev' => 'medium', 'title' => 'Room service forty minutes late and cold',
        'desc' => 'Guest ordered at 20:10, food arrived at 20:55 and was cold. Kitchen had one hand on shift.',
        'root' => 'Single kitchen hand rostered on a night with two functions in the hall', 'action' => 'comp', 'detail' => 'Meal comped, dinner for two offered on the next stay',
        'cost' => 24500, 'status' => 'closed', 'note' => 'Roster rule: never fewer than two in the kitchen when the hall is booked.', 'days' => 9),
    array('dept' => 'frontdesk', 'sev' => 'high', 'title' => 'Guaranteed late checkout not honoured',
        'desc' => 'Corporate guest was promised 16:00 checkout at booking; housekeeping knocked at 12:00 and reception charged a late fee.',
        'root' => '', 'action' => 'none', 'detail' => '', 'cost' => 0, 'status' => 'investigating', 'note' => '', 'days' => 2),
    array('dept' => 'engineering', 'sev' => 'critical', 'title' => 'Lift stuck between floors with a guest inside for 20 minutes',
        'desc' => 'Guest trapped in the guest lift during a changeover to generator. Released by the technician. Guest was shaken but unhurt.',
        'root' => '', 'action' => 'none', 'detail' => '', 'cost' => 0, 'status' => 'open', 'note' => '', 'days' => 1),
);
$newCases = 0;
foreach ($caseSpecs as $n => $spec) {
    if ((int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_case` WHERE title="'.pSQL($spec['title']).'"')) { continue; }
    $g = $guests[($n * 6) % count($guests)];
    $id = PulseCrmCase::open(array('source' => $n % 2 ? 'survey' : 'staff', 'severity' => $spec['sev'], 'department' => $spec['dept'],
        'id_customer' => $g['id'], 'title' => $spec['title'], 'description' => $spec['desc']));
    $opened = date('Y-m-d H:i:s', strtotime('-'.$spec['days'].' day'));
    $u = array('opened_at' => $opened, 'business_date' => date('Y-m-d', strtotime('-'.$spec['days'].' day')),
        'root_cause' => pSQL($spec['root']), 'recovery_action' => pSQL($spec['action']), 'recovery_detail' => pSQL($spec['detail']),
        'recovery_cost' => (float) $spec['cost'], 'status' => pSQL($spec['status']), 'closing_note' => pSQL($spec['note'], true),
        'sla_due' => date('Y-m-d H:i:s', strtotime($opened.' +4 hour')), 'date_upd' => $now);
    if ($spec['status'] === 'closed') { $u['closed_at'] = date('Y-m-d H:i:s', strtotime('-'.max(0, $spec['days'] - 2).' day')); }
    $db->update('pulse_crm_case', $u, 'id_pulse_crm_case='.(int) $id);
    $newCases++;
}
$summary[] = $newCases.' service-recovery case(s) written (two left open on purpose, with the SLA already running)';

/* ---------- 9. twenty-five reviews across the portals ---------- */
$reviewSpecs = array(
    array('google', 5, 'Excellent stay', 'Very comfortable room, staff were friendly and the power never went off. Breakfast was good. Will stay again when I am in PH.', 'Chidi A.', 3),
    array('google', 4, 'Good value', 'Clean and quiet. Wifi struggled in the evening but everything else was fine.', 'Maryam I.', 6),
    array('google', 5, '', 'Very good. The reception staff went out of their way to get me a car to the airport at 5am.', 'Tunde O.', 9),
    array('google', 2, 'Disappointed', 'No water in the morning and nobody at the desk could tell me when it would come back. The room itself was fine.', 'Ngozi C.', 12),
    array('google', 4, 'Comfortable', 'Solid business hotel. Good location for Trans Amadi. Restaurant is a bit slow at lunch.', 'Emeka N.', 15),
    array('tripadvisor', 5, 'A find in Port Harcourt', 'I have stayed in most of the business hotels in PH and this is now my default. Rooms are properly cleaned and the generator is quiet.', 'BizTraveller_Lagos', 4),
    array('tripadvisor', 4, 'Good, with one gripe', 'Room and staff excellent. The air conditioner in 312 rattles — ask for a different room.', 'Adaeze_travels', 8),
    array('tripadvisor', 3, 'Average', 'Fine for a night. Breakfast is the same every day and the coffee is instant.', 'JMorrison_UK', 14),
    array('tripadvisor', 5, 'Very well run', 'Checked in at midnight and was in my room in four minutes. That never happens.', 'PHoilman', 20),
    array('tripadvisor', 1, 'Avoid', 'Booked a king, given a twin, told there was nothing they could do. Then the power went for three hours.', 'DisappointedGuest22', 25),
    array('booking', 9.2, 'Superb', 'Liked: the staff, the quiet, the breakfast. Disliked: nothing worth mentioning.', 'Ibrahim', 2),
    array('booking', 8.8, 'Very good', 'Liked: clean rooms, strong shower. Disliked: wifi in the evenings.', 'Grace', 5),
    array('booking', 7.5, 'Good', 'Liked: location. Disliked: the restaurant took a long time on a busy night.', 'Peter', 11),
    array('booking', 9.6, 'Exceptional', 'Liked: everything. The manager checked on us personally.', 'Folake', 16),
    array('booking', 4.6, 'Poor', 'Liked: the bed. Disliked: the bathroom was not clean on arrival and it took two calls to fix.', 'Anonymous', 19),
    array('booking', 8.3, 'Very good', 'Liked: fast check-in, good air conditioning. Disliked: parking is tight when the hall is booked.', 'Sadiq', 23),
    array('expedia', 4, 'Reliable', 'Third stay this year. Consistent, which is what I want from a business hotel.', 'K. Amadi', 7),
    array('expedia', 5, 'Great team', 'The front desk remembered my room preference from the last visit. Small thing, big difference.', 'R. Iheanacho', 13),
    array('expedia', 3, 'Fine', 'Room was fine, breakfast was thin. No complaints otherwise.', 'M. Garba', 21),
    array('agoda', 8.7, 'Very good', 'Comfortable and safe. Would recommend for solo travellers.', 'Blessing E.', 10),
    array('agoda', 9.1, 'Excellent', 'Good value for Port Harcourt. Quiet at night despite the main road.', 'Preye O.', 17),
    array('hotels_ng', 4, 'Good hotel', 'Nice place, good service. The pool could be cleaner.', 'Uche A.', 22),
    array('hotels_ng', 5, 'Highly recommended', 'Stayed four nights for a conference. Everything worked. Staff are proud of the place and it shows.', 'Yetunde F.', 28),
    array('facebook', 5, '', 'Best jollof in a hotel restaurant in PH. I will not be taking questions.', 'Tonye A.', 30),
    array('facebook', 2, '', 'Waited an hour to check in because the system was down. Nobody offered so much as a chair.', 'Efe O.', 33),
);
$newReviews = 0;
foreach ($reviewSpecs as $n => $rv) {
    $ext = 'seed-'.$rv[0].'-'.$n;
    if ((int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_review` WHERE source="'.pSQL($rv[0]).'" AND external_id="'.pSQL($ext).'"')) { continue; }
    $res = PulseCrmReview::save(array('source' => $rv[0], 'external_id' => $ext, 'rating' => $rv[1], 'title' => $rv[2], 'body' => $rv[3], 'author' => $rv[4],
        'review_date' => date('Y-m-d', strtotime('-'.($rv[5] * 7).' day')), 'language' => 'en', 'url' => 'https://example.invalid/review/'.$ext));
    // most, but not all, have been answered — the queue should not be empty
    if ($res['created'] && $n % 4 !== 1) {
        $db->update('pulse_crm_review', array('responded' => 1, 'response_text' => pSQL('Thank you for taking the time to write. '.($rv[1] >= 4 || $rv[1] >= 8 ? 'We are glad the stay worked for you and we look forward to having you again.' : 'You are right, and we are sorry. The department concerned has been spoken to and the fix is in place. Please ask for the duty manager on your next stay.'), true),
            'responded_at' => pSQL(date('Y-m-d H:i:s', strtotime('-'.max(0, $rv[5] * 7 - (1 + $n % 3)).' day'))), 'responded_by' => (int) Context::getContext()->employee->id ?: null,
            'date_upd' => $now), 'id_pulse_crm_review='.(int) $res['id']);
    }
    if ($res['created']) { $newReviews++; }
}
$summary[] = $newReviews.' review(s) imported across Google, TripAdvisor, Booking.com, Expedia, Agoda, Hotels.ng and Facebook';

/* ---------- 10. four corporate accounts with contacts, activity, pipeline and rates ---------- */
$accountSpecs = array(
    array('name' => 'Riverstate Energy Services Ltd', 'industry' => 'oil & gas', 'segment' => 'corporate', 'status' => 'active', 'nights' => 620, 'value' => 52000000,
        'contact' => array('Ledum Nwiado', 'Travel Manager', 'ledum.nwiado@riverstate-demo.ng', '08033120045', 'decision_maker'),
        'rate' => 82000, 'opps' => array(array('2026 accommodation contract renewal', 'negotiation', 620, 52000000, 70))),
    array('name' => 'Delta Bank Plc — South-South', 'industry' => 'banking', 'segment' => 'corporate', 'status' => 'active', 'nights' => 310, 'value' => 24800000,
        'contact' => array('Amaka Nwosu', 'Regional Admin Head', 'amaka.nwosu@deltabank-demo.ng', '08061340098', 'booker'),
        'rate' => 76000, 'opps' => array(array('Audit team block, Q2', 'proposal', 90, 6800000, 50))),
    array('name' => 'Rivers State Ministry of Works', 'industry' => 'government', 'segment' => 'government', 'status' => 'active', 'nights' => 185, 'value' => 13000000,
        'contact' => array('Dumo Georgewill', 'Protocol Officer', 'protocol@rsmow-demo.ng', '08131200761', 'influencer'),
        'rate' => 68000, 'opps' => array(array('Contractors conference, 40 rooms x 3 nights', 'qualified', 120, 8400000, 40))),
    array('name' => 'MediRelief International (NGO)', 'industry' => 'NGO', 'segment' => 'ngo', 'status' => 'prospect', 'nights' => 0, 'value' => 0,
        'contact' => array('Sarah Peterside', 'Logistics Coordinator', 'sarah.p@medirelief-demo.org', '07031290334', 'booker'),
        'rate' => 62000, 'opps' => array(array('Field team long-stay, 6 rooms x 90 nights', 'lead', 540, 33500000, 20))),
);
$activityKinds = array(array('visit', 'Site visit and show-round', 'Toured the new wing and the conference hall'),
    array('call', 'Quarterly rate discussion', 'They want the 2025 rate held through Q1'),
    array('email', 'Sent the corporate proposal', 'Proposal with the three-tier rate grid'),
    array('meeting', 'Contract review at their office', 'Finance want net-30 rather than net-14'));
$newAccounts = 0; $newActivities = 0; $newOpps = 0;
foreach ($accountSpecs as $n => $spec) {
    $idAcc = (int) $db->getValue('SELECT id_pulse_crm_account FROM `'._DB_PREFIX_.'pulse_crm_account` WHERE name="'.pSQL($spec['name']).'"');
    if (!$idAcc) {
        $idCompany = 0;
        if (PulseCrmService::tableExists('pulse_company')) {
            $idCompany = (int) $db->getValue('SELECT id_pulse_company FROM `'._DB_PREFIX_.'pulse_company` WHERE name="'.pSQL($spec['name']).'"');
            if (!$idCompany && class_exists('PulseCompany')) {
                $co = new PulseCompany();
                $co->name = $spec['name']; $co->type = $spec['segment'] === 'government' ? 'corporate' : $spec['segment'];
                $co->contact_name = $spec['contact'][0]; $co->email = $spec['contact'][2]; $co->phone = $spec['contact'][3];
                $co->address = 'Trans Amadi Industrial Layout, Port Harcourt'; $co->tin = '2'.str_pad((string) (1000000 + $n * 137), 7, '0', STR_PAD_LEFT).'-0001';
                $co->credit_limit = $spec['value'] > 0 ? round($spec['value'] / 12, 2) : 2000000; $co->discount_pct = 10 + $n * 2; $co->active = 1;
                try { $co->add(); $idCompany = (int) $co->id; } catch (Exception $e) { $idCompany = 0; }
            }
        }
        $idAcc = PulseCrmCorporate::saveAccount(array('id_pulse_company' => $idCompany, 'name' => $spec['name'], 'industry' => $spec['industry'],
            'segment' => $spec['segment'], 'account_manager' => (int) Context::getContext()->employee->id, 'status' => $spec['status'],
            'potential_nights' => $spec['nights'], 'potential_value' => $spec['value'], 'next_review' => date('Y-m-d', strtotime('+'.(30 + $n * 15).' day')),
            'notes' => 'Seeded demo account. Rate grid agreed for the calendar year; production is reported against last year on the Production tab.'));
        $newAccounts++;
        PulseCrmCorporate::saveContact(array('id_pulse_crm_account' => $idAcc, 'name' => $spec['contact'][0], 'title' => $spec['contact'][1],
            'email' => $spec['contact'][2], 'phone' => $spec['contact'][3], 'decision_role' => $spec['contact'][4], 'is_primary' => 1,
            'notes' => 'Primary contact for room bookings and the annual rate letter'));
        // attach a few travellers to the company so production has something to report
        if ($idCompany && PulseCrmService::tableExists('pulse_guest_profile')) {
            foreach (array_slice($guests, $n * 5, 5) as $g) { $db->update('pulse_guest_profile', array('id_pulse_company' => $idCompany, 'date_upd' => $now), 'id_customer='.(int) $g['id']); }
        }
        foreach ($activityKinds as $k => $a) {
            if (($k + $n) % 4 === 3 && $n !== 0) { continue; }
            PulseCrmCorporate::logActivity(array('id_pulse_crm_account' => $idAcc, 'type' => $a[0], 'subject' => $a[1], 'notes' => $a[2],
                'outcome' => $k === 3 ? 'Awaiting their finance sign-off' : 'Positive',
                'activity_date' => date('Y-m-d H:i:s', strtotime('-'.(7 + $k * 21 + $n * 4).' day')),
                'follow_up_at' => $k === 1 ? date('Y-m-d H:i:s', strtotime('+'.(2 + $n).' day')) : null));
            $newActivities++;
        }
        foreach ($spec['opps'] as $o) {
            PulseCrmCorporate::saveOpportunity(array('id_pulse_crm_account' => $idAcc, 'name' => $o[0], 'stage' => $o[1], 'expected_nights' => $o[2],
                'expected_value' => $o[3], 'probability' => $o[4], 'close_date' => date('Y-m-d', strtotime('+'.(21 + $n * 20).' day')),
                'owner' => (int) Context::getContext()->employee->id, 'notes' => 'Seeded demo opportunity'));
            $newOpps++;
        }
        PulseCrmCorporate::saveRate(array('id_pulse_crm_account' => $idAcc, 'id_product' => 0, 'room_type_name' => 'Standard / Executive',
            'rate_tax_excl' => $spec['rate'], 'includes_breakfast' => 1, 'valid_from' => date('Y-01-01'), 'valid_to' => date('Y-12-31'),
            'note' => 'Contracted rate, breakfast included, VAT and consumption tax on top'));
    }
}
$summary[] = $newAccounts.' corporate account(s) with contacts, '.$newActivities.' activity log entries, '.$newOpps.' opportunities and a contracted rate each';

/* ---------- 11. refresh segments once more so the new data is materialised ---------- */
$r = PulseCrmSegment::refreshAll(50);
$summary[] = 'segments re-materialised: '.$r['segments'].' segments, '.$r['members'].' memberships';

/* ---------- done ---------- */
echo "Pulse CRM demo data — Port Harcourt, 52 rooms\n";
foreach ($summary as $s) { echo ' · '.$s."\n"; }
echo "Open CRM ▸ CRM for the dashboard. Guests ▸ search a name to see the 360; Service Recovery has two cases waiting;\n";
echo "Reviews ▸ Needs a reply has the unanswered ones; Corporate ▸ Production compares this year with last.\n";

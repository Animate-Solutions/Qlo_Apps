<?php
/**
 * Stay feedback captured on the check-out screen. It lands in one flat table the CRM module can read
 * (pulse_gp_feedback, crm_synced flag left for it), and a poor score raises a ticket while the guest is
 * still in the building — which is the only time a hotel can actually fix anything.
 */
class PulseGpFeedback
{
    public static function save(array $device, array $session, array $d)
    {
        $b = PulseGpService::booking((int) $session['id_htl_booking']);
        $r = function ($v) { return max(0, min(5, (int) $v)); };
        $row = array(
            'id_room' => $device['id_room'] ? (int) $device['id_room'] : null, 'room_num' => pSQL($device['room_num']),
            'id_htl_booking' => (int) $session['id_htl_booking'], 'id_customer' => (int) $session['id_customer'],
            'guest_name' => pSQL($b ? $b['guest'] : $session['guest_name']), 'email' => pSQL($b ? $b['email'] : ''),
            'rating_overall' => $r(isset($d['overall']) ? $d['overall'] : 0), 'rating_room' => $r(isset($d['room']) ? $d['room'] : 0),
            'rating_service' => $r(isset($d['service']) ? $d['service'] : 0), 'rating_fnb' => $r(isset($d['fnb']) ? $d['fnb'] : 0),
            'rating_cleanliness' => $r(isset($d['cleanliness']) ? $d['cleanliness'] : 0),
            'nps' => isset($d['nps']) ? max(0, min(10, (int) $d['nps'])) : null, 'would_return' => empty($d['would_return']) ? 0 : 1,
            'comment' => pSQL(Tools::substr((string) (isset($d['comment']) ? $d['comment'] : ''), 0, 2000), true),
            'locale' => pSQL($session['locale']), 'source' => 'portal', 'business_date' => pSQL(PulseGpService::bd()), 'date_add' => date('Y-m-d H:i:s'),
        );
        Db::getInstance()->insert('pulse_gp_feedback', $row);
        $id = (int) Db::getInstance()->Insert_ID();
        $low = (int) PulseGpService::cfg('FEEDBACK_ALERT_AT', 3);
        if ($row['rating_overall'] > 0 && $row['rating_overall'] <= $low) {
            if (class_exists('PulseTicket')) {
                PulseTicket::create(array('category' => 'complaint', 'department' => 'frontdesk', 'priority' => 'high',
                    'title' => 'Low portal rating ('.$row['rating_overall'].'/5) — room '.$device['room_num'],
                    'description' => 'Guest '.$row['guest_name'].' rated the stay '.$row['rating_overall'].'/5 on the in-room TV.'.(!empty($d['comment']) ? ' "'.Tools::substr($d['comment'], 0, 400).'"' : ''),
                    'id_room' => $device['id_room'], 'id_htl_booking' => (int) $session['id_htl_booking'], 'id_customer' => (int) $session['id_customer'], 'source' => 'portal'));
            }
            if (class_exists('PulseTrace')) { PulseTrace::add('alert', 'Room '.$device['room_num'].' rated the stay '.$row['rating_overall'].'/5 on the TV', date('Y-m-d H:i:s'), (int) $session['id_htl_booking'], (int) $device['id_room'], null, 'frontdesk'); }
        }
        PulseGpService::audit('feedback', array('overall' => $row['rating_overall']), 'pulse_gp_feedback', $id);
        PulseGpService::event('actionPulsePortalFeedback', array('id_feedback' => $id, 'overall' => $row['rating_overall'], 'id_htl_booking' => (int) $session['id_htl_booking']));
        return $id;
    }

    public static function given($idBooking) { return (bool) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_gp_feedback` WHERE id_htl_booking='.(int) $idBooking); }
    public static function recent($limit = 50) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_feedback` ORDER BY id_pulse_gp_feedback DESC LIMIT '.(int) $limit); }
    /** Averages and NPS for the dashboard; NPS is promoters minus detractors as a whole percentage. */
    public static function stats($from, $to)
    {
        $s = Db::getInstance()->getRow('SELECT COUNT(*) n, ROUND(AVG(NULLIF(rating_overall,0)),2) overall, ROUND(AVG(NULLIF(rating_room,0)),2) room, ROUND(AVG(NULLIF(rating_service,0)),2) service,
            ROUND(AVG(NULLIF(rating_fnb,0)),2) fnb, ROUND(AVG(NULLIF(rating_cleanliness,0)),2) cleanliness,
            SUM(nps>=9) promoters, SUM(nps<=6 AND nps IS NOT NULL) detractors, SUM(nps IS NOT NULL) rated
            FROM `'._DB_PREFIX_.'pulse_gp_feedback` WHERE business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"');
        $s['nps'] = (int) $s['rated'] > 0 ? (int) round((((int) $s['promoters'] - (int) $s['detractors']) / (int) $s['rated']) * 100) : null;
        return $s;
    }
}

<?php
/**
 * The default adapter: a software clocking device. It reads the published roster and generates the punches
 * those staff would actually have made — early birds, a couple of late arrivals a week, the odd missed
 * clock-out — so the whole module (pairing, rounding, overtime, exceptions, timesheets, approval) demos and
 * can be trained on with no hardware in the building.
 *
 * The generator is deterministic: the jitter comes from a hash of (staff, date), never from rand(). Pulling
 * twice therefore produces byte-identical punches, which the dedupe hash then collapses — exactly what a real
 * device does when it re-sends its log after a network glitch.
 */
class PulseTaSimulator extends PulseTaDeviceBase
{
    protected $vendor = 'simulator';

    /** Stable pseudo-random integer in [0,$mod) from a string seed. */
    protected function jitter($seed, $mod) { return $mod > 0 ? (int) (hexdec(Tools::substr(md5($this->dev['serial'].'|'.$seed), 0, 6)) % $mod) : 0; }

    public function testConnection()
    {
        $staff = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_staff` WHERE status="active"');
        return array('ok' => true, 'firmware' => 'pulse-simulator 1.0', 'model' => 'Pulse Simulator', 'serial' => $this->dev['serial'],
            'users' => $staff, 'punches' => 0, 'device_time' => $this->deviceNow(), 'drift_sec' => 0,
            'message' => 'Simulator ready — punches are generated from the published roster for '.$staff.' active staff, no hardware needed.');
    }

    public function syncTime() { return array('ok' => true, 'before' => $this->deviceNow(), 'after' => $this->deviceNow(), 'timezone' => $this->dev['timezone']); }

    /**
     * Generate punches from $since to now. Behaviour knobs live in the device options:
     *  late_pct (default 18), missing_out_pct (6), absent_pct (3), early_min (25), ot_pct (12).
     */
    public function pullPunches($since)
    {
        $from = $since ? date('Y-m-d', strtotime($since)) : date('Y-m-d', strtotime('-1 day'));
        $to = date('Y-m-d');
        if (strtotime($from) < strtotime('-60 day')) { $from = date('Y-m-d', strtotime('-60 day')); }
        $latePct = (int) $this->opt('late_pct', 18); $missPct = (int) $this->opt('missing_out_pct', 6);
        $absentPct = (int) $this->opt('absent_pct', 3); $earlyMin = (int) $this->opt('early_min', 25); $otPct = (int) $this->opt('ot_pct', 12);
        $rows = Db::getInstance()->executeS('SELECT r.roster_date, r.day_type, s.id_pulse_ta_staff, e.device_user_id, sh.start_time, sh.end_time, sh.crosses_midnight, sh.break_punched, sh.break_minutes
            FROM `'._DB_PREFIX_.'pulse_ta_roster` r
            INNER JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=r.id_pulse_ta_staff AND s.status="active"
            INNER JOIN `'._DB_PREFIX_.'pulse_ta_enrolment` e ON e.id_pulse_ta_staff=s.id_pulse_ta_staff AND e.id_pulse_ta_device='.(int) $this->dev['id_pulse_ta_device'].' AND e.status<>"removed"
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_shift` sh ON sh.id_pulse_ta_shift=r.id_pulse_ta_shift
            WHERE r.day_type="work" AND r.published=1 AND r.roster_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY r.roster_date, s.id_pulse_ta_staff');
        $out = array(); $now = time();
        foreach ((array) $rows as $r) {
            if (!$r['start_time']) { continue; }
            $seed = $r['id_pulse_ta_staff'].'|'.$r['roster_date'];
            if ($this->jitter($seed.'|absent', 100) < $absentPct) { continue; }
            $start = strtotime($r['roster_date'].' '.$r['start_time']);
            $end = strtotime($r['roster_date'].' '.$r['end_time']) + ((int) $r['crosses_midnight'] ? 86400 : 0);
            $late = $this->jitter($seed.'|late', 100) < $latePct;
            $in = $start + ($late ? 60 * (5 + $this->jitter($seed.'|lm', 45)) : -60 * $this->jitter($seed.'|em', $earlyMin));
            if ($in > $now) { continue; }
            $out[] = $this->punch($r['device_user_id'], date('Y-m-d H:i:s', $in), 0, 1, '', 'sim:in');
            if ((int) $r['break_punched'] && (int) $r['break_minutes'] > 0) {
                $bo = $start + (int) (($end - $start) / 2);
                $bi = $bo + 60 * (int) $r['break_minutes'];
                if ($bi < $now) { $out[] = $this->punch($r['device_user_id'], date('Y-m-d H:i:s', $bo), 2, 1, '', 'sim:break_out'); $out[] = $this->punch($r['device_user_id'], date('Y-m-d H:i:s', $bi), 3, 1, '', 'sim:break_in'); }
            }
            $ot = $this->jitter($seed.'|ot', 100) < $otPct ? 60 * (30 + $this->jitter($seed.'|otm', 120)) : 0;
            $outAt = $end + $ot + 60 * $this->jitter($seed.'|om', 12);
            if ($outAt > $now) { continue; }
            if ($this->jitter($seed.'|miss', 100) < $missPct) { continue; } // the finger that did not read — this is what the exception queue is for
            $out[] = $this->punch($r['device_user_id'], date('Y-m-d H:i:s', $outAt), 1, 1, '', 'sim:out');
        }
        return $this->after($out, $since);
    }

    public function pushUser(array $employee) { return array('ok' => true, 'device_user_id' => isset($employee['device_user_id']) ? $employee['device_user_id'] : '', 'note' => 'Simulator accepted the enrolment.'); }
    public function deleteUser($deviceUserId) { return array('ok' => true); }

    public function pullUsers()
    {
        $rows = Db::getInstance()->executeS('SELECT e.device_user_id, CONCAT(s.firstname," ",s.lastname) name, e.card_no, e.privilege FROM `'._DB_PREFIX_.'pulse_ta_enrolment` e INNER JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=e.id_pulse_ta_staff WHERE e.id_pulse_ta_device='.(int) $this->dev['id_pulse_ta_device'].' AND e.status<>"removed"');
        $out = array();
        foreach ((array) $rows as $r) { $out[] = array('device_user_id' => $r['device_user_id'], 'name' => $r['name'], 'card_no' => $r['card_no'], 'privilege' => (int) $r['privilege'], 'has_finger' => 1, 'has_face' => 0, 'has_card' => $r['card_no'] ? 1 : 0, 'has_password' => 0); }
        return $out;
    }

    public function clearLog() { return array('ok' => true); }

    public function deviceInfo()
    {
        return array('vendor' => 'simulator', 'name' => 'Pulse Simulator', 'serial' => $this->dev['serial'], 'firmware' => 'pulse-simulator 1.0',
            'users' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_enrolment` WHERE id_pulse_ta_device='.(int) $this->dev['id_pulse_ta_device']),
            'punches' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_punch` WHERE id_pulse_ta_device='.(int) $this->dev['id_pulse_ta_device']));
    }

    public function capabilities()
    {
        return array('vendor' => 'Pulse Simulator (no hardware)', 'pull' => true, 'push_endpoint' => false, 'sync_time' => true, 'push_user' => true, 'delete_user' => true,
            'pull_users' => true, 'clear_log' => true, 'card' => true, 'face' => true, 'palm' => false, 'realtime' => false, 'work_codes' => false, 'default_port' => 0);
    }
}

<?php
/**
 * /pulse/api/time/{resource}/{id} — the kiosk and the staff portal (scope `ess`), supervisor dashboards
 * (scope `time`), department heads approving a period (scope `manager`) and Payroll reading locked
 * timesheets (scope `payroll`).
 *
 * Nothing here returns device credentials, and a punch posted through this API is stored with source
 * `mobile` or `manual` and is visible as such on every screen — a punch that did not come off a reader is
 * never allowed to look like one that did.
 */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulsetime/classes/autoload.php';

class PulseTimeApiModuleFrontController extends PulseApiController
{
    protected $resources = array('ping' => 'ping', 'punch' => 'punch', 'board' => 'board', 'timesheet' => 'timesheet',
        'exceptions' => 'exceptions', 'device_health' => 'deviceHealth', 'payroll_extract' => 'payrollExtract', 'roster' => 'roster');

    protected function ping()
    {
        return array('module' => 'pulsetime', 'version' => class_exists('PulseTime') ? PulseTime::VERSION : '1.0.0',
            'business_date' => PulseTaService::bd(), 'adapters' => array_keys(PulseTaDevice::adapters()),
            'push_endpoint' => PulseTaService::pushUrl(), 'push_enabled' => PulseTaAdms::enabled() ? 1 : 0,
            'hr' => PulseTaService::hr() ? 1 : 0, 'pos' => PulseTaService::pos() ? 1 : 0);
    }

    /**
     * Kiosk or mobile punch. Body: staff_no (or id_staff), direction, at (optional, defaults to now),
     * latitude, longitude, accuracy_m, source ('mobile' | 'manual').
     * A mobile punch is geofenced when a site latitude/longitude is configured; a kiosk punch is not.
     */
    protected function punch($id, $body)
    {
        $this->requireScope('ess');
        $staff = null;
        if ($id) { $staff = PulseTaService::staff($id); }
        elseif (!empty($body['staff_no'])) { $staff = PulseTaService::staffByNo((string) $body['staff_no']); }
        if (!$staff) { throw new PrestaShopException('Staff member not found', 404); }
        if ($staff['status'] === 'exited') { throw new PrestaShopException('That staff member has left', 403); }
        $source = (isset($body['source']) && $body['source'] === 'manual') ? 'manual' : 'mobile';
        if ($source === 'mobile' && !(int) PulseTaService::cfg('MOBILE_PUNCH', 1)) { throw new PrestaShopException('Mobile punching is switched off for this property', 403); }
        $at = !empty($body['at']) ? (string) $body['at'] : date('Y-m-d H:i:s');
        if (!strtotime($at)) { throw new PrestaShopException('Invalid punch time', 400); }
        // A phone's clock is not evidence: a punch may not be back-dated or future-dated through this API.
        $drift = abs(strtotime($at) - time());
        if ($drift > 300) { $at = date('Y-m-d H:i:s'); }
        $lat = isset($body['latitude']) ? (float) $body['latitude'] : null;
        $lng = isset($body['longitude']) ? (float) $body['longitude'] : null;
        $geo = $this->geofence($source, $lat, $lng);
        $dir = isset($body['direction']) && in_array($body['direction'], array('in', 'out', 'break_out', 'break_in'), true) ? $body['direction'] : 'unknown';
        $idPunch = PulseTaPunch::manual((int) $staff['id_pulse_ta_staff'], $at, $dir, $source, array(
            'latitude' => $lat, 'longitude' => $lng, 'accuracy_m' => isset($body['accuracy_m']) ? (int) $body['accuracy_m'] : null,
            'device_serial' => $source === 'mobile' ? 'MOBILE' : 'KIOSK',
            'raw' => $source.' punch'.($geo['inside'] === null ? '' : ($geo['inside'] ? ' inside the geofence' : ' OUTSIDE the geofence, '.$geo['distance_m'].'m from site')),
        ));
        if ($geo['inside'] === false) {
            PulseTaExceptionQueue::raise((int) $staff['id_pulse_ta_staff'], date('Y-m-d', strtotime($at)), 'pos_mismatch', 'warn',
                'Mobile punch recorded '.$geo['distance_m'].'m from the site (fence is '.$geo['radius_m'].'m) — confirm with the supervisor.', $geo['distance_m'], null, $idPunch ?: null, 'geo'.$idPunch);
        }
        PulseTaEngine::buildOne((int) $staff['id_pulse_ta_staff'], date('Y-m-d', strtotime($at)));
        return array('id_punch' => (int) $idPunch, 'duplicate' => $idPunch ? 0 : 1, 'punched_at' => date('Y-m-d H:i:s', strtotime($at)),
            'direction' => $dir, 'source' => $source, 'clock_skew_corrected' => $drift > 300 ? 1 : 0, 'geofence' => $geo,
            'staff' => array('id' => (int) $staff['id_pulse_ta_staff'], 'staff_no' => $staff['staff_no'], 'name' => trim($staff['firstname'].' '.$staff['lastname'])));
    }

    /** Great-circle distance against the configured site, when one is configured. */
    protected function geofence($source, $lat, $lng)
    {
        $sLat = (string) PulseTaService::cfg('SITE_LAT', '');
        $sLng = (string) PulseTaService::cfg('SITE_LNG', '');
        $radius = (int) PulseTaService::cfg('GEOFENCE_M', 250);
        if ($source !== 'mobile' || $sLat === '' || $sLng === '' || $lat === null || $lng === null) { return array('inside' => null, 'distance_m' => null, 'radius_m' => $radius); }
        $r = 6371000.0;
        $d1 = deg2rad((float) $sLat - $lat); $d2 = deg2rad((float) $sLng - $lng);
        $a = sin($d1 / 2) * sin($d1 / 2) + cos(deg2rad($lat)) * cos(deg2rad((float) $sLat)) * sin($d2 / 2) * sin($d2 / 2);
        $dist = (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
        return array('inside' => $dist <= $radius, 'distance_m' => $dist, 'radius_m' => $radius);
    }

    /** Who is in, right now. The 6 a.m. screen. */
    protected function board($id, $body)
    {
        $this->requireScope('time');
        $dept = isset($body['department']) ? (string) $body['department'] : (string) Tools::getValue('department', '');
        return array('business_date' => PulseTaService::bd(), 'counters' => PulseTaService::dashboard(), 'staff' => PulseTaService::board($dept));
    }

    /** One person's timesheet (id = staff) or a range roll-up. */
    protected function timesheet($id, $body)
    {
        $from = !empty($body['from']) ? (string) $body['from'] : date('Y-m-01');
        $to = !empty($body['to']) ? (string) $body['to'] : PulseTaService::bd();
        if ($id) {
            $this->requireScope('ess');
            $rows = PulseTaTimesheet::grid($from, $to);
            $mine = array();
            foreach ($rows as $r) { if ((int) $r['id_pulse_ta_staff'] === (int) $id) { $mine[] = $r; } }
            $tot = PulseTaTimesheet::totals($from, $to);
            $mineTot = null;
            foreach ($tot as $t) { if ((int) $t['id_pulse_ta_staff'] === (int) $id) { $mineTot = $t; } }
            return array('from' => $from, 'to' => $to, 'days' => $mine, 'totals' => $mineTot);
        }
        $this->requireScope('time');
        $dept = isset($body['department']) ? (string) $body['department'] : '';
        return array('from' => $from, 'to' => $to, 'totals' => PulseTaTimesheet::totals($from, $to, $dept),
            'by_department' => PulseTaTimesheet::byDepartment($from, $to), 'periods' => PulseTaTimesheet::periods(12),
            'labour_per_room' => PulseTaService::labourPerOccupiedRoom($from, $to));
    }

    /** The supervisor queue; POST a body with `resolve` to clear one (scope `manager`). */
    protected function exceptions($id, $body)
    {
        if (!empty($body['resolve'])) {
            $this->requireScope('manager');
            if (!$id) { throw new PrestaShopException('An exception id is required', 400); }
            return PulseTaExceptionQueue::resolve((int) $id, (string) $body['resolve'], array(
                'reason' => isset($body['reason']) ? $body['reason'] : '', 'punched_at' => isset($body['punched_at']) ? $body['punched_at'] : null,
                'direction' => isset($body['direction']) ? $body['direction'] : 'unknown', 'minutes' => isset($body['minutes']) ? (int) $body['minutes'] : 0,
                'id_punch' => isset($body['id_punch']) ? (int) $body['id_punch'] : null));
        }
        $this->requireScope('time');
        return array('counts' => PulseTaExceptionQueue::counts(), 'types' => PulseTaExceptionQueue::types(),
            'rows' => PulseTaExceptionQueue::search(array('status' => isset($body['status']) ? $body['status'] : 'open',
                'type' => isset($body['type']) ? $body['type'] : '', 'department' => isset($body['department']) ? $body['department'] : '',
                'from' => isset($body['from']) ? $body['from'] : '', 'to' => isset($body['to']) ? $body['to'] : ''), 200));
    }

    /** Device fleet health — what a remote support desk asks for first. Credentials are never included. */
    protected function deviceHealth($id, $body)
    {
        $this->requireScope('time');
        if ($id && !empty($body['test'])) { $this->requireScope('manager'); return PulseTaDevice::test((int) $id); }
        if ($id && !empty($body['poll'])) { $this->requireScope('manager'); return PulseTaDevice::poll((int) $id, true); }
        $out = array();
        foreach (PulseTaDevice::statusAll() as $d) {
            $out[] = array('id' => (int) $d['id_pulse_ta_device'], 'name' => $d['name'], 'brand' => $d['brand'], 'adapter' => $d['adapter'],
                'location' => $d['location'], 'mode' => $d['mode'], 'status' => $d['status'], 'health' => $d['health'], 'stale' => (int) $d['stale'],
                'silent_min' => $d['silent_min'], 'last_seen_at' => $d['last_seen_at'], 'last_punch_at' => $d['last_punch_at'],
                'punch_count' => (int) $d['punch_count'], 'enrolled' => (int) $d['enrolled'], 'queued_cmds' => (int) $d['queued_cmds'],
                'firmware' => $d['firmware'], 'last_error' => $d['last_error'], 'capabilities' => $d['capabilities']);
        }
        return array('devices' => $out, 'counters' => PulseTaService::dashboard(), 'push_endpoint' => PulseTaService::pushUrl());
    }

    /** What Payroll reads: locked timesheets only, plus an honest count of what is not yet locked. */
    protected function payrollExtract($id, $body)
    {
        $this->requireScope('payroll');
        $from = !empty($body['from']) ? (string) $body['from'] : date('Y-m-01');
        $to = !empty($body['to']) ? (string) $body['to'] : date('Y-m-t');
        return PulseTaTimesheet::payrollExtract($from, $to, isset($body['department']) ? (string) $body['department'] : '');
    }

    /** The published roster, for the staff portal and the kiosk's "your next shift" line. */
    protected function roster($id, $body)
    {
        $this->requireScope($id ? 'ess' : 'time');
        $from = !empty($body['from']) ? (string) $body['from'] : date('Y-m-d');
        $days = isset($body['days']) ? max(1, min(31, (int) $body['days'])) : 7;
        if ($id) {
            $rows = array();
            for ($i = 0; $i < $days; $i++) {
                $d = date('Y-m-d', strtotime($from.' +'.$i.' day'));
                $r = PulseTaRoster::get((int) $id, $d);
                $rows[] = array('date' => $d, 'day_type' => $r ? $r['day_type'] : null, 'shift' => $r ? $r['shift_code'] : null,
                    'start' => $r ? $r['start_time'] : null, 'end' => $r ? $r['end_time'] : null, 'night' => $r ? (int) $r['is_night'] : 0, 'note' => $r ? $r['note'] : '');
            }
            return array('id_staff' => (int) $id, 'from' => $from, 'days' => $rows);
        }
        return PulseTaRoster::week($from, $days, isset($body['department']) ? (string) $body['department'] : '');
    }
}

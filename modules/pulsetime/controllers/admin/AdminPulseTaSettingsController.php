<?php
/** T&A Settings — pairing policy, rounding, night window, the push endpoint's security, retention and cron tokens. */
class AdminPulseTaSettingsController extends ModuleAdminController
{
    /** Settings grouped for the form. key => array(label, type, help[, options]). Types: text, int, bool, time, select, secret. */
    protected function groups()
    {
        $f = $this->fields();
        $g = array(
            'Pairing &amp; the working day' => array('TZ', 'DAY_START', 'ATTRIBUTE_BY', 'DEFAULT_SHIFT', 'MIN_GAP_SEC', 'AUTO_DIRECTION', 'ABSENT_AFTER_MIN', 'LATE_EXCEPTION_MIN'),
            'Rounding' => array('ROUND_MODE', 'ROUND_MIN', 'ROUND_IN', 'ROUND_OUT'),
            'Overtime &amp; night work' => array('NIGHT_FROM', 'NIGHT_TO', 'WEEK_START', 'OT_APPROVAL'),
            'Devices &amp; polling' => array('POLL_OVERLAP_MIN', 'DEVICE_STALE_MIN', 'JOB_MAX_ATTEMPTS', 'HTTP_TIMEOUT'),
            'ADMS push endpoint' => array('PUSH_ENABLED', 'PUSH_AUTO_REGISTER', 'PUSH_REQUIRE_KEY', 'PUSH_KEY_PARAM', 'PUSH_RATE_PER_MIN', 'PUSH_RATE_PER_IP_MIN', 'PUSH_MAX_PENDING', 'PUSH_MAX_BYTES',
                'PUSH_DELAY', 'PUSH_ERROR_DELAY', 'PUSH_TRANS_INTERVAL', 'PUSH_TRANS_TIMES', 'PUSH_REALTIME', 'PUSH_TIMEZONE', 'PUSH_CMD_BATCH'),
            'Mobile punching' => array('MOBILE_PUNCH', 'GEOFENCE_M', 'SITE_LAT', 'SITE_LNG'),
            'POS reconciliation' => array('POS_RECONCILE', 'POS_TOLERANCE_MIN'),
            'Retention &amp; tokens' => array('LOG_RETENTION', 'PUNCH_RETENTION', 'CRON_TOKEN', 'KIOSK_TOKEN'),
        );
        $out = array();
        foreach ($g as $title => $keys) {
            $rows = array();
            foreach ($keys as $k) { if (isset($f[$k])) { $rows[$k] = $f[$k]; } }
            $out[$title] = $rows;
        }
        return $out;
    }

    /** key => array(label, type, help[, options]). Types: text, int, bool, time, select, secret. */
    protected function fields()
    {
        return array(
            'TZ' => array('Device timezone default', 'text', 'Applied to a new device. Each device also carries its own, because a reader in a back office is often set to a different zone from the server.'),
            'DAY_START' => array('Business day starts at', 'time', 'Used only for staff with no shift rostered — the window their punches are read in.'),
            'ATTRIBUTE_BY' => array('Attribute a shift to', 'select', 'shift_start pays a 22:00–06:00 night as the date it started, which is how a hotel rota is written. Change this only before the first payroll run.', array('shift_start', 'shift_end')),
            'DEFAULT_SHIFT' => array('Fallback shift code', 'text', 'Used when a staff member has no roster row and no default shift of their own.'),
            'ROUND_MODE' => array('Rounding', 'select', 'none disables rounding entirely and pays the punch times as recorded.', array('none', 'nearest')),
            'ROUND_MIN' => array('Round to (minutes)', 'int', 'Typically 5, 10 or 15. Set 0 to disable.'),
            'ROUND_IN' => array('Round clock-in', 'select', 'up = a 07:58 punch on a 5-minute grid becomes 08:00. This is the usual employer policy; "none" pays the exact time.', array('none', 'up', 'down', 'nearest')),
            'ROUND_OUT' => array('Round clock-out', 'select', 'down = a 17:04 punch becomes 17:00.', array('none', 'up', 'down', 'nearest')),
            'MIN_GAP_SEC' => array('Ignore repeat punches within (seconds)', 'int', 'A finger read twice on the same reader is one punch, not two.'),
            'AUTO_DIRECTION' => array('Infer in/out when the device does not say', 'bool', 'Alternates in, out, in, out through the shift window. Turn off only if every reader is wired as a fixed in or out door.'),
            'LATE_EXCEPTION_MIN' => array('Raise a late exception after (minutes)', 'int', 'Beyond the shift grace. Below this, lateness is recorded on the timesheet but does not fill the supervisor queue.'),
            'ABSENT_AFTER_MIN' => array('Treat as absent after (minutes)', 'int', 'How long after the shift start a no-show becomes an absence rather than a lateness.'),
            'NIGHT_FROM' => array('Night premium from', 'time', 'Minutes worked inside this window are counted as night minutes for Payroll.'),
            'NIGHT_TO' => array('Night premium to', 'time', ''),
            'WEEK_START' => array('Week starts on', 'select', '0 = Sunday, 1 = Monday. Used for the weekly overtime threshold.', array('0', '1', '2', '3', '4', '5', '6')),
            'OT_APPROVAL' => array('Overtime needs approval', 'bool', 'When on, overtime is computed but only paid once the period is approved.'),
            'POS_RECONCILE' => array('Reconcile against POS clock-ins', 'bool', 'A waiter who clocked into the POS but not the reader raises an exception instead of being marked absent.'),
            'POS_TOLERANCE_MIN' => array('POS tolerance (minutes)', 'int', ''),
            'POLL_OVERLAP_MIN' => array('Poll overlap (minutes)', 'int', 'How far back a poll re-reads. Overlap is safe: punches de-duplicate. It is what saves the shift after a link drops mid-pull.'),
            'DEVICE_STALE_MIN' => array('Flag a device silent after (minutes)', 'int', ''),
            'JOB_MAX_ATTEMPTS' => array('Retry queue attempts', 'int', ''),
            'HTTP_TIMEOUT' => array('Default HTTP timeout (seconds)', 'int', 'Per-device timeouts override this.'),
            'PUSH_ENABLED' => array('ADMS push endpoint enabled', 'bool', 'The /iclock endpoint devices dial into. Turn it off and no push device can deliver anything.'),
            'PUSH_AUTO_REGISTER' => array('Register unknown serials as pending', 'bool', 'On: a new device appears on the Devices screen for an administrator to claim. Off: unknown serials are refused outright. Either way an unclaimed device is never trusted.'),
            'PUSH_REQUIRE_KEY' => array('Require a shared key', 'bool', 'Devices must send the key as a query parameter. Set the key on the Devices screen; rotating it locks out every device until they are updated.'),
            'PUSH_KEY_PARAM' => array('Shared key parameter name', 'text', 'The query-string name the device appends, e.g. pushkey.'),
            'PUSH_RATE_PER_MIN' => array('Max requests per serial per minute', 'int', 'A device stuck in a retry loop is throttled rather than allowed to flood the link. 0 disables the limit.'),
            'PUSH_RATE_PER_IP_MIN' => array('Max requests per address per minute', 'int', 'The serial limit alone is not a limit: a caller that changes the SN on every request gets a fresh bucket. This one counts the address, which is what actually throttles a flood. 0 disables it.'),
            'PUSH_MAX_PENDING' => array('Max unclaimed serials waiting', 'int', 'Once this many devices are pending, a further unknown serial is refused and logged instead of creating another row. Claim or delete the ones on the Devices screen. 0 disables the cap.'),
            'PUSH_MAX_BYTES' => array('Max request body (bytes)', 'int', ''),
            'PUSH_DELAY' => array('Device poll delay (seconds)', 'int', 'Sent in the handshake: how often the device asks for work.'),
            'PUSH_ERROR_DELAY' => array('Device retry delay on error (seconds)', 'int', ''),
            'PUSH_TRANS_INTERVAL' => array('Transfer interval (minutes)', 'int', ''),
            'PUSH_TRANS_TIMES' => array('Transfer times', 'text', 'Semicolon-separated, e.g. 00:00;14:00.'),
            'PUSH_REALTIME' => array('Real-time delivery', 'bool', 'Device sends each punch as it happens rather than in batches.'),
            'PUSH_TIMEZONE' => array('Device timezone offset (hours)', 'int', 'Sent in the handshake. 1 for Nigeria (WAT).'),
            'PUSH_CMD_BATCH' => array('Commands per call-in', 'int', ''),
            'MOBILE_PUNCH' => array('Allow mobile punches', 'bool', 'The fallback when a reader is down, and the only option for off-site staff. Every mobile punch is flagged as such.'),
            'GEOFENCE_M' => array('Geofence radius (metres)', 'int', ''),
            'SITE_LAT' => array('Site latitude', 'text', 'Leave blank to disable the geofence. A punch outside the fence is still recorded — it raises an exception for the supervisor rather than being refused.'),
            'SITE_LNG' => array('Site longitude', 'text', ''),
            'LOG_RETENTION' => array('Push traffic log retention (days)', 'int', ''),
            'PUNCH_RETENTION' => array('Punch retention (days)', 'int', 'DEFAULT 0 = keep forever, which is the right answer: punches are the evidence behind every payslip. A punch linked to a timesheet is never purged even if this is set.'),
            'CRON_TOKEN' => array('Cron token', 'secret', 'Used by cron/poll.php and cron/build.php.'),
            'KIOSK_TOKEN' => array('Kiosk token', 'secret', 'For an unattended clocking tablet posting to the API.'),
        );
    }

    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('T&A Settings'); }

    public function initContent()
    {
        parent::initContent();
        $values = array();
        foreach ($this->fields() as $k => $f) { $values[$k] = Configuration::get('PULSE_TA_'.$k); }
        $this->context->smarty->assign(array(
            'groups' => $this->groups(), 'values' => $values, 'push_url' => PulseTaService::pushUrl(),
            'push_key' => (string) PulseCoreService::setting('pulsetime', 'push_key'),
            'shop_tz' => Configuration::get('PS_TIMEZONE'), 'counters' => PulseTaService::dashboard(),
            'hr' => PulseTaService::hr(), 'pos' => PulseTaService::pos(), 'fd' => PulseTaService::fd(),
            'cron_url' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/pulsetime/cron/',
            'devices_url' => $this->context->link->getAdminLink('AdminPulseTaDevices'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('settings.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveSettings')) {
                if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to change settings')); }
                $changed = array();
                foreach ($this->fields() as $k => $f) {
                    if (!Tools::getIsset('s_'.$k) && $f[1] !== 'bool') { continue; }
                    $v = Tools::getValue('s_'.$k);
                    if ($f[1] === 'bool') { $v = (int) Tools::getValue('s_'.$k) ? 1 : 0; }
                    elseif ($f[1] === 'int') { $v = (int) $v; }
                    elseif ($f[1] === 'time') { $v = preg_match('/^\d{1,2}:\d{2}$/', (string) $v) ? $v : Configuration::get('PULSE_TA_'.$k); }
                    if ((string) Configuration::get('PULSE_TA_'.$k) !== (string) $v) { $changed[$k] = $v; }
                    Configuration::updateValue('PULSE_TA_'.$k, $v);
                }
                if ($changed) { PulseTaService::audit('settings_save', $changed); }
                $this->confirmations[] = $this->l('Settings saved.').(isset($changed['ROUND_MIN']) || isset($changed['ROUND_IN']) || isset($changed['ROUND_OUT']) || isset($changed['ROUND_MODE'])
                    ? ' '.$this->l('Rounding changed — rebuild any unapproved period so its totals match the new policy.') : '');
            }
            if (Tools::isSubmit('newCronToken')) { Configuration::updateValue('PULSE_TA_CRON_TOKEN', Tools::passwdGen(32)); PulseTaService::audit('cron_token_rotate'); $this->confirmations[] = $this->l('Cron token regenerated — update your crontab.'); }
            if (Tools::isSubmit('newKioskToken')) { Configuration::updateValue('PULSE_TA_KIOSK_TOKEN', Tools::passwdGen(32)); PulseTaService::audit('kiosk_token_rotate'); $this->confirmations[] = $this->l('Kiosk token regenerated.'); }
            if (Tools::isSubmit('setPushKey')) {
                $k = trim((string) Tools::getValue('push_key_value'));
                if ($k === '') { $k = Tools::passwdGen(32); }
                PulseCoreService::setting('pulsetime', 'push_key', $k);
                PulseTaService::audit('push_key_set');
                $this->confirmations[] = $this->l('Shared push key set. Every push device must now send it or it will be refused.');
            }
            if (Tools::isSubmit('purgeLogs')) { $r = PulseTaAdms::purge(); $this->confirmations[] = sprintf($this->l('%d traffic row(s) purged, %d stale command(s) expired.'), $r['log_rows'], $r['commands_expired']); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}

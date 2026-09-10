<?php
/**
 * Clocking-device adapter contract. One implementation per device family.
 * Every method either returns an array or throws PulseTaDeviceException, so a dead reader never hangs
 * a cron run or a page load — the poller marks the device offline and moves on to the next one.
 *
 * A normalised punch (what pullPunches returns, and what PulseTaPunch::ingest consumes) is:
 *   array(device_serial, employee_ref, punched_at 'Y-m-d H:i:s', direction, verify_mode, work_code, raw)
 */
interface PulseTaDeviceInterface
{
    /** $device is the pulse_ta_device row plus 'credentials' (decrypted array) and 'options' (decoded json). */
    public function __construct(array $device);

    /** Reachability + identity probe. @return array [ok, firmware, serial, users, punches, message] */
    public function testConnection();

    /** Set the device clock from the server, in the device's own configured timezone. @return array [ok, before, after] */
    public function syncTime();

    /** Punches at or after $since ('Y-m-d H:i:s'). @return array of normalised punches */
    public function pullPunches($since);

    /** Create or update one user on the device. $employee: device_user_id, name, card_no, privilege, password, group. @return array [ok, device_user_id] */
    public function pushUser(array $employee);

    /** Remove a user (and their templates) from the device. @return array [ok] */
    public function deleteUser($deviceUserId);

    /** Everyone the device knows. @return array of [device_user_id, name, card_no, privilege, has_finger, has_face, has_card, has_password] */
    public function pullUsers();

    /** Erase the on-device attendance log. Destructive — only call it after a successful pull. @return array [ok] */
    public function clearLog();

    /** Model, firmware, serial, capacity and current counters. @return array */
    public function deviceInfo();

    /** Feature matrix the UI reads so it never offers what a brand cannot do. @return array */
    public function capabilities();
}

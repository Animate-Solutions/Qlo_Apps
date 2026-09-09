<?php
/**
 * Lock-vendor adapter contract. One implementation per lock family (Onity, Salto, Hune, Dormakaba, Simulator).
 * Every method either returns an array or throws PulseKcEncoderException so the desk can be told
 * "encoder offline — issue a mechanical key" instead of hanging on a dead socket.
 */
interface PulseKcAdapterInterface
{
    /** $config: host, port, protocol, endpoint, encoder_ref, timeout, test_mode, credentials (decrypted array), options. */
    public function __construct(array $config);

    /**
     * Encode a card. $key keys: type, room_nums[], guest_name, valid_from, valid_to, override_deadbolt,
     * override_dnd, common_doors[], sequence, encoder_ref, key_no, staff_group, all_rooms, mobile.
     * @return array [ok, key_ref, card_serial, sequence, payload, raw]
     */
    public function encode(array $key);

    /** Cancel/void a previously encoded credential by its vendor reference. @return array [ok, raw] */
    public function cancel($keyRef);

    /** Read whatever card is sitting on the encoder. @return array [ok, card_serial, room_nums, valid_from, valid_to, type, raw] */
    public function readCard($encoderId);

    /** Pull door-open events from a lock (or the gateway that collects them). @return array of [opened_at, event, result, card_serial, battery_pct, raw] */
    public function readAudit($lockId);

    /** Reachability probe. @return array [ok, encoder, firmware, message] */
    public function testEncoder($encoderId);

    /** Feature matrix the UI reads to hide what a vendor cannot do. @return array */
    public function capabilities();
}

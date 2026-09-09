<?php
/**
 * FingerTec (Ingress family: TA100C, AC100C, Q2i, R2, Face ID) — the TCP protocol is ZK-derived, so this
 * subclasses the ZK adapter and overrides only what actually differs, rather than duplicating 400 lines.
 *
 * What differs in the field:
 *  * FingerTec ships readers with the device "password" (comm key) set at the terminal rather than blank, so
 *    comm-key auth is the normal path, not the exception. Store it as the `comm_key` credential.
 *  * Ingress terminals frequently answer CMD_GET_FREE_SIZES with a short block; the record-count hint is then
 *    unavailable and the record width falls back to divisibility, which is why `record_size` is exposed.
 *  * Their attendance state codes carry FingerTec's own six work states (IN, OUT, OT-IN, OT-OUT, Break-out,
 *    Break-in) in the same byte positions as ZK, but a handful of firmwares swap the verify and state bytes —
 *    `swap_state_verify` handles that without a code change.
 *  * Ingress also exposes a separate MySQL/SQL Server database on the Ingress PC. That is a licensed product
 *    and is not what this adapter talks to: this speaks to the terminal directly.
 *
 * Verify against your firmware version: FingerTec has shipped several protocol revisions under the same model
 * names. Run Test connection and then Preview punches before you trust a new site.
 */
class PulseTaFingertec extends PulseTaZkTcp
{
    protected $vendor = 'fingertec';

    /** FingerTec terminals answer the ZK option strings but label themselves differently. */
    public function testConnection()
    {
        $r = parent::testConnection();
        $r['message'] = str_replace('ZK device reachable', 'FingerTec terminal reachable', $r['message']);
        return $r;
    }

    public function capabilities()
    {
        $c = parent::capabilities();
        $c['vendor'] = 'FingerTec Ingress (ZK-derived, native 4370)';
        $c['work_codes'] = true;
        $c['verify_note'] = 'Verify against your firmware version — FingerTec has shipped several protocol revisions under the same model names.';
        return $c;
    }
}

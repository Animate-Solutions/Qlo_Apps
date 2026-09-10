# Pulse Key Card v1.0 — benchmark vs Onity, Salto, Hune, Dormakaba and hotel mobile keys

Lock vendors ship a desk application; PMS vendors ship an interface to it. Pulse Key Card is both halves in
one module: a vendor-adapter layer that speaks each system's own encoder protocol, and the desk, register,
audit and mobile-key screens a 52-room property actually works from. It ships with a signing simulator so the
whole module — issue, duplicate, extend, cancel, mobile key, lock audit — runs end to end with no hardware.

| Capability | Onity HT/Advance | Salto ProAccess | Hune | Dormakaba Ambiance | ZKTeco | Pulse |
|---|---|---|---|---|---|---|
| Guest key at check-in: room, guest name, valid-from/to, new-key sequence | ✓ | ✓ | ✓ | ✓ | ✓ | ✅ all four adapters + simulator |
| Duplicate, one-shot (maintenance/inspection), emergency, common-door cards | ✓ | ✓ | ✓ | ✓ | partial | ✅ |
| Deadbolt / do-not-disturb override flags | ✓ | ✓ | ✓ | ✓ | — | ✅ per key and per staff group |
| One card opening several rooms (family, connecting suite) | ✓ (licence) | ✓ | ✓ | ✓ | — | ✅ `id_rooms` csv, capped by the adapter's `max_rooms` |
| Extend a stay → re-encode the card, mobile key follows automatically | ✓ | ✓ (SVN) | ✓ | ✓ | — | ✅ hook `actionPulseStayChanged` |
| Cancel every card for a room after a lost card | ✓ | ✓ (blacklist) | ✓ | ✓ | ✓ | ✅ one button, plus SVN blacklist where supported |
| Encoder registry: several workstations, per-encoder credentials, health | desk app | ✓ | desk app | ✓ | — | ✅ `pulse_kc_encoder`, credentials encrypted |
| Encoder offline → clerk told to issue a mechanical key, work retried later | — | — | — | — | — | ✅ typed error + retry queue + mechanical key log |
| BLE / QR mobile key, revocable, device-bound | — | ✓ JustIN | — | ✓ (option) | ✓ | ✅ signed, short-TTL, refreshable credential |
| Staff groups → door groups, shift window, card expiry | ✓ | ✓ | ✓ | ✓ | ✓ | ✅ |
| Lost master → re-issue a whole department in one pass | manual | ✓ | manual | ✓ | manual | ✅ |
| Lock audit trail with "who opened this door" per room | ✓ (portable programmer) | ✓ | ✓ | ✓ | ✓ | ✅ `pulse_kc_lock_audit`, attributed to key → guest/staff |
| Flat lock battery → maintenance work order | alert only | alert only | alert only | alert only | — | ✅ `PulseTicket::create` department `maintenance` |
| PMS-side API for the guest app / TV portal / security console | partner | ✓ | — | ✓ | SDK | ✅ `/pulse/api/keycard/*` |

Runs standalone. Without Front Desk the Key Desk still issues keys by room and everything else works; with it,
keys follow check-in, room move, stay change and check-out. Licence entitlement: `pulsekeycard`.

## Tables

| Table | What it holds |
|---|---|
| `pulse_kc_encoder` | one row per encoder/workstation: adapter, protocol/host/port/endpoint, encrypted credentials, location, test mode, timeout, status, last seen, keys cut |
| `pulse_kc_door` | common doors, lifts, gates, back-of-house, wall readers and every room lock: code, vendor `lock_id`, default-on-guest-key flag, battery level, last audit pull |
| `pulse_kc_staff_group` | department → door list, all-guest-rooms flag, shift window + day mask, card life in days, override flags, master flag |
| `pulse_kc_key` | every credential ever cut: type, booking/customer or employee holder, rooms csv, doors csv, validity, overrides, encoder + adapter + vendor `key_ref` + card serial + sequence, encrypted payload and its hash, status, issue/cancel stamps and employee |
| `pulse_kc_mobile_key` | BLE/QR credential per key: delivery token, encrypted short-lived credential, device fingerprint, refresh counter, delivery channel, status |
| `pulse_kc_lock_audit` | door events: lock, room, card serial, resolved key + holder, event, result, battery, source, timestamp; unique on (lock, time, serial, event) so pulls are idempotent |
| `pulse_kc_job` | work parked because an encoder was unreachable — encode, cancel, mobile revoke, audit pull, blacklist — with attempts and back-off |

## Admin screens

* **Key Desk** — one search box (room number or guest name, F2 jumps back to it), the stay card, and big buttons: cut key, duplicate, lost → re-issue, extend, cancel, cancel every key for the room, send a mobile key, and "encoder down — log a mechanical key". Arrivals with no key yet are listed so keys can be cut before the rush. Read-card tells the clerk whose card is sitting on the encoder.
* **Keys** — the register with filters (status, type, date, free text over key no / serial / guest / room), the mobile-key list, and the retry queue with a Run now button. One key opens a detail page showing where that card has been used.
* **Encoders** — register, edit, test (from the server or from the clerk's own PC), deactivate; the capability matrix of each adapter is shown so the UI never offers what a vendor cannot do.
* **Lock Audit** — filtered door events, the denied-swipes summary for the last 24 h, the battery report with a raise-work-orders button, and the door/lock register with per-door pull.
* **Staff Access** — access groups (doors, shift, days, card life, overrides), staff cards, cards expiring in 14 days, and the lost-master department re-issue.
* **Key Card Settings** — default adapter, grace hours, auto-issue, duplicate limit, encoder timeout, mobile-key policy, battery threshold, audit retention, stale-encoder hours, local-agent switch, cron token; common doors are managed here too.

## API

`/pulse/api/keycard/{resource}/{id}` with `Authorization: Bearer <token>` from Pulse Core.

| Resource | Scope | Notes |
|---|---|---|
| `ping` | — | module, version, available adapters, whether mobile keys are on |
| `issue` | `desk` | id = booking (or body carries rooms/doors/validity). 503 with the clerk-facing message when the encoder is down |
| `duplicate` | `desk` | id = key |
| `cancel` | `desk` | id = key, or body `all_for_room` to kill a whole room |
| `extend` | `desk` | id = key, body `valid_to` |
| `mobile_key` | `desk` to issue, `portal` to fetch | with `token` it returns the credential and binds the device; without one (and with `desk`) it issues and returns the delivery link |
| `mobile_key_refresh` | `portal` | re-mints before the short TTL lapses; bound device only |
| `encoder_status` | `desk` | all encoders + counters, or probe one by id |
| `audit` | `security` | filtered events, per-room history, battery report, denials; `pull:true` with a door id fetches fresh events |

## Cron

`modules/pulsekeycard/cron/expire.php?token=<PULSE_KC_CRON_TOKEN>` — run hourly. Expires keys and mobile keys
past their window, drains the retry queue with a growing back-off, purges audit rows past the retention
setting, probes and flags encoders nobody has heard from for N hours, pulls lock audit and raises one
maintenance work order per flat lock (at most one a week per door).

## Adapters

`PulseKcAdapterInterface`: `encode`, `cancel`, `readCard`, `readAudit`, `testEncoder`, `capabilities`.
`PulseKcAdapterBase` gives every adapter a hard-timeout HTTP call with one retry on connect failure, a raw
socket path for line protocols, credential access, and event-code normalisation.

| Adapter | Transport | Shape |
|---|---|---|
| `PulseKcAdapterSimulator` | local | Signs an HMAC-SHA256 envelope locally and validates it on read-back. **Default.** Also produces deterministic pseudo-audit so the security screen has data with no hardware. |
| `PulseKcAdapterOnity` | HTTP POST or raw TCP to the desk service | `<PMSMessage><Command>MakeKey…` with site code, operator, encoder, key type STANDARD/DUPLICATE/ONESHOT, room + additional rooms, deadbolt/privacy overrides, common-door items |
| `PulseKcAdapterSalto` | HTTPS JSON to ProAccess SPACE | Basic auth + installation header; `keys` / `mobile-keys` with a zone list, `DELETE keys/{id}` plus SVN `blacklist`, `svn/update-points` |
| `PulseKcAdapterHune` | HTTP JSON to the Hune desk service | Vendor's Spanish schema: `habitacion`, `nombre`, `f_inicio`, `f_fin`, `tipo_llave`, `puertas`, `anular_cerrojo` |
| `PulseKcAdapterDormakaba` | HTTPS JSON to Ambiance | Bearer/API-key + site id; `Keys` with `RoomList`, `KeyOptions{NewKey,Deadbolt,DoNotDisturb,SingleUse}`, `Keys/{id}/Cancel`, `Locks/{id}/Audit` |

Every adapter takes host, port, protocol, endpoint, encoder reference, timeout and credentials from
`pulse_kc_encoder`; credentials are encrypted with `PulseCoreService::encrypt` and are write-only in the UI.
A failure raises `PulseKcEncoderException` with a code (`UNREACHABLE`, `TIMED_OUT`, `REJECTED`,
`NOT_CONFIGURED`, `UNSUPPORTED`, `BAD_RESPONSE`) and a `userMessage()` the desk shows verbatim — the clerk is
told to issue a mechanical key rather than left watching a spinner.

ZKTeco locks are covered by the same interface: their standalone locks have no PMS-side encoder, so the
integration point is the audit push (`PulseKcAudit::ingest` via the `audit` API resource with scope
`security`) plus mobile credentials — implement `PulseKcAdapterInterface` against their SDK bridge and register
it in `PulseKcEncoder::adapters()`.

## Local encoder agent vs server-side calls — and why both

Onity's and Hune's encoder services listen on the front-desk PC, usually on `127.0.0.1`. In a Port Harcourt
property the web server is often a VPS on the other side of an intermittent link and simply cannot reach that
socket. So:

* Adapters marked **local_only** (Onity, Hune) are driven from the **clerk's browser**: `views/js/keycard.js`
  calls `http://127.0.0.1:<agent port>/status|read` and posts the outcome back to Pulse
  (`ajaxProcessAgentReport`), which updates the encoder's status for everyone.
* Every other adapter (Salto, Dormakaba, the simulator) is called **from the server**, so keys can also be cut
  by the API, by cron retries and by the check-in hook with nobody at a browser.

The key row, its audit entry and its retry job are always written server-side. The browser bridge is a
transport of convenience, never the source of truth — which is why a key cut through the agent still appears
in the register, still has a sequence number, and still gets cancelled by check-out.

The agent itself is the vendor's own service where they publish an HTTP endpoint; where they do not, a ~50-line
listener on the desk PC that forwards `status`, `read` and `encode` to the vendor DLL/serial port is all that is
needed. Switch the whole mechanism off with `PULSE_KC_LOCAL_AGENT` and Pulse falls back to server-side calls.

## Mobile key — what Pulse does and what a real BLE lock still needs

Pulse issues a **signed, time-bounded, device-bound credential**: `base64url(claims).base64url(HMAC-SHA256)`
carrying the rooms, the door codes, `nbf`/`exp`, the override flags, a nonce and the device fingerprint. It is
stored encrypted, has a short TTL (`PULSE_KC_MOBILE_TTL_MIN`, default 4 h) inside the stay window, refreshes
through `mobile_key_refresh`, binds to the first device that fetches it, and is revoked by check-out, by the
desk, or by cancelling the underlying key. The delivery link goes out through `PulseComms` (falling back to a
direct mail if the Front Desk build has no `mobile_key` template), and the API returns `qr_text` for the app or
TV portal to render.

That is everything the PMS side of a mobile key is — but it does **not** by itself open a BLE lock. A real
deployment additionally needs, from the lock vendor:

1. **Their mobile SDK in the guest app** (Salto JustIN, dormakaba Mobile Access, ASSA ABLOY Seos…). The
   BLE handshake, the lock's own key diversification and the secure element are inside that SDK; nobody can
   reimplement them over an open BLE characteristic.
2. **A vendor-issued credential**, not ours. The vendor's cloud mints the blob the lock will accept; Pulse's
   job is to call `encode()` with `mobile => 1` — which `PulseKcAdapterSalto` already does against
   `mobile-keys` — and hand the returned `credential` to the app. Pulse's own signed envelope is what QR
   readers, the TV portal and the simulator use, and it is what wraps the vendor blob for transport.
3. **A gateway or wall reader** for online revoke. Offline BLE locks learn about a revoked key only from the
   phone itself or at an update point; that is why `cancel()` on Salto also pushes the serial to the SVN
   blacklist, and why the desk should be told to collect the card when a stay ends badly.
4. **Push credentials** so the app can wake and refresh before the TTL lapses on a phone in a lift.

In short: the lifecycle, the policy, the audit and the revocation live here; the last 10 cm of radio belongs to
the vendor SDK. Swapping in a real vendor is one adapter class, not a rewrite.

## Security

* Key payloads are encrypted at rest (`payload_enc`) and only their SHA-256 is ever audited. The register shows
  the hash, never the payload; no adapter response body is logged in clear.
* Vendor credentials go through `PulseCoreService::encrypt`; the encoder form is write-only (blank keeps).
* Every issue, cancel, extend, re-issue, mobile issue/revoke, encoder save and battery ticket writes a
  `PulseCoreService::audit` row with the employee.
* Back-office actions are permission-checked against the tab's edit right before anything is cut or killed.
* The API is scoped `desk` / `portal` / `security`; the mobile-key handle is a 64-hex bearer that only ever
  returns its own credential, to its own bound device.

## Parity checklist

- [x] Adapter interface with five implementations, capability matrix, encrypted config, short timeouts, typed offline error
- [x] Encoder registry with per-workstation selection, health, stale detection and browser-side local agent
- [x] Guest / duplicate / one-shot / staff / master / common / emergency keys, multi-room, overrides, sequence
- [x] Issue, re-issue, duplicate, extend, cancel, cancel-all-for-room, mechanical fallback, offline retry queue
- [x] Front Desk hooks: check-in auto-issue, room move, stay change, check-out — all degrading gracefully
- [x] Mobile BLE/QR key: signed, short-TTL, device-bound, refreshable, revocable, delivered by Pulse Comms
- [x] Staff groups → doors, shift window, card expiry, expiring list, lost-master department re-issue
- [x] Lock audit ingest, per-room "who opened this door", denial summary, battery report → maintenance work order
- [x] JSON API (`ping`, `issue`, `duplicate`, `cancel`, `extend`, `mobile_key`, `mobile_key_refresh`, `encoder_status`, `audit`)
- [x] Hourly cron: expire, drain queue, purge audit, flag encoders, pull audit, raise battery work orders
- [x] Seed script: four encoders, eleven common doors, five staff groups with cards, guest + mobile keys for
      in-house rooms, three days of lock audit and one lock at 8% battery

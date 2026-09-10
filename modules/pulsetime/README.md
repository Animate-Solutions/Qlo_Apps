# Pulse Time & Attendance v1.0 — benchmark vs ZKTeco BioTime, eSSL eTimeTrackLite, Suprema BioStar 2 and Matrix COSEC

Biometric vendors ship a Windows application that owns the punches; PMS vendors ship an import. Pulse Time &
Attendance is both halves in one module: a device-adapter layer that speaks each brand's own protocol, an ADMS
push endpoint the readers dial into, and the live board, exception queue and locked timesheets a 52-room
property actually works from. It ships with a software clocking device so the whole module runs end to end
with no hardware in the building.

| Capability | ZKTeco BioTime | eSSL eTimeTrackLite | Suprema BioStar 2 | Matrix COSEC | Pulse |
|---|---|---|---|---|---|
| Native ZKTeco 4370 protocol: pull punches, push users, set the clock | ✓ | ✓ | — | — | ✅ `PulseTaZkTcp`, TCP **and** UDP, comm-key auth, buffered and legacy read paths |
| ADMS push: the reader dials the server (right answer behind NAT on a flaky link) | ✓ | ✓ | — | — | ✅ `/iclock/cdata`, `/getrequest`, `/devicecmd`, with an unknown serial held as `pending` |
| Hikvision, Suprema, Anviz, Matrix, Dahua, IDEMIA, FingerTec on the same interface | — | — | — | — | ✅ eleven adapters behind one contract |
| Works with no hardware at all (demo, training, a site waiting for devices) | — | — | — | — | ✅ `PulseTaSimulator`, deterministic, roster-driven |
| CSV / file import for a brand nobody anticipated | partial | ✓ | — | ✓ | ✅ configurable column mapping, folder or URL |
| Punch store is append-only; corrections are approved adjustments | — | — | — | — | ✅ `pulse_ta_punch` + `pulse_ta_adjustment` with an approver |
| Night shift 22:00–06:00 paired correctly, every night of the year | partial | partial | ✓ | ✓ | ✅ shift owns a window, punches are consumed once, verified by an offline test |
| Missing punch → supervisor exception queue → auditable fix | ✓ | ✓ | ✓ | ✓ | ✅ blocking exceptions stop a period being approved |
| Rounding policy (grid, in-up/out-down, or off) | ✓ | ✓ | ✓ | ✓ | ✅ configurable, and a change tells you to rebuild |
| Overtime: daily, weekly, rest day, public holiday, night premium | ✓ | ✓ | partial | ✓ | ✅ data-driven rules with effective dates, no double-paid minute |
| Nigerian public holidays seeded, moon-dependent dates flagged | — | — | — | — | ✅ 2026 and 2027, `confirmed=0` where a declaration is needed |
| Timesheet approval that locks a period for payroll | ✓ | ✓ | ✓ | ✓ | ✅ approve → lock → reopen with a reason, all audited |
| Reconcile against the POS clock-in as a second witness | — | — | — | — | ✅ `pulse_pos_clock` |
| Mobile / kiosk punch with a geofence as the fallback when a reader dies | — | partial | ✓ | ✓ | ✅ flagged as mobile, coordinates kept, outside-fence raises an exception |
| Labour hours per occupied room | — | — | — | — | ✅ the hospitality number the generic packages do not have |
| Device fleet health, silent-device alerting, retry queue | ✓ | partial | ✓ | ✓ | ✅ plus an offline protocol self-test |

Runs standalone. Without Pulse HR it keeps its own staff roster and says so in the UI; without Pulse POS it
simply skips the reconciliation; without Front Desk it uses today's date instead of the hotel business date.
Licence entitlement: `pulsetime`.

## Tables

| Table | What it holds |
|---|---|
| `pulse_ta_device` | one row per reader: brand, adapter, mode (pull / push / file), protocol/host/port/endpoint, serial, encrypted credentials, timezone, direction mode, poll interval, health, `pending\|active\|blocked`, who claimed it, punch counters |
| `pulse_ta_device_cmd` | the ADMS command queue a push device collects on its next call-in, with the device's reply and return code |
| `pulse_ta_device_log` | every inbound request to the push endpoint: serial, path, table, IP, bytes, rows in/kept, result, first line of the body — the audit trail for a surface with no login |
| `pulse_ta_staff` | the local roster: staff number, names, department, position, default shift, pay basis and rates, links out to `pulse_hr_employee`, the back-office `employee` and `pulse_pos_staff` |
| `pulse_ta_enrolment` | person ↔ device user id, **per device**, plus card number, template presence flags, push status and last error |
| `pulse_ta_punch` | **append-only.** device, serial, device user id, resolved person, punched-at (shop time) and device-local time, direction, verify mode, work code, source, coordinates, the raw vendor payload, and a unique dedupe hash |
| `pulse_ta_adjustment` | every correction: type, time, minutes, reason, the exception it answers, the requesting and **approving** employee, approval timestamp |
| `pulse_ta_shift` | shift definitions: times, crosses-midnight, night flag, break rules, grace, the punch window either side, min/max shift, split second leg, nominal paid minutes |
| `pulse_ta_roster` | who works which shift on which date, `work\|rest\|leave\|holiday\|training\|off_site`, published flag, mirrored from Pulse HR when present |
| `pulse_ta_timesheet` | one row per person per business date: shift window, first in / last out, pairs, raw / break / worked / rounded minutes, late, early out, short, overtime split four ways plus weighted, night minutes, status, lock |
| `pulse_ta_timesheet_punch` | which punches this timesheet consumed and in what role — the reason a night shift's 05:55 clock-out can never also be read as an early arrival |
| `pulse_ta_exception` | the supervisor queue, deduped per person/date/type, with severity, resolution, the adjustment that fixed it and who resolved it |
| `pulse_ta_period` | approval periods by department and date range: open → submitted → locked → reopened, with totals and the count of blocking exceptions |
| `pulse_ta_ot_rule` | overtime rules: scope, department, threshold, multiplier, cap, effective from/to |
| `pulse_ta_holiday` | public holidays with a multiplier and a `confirmed` flag for the moon-dependent ones |
| `pulse_ta_job` | provisioning and pull work parked because a device was unreachable, with attempts and a growing back-off |

## Admin screens

* **Live Board** — who is in, by department, right now, with rostered times, first in, last punch, which reader,
  minutes on site and lateness. Tiles for on-site / rostered-not-in / late / open exceptions / offline devices.
  Quick actions: poll every device, record a punch by hand, rebuild a day.
* **Punches** — the append-only register with filters (date, device, department, source, free text), the
  unmatched-device-id list with one-click mapping, and the manual-entry form. One punch opens its evidence
  page: shop time, device-local time, raw payload, dedupe hash and the timesheet it was read into.
* **Exceptions** — the queue, filtered by status, type, severity, department and date. Opening one shows the
  punches the engine read, the POS clock for the same window, the corrections already on that day, and the
  fix form. Every fix demands a reason and writes an adjustment carrying the approver.
* **Timesheets** — totals by person, day by day, by department, and the approval periods. Rebuild a range,
  export CSV, create a period, submit, approve-and-lock, reopen with a reason. Warns when days in the range
  are not locked, because Payroll only reads locked days.
* **Devices** — the fleet with health, silent-time, last punch, queued commands and each adapter's capability
  matrix; the add/edit form with encrypted credentials; the push-traffic log; the ADMS command queue; the
  retry queue; and the **ZK protocol self-test**.
* **Enrolment** — the staff roster (mirrored from Pulse HR or maintained here), enrolment per device,
  provision-to-all-devices, remove-from-all-devices, reconcile against the device's own user list, and three
  "needs attention" lists: failed enrolments, unmapped device ids, and active staff on no reader at all.
* **Overtime & Roster** — the weekly roster grid with a pattern applier (`EARLY,EARLY,LATE,LATE,NIGHT,NIGHT,REST,REST`),
  coverage by shift, shift definitions, overtime rules with effective dates, the holiday calendar and the
  overtime register.
* **T&A Settings** — pairing, rounding, night window, week start, device polling, the push endpoint's
  security, mobile punching and the geofence, POS reconciliation, retention and tokens.

## API

`/pulse/api/time/{resource}/{id}` with `Authorization: Bearer <token>` from Pulse Core.

| Resource | Scope | Notes |
|---|---|---|
| `ping` | — | version, business date, available adapters, the push endpoint URL |
| `punch` | `ess` | kiosk or mobile punch. id = staff, or body `staff_no`. Geofenced when a site lat/lng is set; a phone's clock cannot back-date a punch by more than 5 minutes |
| `board` | `time` | who is in, plus the counters |
| `timesheet` | `ess` with an id, `time` without | one person's days and totals, or the whole property with the department roll-up and labour-per-room |
| `exceptions` | `time` to read, `manager` to resolve | body `resolve: add_punch\|ignore_punch\|set_minutes\|add_overtime\|paid_absence\|unpaid_absence\|waive_late\|waive` with a `reason` |
| `device_health` | `time`, `manager` to test or poll | fleet health and capabilities; never returns credentials |
| `payroll_extract` | `payroll` | **locked days only**, plus a count of days that are not locked so Payroll can refuse to run |
| `roster` | `ess` with an id, `time` without | the published roster |

## Cron

```
*/10 * * * *  php modules/pulsetime/cron/poll.php <PULSE_TA_CRON_TOKEN>
5    3 * * *  php modules/pulsetime/cron/build.php <PULSE_TA_CRON_TOKEN> 3
```

`poll.php` polls pull-mode devices, health-checks push devices, drains the provisioning queue, rebuilds any
timesheet whose punches moved, flags silent devices and purges old push traffic.
`build.php` rebuilds the last N days oldest-first (the order matters, because a night shift claims punches
across midnight), refreshes every open period's counters, applies the punch-retention setting and raises an
alert if a blocking exception would stop a payroll approval. It is also hooked to `actionPulseNightAuditClosed`,
so with Front Desk installed the nightly rebuild happens as part of the night audit.

---

# The ADMS push endpoint — read this before pointing hardware at it

ZKTeco ADMS firmware has its paths compiled in. It will GET and POST **exactly** these and nothing else:

```
GET  /iclock/cdata?SN=<serial>&options=all&pushver=…     handshake
POST /iclock/cdata?SN=<serial>&table=ATTLOG&Stamp=…      punches, tab-separated
POST /iclock/cdata?SN=<serial>&table=OPERLOG             operation log, including USER lines
GET  /iclock/getrequest?SN=<serial>                      "have you got work for me?"
POST /iclock/devicecmd?SN=<serial>                       the result of a command
```

The module registers the route `iclock/{:action}` through `hookModuleRoutes`, so **with friendly URLs on it
works out of the box**: point the device's *Comm ▸ Server (ADMS)* page at this property's host, port and the
path Pulse shows on the Devices screen.

### If friendly URLs are off

PrestaShop only honours `moduleRoutes` when friendly URLs are enabled. Turn them on
(Preferences ▸ SEO & URLs ▸ Friendly URL = Yes) — that is the one-line fix. If the property cannot, add a
rewrite so `/iclock/<action>` reaches the module's front controller. The module front controller's canonical
entry point is:

```
index.php?fc=module&module=pulsetime&controller=iclock&action=<cdata|getrequest|devicecmd|ping|event>
```

**Apache** (in the shop's `.htaccess`, above the PrestaShop block):

```apache
RewriteEngine On
RewriteRule ^iclock/([a-zA-Z_]+)$ index.php?fc=module&module=pulsetime&controller=iclock&action=$1 [QSA,L]
```

**nginx**:

```nginx
location ~ ^/iclock/([a-zA-Z_]+)$ {
    rewrite ^/iclock/([a-zA-Z_]+)$ /index.php?fc=module&module=pulsetime&controller=iclock&action=$1 last;
}
```

If the shop lives in a subdirectory (`https://hotel.example.com/pms/`), prefix both patterns with it and give
the device the full path — the Devices screen prints the exact URL to type into the reader.

Devices that only accept a host and a port (no path) must be given a host whose document root is the shop, or
a reverse proxy that maps `/iclock/` onto the shop. Nothing about that is Pulse-specific; it is the same
constraint every ADMS server has.

### What Pulse does with a device that dials in

1. **Unknown serial** → a `pending` device row appears on the Devices screen with the IP it came from. It is
   answered politely so it keeps buffering, but **none of its punches are stored.** An administrator claims it,
   and only then does it start delivering. A serial is never auto-trusted.
2. **Claimed device** → identified by its registered serial, plus (when `PULSE_TA_PUSH_REQUIRE_KEY` is on) a
   shared key compared with `hash_equals()`, plus an optional per-device IP allowlist (`allow_ips` in the
   device options).
3. **Rate limit** — two counters, because one is not enough. `PULSE_TA_PUSH_RATE_PER_MIN` caps requests per
   serial per minute, which stops a reader stuck in a retry loop flooding a Port Harcourt link;
   `PULSE_TA_PUSH_RATE_PER_IP_MIN` caps them per **address**, which is what stops a caller that simply
   changes the SN on every request and would otherwise get a fresh, empty bucket each time. The address
   counter is checked before the SN is even required. `PULSE_TA_PUSH_MAX_PENDING` caps how many unclaimed
   serials may be parked at once, so a serial-varying flood cannot fill `pulse_ta_device`. Both counters read
   `pulse_ta_device_log`, which is why every terminal path — including `ping` and the bare handshake — logs.
4. **Body cap** — `PULSE_TA_PUSH_MAX_BYTES`; `Content-Length` is checked before a single byte is read, and the
   read itself is capped in **bytes**.
5. **Every request is logged** to `pulse_ta_device_log` with the first line of the body reduced to printable
   ASCII. Templates escape it again. No device-supplied string is ever concatenated into SQL or rendered raw.
6. **Replies are what the firmware expects** — `GET OPTION FROM: <SN>` for the handshake, `OK: <n>` for a
   punch batch, `C:<id>:<command>` lines for queued work, `OK` otherwise. Even a refusal answers 200 with a
   body the device understands, because a device that receives an error page retries in a tight loop.

Hikvision terminals with *Network ▸ HTTP Listening* configured can post to `/iclock/event?SN=<serial>` and go
through exactly the same admission rules.

---

# Adapters

`PulseTaDeviceInterface`: `testConnection`, `syncTime`, `pullPunches($since)`, `pushUser`, `deleteUser`,
`pullUsers`, `clearLog`, `deviceInfo`, `capabilities`. `PulseTaDeviceBase` gives every adapter a
hard-timeout HTTP call with bounded retries, a timeout-guarded socket with an exact-read helper, credential
access, device→shop timezone conversion and punch normalisation. A failure raises `PulseTaDeviceException`
(`UNREACHABLE`, `TIMED_OUT`, `AUTH_FAILED`, `NOT_CONFIGURED`, `UNSUPPORTED`, `BAD_RESPONSE`, `REJECTED`,
`BUSY`) whose `userMessage()` the Devices screen shows verbatim.

| Adapter | Transport | What is implemented | Configurable | Verify against your firmware |
|---|---|---|---|---|
| `PulseTaSimulator` | none | Deterministic punch generation from the published roster: early arrivals, lateness, missed clock-outs, overtime, punched breaks. **The default.** | `late_pct`, `missing_out_pct`, `absent_pct`, `early_min`, `ot_pct` | — |
| `PulseTaZkTcp` | TCP or UDP 4370 | Full native protocol: framing, ones-complement checksum, session + reply sequencing, comm-key auth, buffered (`CMD_DATA_WRRQ`/`CMD_DATA_RDY`/`CMD_FREE_DATA`) and legacy (`CMD_PREPARE_DATA`/`CMD_DATA`) reads, 40/16/8-byte attendance records, 72/28-byte user records, `CMD_SET_TIME`, `CMD_USER_WRQ`, `CMD_DELETE_USER`, `CMD_CLEAR_ATTLOG`, `CMD_GET_FREE_SIZES`, `~SerialNumber`/`~DeviceName`/`~Platform` | host, port, tcp/udp, comm key, timeout, retries, `record_size`, `user_packet_size`, `swap_state_verify`, `disable_during_pull`, `attlog_fct`, `ignore_checksum` | Record width and which byte carries verify vs punch state differ between firmware generations. Run **Devices ▸ Diagnostics ▸ self-test**, then Poll and check a punch you made yourself. |
| `PulseTaZkPush` | inbound HTTP (`/iclock`) | The whole ADMS server side; writes are queued as ADMS commands (`DATA UPDATE USERINFO`, `DATA DELETE USERINFO`, `CLEAR LOG`, `SET OPTIONS DateTime=`, `CHECK`, `REBOOT`) and confirmed by the device's `devicecmd` reply | serial, shared key, IP allowlist, `attlog_swap`, `silent_alert_min`, handshake options (Delay, ErrorDelay, TransTimes, TransInterval, TransFlag, Realtime, TimeZone) | Some firmwares emit Verify before Status in an ATTLOG row — set `attlog_swap` if in/out looks inverted. |
| `PulseTaFingertec` | TCP 4370 | Subclasses `PulseTaZkTcp`; the protocol is ZK-derived. Comm-key auth is the normal path on Ingress terminals rather than the exception. | as ZK, plus the same `record_size` / `swap_state_verify` escape hatches | FingerTec has shipped several protocol revisions under the same model names. Test connection, then Preview punches, before a live site. |
| `PulseTaHikvision` | HTTP digest, ISAPI | `POST /ISAPI/AccessControl/AcsEvent?format=json` with an `AcsEventCond` block and `searchResultPosition` paging; `UserInfo/Record`, `UserInfo/Delete`, `UserInfo/Search`, `CardInfo/Record`; `GET/PUT /ISAPI/System/time` (XML); `System/deviceInfo`. Inbound HTTP event listener accepted at `/iclock/event`. | user/password, `event_major` (5), `event_minor` (list), `page_size`, `door_no`, `door_right`, `plan_template`, `isapi_timezone` | **The `minor` event codes that count as a legal punch differ between firmware families.** Pull a day, look at the raw payloads on the Punches screen, then set `event_minor`. |
| `PulseTaSuprema` | HTTPS, BioStar 2 local REST | `POST /api/login` → `bs-session-id` carried on every later call and re-established on a 401; `POST /api/events/search` with a period + device filter and offset paging; `/api/users` GET/POST/PUT/DELETE; `/api/devices` | user/password, `allow_self_signed` (off by default), `event_codes`, `page_size`, `all_devices`, `user_group_id`, `access_group_id` | BioStar 2 changed its event and user payloads between 2.7, 2.8 and 2.9. Re-test after any server upgrade. |
| `PulseTaAnviz` | HTTPS, CrossChex | The CrossChex OpenAPI envelope (`authorize.token/token`, `attendance.record/getrecord`, `employee.employee/getemployee\|addemployee\|deleteemployee`) **and** a flat REST mode (`/api/oauth/token`, `/api/employee`, `/api/records`) | `mode` (openapi\|rest), `api_path`, `api_version`, `path_token`, `path_users`, `path_records`, `page_size`, api key + secret | Anviz issues the key, secret and regional endpoint per account. **The legacy TC/IP binary protocol on port 5010 is deliberately not implemented** — it needs Anviz's SDK documentation under NDA, and guessing at a binary time format would put wrong times on payslips. Export to file and use `PulseTaCsv` for those terminals. |
| `PulseTaMatrix` | HTTP Basic, COSEC REST | Attendance events with date-range and paging, user list, user create/delete, device status | endpoint base, `path_events`, `path_users`, `path_status`, `param_from`/`param_to`/`param_page`/`param_size`, `first_page`, `page_size`, `user_group` | **Matrix versions its REST paths per product family and has moved the version segment more than once.** Every path and query parameter is a device option; verify yours before the first live run. Where a site runs COSEC CENTRA with a CSV/SQL export, `PulseTaCsv` is the lower-risk route. |
| `PulseTaDahua` | HTTP digest, CGI | The full `recordFinder.cgi` state machine (`factory.create` → `startFind` → paged `doFind` → `destroy`) with a single-shot `action=find` fallback; `AccessUser.cgi` insertMulti/updateMulti/removeMulti/list; `global.cgi` get/setCurrentTime; `magicBox.cgi` system info, serial and software version | user/password, `record_table` (default `AccessControlCardRec`), `page_size`, `door_no`, `include_denied` | Dahua renamed the record table and changed the `Method` / `Status` code meanings across generations. Check one day's pull against the terminal's own event list. |
| `PulseTaIdemia` | HTTPS/JSON | The SIGMA HTTP remote-messaging surface: transaction pull with a period and paging, user create/delete, heartbeat | `auth_style` (bearer\|basic\|api_key), `header_name`, `path_transactions`, `path_users`, `path_heartbeat`, `param_from`/`param_to`/`param_limit`/`param_offset`, `field_user`/`field_time`/`field_direction`/`field_verify`, `user_method` | **NOT vendor-certified.** MorphoManager, the MA5G SDK and IDEMIA's biometric template format are licensed and under NDA and are not shipped. What is implemented is the documented HTTP/JSON surface a SIGMA terminal exposes when remote messaging is enabled. Pull one day, reconcile it against the terminal's own event list, and only then enable the device for payroll. |
| `PulseTaCsv` | file or HTTP | Newest-file or whole-folder scan, or a URL with optional Basic auth; delimiter auto-detection; header-name or index column mapping; separate date and time columns; `DateTime::createFromFormat` when a format is given; encoding conversion; processed files moved to an archive, never deleted | `path`, `pattern`, `url`, `delimiter`, `has_header`, `encoding`, `date_format`, `col_ref`, `col_datetime`, `col_date`, `col_time`, `col_direction`, `map_in`, `map_out`, `col_verify`, `col_workcode`, `archive_dir`, `max_files`, `max_bytes` | Map the columns against your own export first — Test connection prints the mapping it will use. |

**Honesty about certification.** Only the ZKTeco family (native and ADMS) is implemented against a protocol
that is fully documented in the public domain, and it is the one covered by an offline self-test. Hikvision
ISAPI, Suprema BioStar 2, Anviz CrossChex, Matrix COSEC, Dahua CGI and IDEMIA SIGMA are implemented against
their documented HTTP surfaces with every endpoint, auth style and field name exposed as configuration. None
of them is vendor-certified, none ships a vendor SDK binary, and each carries a "verify against your firmware
version" note in its `capabilities()` and on the Devices screen. Set a device to **test mode** while you
verify it: reads work normally and no write reaches the hardware.

**What an adapter does not do.** Writing a user to a reader creates the identity, the validity window and the
door rights. It does **not** enrol a fingerprint, a face or a palm — those templates are captured at the
device (or in the vendor's own console) and are in the vendor's proprietary format. Pulse tells you which id
that person will punch under; the biometric itself stays where it belongs.

---

# Night shifts, midnight and missing punches

A hotel runs 22:00–06:00 every night of the year, so a "day" is not a calendar day. Four rules make it work:

1. **A shift owns a window, not a date.** For a night shift rostered on Monday the window runs from Monday
   22:00 minus the shift's early grace to Tuesday 06:00 plus its late grace. Every punch in that window
   belongs to Monday's timesheet. Tuesday's own window starts at Tuesday 22:00 and cannot reach back into it.
2. **Business-date attribution is `shift_start`** by default (`PULSE_TA_ATTRIBUTE_BY`): the night of Monday is
   paid as Monday, which is how a rota is written and how a night allowance is argued about.
3. **A punch is consumed by exactly one timesheet.** `pulse_ta_timesheet_punch` records which one, so the
   05:55 clock-out of a night shift can never also be read as an early arrival for the 06:00 morning shift.
   Days are rebuilt oldest-first; if a later, unlocked day was holding a punch this one now claims, the link
   is taken back and that day is rebuilt too.
4. **All arithmetic is on absolute timestamps.** Nothing compares clock faces, nothing assumes the clock-out
   is "later in the day", and nothing does date maths on a device's encoded counter — the ZK counter runs on
   a calendar where every month has 31 days, so subtracting two encoded values across 28 February is off by
   three days. `PulseTaZkProtocol::selfTest()` asserts that explicitly.

**Missing punches.** An odd number of punches raises a `missing_in` or `missing_out` exception at **block**
severity, which stops the period being approved. A supervisor opens it, sees the punches the engine read and
the POS clock-in for the same window, and records the correction. The correction is a `pulse_ta_adjustment`
row with the approving employee, the timestamp and a mandatory reason; an added punch also becomes a real
punch row tagged `source='adjustment'`. **The device's own punches are never edited or deleted.** Six months
later the original evidence and the decision that changed it are both still there.

**Timezones.** Punches are stored in the shop timezone. Each device carries its own, and conversion is
explicit — a reader in the back office set to UTC while the server is on `Africa/Lagos` would otherwise shift
every punch by an hour. Test connection reports the device's clock drift and tells you to run Sync time.

---

# Adding a rate change without a code release

A 2027 change to the daily overtime multiplier is one insert. Nothing is recompiled and nothing is deployed:

```sql
UPDATE `PREFIX_pulse_ta_ot_rule` SET `effective_to` = '2026-12-31' WHERE `code` = 'OT_DAILY';

INSERT INTO `PREFIX_pulse_ta_ot_rule`
  (`code`,`name`,`scope`,`department`,`threshold_minutes`,`multiplier`,`cap_minutes`,`requires_approval`,`effective_from`,`sort`,`active`)
VALUES
  ('OT_DAILY_27','Daily overtime beyond 8 hours (2027)','daily','',480,1.750,240,1,'2027-01-01',1,1);
```

The engine picks the rule in force on each timesheet's own business date, so a back-dated rebuild of a 2026
day still uses the 2026 multiplier. The Overtime & Roster screen does the same thing through a form.

---

# Security

* The `/iclock` endpoint is the only unauthenticated surface. An unknown serial is `pending` and inert; a
  claimed device authenticates by serial plus an optional shared key (`hash_equals`) and an optional IP
  allowlist; requests are rate-limited per serial and capped in bytes; every request is logged.
* Device credentials (user, password, ZK comm key, API key and secret, push key) go through
  `PulseCoreService::encrypt` and are write-only in the UI — a blank field keeps the stored value. They are
  never returned by the API and never rendered in a template.
* `pulse_ta_punch` is append-only. The only post-insert write is filling in the person when an unmatched
  device id is later mapped, and that is audited.
* Approving a period locks it: no punch, no adjustment and no rebuild for those dates. Reopening needs a
  reason, is audited, and raises an alert.
* Every device save, claim, block, log-clear, enrolment push and revoke, exception resolution, adjustment,
  period submit/approve/reopen and settings change writes a `PulseCoreService::audit` row with the employee.
* Back-office actions are permission-checked against the tab's edit right. Clearing a device's on-board log
  additionally requires typing `CLEAR`.
* Device-supplied text is escaped on the way in (printable ASCII for the traffic sample, UTF-8-validated or
  hexed for the raw payload) and escaped again in every template. `pSQL()` and casts throughout.

# Parity checklist

- [x] Eleven device adapters behind one interface, with a capability matrix, encrypted credentials, hard
      timeouts, bounded retries and a typed offline error carrying a `userMessage()`
- [x] ZKTeco native: framing, ones-complement checksum, session/reply sequencing, comm-key auth, buffered and
      legacy read paths, 40/16/8-byte records, 72/28-byte users, TCP and UDP — plus an offline self-test
- [x] ADMS push endpoint with pending-device claiming, shared key, IP allowlist, rate limit, body cap,
      full traffic log and an outbound command queue
- [x] Hikvision, Suprema, Anviz, Matrix, Dahua, IDEMIA, FingerTec, CSV import and a hardware-free simulator
- [x] Enrolment mapping per device, provision-to-all, retry queue, device reconciliation, unmatched-id mapping
      that re-attributes historic punches without editing one
- [x] Append-only punch store with de-duplication, raw payload retention and manual/mobile/import sourcing
- [x] Night-shift-safe pairing, rounding policy, break handling, split shifts, minimum and maximum shift
- [x] Exception queue with blocking severities, an auditable fix path and POS reconciliation
- [x] Overtime by daily, weekly, rest-day, holiday and night rules with effective dates, no double-paid minute
- [x] Nigerian public holidays for 2026 and 2027, moon-dependent dates flagged for confirmation
- [x] Timesheet approval that locks a period, with a reasoned, audited reopen
- [x] Live board, punches, exceptions, timesheets, devices, enrolment, overtime and settings screens
- [x] JSON API (`ping`, `punch`, `board`, `timesheet`, `exceptions`, `device_health`, `payroll_extract`, `roster`)
- [x] Cron: poll + drain the queue every 10 minutes, rebuild + refresh periods nightly, hooked to the night audit
- [x] Seed script: 85 staff, three devices, a five-week roster with a real night rota, a month of punches with
      lateness, two missing clock-outs and one unmapped device id, and an approved locked period

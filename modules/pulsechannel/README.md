# Pulse Channel Manager v1.0 — benchmark vs eZee Centrix, RateTiger & SiteMinder

A two-way channel manager inside QloApps. Availability, rates and restrictions are computed from real
inventory and pushed to every mapped channel; OTA reservations come back and become real QloApps orders
that land on the Front Desk tape chart. Nothing is ever silently dropped: a payload that cannot be mapped
goes to a failed queue with its raw body and a one-click manual assign.

| Capability | eZee Centrix | RateTiger | SiteMinder | Pulse |
|---|---|---|---|---|
| Channel registry with per-channel credentials, endpoint, commission, currency | ✓ | ✓ | ✓ | ✅ credentials encrypted via `PulseCoreService::encrypt` |
| Pluggable adapters (add an OTA without touching the engine) | partner-only | partner-only | partner-only | ✅ `PulseChAdapterInterface` |
| Documented open contract a partner can build to | — | — | — | ✅ generic JSON contract, below |
| File-drop / e-mail channel for OTAs with no API | partial | — | — | ✅ `PulseChAdapterCsv` |
| Room type + rate plan mapping with occupancy and derived rates | ✓ | ✓ | ✓ | ✅ single supplement, extra adult, child, channel markup |
| Loud unmapped-inventory warning | partial | partial | ✓ | ✅ dashboard + mapping screen + `/health` |
| ARI computed from physical rooms − bookings − blocks − OOO − allotment | ✓ | ✓ | ✓ | ✅ |
| Dirty-cell queue, batching, exponential backoff, poison cap | ✓ | ✓ | ✓ | ✅ `pulse_ch_queue` |
| Event-driven availability (check-in/out, stay change, no-show, OOO, group blocks) | via PMS | via PMS | via PMS | ✅ Pulse suite events + block fingerprinting |
| Reservation delivery with dedupe, modification and cancellation on the same reference | ✓ | ✓ | ✓ | ✅ |
| Failed-reservation queue with raw payload and manual assign | partial | partial | ✓ | ✅ |
| Rate parity screen (our rate vs pushed rate, drift flag) | ✓ | ✓ | ✓ | ✅ 30 days |
| Bulk rate/restriction update across dates, channels, days of week | ✓ | ✓ | ✓ | ✅ |
| One-button close-out (stop-sell a date range everywhere) | ✓ | ✓ | ✓ | ✅ |
| Allotment per channel, oversell buffer, overbooking check | ✓ | ✓ | ✓ | ✅ against `pulse_overbooking` |
| Full message log (request, response, duration, status) | ✓ | ✓ | ✓ | ✅ `pulse_ch_log` |
| Health dashboard + alert when a channel has been failing for N minutes | ✓ | ✓ | ✓ | ✅ Pulse Comms SMS or e-mail |
| Inbound webhook with HMAC + replay protection | ✓ | ✓ | ✓ | ✅ `/pulse/api/channel/reservation` |

Runs standalone. Without Front Desk it still reads real QloApps bookings and creates real orders; group
blocks, out-of-order rooms and overbooking limits are simply not counted (the dashboard says so).

## Certification honesty

Booking.com, Expedia, Agoda, Airbnb and Traveloka **do not issue connectivity credentials without partner
certification**, and the endpoint you get is specific to your property. This module therefore ships those
channels **disabled, with blank endpoints and no credentials** — nothing is fabricated. What is fully
implemented is the connector itself: `PulseChAdapterHttp` builds and signs real OpenTravel messages
(`OTA_HotelAvailNotifRQ`, `OTA_HotelRateAmountNotifRQ`, `OTA_ReadRQ`, `OTA_NotifReportRQ`), parses the
responses including `<Errors>` and SOAP faults, and maps HTTP failures to operator-readable text. Enter
the endpoint, auth style and machine account your account manager issues, add a SOAP/WS-Security wrapper
in the body template if the OTA needs one, and press **Test connection**.

For everything else — a Nigerian OTA, an intermediary such as eZee Centrix / RateTiger / SiteMinder, or a
bespoke integration — use `PulseChAdapterGeneric` and hand your partner the contract below.

## Tables

| Table | What it holds |
|---|---|
| `pulse_ch_channel` | channel registry: adapter, endpoints, auth type, encrypted credentials, hotel code, currency, commission, sync mode, allotment, oversell buffer, health, last success/failure |
| `pulse_ch_rate_plan` | our rate plans (BAR, BB, NONREF, CORP, LONGSTAY) with derivation from a parent plan |
| `pulse_ch_mapping` | `id_product` + rate plan → channel room code + rate code, occupancy, single/extra-adult/child adjustments, per-channel allotment |
| `pulse_ch_ari` | one row per channel × room type × rate plan × date: availability, rates, min/max LOS, CTA, CTD, stop-sell, release days, `cell_hash` vs `pushed_hash`, manual overrides |
| `pulse_ch_queue` | dirty-cell batches: date range, reason, attempts, `next_attempt_at`, poison flag |
| `pulse_ch_reservation` | inbound bookings: channel reference (unique per channel), guest, stay, money, commission, status, linked order/bookings, raw payload, error |
| `pulse_ch_log` | every message in and out: direction, type, HTTP status, request, response, duration, error |

## Admin screens

* **Channel Manager** — per-channel health, queue depth, unmapped inventory, parity summary, 30-day production, close-out, sync/rebuild buttons.
* **ARI Calendar** — editable date × room-type grid with stop-sell / CTA / CTD / min-LOS badges, bulk update across dates, channels and days of week, parity list, queue.
* **Channel Mappings** — mapping list, the unmapped gap list with a quick-map form, mapping editor with derived-rate rules, rate plans, copy-channel.
* **Channel Reservations** — failed queue with manual assign, awaiting delivery, delivered, cancelled/ignored, production, and a paste-a-payload box for when a booking arrives by e-mail.
* **Channel Logs** — 24-hour health table, full message log with request/response bodies, queue with retry/cancel.
* **Channel Settings** — channel registry editor (endpoints, auth, credentials, allotment, body template) plus module settings and the integrator quick reference.

## Hooks

Listens to `actionValidateOrder`, `actionPulseCheckIn`, `actionPulseCheckOut`, `actionPulseStayChanged`,
`actionPulseNoShow`, `actionPulseRoomStatusChange` (out of order), `actionPulseNightAuditClosed`.
Group blocks raise no suite event, so `PulseChService::detectBlockChanges()` fingerprints
`pulse_group_block_allot` on every sync run and dirties whatever moved.

Raises `actionPulseChannelReservation`, `actionPulseChannelAriPushed`, `actionPulseChannelHealth`.

## Cron

```
*/5 * * * *  php modules/pulsechannel/cron/sync.php        <PULSE_CH_CRON_TOKEN>
15  4 * * *  php modules/pulsechannel/cron/rebuild_ari.php <PULSE_CH_CRON_TOKEN>
```

`sync.php` detects group-block changes, drains the push queue and pulls reservations.
`rebuild_ari.php` recomputes the whole window; run it after the night audit.
Both accept an optional channel id as a second argument, and both work over the browser with `?token=`.

## JSON API — `/pulse/api/channel/{resource}`

### Authentication

Two ways in. Partners use HMAC:

```
X-Pulse-Channel:   hotels_ng
X-Pulse-Timestamp: 1789412345
X-Pulse-Signature: sha256=<hmac_sha256(timestamp + "." + raw_body, secret)>
Content-Type:      application/json
```

`secret` is the channel's stored `secret` credential, falling back to `PULSE_CH_API_SECRET`
(Channel Settings). Timestamps more than 300 seconds old are rejected, so a captured request cannot be
replayed. Signature comparison is constant-time.

Our own tools use the suite token instead: `Authorization: Bearer <64-char pulse_api_token>` with the
`channel` scope.

Every response is `{"ok":true,"data":…}` or `{"ok":false,"error":"…"}` with a matching HTTP status.

### `GET /pulse/api/channel/ping`

```json
{"ok":true,"data":{"module":"pulsechannel","version":"1.0.0","business_date":"2026-09-08","channel":"hotels_ng","server_time":"2026-09-08T09:12:44+01:00"}}
```

### `GET /pulse/api/channel/ari?from=2026-09-08&to=2026-10-08&changed_only=0`

Pull current ARI. Optional `room_code`, `id_product`, `days`. Rows are exactly what our adapters push:

```json
{"ok":true,"data":{
  "hotel_code":"PHC-GRA-52","currency":"NGN","from":"2026-09-08","to":"2026-10-08","count":930,
  "rows":[{
    "date":"2026-09-08","room_code":"DELUXE-KING","rate_code":"DELUXE-KING-BAR",
    "base_occupancy":2,"max_occupancy":3,
    "available":7,"rate":85000.00,"rate_single":80000.00,"rate_extra_adult":12000.00,"rate_child":6000.00,
    "min_los":1,"max_los":0,"cta":0,"ctd":0,"stop_sell":0,"release_days":0,"currency":"NGN"
  }]
}}
```

Field meanings: `available` is what this channel may sell that night after allotment and buffer;
`rate` is the rate at `base_occupancy`, tax inclusive, in `currency`; `rate_single` is the one-guest rate;
`rate_extra_adult` / `rate_child` are per-person-per-night supplements; `cta` / `ctd` are closed to
arrival / departure; `stop_sell` closes the night entirely; `release_days` is the cut-off.

### `POST /pulse/api/channel/reservation` — the inbound webhook

Body is one reservation object, or `{"reservations":[ … ]}`. Minimum: `reference`, `status`,
`room_code`, `arrival`, `departure`.

```json
{"reservations":[{
  "reference":"HNG-PHC-880431",
  "status":"new",
  "firstname":"Chinedu","lastname":"Okafor",
  "email":"chinedu.okafor@example.ng","phone":"+2348031122334","country":"NG",
  "room_code":"DELUXE-KING","rate_code":"DELUXE-KING-BAR",
  "arrival":"2026-09-11","departure":"2026-09-14",
  "rooms":1,"adults":2,"children":0,
  "amount":255000,"currency":"NGN",
  "commission_pct":12,
  "payment_type":"hotel_collect"
}]}
```

* `status`: `new` | `modify` | `cancel` (anything containing "cancel", "modif"/"change"/"amend" is read accordingly).
* `guest_name` is accepted instead of `firstname`/`lastname`.
* `amount` is the gross stay total, tax inclusive, in `currency`.
* `payment_type`: `hotel_collect` or `channel_collect` (any value containing "channel", "virtual" or "prepaid" is treated as channel-collect).
* Dates accept `YYYY-MM-DD`, `DD/MM/YYYY` or any ISO timestamp.
* A `modify` or `cancel` must reuse the original `reference`.

Response:

```json
{"ok":true,"data":{"received":1,"results":[
  {"reference":"HNG-PHC-880431","accepted":true,"id":41,"status":"delivered","id_order":1207,"error":null}
]}}
```

`status` is `delivered` when the QloApps order was created, `failed` when it landed in the manual queue
(with `error` explaining why) — the booking is stored either way, never dropped. Re-sending a reference
that is already delivered is a no-op, so retries are safe.

### `POST /pulse/api/channel/ack`

`{"reference":"HNG-PHC-880431"}` — marks the reservation acknowledged so it stops being redelivered.

### `GET /pulse/api/channel/health`

```json
{"ok":true,"data":{"business_date":"2026-09-08","channels":[{
  "code":"hotels_ng","name":"Hotels.ng","enabled":true,"health":"ok",
  "last_success":"2026-09-08 09:10:02","last_failure":null,"last_error":null,
  "queue_pending":0,"queue_failed":0,"queue_poison":0,
  "error_rate_24h":0.0,"unmapped_room_types":0,"failed_reservations":1
}]}}
```

## What a partner must implement (the other direction)

If you connect as a partner rather than being polled, implement the three endpoints
`PulseChAdapterGeneric` calls, all carrying the channel's configured auth headers plus
`X-Pulse-Hotel` and `X-Pulse-Idempotency` (an md5 of the body — dedupe retried batches on it):

* `POST {endpoint}` — `{"hotel_code","currency","test","sent_at","rows":[ARI rows as above]}`.
  Return 2xx. To reject individual cells return `{"errors":[…]}` and the batch is retried with backoff.
* `GET {pull_endpoint}?hotel_code=…&since=YYYY-MM-DD HH:MM:SS` — `{"reservations":[…]}` in the shape above.
* `POST {ack_endpoint}` — `{"hotel_code","reference","status":"delivered","acked_at"}`.
* `GET {endpoint}?ping=1&hotel_code=…` — 2xx, used by Test connection.

## CSV / e-mail channel

Outbound ARI is written to `csv_out_dir` as
`date,room_code,rate_code,available,rate,rate_single,extra_adult,child,min_los,max_los,cta,ctd,stop_sell,currency`.
Inbound reservation files are read from `csv_in_dir` (any `*.csv`), moved to `csv_in_dir/processed`
after import so nothing is imported twice. Header columns (order-independent, case-insensitive):
`reference,status,guest_name,email,phone,room_code,rate_code,arrival,departure,rooms,adults,children,amount,currency,commission_pct,payment_type`.
Both folders default to `download/pulsechannel/<code>/{in,out}` if left blank.

## How availability is computed

```
physical  = rooms of the type − rooms inactive in QloApps − rooms flagged out_of_order/out_of_service
booked    = htl_booking_detail rows overlapping the night, not cancelled/refunded/checked out
blocked   = Σ (group block allotment − pick-up) for live blocks still inside their cut-off
base      = max(0, physical − booked − blocked)
channel   = min(base, allotment)  when an allotment is set
available = max(0, channel + oversell buffer)     buffer capped by pulse_overbooking.max_over
stop_sell = 1 when available is 0, or when the desk set a manual stop-sell
```

Rates: QloApps feature pricing for the night → rate plan derivation (percent or amount off the parent)
→ channel adjustment → occupancy derivation (`rate_single = rate + single_adj`, so a −5,000 supplement
sells the single at ₦5,000 below the double). A manual rate typed into the ARI grid overrides the
computed rate until it is cleared in **Bulk update → Clear manual overrides**.

## Overbooking

Every delivery checks the worst night of the stay against `available + pulse_overbooking.max_over`.
`PULSE_CH_OVERBOOK_ACTION` decides what happens when it does not fit:

* `accept_flag` (default) — take the booking, mark it `overbooked`, open an urgent Front Desk ticket and
  a trace. Refusing an OTA booking that already exists at the OTA does not un-sell it.
* `queue` — hold it in the failed queue for manual assignment.

## Settings

`PULSE_CH_ARI_DAYS` · `PULSE_CH_MIN_LOS` · `PULSE_CH_RELEASE_DAYS` · `PULSE_CH_OVERSELL_BUFFER` ·
`PULSE_CH_OVERBOOK_ACTION` · `PULSE_CH_BATCH_SIZE` · `PULSE_CH_MAX_ATTEMPTS` · `PULSE_CH_BACKOFF_BASE` ·
`PULSE_CH_HTTP_TIMEOUT` · `PULSE_CH_ALERT_MINUTES` · `PULSE_CH_ALERT_EMAIL` · `PULSE_CH_ALERT_PHONE` ·
`PULSE_CH_TAX_PCT` (7.5) · `PULSE_CH_PARITY_TOLERANCE` · `PULSE_CH_AUTO_DELIVER` ·
`PULSE_CH_PAYMENT_MODULE` · `PULSE_CH_LOG_KEEP_DAYS` · `PULSE_CH_CRON_TOKEN` · `PULSE_CH_API_SECRET`.

## At 2 a.m. on a bad link

Every outbound call has a connect and total timeout. Hooks never call an OTA — they write one queue row
and return, so a check-in is never blocked by connectivity. Failed batches back off exponentially
(`base ^ attempts` minutes, capped at 4 hours) and are poisoned after `PULSE_CH_MAX_ATTEMPTS` so the
queue cannot spin all night; a poisoned batch is one click to retry. A channel failing longer than
`PULSE_CH_ALERT_MINUTES` raises exactly one alert. If the link is down entirely, the desk can still
close out inventory locally, paste a booking that arrived by e-mail into the reservations screen, and
hand the CSV channel its ARI file by hand.

## Demo data

```
php modules/pulsechannel/seed/seed.php
```

Three channels for a 52-room Port Harcourt property (Hotels.ng sandbox on the generic JSON contract in
test mode, Jumia Travel as a CSV drop with a 12-room allotment, Booking.com disabled pending
certification), mappings for every room type on BAR/BB/NONREF with a ₦5,000 single supplement, 30 days of
ARI with a weekend uplift, two delivered OTA bookings and one with an unmappable room code sitting in the
failed queue.

Licence entitlement: `pulsechannel`.

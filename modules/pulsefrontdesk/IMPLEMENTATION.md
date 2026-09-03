# Pulse Front Desk — implementation guide

## 1. Architecture

```
QloApps 1.7 core                       Pulse Front Desk (this module)
─────────────────                      ────────────────────────────────────────────────
htl_booking_detail  ◄── reads/updates  PulseFdService   check-in, check-out, move, no-show
htl_room_information◄── reads/updates  PulseRoom        board, HK/FO status, availability
orders / order_payment ─ reads ──────► PulseFolio       charges, payments, transfer, close
product_lang (room types) ─ reads ───► PulseNightAudit  EOD: no-shows → room posting → HK → stats → roll date
employee ─ reads ────────────────────► PulseCashierSession, PulseTrace, PulseGuestProfile, PulseCompany, PulseFdReport
                                              │
                                    PulseCore::event()  → actionPulseCheckIn / CheckOut / FolioPost / RoomStatusChange …
                                              │
                                pulsekeycard · pulsepos · pulseguestportal · pulsepayments (listeners)
```

Rules the code follows:
- **No QloApps core files are edited.** The module only writes to `htl_booking_detail` (`id_status`, `check_in`, `check_out`, `id_room`, `room_num`, `is_cancelled`, `comment`) and `htl_room_information.id_status`. Everything else is in `pulse_*` tables.
- **QloApps stays the reservation system of record.** Pulse never creates bookings; it operates on them.
- **Money:** QloApps booking totals are tax-inclusive. Night audit backs tax out using the `ROOM` charge code rate so folio lines carry net + tax + gross consistently. All manual postings are entered net with a tax %.
- **Business date ≠ calendar date.** Every folio line, task and cashier session stamps `business_date`. It only advances when night audit closes. Reports run on business date.
- **Every mutation is audited** in `pulse_audit` (who, what, entity, payload).

## 2. Data model (27 tables)

| Table | Purpose |
|---|---|
| `pulse_room_status` / `_log` | HK + FO status per room, OOO reason/until, full history |
| `pulse_housekeeping_task` | Task queue: type, priority, assignee, status, business date |
| `pulse_guest_profile` | VIP, company, preferences (JSON), blacklist, stay stats |
| `pulse_guest_identity` | ID documents captured at check-in |
| `pulse_company` | Corporate / TA accounts with credit limit and ledger balance |
| `pulse_charge_code` | Departmental charge & payment codes with default tax |
| `pulse_folio` / `pulse_folio_line` | Folios (guest/company/group/master/house) and postings |
| `pulse_cashier_session` | Shift open/close, float, expected vs counted |
| `pulse_room_move` | Move history |
| `pulse_trace` | Traces, messages, wake-ups, alerts |
| `pulse_night_audit` | One row per closed business date with statistics snapshot |
| `pulse_booking_ext` | Pulse extras per QloApps booking: group link, day-use, source, pre-check-in & self-checkout tokens, card hold, late-fee flag |
| `pulse_group_block` / `_allot` | Group blocks and per-type allotments |
| `pulse_waitlist`, `pulse_overbooking` | Waitlist queue; overbooking allowance per room type |
| `pulse_registration_card` | Signed registration cards (PNG signature, terms version, snapshot) |
| `pulse_upsell_offer` / `_sale` | Offer catalogue and sales |
| `pulse_routing_rule` | Charge routing (booking / company / group → target folio) |
| `pulse_drawer_movement` | Blind drops, paid-outs, float movements |
| `pulse_ticket` / `_note` | Guest-service tickets with SLA and timeline |
| `pulse_comms_log`, `pulse_pabx_log` | Outbound messages; PABX CDR and dial-code events |

## 3. Core flows

**Check-in** (`PulseFdService::checkIn`)
1. Guards: booking is `STATUS_ALLOTED`; not blacklisted (or override); arrival date ≤ business date (or early flag).
2. Optional room re-assignment → validated against `availableRooms()` (same type, no overlap, not OOO).
3. Room must not be vacant-dirty/OOO unless override.
4. ID captured (required by default).
5. `id_status → 2`, `check_in = now`; optional QloApps order-state sync; FO=occupied, HK=occupied_clean.
6. Folio created; online prepayment on the order applied as a `DEP` line (apportioned across rooms of the order); optional deposit posted.
7. Events: `actionPulseCheckIn` → key card encode, TV welcome.

**Check-out** (`PulseFdService::checkOut`)
1. Guard: in-house. Optional late fee posted.
2. Balance must reach zero: settle by method, route to city ledger (credit-limit checked) or refund credit. Otherwise blocked.
3. Folio closed; `id_status → 3`; room → vacant/vacant_dirty; departure-clean task created; guest stats updated.
4. Events: `actionPulseCheckOut` → key invalidation, TV session wipe.

**Night audit** (`PulseNightAudit::run`)
pre-checks → no-shows (charge 1 night, cancel booking) → post ROOM to every in-house folio for the business date (idempotent) → generate tomorrow's HK tasks → snapshot occupancy/revenue/ledgers → roll business date. Failures are logged with status `failed` and the date is not rolled.

## 3b. v1.1 flows

**Desk reservation** (`PulseReservation::create`): availability incl. overbooking allowance and un-picked group allotments → customer find/create → QloApps cart + `htl_cart_booking_data` rows → `validateOrder` via the configured payment module → `htl_booking_detail` rows appear → Pulse extension row with tokens → contracted rate rewrite → optional deposit → confirmation message. This is the one place the module writes into QloApps' cart tables; verify the column list against your `HotelCartBookingData` class on first run.

**Pre-check-in**: hourly cron at 09:00 sends links N days before arrival → guest page (`/pulse/precheckin?t=…`) → identity + scan, preferences, upsells, signature, optional hold → trace for the desk. At the desk the same booking shows the signed card and the agent skips paperwork.

**Self check-out**: 07:00 links to today's departures → guest page (`/pulse/checkout?t=…`) → balance captured from card hold or paid via link → `PulseFdService::checkOut` → receipt emailed → trace to collect keys.

**Routing**: `PulseFolio::post()` asks `PulseRouting::resolve()` before inserting a charge; if a rule matches, the line is posted on the target folio with the source folio referenced. Payments never route.

**PABX**: outbound calls go through `PulsePabx::driver()`; inbound events hit `/pulse/api/frontdesk/pabx_cdr` and `/pabx_code` with a token scoped `pabx`.

## 4. Installation

1. Install `pulsecore` first, then upload `pulsefrontdesk` (Modules → Add new module) and install. Tables, tabs under **Pulse** and default settings are created; every existing room gets a status row.
2. **Settings → Front Desk Settings**: check-in/out times, ID requirement, inspection step, no-show charge, late fee, and map the QloApps "Check-in"/"Check-out" order states (optional). Review charge codes and tax rates (defaults: 7.5% VAT on rooms, 12.5% on F&B — adjust to the hotel's actual regime).
3. Set the **business date** to today once, before go-live.
4. **Permissions**: Administration → Permissions → give Front Desk profile access to Front Desk / Room Board / Arrivals / Folios / Housekeeping; Housekeeping profile only Housekeeping; Accounts profile Folios, Companies, Reports; Manager everything incl. Night Audit and Settings.
5. **Cron**: nightly `0 3 * * * curl -s ".../modules/pulsefrontdesk/cron/night_audit.php?token=<PULSE_FD_CRON_TOKEN>"` and hourly `0 * * * * curl -s ".../modules/pulsefrontdesk/cron/hourly.php?token=<PULSE_FD_CRON_TOKEN>"` (late fees, waitlist offers, 07:00 express-checkout links, 09:00 pre-check-in links). The token is shown on the Night Audit screen.
5b. **Settings → Front Desk Settings** also holds: desk-reservation payment module & order state, late-fee grace, SMS adapter/API key/sender/channel, pre-check-in lead days, registration terms, PABX driver/bridge/map/dial codes, upsell offers.
5c. **Pretty URLs**: the module registers `pulse/precheckin`, `pulse/checkout` and `pulse/api/frontdesk/...` via `moduleRoutes`; if friendly URLs are off the standard `index.php?fc=module&module=pulsefrontdesk&controller=precheckin&t=…` form is used automatically.
6. **API tokens** (for HK phones / TV): insert into `pulse_api_token` with scopes `housekeeping` or `portal`.

## 5. Things to verify on your QloApps 1.7.0 build (first hour of dev)
- `HotelBookingDetail::STATUS_ALLOTED / STATUS_CHECKED_IN / STATUS_CHECKED_OUT` constants and the `id_status`, `check_in`, `check_out`, `is_cancelled` columns on `htl_booking_detail` — the module assumes 1/2/3.
- `htl_room_information.id_status` values (1 active, 2 inactive, 3 temporarily inactive) used for OOO handling.
- The `htl_branch_info_lang` join in the Room Board hotel filter.
- Hook names `actionObjectHotelBookingDetailAddAfter` / `actionObjectHotelRoomInformationAddAfter` fire from ObjectModel `add()`; if QloApps inserts bookings with raw SQL in places, the board still self-heals via `syncRooms()` and date-based queries.
- Order-state sync uses `OrderHistory::changeIdOrderState`; confirm QloApps hasn't overridden it to also alter booking status (if it has, leave `PULSE_FD_OS_*` unset to avoid double handling).

## 6. UAT test scripts (run on staging with the client's front-desk lead)

| # | Scenario | Expected |
|---|---|---|
| T1 | Online booking with 50% deposit → Arrivals → check in with NIN | Folio opens with DEP line = 50%; room turns blue on board; key-card event logged |
| T2 | Check in to a vacant-dirty room without override | Blocked with message; override checkbox allows it |
| T3 | Post minibar from board drawer, then restaurant bill via POS | Folio shows both lines with correct tax; balance updates |
| T4 | Transfer a line to company folio; route remainder to city ledger | Guest folio zero; company ledger balance increases; credit limit enforced |
| T5 | Check out with balance, no method | Blocked; choose Cash → folio closes; room orange (vacant dirty); departure task created |
| T6 | Room move mid-stay | Old room dirty + task; new room occupied; folio room updated; events fired |
| T7 | Booking for today never arrives; run night audit | Marked no-show, one night charged, booking cancelled, room freed |
| T8 | Night audit with an open cashier shift | Blocked by pre-check; close shift → passes; business date rolls; room charges posted once |
| T9 | Run night audit twice for same date | Second run refused ("already closed") |
| T10 | Cashier shift: take ₦50,000 cash, close with ₦48,000 counted | Variance −2,000 recorded; appears in Cashier shifts report |
| T11 | Reports for the month, export CSV | Occupancy, ADR, RevPAR match manual calculation from audit rows |
| T12 | Blacklist a guest, try to check in | Blocked; manager override succeeds and is audited |
| T13 | Mark room OOO until Friday | Room hatched on board; not bookable in QloApps front office; returns to VC when set back |
| T14 | Front-desk profile tries to open Night Audit | Permission denied |
| T15 | Walk-in for 2 nights, deposit ₦20,000 cash, check in now | QloApps order created; folio shows DEP; room occupied; confirmation SMS logged |
| T16 | Tape chart: drag a reserved stay to another room of the same type; drag a different type | Same type moves silently; different type prompts differential and posts UPG |
| T17 | Tape chart: drag right edge from 2 to 3 nights | Booking and order totals reprice; night audit posts 3 nights in total |
| T18 | Group block 10 rooms, cut-off yesterday, 6 picked up; run night audit | Allotment reduced to 6; block status "released"; availability restored |
| T19 | Rooming-list CSV with 5 rows, one invalid room type | 4 reservations created at contracted rate; 1 row error reported |
| T20 | Waitlist entry for a sold-out type; cancel a booking; run hourly cron | Entry becomes "offered", message logged; "Book now" creates the reservation |
| T21 | Pre-check-in link: complete with signature and an upsell | Registration card stored; upsell posted to folio; trace "key can be pre-cut" on arrival day |
| T22 | Company routing rule "rooms → company"; guest posts minibar; night audit | Room charge lands on company folio, minibar on guest folio |
| T23 | Cashier: ₦100,000 cash taken, blind drop ₦80,000, close with ₦25,000 counted (float 5,000) | Expected 25,000; variance 0 |
| T24 | Settle a folio in USD cash | Line shows USD amount and rate; folio balance in NGN reaches zero |
| T25 | Ticket "AC not cooling" urgent, engineering; resolve with notify | HK maintenance task created; SLA 30 min; guest SMS logged on resolve |
| T26 | Self check-out link with a zero balance | Guest confirms; folio closed; receipt emailed; trace to collect keys |
| T27 | Departure still in-house at check-out time + grace; hourly cron | LATE fee posted once; alert trace created; second run does nothing |
| T28 | Two profiles with same phone → Find duplicates → merge | One profile with combined stays; bookings and folios reassigned |

## 7. Training outline (½ day per role)
- **Front desk**: board colours, arrivals flow, ID capture, deposits, drawer quick-post, check-out settlement, room moves, traces/wake-ups, cashier open/close.
- **Housekeeping**: My tasks, start/done, OOO, inspection step.
- **Accounts**: folios, voids/transfers, companies & ledger receipts, reports & CSV.
- **Manager**: night audit, settings, charge codes, permissions, reading the audit log.

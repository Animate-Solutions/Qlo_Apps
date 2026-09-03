# Pulse Front Desk v1.1 — benchmark vs eZee Front Desk and Oracle OPERA

Scored against the 27-feature requirements list (sections 1–6) plus the wider 62-point matrix from v1.0.
Legend: **✅ built** · **🟡 partial / depends on another module or hardware** · **⬜ not built**

## Section 1 — Reservation & Inventory
| Feature | v1.0 | v1.1 | Where |
|---|---|---|---|
| Tape chart / interactive grid | ⬜ | ✅ rooms × 7–31 days; drag to move room or shift dates; drag right edge to extend/shorten; click empty cell → reservation; OOO hatching; group-block overlay; cross-type moves prompt for a differential | `AdminPulseTapeChart`, `tapechart.js` |
| Walk-in & direct booking | via QloApps | ✅ single-screen: dates → live availability & rate quote per type → guest lookup/creation → room pick or auto-assign → deposit → optional immediate check-in. Creates a real QloApps order | `AdminPulseWalkIn`, `PulseReservation::create` |
| Group & block allocation | 🟡 | ✅ blocks with per-type allotments, cut-off date auto-release at night audit, CSV rooming-list import (one reservation per row at contracted rate), master folio, billing modes (individual / rooms-to-master / all-to-master) via routing | `PulseGroupBlock`, `AdminPulseGroups` |
| Overbooking & waitlist | ⬜ | ✅ per-type overbooking allowance honoured by desk reservations; prioritised waitlist; hourly availability check sends offers (SMS/email), 24 h expiry, one-click convert to reservation | `PulseWaitlist`, `pulse_overbooking` |
| Day-use / hourly | ⬜ | ✅ day-use flag with out-by time and flat rate; night audit posts DAYUSE instead of a night and alerts if still in-house | walk-in screen, `pulse_booking_ext` |

## Section 2 — Check-in & Arrival
| Feature | v1.0 | v1.1 | Where |
|---|---|---|---|
| Express / one-click check-in | 🟡 | ✅ pre-arrival link (SMS/email N days before) → guest completes details, ID + photo upload, preferences, e-signature, upsells, optional card hold; desk sees "pre-checked-in, key can be pre-cut" trace. Card pre-auth at desk. Key cutting via `actionPulseCheckIn` | `precheckin.php`, `PulsePaymentBridge` |
| Smart room auto-assignment | 🟡 | ✅ scoring: inspected > clean, floor preference, VIP → higher floors, returning guest → same room, open maintenance penalised for long stays; suggested room pre-selected in the check-in dialog and used by walk-in/rooming lists | `PulseRoom::autoAssign` |
| Digital registration card & e-sign | ⬜ | ✅ terms text/version in settings; signature pad (touch/mouse) at desk or online; PNG signature + snapshot of stay details stored; visible on folio | `PulseRegistrationCard`, `signature.js` |
| Check-in upselling | ⬜ | ✅ offer engine: room upgrades generated from real availability and rack differential (or fixed price, with min-availability threshold), early check-in, late check-out, breakfast, packages; shown at desk check-in, pre-check-in and via TV-portal API; sales tracked | `PulseUpsell` |
| Keycard integration | 🟡 | 🟡 events fire on check-in/out/move; encoder drivers live in `pulsekeycard` (next module) | — |

## Section 3 — Guest CRM
| Feature | v1.0 | v1.1 | Where |
|---|---|---|---|
| 360° profile & history | ✅ | ✅ | Guest Profiles |
| Profile matching & dedup | ⬜ | ✅ candidates by email / ID number / phone / full name; merge repoints bookings, orders, folios, IDs, tickets; sums stats; deactivates the duplicate | `PulseGuestProfile::findDuplicates/merge` |
| VIP & tiering | ✅ | ✅ (loyalty programme still out of scope) | |
| Guest messaging & traces | 🟡 | ✅ traces/wake-ups/messages + outbound comms engine: email (PrestaShop Mail) and SMS/WhatsApp via adapter (Termii shipped; interface for others). Templates: confirmation, pre-check-in, welcome, wake-up, express check-out, receipt, waitlist offer, ticket update. Every send logged | `PulseComms`, `PulseCommsTermii` |

## Section 4 — Folio, Cashiering & Billing
| Feature | v1.0 | v1.1 | Where |
|---|---|---|---|
| Multi-folio & split billing | 🟡 | ✅ automatic routing rules (per booking / company / group; per department or all) resolved at posting time; manual transfer retained | `PulseRouting` |
| Cashier shift & drawer control | 🟡 | ✅ blind drops, paid-outs, float in/out, corrections with optional witness; expected cash accounts for movements; full audit | `PulseCashierSession::movement` |
| Payment gateway & terminal sync | ⬜ | 🟡 bridge in place: pre-auth at check-in/pre-check-in, capture at check-out and self check-out, payment links. Live only when `pulsepayments` is installed; otherwise recorded as manual holds | `PulsePaymentBridge` |
| Multi-currency & tax engine | 🟡 | ✅ foreign-currency cash settlement using QloApps currency rates; original amount, ISO and rate kept on the line; tax engine unchanged | `PulseFolio::postForeignPayment` |
| Direct billing / AR | 🟡 | ✅ statements (opening/closing, running balance, statement number, print/PDF), ageing 0-30/31-60/61-90/90+ (FIFO), standing company routing rules | `PulseAr` |

## Section 5 — In-House Operations
| Feature | v1.0 | v1.1 | Where |
|---|---|---|---|
| Live housekeeping board | ✅ | ✅ + HK status by PABX dial code | |
| Room moves & upgrades | 🟡 | ✅ upgrade/downgrade across room types with per-night differential (or complimentary), from drawer, tape chart or upsell engine; key re-issue events | `PulseReservation::changeRoomType` |
| Guest service / ticket logging | 🟡 | ✅ tickets with category, department, priority-based SLA, assignment, notes timeline, resolution, guest notification; maintenance/HK tickets auto-create HK tasks; JSON API for engineering phones; TV-portal requests become tickets | `PulseTicket`, `AdminPulseTickets` |
| Wake-up call & PABX | 🟡 | ✅ interface + generic HTTP-bridge driver: wake-ups pushed to PABX, room phone enabled/barred on check-in/out, inbound CDR → telephone charges on folio, inbound HK dial codes → room status | `PulsePabx*`, API `pabx_cdr`, `pabx_code` |
| Minibar & service billing | ✅ | ✅ | |

## Section 6 — Check-out & EOD
| Feature | v1.0 | v1.1 | Where |
|---|---|---|---|
| Express / self check-out | 🟡 | ✅ morning SMS/email link → guest reviews folio, settles via card on file or payment link, confirms; folio closed, receipt emailed, desk trace to collect keys | `selfcheckout.php` |
| Late check-out fee automation | 🟡 | ✅ hourly job posts the fee once per stay after check-out time + grace and raises an alert | `automateCheckoutDay` |
| Automated night audit | ✅ | ✅ + group cut-off release, waitlist offers, day-use handling | |
| Audit trails | ✅ | ✅ | |

**Score: 24 of 27 fully built, 3 partial (keycard drivers, live card processing, both waiting on their own modules; wider loyalty out of scope).**

## Remaining dependencies
- `pulsepayments` — replaces manual holds with live Paystack/Flutterwave authorisation, capture and hosted payment links (bridge already calls it).
- `pulsekeycard` — encoder drivers; Front Desk already raises the events and traces "key can be pre-cut".
- PABX bridge — a small service on the PABX server (or the PABX's own HTTP API) that implements four endpoints (`/wakeup`, `/wakeup/cancel`, `/room`, `/ping`) and posts CDR/dial-code events to the Pulse API. Yeastar/Grandstream expose this natively; Panasonic needs a CTI bridge.
- Hardware: ID scanner (pre-check-in accepts photo upload today), signature tablet (any browser tablet works).

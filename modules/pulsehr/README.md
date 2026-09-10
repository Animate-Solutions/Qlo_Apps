# Pulse HR v1.0 — benchmark vs OPERA/HRMS add-ons, eZee HR, SeamlessHR and the generic HRIS

OPERA has no HR module: hotels running it bolt on Oracle HCM or a local HRIS and reconcile by spreadsheet. eZee ships a light HR screen inside FrontDesk (employee list, attendance, simple leave). SeamlessHR, PeopleHum, BambooHR and Zoho People are proper HRIS products but know nothing about occupancy, room credits, food handler certificates or a night shift that crosses midnight. Pulse HR is an HRIS that lives inside the PMS, so the roster argues with the forecast and the KPI is labour hours per occupied room.

| Capability | OPERA | eZee | Generic HRIS | Pulse HR |
|---|---|---|---|---|
| Employee master linked to the PMS/POS user rather than duplicated | — | partial | — | ✅ `id_employee` link to `employee`, POS through `pulse_pos_staff` |
| Org structure: departments, sections, positions, grades with salary bands, org chart | — | partial | ✓ | ✅ |
| **Establishment vs actual headcount by department** | — | — | partial | ✅ budgeted heads per position, vacancies and over-establishment |
| **Effective-dated contracts; payroll reads the version in force on the period** | — | — | partial | ✅ `PulseHrContract::onDate()` / `::forPeriod()` |
| Mid-month promotion without rewriting last month's pay basis | — | — | rarely | ✅ versions tile without gaps; only the newest may be removed |
| Documents with expiry, reminders and tickets — incl. **food handler and medical screening** | — | — | partial | ✅ inspection-facing types, nagging cron, ticket on lapse |
| Leave: types, accrual, per-grade entitlement, carry-over cap, approval chain, encashment | — | partial | ✓ | ✅ |
| **Leave blackouts tied to forecast occupancy** | — | — | — | ✅ "not four housekeepers off in a full house" |
| Leave liability valued for finance | — | — | partial | ✅ days owed × daily rate from the contract in force |
| Roster: shift patterns incl. **22:00–06:00 nights**, weekly grid, publish-to-staff, swaps | — | partial | partial | ✅ |
| **Coverage warnings against occupancy** (credit minutes per room → heads needed) | — | — | — | ✅ per department, on the grid and the dashboard |
| Onboarding / offboarding checklists that **actually issue and revoke** keys and POS logins | — | — | — | ✅ through `pulsekeycard` and `pulsepos` when present |
| Discipline with staff acknowledgement (time and address recorded) | — | — | partial | ✅ acknowledged from the staff portal |
| Appraisal cycle with weighted objectives and two signatures | — | — | ✓ | ✅ |
| Training records that expire | — | — | ✓ | ✅ |
| **Staff self-service on the employee's own phone** | — | — | ✓ | ✅ `/pulse/hr` — roster, leave, payslips, documents, record |
| **Geofenced mobile clock-in with coordinates, accuracy and a supervisor queue** | — | — | rarely | ✅ every punch stores lat/lng/accuracy/distance; mobile is visibly not biometric |
| Labour hours per occupied room | — | — | — | ✅ from timesheets, mobile punches or the roster — the report says which |
| Scoped JSON API for integrations and the portal | — | — | partial | ✅ `/pulse/api/hr/*` |

Runs standalone. Front Desk, Key Cards, POS, Pulse Time and Pulse Payroll are each optional and guarded; where one is absent the screen (and the staff portal) says so in plain words rather than failing. Licence entitlement: `pulsehr`.

## Tables (`pulse_hr_*`, 30)

| Table | What it holds |
|---|---|
| `pulse_hr_department` | rooms, housekeeping, fnb, laundry, maintenance, security, sales, accounts, admin — cost centre, head, **credit minutes per occupied room** |
| `pulse_hr_section` | sections within a department (front desk, kitchen, linen room…) |
| `pulse_hr_grade` | G1–G8 with salary band, default annual leave and notice |
| `pulse_hr_position` | job with department, section, grade, **budgeted establishment**, night flag |
| `pulse_hr_employee` | the person: names, NIN, TIN, RSA PIN + PFA, **NHF number and dated consent**, bank + NUBAN, next of kin, hire/probation/confirmation/exit, portal PIN hash, `id_employee` link |
| `pulse_hr_contract` | **effective-dated versions**: type, position/department/grade/manager, `effective_from`/`effective_to`, pay basis and rate, hours, notice, probation, reason, status |
| `pulse_hr_document` | typed documents with issue/expiry, verification, reminder days and status |
| `pulse_hr_leave_type` | accrual method, days/year, carry cap, max run, min service, gender, evidence, encashable |
| `pulse_hr_leave_entitlement` | per-grade override of a type's days |
| `pulse_hr_leave_balance` | opening / carried / accrued / taken / pending / encashed / adjustment, with `last_accrued` so a re-run cannot double-credit |
| `pulse_hr_leave_request` | dates, days, half day, relief, contact, status, source (admin / ess / api) |
| `pulse_hr_leave_approval` | the chain: manager → head of department → HR, one row per step |
| `pulse_hr_blackout` | dates, department, max off, minimum occupancy at which it bites |
| `pulse_hr_shift` | E / L / N / G / split / on-call with break, paid hours and the **night flag payroll reads** |
| `pulse_hr_roster` | one cell per person per day: shift or off, planned or published |
| `pulse_hr_roster_swap` | swap requests from the portal and the supervisor's decision |
| `pulse_hr_checklist_template`, `_task_template` | onboarding and clearance templates with owners, due offsets and actions |
| `pulse_hr_checklist`, `_task` | the live checklists, what was done, by whom and the reference it produced |
| `pulse_hr_case` | queries, warnings, suspensions, commendations, grievances; response, acknowledgement time and IP, expiry |
| `pulse_hr_appraisal_cycle`, `_appraisal`, `_appraisal_objective` | the cycle, one appraisal per person, weighted objectives, both signatures |
| `pulse_hr_training` | course, provider, completion and expiry, cost, certificate |
| `pulse_hr_ess_session` | signed portal sessions: sid, token hash, expiry, payslip window, IP, agent, revocation |
| `pulse_hr_ess_login` | every sign-in attempt, good and bad — the lockout counts from here |
| `pulse_hr_punch` | mobile/QR punches: **lat, lng, accuracy, distance, inside-geofence, status, flag reason**, review, sync flag |
| `pulse_hr_change_request` | a member of staff asking HR to correct a detail |
| `pulse_hr_rate` | fixed-window rate-limit buckets for the portal |

Seed rows in `install.sql` are reference data only: nine departments, eight grades, seven leave types, six shift patterns and the two checklist templates. All demo data lives in `seed/seed.php`.

## Admin screens (tabs 140–148, under `AdminPulseCore`)

* **HR** (`AdminPulseHr`) — headcount against establishment, coverage against occupancy, expiring documents and contracts, leave waiting, checklist tasks due, **flagged mobile punches with a map link and an accept/reject**, birthdays, and a one-click leave accrual.
* **Employees** — searchable list; the employee file with eight tabs: personal (including the NHF consent box), **contracts as a version timeline**, documents, leave balances and adjustments, roster and clockings, onboarding/exit, record (discipline, appraisals, training) and access (back-office link, POS, key cards, portal PIN, status).
* **Org & Positions** — departments with their room-credit minutes, sections, grades with bands, positions with the establishment, shift patterns, and the org chart.
* **Leave** — approval queue, departmental calendar, booking form with live warnings, types and per-grade entitlements, blackouts, liability, history.
* **Roster** — the weekly grid by department with a fill-the-week control, copy last week, publish to staff, and a coverage row per department under every day. Prints cleanly for the notice board.
* **Onboarding & Exit** — open checklists, tasks due, templates, and the per-task action buttons.
* **Discipline & Appraisal** — cases with acknowledgement state, the appraisal cycle and its weighted objectives, training with expiry.
* **HR Reports** — headcount, turnover, leavers, absence, expiry dashboard, leave liability, service bands, **pay basis in force on a date**, labour hours per occupied room. Every one exports to CSV.
* **HR Settings** — probation and leave rules, the portal, the geofence and entrance QR, live sessions, failed sign-ins, and the cron URL.

## The staff portal (`/pulse/hr`)

A phone-first page a member of staff opens on their own handset. It is treated as hostile:

* The page ships **no personal data at all** — a shell plus the API URL. Everything comes after a staff number and PIN have been exchanged for a signed, short-lived session.
* The PIN is hashed with `_COOKIE_KEY_` exactly as `PulsePosService::login` does, so one PIN can serve the POS too. Weak PINs (repeats, `1234`) are refused.
* Sign-in failures are counted **per staff number and per address** inside a window, and the whole window is refused once the limit is hit; every attempt, good or bad, is logged with its address and agent.
* Sessions are `sid.exp.HMAC`, verified on every call against the stored token hash, the expiry, the revocation flag **and the person's current standing** — an exit or a suspension kills a live session mid-use.
* **No resource ever takes the employee id from the request.** The subject of a self-service call is the session's employee, full stop; a colleague's id in a body changes nothing. Withdrawing a leave request re-checks ownership from the row.
* Salary is behind a second gate: the payslip section stays locked until the PIN is entered again, opens for a few minutes, then re-locks. `me` never returns pay, and the bank account comes back masked.
* The token lives in `sessionStorage`, so closing the tab on a borrowed phone signs the person out.
* Mobile punches are **visibly not biometric**: source `mobile` or `qr`, with coordinates, GPS accuracy, distance from the hotel and a flag reason, all shown to the supervisor with a map link. Outside the fence is rejected (or flagged, if enforcement is off) — never silently accepted, and never silently discarded either.

Where Pulse Time or Pulse Payroll is missing, the portal shows the sections it can and states plainly why the rest are unavailable. It does not fatal, and it does not invent a figure.

## Cross-module integration

| Module | What Pulse HR does with it | Without it |
|---|---|---|
| `pulsefrontdesk` | occupancy from the night audit for roster coverage and the labour KPI; `PulseTicket` for lapsed documents; `PulseTrace` for coverage gaps and odd punches | occupancy is derived from `htl_booking_detail`; no tickets or traces |
| `pulsekeycard` | onboarding issues a staff card via `PulseKcStaff::issueCard`, clearance cancels and blacklists every live card | the task stays a manual tick with the reason shown |
| `pulsepos` | onboarding writes a `pulse_pos_staff` PIN, clearance disables the login; `pulse_pos_clock` is readable as another attendance source | the task says the module is absent |
| `pulsetime` | accepted mobile punches are handed over through `PulseTaPunch::ingest/record/add` if it publishes one, and the cron retries anything unsynced | punches stay here, and the portal says biometric history is unavailable |
| `pulsepayroll` | payslips on the portal behind the PIN gate; `PulseHrContract::forPeriod()` is the pay basis it must read | the portal says payslips are unavailable and why |
| `pulsecomms` (Front Desk) | leave decisions notified by SMS/WhatsApp using Comms' own `ticket_update` template — we do not invent a template it does not know | the portal is the notification |

Events raised: `actionPulseHrEmployeeHired`, `actionPulseHrEmployeeExited`, `actionPulseHrLeaveApproved`, `actionPulseHrRosterPublished`, `actionPulseHrMobilePunch`.
Listened to: `actionPulseNightAuditClosed` (accrue leave on the configured day, roll leave, restamp documents, roll the roster day and warn on tomorrow's coverage).

## JSON API — `/pulse/api/hr/{resource}/{id}`

Auth is `X-Pulse-Ess: <session>` (staff portal) or `Authorization: Bearer <token>` with scope `hr`, `manager` or `ess`.

| Resource | Scope | What it does |
|---|---|---|
| `ping` | open | module, business date, which portal sections are available |
| `login` / `logout` | open (rate limited) | staff number + PIN → signed session |
| `me` | ess | the person, with no pay and a masked account |
| `employees` / `employee` | hr | search and read the master |
| `leave_types`, `leave_balance`, `leave_request` | ess / manager | balances, raise a request, withdraw your own |
| `roster` | ess / manager | your published shifts, or the grid and coverage |
| `swap` | ess | ask a colleague (by staff number) to take a shift |
| `clock` | ess only | the geofenced mobile punch |
| `punches` | ess / manager | your clockings, or a department's |
| `reveal` → `payslips` | ess / hr | PIN re-entry opens a short window; payslips come from Pulse Payroll |
| `documents`, `document_expiry` | ess / hr | your documents, or everything lapsing |
| `cases`, `acknowledge` | ess | your record, and acknowledging a query or warning |
| `update_request` | ess | ask HR to correct a detail |
| `coverage` | manager | heads rostered against heads the occupancy needs |

## Cron

```
php modules/pulsehr/cron/hr.php <PULSE_HR_CRON_TOKEN> [all|accrue|leave|documents|contracts|roster|punches|housekeeping]
```

Nightly, after the night audit. Accrues leave on the configured day of the month, rolls leave whose dates have arrived (and carries balances forward on 1 January), restamps document statuses and raises a ticket for anything lapsed, traces contracts and probations coming up, warns on tomorrow's coverage, retries handing punches to Pulse Time, and purges expired sessions, old sign-in logs and rate buckets. Every step is idempotent — the night-audit hook does the same work when Front Desk is installed, and neither doubles the other up.

## Seed

```
php modules/pulsehr/seed/seed.php
```

A 52-room Port Harcourt hotel: 13 sections, 33 positions with a budgeted establishment, ~86 staff with Nigerian names and naira salaries by grade (room attendant ₦150k, line cook ₦150k, supervisor ₦310k, duty manager ₦470k, HOD ₦720k, FC ₦1.15m, GM ₦2.25m), effective-dated contracts with **six mid-month promotions**, documents including two medicals lapsing this week and one food handler certificate already lapsed, leave accrued to date with taken and pending requests, a December blackout, two weeks of roster with the current week published, thirty portal PINs, a fortnight of mobile punches with realistic lateness, two missing out-punches and two flagged locations, three leavers, two part-done onboardings, one completed clearance, three discipline cases, an appraisal cycle and twenty training records. Idempotent — staff numbers are deterministic, so a second run refreshes rather than duplicates.

## Parity checklist

- [x] Employee master linked, not duplicated, to `employee` and `pulse_pos_staff`
- [x] Departments, sections, positions, grades, establishment, org chart
- [x] **Contracts versioned with effective dates; `onDate()` and `forPeriod()` for payroll**
- [x] Documents with expiry, reminders and a ticket when something lapses
- [x] Leave types, accrual, per-grade entitlement, balances, approval chain, calendar, blackouts, encashment, liability
- [x] Roster with night shifts, publish, swaps and occupancy-driven coverage
- [x] Onboarding and offboarding checklists that issue and revoke key cards, POS PINs and portal access
- [x] Discipline with acknowledgement, appraisal with weighted objectives, training with expiry
- [x] ESS portal with signed sessions, rate limiting, PIN hashing, a payslip gate and geofenced clocking
- [x] Reports incl. labour hours per occupied room, all CSV-exportable
- [x] JSON API with `hr` / `manager` / `ess` scopes
- [x] Night-audit hook and a nightly cron
- [ ] Deferred to a later pass: document file upload (paths are recorded, the file store is not); an employee photo uploader; multi-level approval routing configurable per department (the chain is manager → HOD → HR); a printable PDF of the appraisal and the contract; Arabic/Pidgin translations of the portal (the guest portal has them, the staff one is English only).

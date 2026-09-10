# Pulse Payroll v1.0 — benchmark vs Sage 300 People, SeamlessHR, PaidHR and OPERA/Sun

Generic payroll packages compute PAYE and print a payslip. A hotel needs three things they do not have:
a service-charge pool distributed to the people who earned it, a fast weekly cash cycle for banqueting
and laundry casuals, and payroll numbers that reconcile to the general ledger and to occupancy. Pulse
Payroll does those, on a multi-country statutory framework where a rate change is a dated row rather
than a code release.

| Capability | Sage 300 People | SeamlessHR / PaidHR | OPERA + Sun | Pulse |
|---|---|---|---|---|
| Nigerian PAYE with annualised bands and de-annualisation | ✓ | ✓ | via export | ✅ Nigeria Tax Act 2025, six bands |
| Consolidated Relief Allowance abolished; rent relief in its place | varies | ✓ | — | ✅ 20% of declared rent, capped at ₦500,000, **evidence-gated** |
| NHF treated as voluntary since the 2025 Act | varies | varies | — | ✅ opt-in only, no deduction without a dated consent |
| Rates as effective-dated data, not code | partial | partial | — | ✅ bands, reliefs and contributions all dated; back-dated 2025 still works |
| Second country without a new build | ✓ (licence) | — | — | ✅ Ghana pack drives entirely off the tables |
| Pension on basic + housing + transport, 8 / 10 | ✓ | ✓ | — | ✅ named-base `BHT`, never gross |
| NSITF 1% employer, ITF 1% accrued monthly and remitted annually | ✓ | ✓ | — | ✅ with the size tests and the remittance due dates |
| Proration for joiners, leavers, unpaid leave, mid-month salary change | ✓ | ✓ | — | ✅ segmented, on calendar / working / thirtieths |
| Variance report against the prior period | ✓ | partial | — | ✅ with starter, leaver and dropped flags |
| Re-run of an unchanged period is byte-identical | assumed | assumed | — | ✅ result hash, proved by the self-check |
| **Service charge / tronc pool by points or hours, with weightings and a management cap** | — | — | — | ✅ taxable, not pensionable, per-employee statements |
| **Casual and per-shift weekly cycle from approved timesheets** | partial | partial | — | ✅ one grid, signing sheet, its own tax treatment |
| Staff loans with schedules, automatic recovery and arrears | ✓ | ✓ | — | ✅ net pay can never go negative |
| NIBSS-style bank files per bank with control totals | ✓ | ✓ | — | ✅ proved by reading the file back before it is stored |
| Payslip confidentiality | email PDF | portal | — | ✅ unguessable token **and** a PIN, throttled, expiring, view-stamped |
| Posts to the general ledger | via export | via export | ✓ | ✅ `PulseAccPosting::payroll()`, refuses to post if it will not balance |
| Labour cost per occupied room | — | — | ✓ (Sun) | ✅ from Front Desk occupancy |
| Synthetic self-check an accountant can verify by hand | — | — | — | ✅ 107 assertions, one button |

Runs standalone. Without Pulse HR the payroll roster is the master employee record; without Pulse Time
timesheets are keyed in; without Pulse Accounts a run completes and simply posts nothing to a ledger.
Licence entitlement: `pulsepayroll`.

---

## The statutory framework

Four tables and one small interface. Nothing time-sensitive is written in code.

| Table | What it holds |
|---|---|
| `pulse_pr_country` | currency, tax-year start, rounding rule, PAYE basis (annual/monthly) and mode (cumulative/non-cumulative), the pack class, and a `verified` flag |
| `pulse_pr_tax_band` | band floor/ceiling, rate, annual or monthly basis, `effective_from` / `effective_to` |
| `pulse_pr_relief` | fixed / percent-of / capped-percent / greater-of, base, cap, `requires_evidence`, the declaration code it is gated on, effective dates |
| `pulse_pr_contribution` | employee and employer rates, named base, floor, ceiling, `mandatory` / `opt_in` / `opt_out`, consent code, pre-tax flag, employer size tests, remittance rule and due days, GL accounts, effective dates |

`PulsePrStatutoryInterface` carries the logic a table cannot express: annualisation and de-annualisation,
cumulative vs non-cumulative, evidence-gated reliefs, the mid-year joiner projection, and what a final
settlement does. `PulsePrStatutoryGeneric` drives a country purely from the tables.
`PulsePrStatutoryNigeria` extends it and overrides only four things: the annualisation window, the
projection for a joiner and a leaver, the pre-tax contribution projection on a final settlement, and the
pre-2026 minimum tax.

### Making a rate change without a release

The Statutory screen has a **Supersede** button that closes the current band set the day before a date
you give and copies it forward, so the old rows keep their `effective_to` and last year still calculates
as last year. Then you edit the new rows. Or do it by hand — a hypothetical 2027 change of the top rate
from 25% to 27% is one update and one insert:

```sql
UPDATE `ps_pulse_pr_tax_band` SET `effective_to` = '2026-12-31'
 WHERE `country` = 'NG' AND `regime` = 'paye' AND `seq` = 6 AND `effective_to` IS NULL;

INSERT INTO `ps_pulse_pr_tax_band`
  (`country`,`regime`,`seq`,`band_from`,`band_to`,`rate_pct`,`basis`,`effective_from`,`effective_to`,`note`)
VALUES
  ('NG','paye',6,50000000,NULL,27,'annual','2027-01-01',NULL,'Finance Act 2027 — top rate raised to 27%');
```

Every run dated 2027-01 or later picks it up; every recalculation of 2026 still produces the 2026 answer.
Run the self-check afterwards.

### Nigeria — complete, verified against the position at September 2026

Effective 1 January 2026 under the **Nigeria Tax Act 2025**:

- PAYE, annual bands: first ₦800,000 nil · next ₦2,200,000 at 15% · next ₦9,000,000 at 18% ·
  next ₦13,000,000 at 21% · next ₦25,000,000 at 23% · above ₦50,000,000 at 25%.
- **The Consolidated Relief Allowance is gone.** It is kept as a historical relief row with
  `effective_to = 2025-12-31`, so a back-dated 2025 recalculation reinstates it automatically.
- **Rent relief** replaces it: 20% of annual rent paid, capped at ₦500,000, and *only* where the
  employee has declared the rent and someone has ticked the evidence as verified on their record. A
  declaration without verified evidence yields nothing and says so on the screen.
- Pension: employee 8%, employer 10%, on **basic + housing + transport only** — the named base `BHT`.
  Remittance is due within 7 working days of paying salary; late remittance attracts 2% a month, and the
  rule and the penalty rate are on the contribution row.
- NSITF: 1% of gross, employer-borne, every employer, monthly by about the 10th.
- ITF: 1% of annual gross payroll, employer-borne, where there are 5+ employees **or** ₦50m+ turnover.
  Accrued on every payslip, aggregated into one annual remittance row due about 1 April.
- **NHF is voluntary.** The contribution is `opt_in` against the declaration code `NHF_CONSENT`. With no
  dated consent on file the module deducts nothing, prints "NHF is voluntary and is not being deducted,
  because no consent is recorded for you" on the payslip, and leaves the employee off the NHF schedule.
  With a consent, 2.5% of basic is deducted, the consent date appears on the payslip and on the schedule,
  and it reduces chargeable income as a pre-tax deduction.
- NHIA/NHIS: shipped as an inactive `opt_in` contribution. Turn it on and set the rates for your scheme.
- Also retained as pre-tax deductions, each evidence-gated: life assurance premiums and mortgage interest
  on an owner-occupied home.

### Ghana — a starting point, **not verified**

> **Read this before running a live Ghanaian payroll on it.** The Ghana pack is a seam test. It proves the
> framework can drive a second country with no new code — monthly bands rather than annual, SSNIT rather
> than PenCom, no annualisation — and its numbers come from published rate tables rather than from a
> current GRA or SSNIT circular checked by a Ghanaian practitioner. The country row is flagged
> `verified = 0`, every screen that touches it says so, and the rate rows carry "UNVERIFIED starting
> point" in their notes. Verify the bands, the SSNIT split and the relief treatment locally, correct the
> rows on the Statutory screen, then tick **Verified** on the country. Nothing stops you running it as it
> is; nothing pretends it is certified either.

---

## Arithmetic

The rules the module holds itself to, all of them checked by the self-check on the Settings screen:

1. **Money rounds at the element level** and the earning lines always sum to the printed gross. A
   percent-of-package structure whose percentages add to 100 puts any rounding crumb on the largest
   element rather than quietly widening gross.
2. **Proration factors are never pre-rounded.** Ten days of a thirty-day month is `10/30` all the way to
   the money, not `0.333333` — which is the difference between ₦60,000.00 and ₦59,999.94 on a payslip.
3. **De-annualisation does not drift.** The cumulative allocator takes `round(annual × elapsed ÷ periods, 2)`
   and subtracts what has already been deducted, so twelve months sum to the annual charge to the kobo
   even when the monthly figure is ₦771,953.33̅.
4. **A re-run is byte-identical.** Calculating rewrites the run from scratch (unwinding its loan
   recoveries first) and stores a `sha1` over every payslip's canonical line list; an unchanged period
   reproduces the same hash.
5. **Net pay is never negative.** A recovery is capped at what the payslip can bear above the protected
   floor, and the shortfall becomes an arrears row against the employee.
6. **A run that does not reconcile cannot be approved**, and one that will not balance in the ledger is
   not posted at all.

---

## Tables

| Table | Purpose |
|---|---|
| `pulse_pr_country` · `pulse_pr_tax_band` · `pulse_pr_relief` · `pulse_pr_contribution` | the effective-dated statutory framework |
| `pulse_pr_element` | pay elements: type, calculation, named base, taxable / pensionable / NSITF-able / in-basic / proratable / recurring, GL account, sequence |
| `pulse_pr_employee` | the payroll roster (mirror of Pulse HR when installed, master when not) with RSA PIN, PFA, TIN, bank, hashed payslip PIN |
| `pulse_pr_employee_element` | pay structures, effective-dated, per employee or per grade (employee `NULL` + a grade is the grade default) |
| `pulse_pr_declaration` | rent, life assurance, mortgage, NHF and NHIS consents — with evidence reference, verified flag, consent date and channel |
| `pulse_pr_opening` | year-to-date carried in from the system the property used before |
| `pulse_pr_timesheet` | the local timesheet, used when Pulse Time has not approved one |
| `pulse_pr_run` · `pulse_pr_payslip` · `pulse_pr_payslip_line` | runs, payslips and every element behind them |
| `pulse_pr_loan` · `pulse_pr_loan_schedule` · `pulse_pr_arrears` | loans, advances, their schedules and parked shortfalls |
| `pulse_pr_tronc_pool` · `pulse_pr_tronc_line` · `pulse_pr_tronc_weight` | the service-charge pool, its distribution and the department weightings |
| `pulse_pr_casual_batch` · `pulse_pr_casual_line` | the weekly casual cycle |
| `pulse_pr_bank` · `pulse_pr_bank_file` | the NIBSS bank register and the generated payment files with their control totals |
| `pulse_pr_remittance` | PAYE, pension, NSITF, ITF and NHF liabilities with their due dates |
| `pulse_pr_audit` | who calculated, approved, reopened, posted, downloaded a payment file or opened a payslip |

`pulsepayments` owns `pulse_pay_*` and `PulsePay*`; this module deliberately uses `pulse_pr_*`,
`PulsePr*` and `AdminPulsePr*`, checked against every table, class and admin controller already in the
suite (253 tables, 150 classes, 98 admin controllers at the time of writing) before a line was written.
The back-office CSS prefix check is `AdminPulsePr`, which cannot match `pulsepayments`' `AdminPulsePay*`.

## Screens

**Payroll** (dashboard, runs, run detail with payslips / variance / by-department / payment / GL preview /
audit trail, and the payslip view) · **Payroll Employees** (roster, pay structure, declarations and
consents, timesheet, payslips and YTD, loans, opening balances) · **Pay Elements** (elements and grade
structures, with a "does this grade add up" check) · **Casual & Weekly Pay** · **Service Charge** ·
**Loans & Advances** · **Statutory & Countries** · **Payroll Reports** · **Payroll Settings** (with the
self-check).

## Hooks

Consumed: `displayBackOfficeHeader`, `moduleRoutes`, `actionPulseNightAuditClosed` (ITF accrual),
`actionPulseHrEmployeeHired`, `actionPulseHrEmployeeExited`.
Raised: `actionPulsePayrollCalculated`, `actionPulsePayrollApproved`, `actionPulsePayrollPosted`,
`actionPulsePayrollTroncDistributed`.

## API — `/pulse/api/payroll/*`

`ping` · `payslips` · `payslip` · `ytd` · `loan_balance` · `service_charge_statement` · `runs` · `run` ·
`remittances`. Scopes `payroll` and `ess`.

A `payroll`-scoped token is a back-office integration and may name any employee. An `ess`-scoped token is
a shared staff-portal credential — it identifies the app, not the person — so it must present the
employee's staff number and payslip PIN with every request. `payslip` additionally requires the payslip's
own token. Pay data is never handed out on a bearer token alone.

## Payslip confidentiality

`/pulse/payslip?t=<48 hex characters>`. The token is `sha256` over the shop cookie key, the run, the
employee and a nonce; it expires (90 days by default); and it opens nothing until the employee enters
their payslip PIN, hashed with `_COOKIE_KEY_` the way POS PINs are. Five wrong PINs against one token
from one address in fifteen minutes and the link goes quiet. Every successful view is stamped on the
payslip and written to the audit trail. If a link leaks, **Reissue the download link** kills it instantly.
The email carries the link and no figures, and goes through `PulseComms` when Front Desk is installed so
the send lands in the suite's communications log, falling back to the module's own mail template when it
is not.

## Cron

```
php modules/pulsepayroll/cron/payroll.php <PULSE_PR_CRON_TOKEN> all
```

`payslips` (email the queue from approved runs) · `accrue` (ITF and the remittance rows) · `loans`
(refresh balances and settle) · `remind` (flag overdue remittances and raise a ticket) · `all` ·
`selfcheck` (exits non-zero on a failure, so a monitoring job can watch it). Nothing here approves, pays
or posts — money never moves without a person pressing a button.

## Parity checklist

- [x] Effective-dated statutory tables; a rate change is one insert
- [x] Nigeria pack complete and verified: 2026 bands, rent relief, pension 8/10 on BHT, NSITF, ITF, NHF opt-in, NHIS optional
- [x] Pre-2026 CRA regime retained with an effective-to date; back-dated 2025 recalculation verified
- [x] Second pack (Ghana) proving the seams, clearly labelled unverified
- [x] Pay elements with named bases, taxable / pensionable / proratable / recurring flags
- [x] Grade default structures with per-employee override, all effective-dated
- [x] Runs: draft → calculated → approved → paid → posted, with a reopen that is audited and refused after payment
- [x] Variance report with starter / leaver / dropped flags
- [x] Proration for joiners, leavers, unpaid leave and a mid-month salary change
- [x] Service charge by points or hours, department weightings, management cap, per-employee statements
- [x] Casual and per-shift weekly cycle with a signing sheet
- [x] Loans and advances with automatic recovery, a protected net floor and arrears
- [x] Statutory schedules: PAYE, pension, NSITF, ITF, NHF — each exportable
- [x] NIBSS-style bank files per bank, control totals proved by reading the file back
- [x] Tokenised, PIN-gated, throttled, expiring payslip download
- [x] GL posting through `PulseAccPosting::payroll()`, idempotent, refuses to post if unbalanced
- [x] Labour cost per occupied room and labour as a share of revenue
- [x] Self-check: 107 assertions against the real calculator, one button
- [x] `php -l` clean on 55 PHP files; all 16 templates compile against the bundled Smarty

## Deliberately left for a later pass

- Payslips render as a print-to-PDF page rather than a server-generated PDF. TCPDF is present in the
  core, but a hand-built PDF layout has no advantage over the browser's own here and one more thing to
  break at 2 a.m.
- The FIRS/state e-filing APIs are not integrated; the schedules export as CSV for upload.
- The Ghana pack has no employer-side SSNIT tier split beyond the single 13% row.

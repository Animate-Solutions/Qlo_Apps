# Pulse Accounts v1.0 — benchmark vs OPERA + Sun Financials, eZee back office, Sage 50 / QuickBooks

An SME hotel in Port Harcourt normally runs its finances in three disconnected places: the PMS folio, a
spreadsheet the accountant keeps, and QuickBooks that somebody re-keys into once a month. OPERA solves
this with an interface to Sun/Oracle Financials — a nightly export file, a mapping table, and a finance
system nobody in the property can see. eZee ships a back-office export in the same spirit. Pulse Accounts
puts the general ledger *inside* the suite: every folio line, POS check, expense, GRN and stock issue
becomes a balanced double-entry journal, and the accountant reads the same numbers the front desk made.

| Capability | OPERA + Sun | eZee back office | Sage 50 / QuickBooks | Pulse Accounts |
|---|---|---|---|---|
| Chart of accounts with USALI departments, control accounts and cash-flow classification | ✓ (in Sun) | — | ✓ (generic) | ✅ ~150 accounts seeded, hotel-shaped, editable tree |
| Double entry that refuses to post unless it balances to the cent | ✓ | — | ✓ | ✅ `PulseAccJournal::post()` |
| Nothing can post twice | via interface control | manual | manual | ✅ `UNIQUE(source, source_ref)` on every journal |
| Posted entries never edited or deleted — reverse with a contra | ✓ | — | ✓ | ✅ `PulseAccJournal::reverse()`, both journals stay live |
| Automatic posting from folio, POS, expenses, GRN and stock | nightly export | export file | re-keyed | ✅ event → queue → night audit, with a manual "post now" |
| POS revenue split food / beverage / liquor by major group | ✓ | partial | — | ✅ posted from the check, not the one-line folio summary |
| Guest ledger, city ledger, advance deposits as real control accounts | ✓ | — | manual | ✅ reconciled on the dashboard |
| City-ledger AR: invoice, age, statement, part-payment allocation, credit notes | ✓ | partial | ✓ | ✅ + credit limit, stop list, 3-level dunning letters |
| Customer withheld 5% WHT and paid 95% — invoice still clears | manual | — | manual | ✅ WHT credit posts to 1260 and clears the invoice |
| AP: supplier bills from GRN, ageing, payment runs, remittance advice | ✓ (Sun) | partial | ✓ | ✅ + WHT withheld at bill or payment |
| GRN accrual cleared by the supplier invoice | ✓ | — | manual | ✅ 2120 in, 2110 out, input VAT on the invoice |
| Nigerian VAT 7.5% register and monthly return | — | — | tax module | ✅ output/input register, return, ledger cross-check, filing journal |
| Rivers State consumption tax split out of a 12.5% F&B charge | — | — | — | ✅ automatic split on every F&B line |
| WHT register with sequential certificate numbers and printable certificates | — | — | — | ✅ deducted and suffered, remittance run |
| FIRS / NRS e-invoicing hand-off | — | — | — | ✅ payload built, validated and queued; endpoint and keys are settings |
| Bank statement import and auto-match | ✓ | — | ✓ | ✅ Nigerian bank CSV layouts, amount + date + reference matching |
| Petty cash / imprest tied to the cashier's float and drawer variance | — | — | manual | ✅ `pulse_cashier_session` variances post to the book |
| Fixed assets: straight line, reducing balance, units of production | ✓ (Sun) | — | ✓ | ✅ + revaluation, impairment, componentisation |
| Depreciation run that cannot run the same month twice | ✓ | — | ✓ | ✅ two independent unique keys |
| Disposals with gain/loss computed and posted | ✓ | — | ✓ | ✅ 4920 / 8700 against net book value |
| Engineering asset register kept separate but linked | — | — | — | ✅ `id_pulse_asset` → Pulse Maintenance, one asset, two views |
| Period control with a hard block on posting into a closed month | ✓ | — | ✓ | ✅ open / closed / locked, closing entries, year-end roll |
| USALI departmental P&L with GOP and EBITDA | ✓ | — | — | ✅ plus occupancy, ADR and RevPAR on the same page |
| Trial balance, balance sheet, indirect cash flow, GL drill-down to source document | ✓ | — | ✓ | ✅ every report exports to CSV |
| Daily revenue journal reconciling the night audit to the ledger | ✓ | partial | — | ✅ with the variance spelled out |

## How posting works

The operational modules never wait for the ledger. `actionPulseFolioPost`, `actionPulsePosBillSettled`
and `actionPulseInvReceived` do one cheap `INSERT IGNORE` into `pulse_acc_queue` and return. At night
audit (`actionPulseNightAuditClosed`) — or when the accountant presses **Post now** — the queue is
drained. Before draining, `PulseAccPosting::sweep()` scans the business date for anything the events
missed: a machine that was offline, a module installed later, an expense approved with no event. Every
document is independent, so one bad rule parks one row as `failed` with its reason on the dashboard
instead of stopping the day.

| Source | Debit | Credit |
|---|---|---|
| Folio charge | guest ledger 1210 (company folio → 1220) | revenue per charge-code rule, VAT 2210, consumption tax 2240 |
| Folio payment | cash / bank / card 11xx, city ledger 1220, deposit 2310 | guest ledger 1210 |
| POS check | tender accounts, or 1210 for a room charge | 4210 food / 4230 soft / 4235 liquor by major group, 4250 service charge, 2340 tips, taxes |
| Expense (approved or paid) | expense account, input VAT 1270 | cash / bank / AP 2110, WHT 2230 where the category attracts it |
| GRN | stock 13xx by item category | GRN accrual 2120 |
| Supplier bill | GRN accrual 2120 or the expense account, input VAT 1270 | trade payables 2110, WHT 2230 |
| Stock issues, waste, counts (one journal a day) | cost of sales 51xx/52xx/54xx or the department expense | stock 13xx |
| Depreciation (one journal per class per month) | 84xx | accumulated depreciation 15xx |

Folio lines whose source is `pos` are deliberately skipped — the whole check posts once from the POS
builder so food and beverage reach their own accounts instead of a single lump of "restaurant".

An **invoice raised from a company folio does not post revenue**: settling a guest folio to the city
ledger already posted Dr 1220 / Cr 1210 through the folio rules. The invoice is a document over
balances the ledger already carries, and it drives ageing, allocation and e-invoicing. A *manual*
invoice does post, because nothing else did.

## Tables

| Table | Holds |
|---|---|
| `pulse_acc_account` | chart of accounts: type, subtype, parent tree, USALI department, normal balance, control flag, cash-flow class |
| `pulse_acc_period` | accounting periods — open / closed / locked, closing journal, year-end flag |
| `pulse_acc_journal` / `_line` | the ledger. `UNIQUE(source, source_ref)` is the anti-double-post guard; lines carry their own date, period, USALI department and posted flag so report sums never join |
| `pulse_acc_map` | posting rules: charge code, expense category, payment method, department, stock category, POS major group, folio type, WHT type, asset class |
| `pulse_acc_queue` | the posting queue: pending → posted / skipped / failed, with attempts and the last error |
| `pulse_acc_invoice` / `_line` | sales invoices and credit notes over the city ledger |
| `pulse_acc_receipt`, `pulse_acc_allocation` | money in, and how it was applied across invoices (AR) or bills (AP) |
| `pulse_acc_dunning` | generated reminder / second notice / final demand letters |
| `pulse_acc_bill` / `_line`, `pulse_acc_payment` | supplier bills and payment runs |
| `pulse_acc_vat` | VAT output and input register, with the consumption-tax column |
| `pulse_acc_wht` | withholding tax deducted and suffered, with certificate numbers and remittance |
| `pulse_acc_einvoice` | FIRS/NRS payload queue: status, attempts, IRN, response, retry backoff |
| `pulse_acc_bank_account`, `_statement`, `_line` | bank / cash / imprest accounts, imported statements and their match state |
| `pulse_acc_petty_cash` | the imprest book, tied to `pulse_cashier_session` |
| `pulse_acc_asset_class`, `pulse_acc_asset`, `pulse_acc_depreciation`, `pulse_acc_asset_event` | fixed assets, the monthly schedule and the register's history |

## Admin screens

* **Accounts** (`AdminPulseAccounts`) — unposted queue with the failures and their reasons, period status,
  cash position, AR/AP ageing, today's daily revenue journal against the night audit, the stop list.
* **Chart of Accounts** (`AdminPulseAccCoa`) — the tree with balances and period movement, add/edit,
  deactivate (an account with postings can never be deleted), drill straight to the ledger, CSV export.
* **Journals** (`AdminPulseAccJournals`) — filter and search, open one with its lines and its *source
  document*, key a manual or payroll journal with a live balance check, reverse a posted one.
* **Receivables** (`AdminPulseAccAr`) — ageing to 120+, invoices, invoice a company folio or key a manual
  one, receipts with WHT and allocation across invoices, statements, credit notes, write-off, stop list, dunning.
* **Payables** (`AdminPulseAccAp`) — ageing, unbilled GRNs, standalone bills, payment runs, remittance advice.
* **Tax** (`AdminPulseAccTax`) — VAT return with a ledger cross-check and a filing journal, output/input
  registers, WHT register and printable certificates, remittance run, the FIRS e-invoicing queue and payloads.
* **Banking** (`AdminPulseAccBank`) — accounts, statement import and matching, create the missing journal
  straight from a statement line, unreconciled report, petty cash, cashier float variances.
* **Fixed Assets** (`AdminPulseAccAssets`) — register, capitalise (optionally linking an engineering asset),
  depreciation preview and run, forecast, CAPEX vs budget, classes, and per asset: schedule, history,
  transfer, revaluation, impairment, disposal, write-off.
* **Accounting Reports** (`AdminPulseAccReports`) — trial balance, general ledger, USALI P&L, balance sheet,
  cash flow, budget vs actual, revenue by department and charge code, daily revenue journal. All export CSV.
* **Posting Rules & Settings** (`AdminPulseAccSettings`) — every rule table with an **unmapped items**
  warning list, period control and year end, Nigerian tax defaults, hotel identity, e-invoicing credentials, cron.

## API — `/pulse/api/accounts/<resource>`

`ping` · `trial_balance` · `pl` · `ar_ageing` · `ap_ageing` · `post_queue` · `journal`.
Everything but `ping` requires the **`finance`** scope. `post_queue` with `drain=1` sweeps and posts;
`journal` accepts a POST body of balanced lines to write a manual entry. Bearer token from Pulse Core.

## FIRS / NRS e-invoicing — what a live connection actually needs

Pulse builds the document for every issued invoice and stores it in `pulse_acc_einvoice`. The payload
follows the UBL / BIS Billing 3.0 vocabulary the FIRS Merchant-Buyer Solution and the NRS e-invoicing
programme are built on: `business_id`, `irn`, `issue_date`, `invoice_type_code` (380 invoice, 381 credit
note), `document_currency_code`, `accounting_supplier_party` / `accounting_customer_party` with TIN and
postal address, `legal_monetary_total`, `tax_total` with a VAT `tax_subtotal`, and one `invoice_line` per
charge. The IRN is built in the documented `<invoice number>-<service id>-<YYYYMMDD>` form.

**Nothing here is fabricated.** Transmission is a `POST` of that JSON to `<endpoint>/api/v1/invoice/signing`
with `x-api-key` and `x-api-secret` headers, a configurable timeout and exponential backoff. All five
values — endpoint, business ID, service ID, key, secret — are settings, and the secret is stored through
`PulseCoreService::encrypt()`. With the endpoint blank the queue runs as a **dry run**: every payload is
built and validated but nothing is sent, so no invoice is lost while the property waits for onboarding.

To go live the property needs, from FIRS or its access-point provider:

1. Taxpayer onboarding on the e-invoicing portal and the **business ID** issued against the TIN.
2. A **service ID** for the invoicing application (it forms part of the IRN).
3. **API key and secret** for the environment (sandbox first, then production).
4. The production **base URL**, and confirmation of the signing/validation path if it differs from
   `/api/v1/invoice/signing`.
5. Confirmation of whether the response returns a **QR payload** and a **cryptographic stamp (CSID)** to
   print on the invoice — Pulse already stores both columns.
6. Its own **customers' TINs**, since the service rejects a B2B invoice without one. The AR screen shows
   the TIN it holds for each company; the validator refuses to spend a request on a document that will bounce.

Until those exist the accountant loses nothing: invoices, VAT register and the return all work, and the
queue can be drained the day the credentials land.

## Nigerian capital allowances

Book depreciation and tax capital allowances are not the same number, and Pulse keeps the book one. Each
asset class carries the CITA Second Schedule note it is judged against, shown on the asset screen:
industrial building 15% initial / 10% annual; plant and machinery 50% / 25%; furniture and fittings
25% / 20%; motor vehicles 50% / 25%; no allowance on land. Generators and vehicles are set to reducing
balance because that is how they actually lose value in Port Harcourt; buildings and FF&E are straight
line. The **WDV register** and the **depreciation forecast** are the two schedules an auditor and a tax
consultant ask for, and both export to CSV.

## Integration points

* **Front Desk** — folio lines and payments (`actionPulseFolioPost`), company folios for city-ledger
  invoicing, `pulse_cashier_session` floats for the imprest book, `pulse_night_audit` for the daily
  revenue journal reconciliation. `PulseAccAr::onStop($idCompany)` answers whether a company should be
  refused more credit.
* **POS** — settled checks (`actionPulsePosBillSettled`), split by `pulse_pos_category.major_group`.
* **Inventory** — GRNs (`actionPulseInvReceived`), stock movements valued at the module's own cost, and
  `pulse_inv_supplier` as the AP supplier list.
* **Reports** — `pulse_expense` is consumed, never duplicated: an approved or paid expense posts once and
  is never re-captured here. `pulse_budget` drives Budget vs Actual and CAPEX vs budget (`capex:<class>`).
* **Maintenance** — `pulse_asset` stays the engineering register; `pulse_acc_asset.id_pulse_asset` links
  to it and the Fixed Assets screen lists engineering assets with a purchase cost that are *not* yet
  capitalised.
* **Events raised**: `actionPulseAccJournalPosted`, `actionPulseAccPeriodClosed`, `actionPulseAccDepreciationRun`.
* **Events consumed**: `actionPulseFolioPost`, `actionPulsePosBillSettled`, `actionPulseInvReceived`,
  `actionPulseNightAuditClosed`.

## Cron

```
*/15 * * * *  php modules/pulseaccounts/cron/post.php <PULSE_ACC_CRON_TOKEN>
15 3 1-5 * *  php modules/pulseaccounts/cron/depreciation.php <PULSE_ACC_CRON_TOKEN>
```

The first sweeps the business date, drains the posting queue and pushes the e-invoice queue. The second
runs last month's depreciation; it is safe to fire on every day of that window because a period already
run is skipped rather than doubled.

## Demo data

```
php modules/pulseaccounts/seed/seed.php
```

Rivers Crest Hotel, 52 rooms, Port Harcourt: opening balances, a full month of daily revenue journals
(rooms split transient / corporate / OTA, F&B split food / soft / liquor, laundry, spa, telephone,
minibar, hall hire, VAT and consumption tax, settled across cash / card / transfer / city ledger),
cost of sales relieved from stock, diesel twice a week, monthly overheads with WHT withheld, payroll,
three city-ledger companies aged 12 / 47 / 104 days with one part-paid under a 5% WHT deduction, five
supplier bills and a payment run, a petty-cash book, ~67 fixed assets (generators, chillers, lift,
kitchen and laundry plant, vehicles, IT, furniture for every room) with the prior month's depreciation
already posted, budget lines, and `seed/bank_statement_sample.csv` — generated from the seeded journals
so the reconciliation screen matches most rows and leaves three (COT, an unknown NIP lodgement, SMS
alert fees) deliberately unmatched.

Runs standalone: without the other Pulse modules nothing posts automatically, but the chart of accounts,
manual journals, AR, AP, tax, banking, fixed assets, period control and every report still work.
Licence entitlement: `pulseaccounts`.

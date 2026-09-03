# Pulse Reports & Owner Snapshot v1.0

**Reports Dashboard** — one page for the whole hotel, any period, with comparison to the previous equal period: revenue (net/tax/gross by department, daily bars, mix), occupancy/ADR/RevPAR (daily trend, by room type, 7-day forecast), operating statement (revenue → cost of sales → gross profit → payroll/utilities/repairs/other → GOP and margin; cash in/out), collections by method, guest/city ledgers and deposits, cashier shifts and variances, front-office activity and guest mix, housekeeping/laundry/maintenance summaries with open items, budget vs actual, and a "needs attention" list.

**Expense ledger** — categorised expenditure with approval workflow above a limit, payee/reference, and automatic feeds from Maintenance (parts, contractors), Laundry (chemicals, vendors), meter readings (diesel, power, water, gas) and front-desk paid-outs, all idempotent. Monthly budgets.

**Scheduled reports** — owner daily snapshot, manager daily, weekly (Mondays) and monthly (1st) by email (HTML) and SMS one-liner; sent automatically when night audit closes with a fallback time; preview, send-now, full send history with the report as delivered.

Benchmarks: eZee Reports/Manager's Flash and OPERA Manager's Report / Daily Revenue Summary cover revenue, occupancy and ledgers; neither ships an expenditure ledger or an owner P&L — that is Pulse-specific, built for owner-operated Nigerian hotels. Depends on pulsecore; every block degrades gracefully when a module is absent. License entitlement: `pulsereports`.

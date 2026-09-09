# Pulse Payments v1.0 — benchmark vs eZee iPay, OPERA OPI and the Nigerian bank reality

eZee ships **iPay** (a hosted gateway bridge with tokenised card-on-file) and OPERA integrates payment terminals through the **Payment Interface (OPI)** — EMV terminal drive, pre-auth at check-in, capture at check-out, settlement files back to the PMS. Both assume a working payment terminal integration and a bank that answers on time. In Port Harcourt the same job is done with Paystack, Flutterwave, Interswitch WebPAY, a Moniepoint transfer alert, and a GTBank POS terminal whose only "API" is a human reading the RRN off a slip. Pulse Payments covers all of it in one module, with a manual fallback on every path.

| Capability | eZee iPay | OPERA OPI | Pulse Payments |
|---|---|---|---|
| Pluggable gateway adapters with capability flags | 1 gateway | via OPI vendor | ✅ `PulsePayGatewayInterface` — Paystack, Flutterwave, Interswitch, manual |
| Tokenised card on file, charge without the guest present | ✓ | ✓ | ✅ Paystack `charge_authorization`, Flutterwave tokenised charge |
| Pre-authorisation at check-in, top-up, partial & full capture, void | partial | ✓ | ✅ with expiry tracking and expiry warnings on the dashboard |
| Fallback when the gateway cannot hold a card | — | — | ✅ recorded manual hold (`hold_type=manual`) — the desk is never blocked |
| Capture at check-out posts a single CARD line to the folio | ✓ | ✓ | ✅ `PulseFolio::post()`, guarded by `pulse_pay_posting` unique key |
| Physical bank POS terminal | via OPI driver | ✓ EMV drive | ✅ request/claim queue **and** straight RRN + last-4 + auth-code entry |
| Payment links (deposit, self check-out, city-ledger invoice) | ✓ | — | ✅ tokenised + 6-character short code, expiry, single/multi-use, amount lock |
| Branded pay page and receipt page | ✓ | — | ✅ `/pulse/pay?t=…` with bank-transfer instructions built in |
| Signed webhooks with replay protection | ✓ | n/a | ✅ HMAC-SHA512 (Paystack), verif-hash (Flutterwave), re-query (Interswitch) |
| Money lands even if the guest closes the browser | partial | n/a | ✅ webhook **plus** a sweep cron that verifies every pending transaction |
| Refunds (full/partial) with reason and approver | ✓ | ✓ | ✅ + `pulse_pay_refund` ledger |
| Chargeback / dispute log | — | — | ✅ `pulse_pay_dispute` with evidence and due date |
| Settlement statement import & reconciliation | — | ✓ (files) | ✅ CSV import, matched / fee-variance / amount-variance / unmatched / missing |
| Gateway fee posted as an expense | — | — | ✅ `pulse_expense` category `BANK` when Pulse Reports is installed |
| Card surcharge with VAT on the surcharge | — | ✓ | ✅ per-channel policy, 7.5% VAT default |
| Credentials encrypted at rest, never echoed back | ✓ | ✓ | ✅ `PulseCoreService::encrypt()`, masked in the form |
| Full request/response log with secrets redacted | — | — | ✅ `pulse_pay_log`, per attempt, with timings |
| JSON API for POS / portal / desk | partial | — | ✅ `/pulse/api/payments/*` |

## Tables

| Table | Holds |
|---|---|
| `pulse_pay_gateway` | adapters, encrypted keys, test-mode flag, fee rate card, channel and capability lists |
| `pulse_pay_transaction` | the ledger: intent → authorized → captured / partially_captured → settled → refunded / voided / failed / expired |
| `pulse_pay_posting` | one row per money movement written to a folio or POS check — `UNIQUE(gateway_ref, purpose)` is the anti-double-post guard |
| `pulse_pay_log` | every gateway call: URL, redacted request/response, HTTP code, attempt number, duration |
| `pulse_pay_link` | payment links: token, short code, expiry, uses, amount lock, amount paid |
| `pulse_pay_event` | webhook deliveries with `UNIQUE(gateway, event_id)` for replay protection |
| `pulse_pay_terminal` | registered bank POS terminals (bank, TID, station, manual/claim mode) |
| `pulse_pay_terminal_request` | the terminal queue: queued → claimed → approved / declined / expired, with RRN and auth code |
| `pulse_pay_refund` | refunds with reason, requester and approver |
| `pulse_pay_dispute` | chargebacks and disputes with evidence and due date |
| `pulse_pay_settlement` / `_line` | imported gateway statements and their per-row match state |
| `pulse_pay_daily` | frozen daily settlement summary per gateway and channel |

Adds charge codes `SURCH` (card processing surcharge) and `MOMO` (mobile money) when Front Desk is installed.

## Admin screens

* **Payments** (`AdminPulsePayments`) — takings by gateway and channel, open pre-auths with expiry warnings, terminal queue with manual RRN entry, failures needing attention, recent webhook deliveries.
* **Transactions** (`AdminPulsePayTransactions`) — filterable ledger, raw payload and gateway call log per transaction, capture / void / refund / verify / manual confirm, dispute log.
* **Payment Links** (`AdminPulsePayLinks`) — mint from an in-house folio, copy or e-mail, cancel.
* **Reconciliation** (`AdminPulsePayRecon`) — import a settlement CSV, matched / variance / unmatched buckets, "captured by us but not on the statement", post fees to the expense ledger.
* **Payment Settings** (`AdminPulsePaySettings`) — gateway credentials (masked), fee rate card, capabilities, webhook URLs to copy, terminals, surcharge / auto-capture / expiry policy, cron token.

## API — `/pulse/api/payments/<resource>`

`ping` · `gateways` · `link_create` · `link_status` · `terminal_request` · `terminal_claim` · `terminal_result` · `terminal_poll` · `verify` · `transaction` · `preauth` · `capture` · `refund`.
Scopes: `pos` (terminal), `portal` (links), `desk` (pre-auth, capture, refund, verify). Bearer token from Pulse Core.

## Integration points

* **Front Desk** — `PulsePaymentBridge` calls `PulsePayments::authorize()`, `::capture()` and `::paymentLink()`. Captures post a `CARD` (or `POS`/`TRF`/`ONL`/`DEP`) payment line to the guest folio exactly once.
* **POS** — `PulsePayments::terminalRequest()` / `::terminalResult()`, or the JSON API. With `auto_settle` the approved terminal charge settles the check through `PulsePosPayment::pay()`; without it, POS settles its own tender and the payment stays a ledger record.
* **Reports** — settlement fees post as a `BANK` expense through `PulseExpense::add()` (idempotent on `source_ref`).
* **Comms** — payment links e-mail through `PulseComms::send('payment_link', …)`.
* **Events raised**: `actionPulsePaymentAuthorized`, `actionPulsePaymentCaptured`, `actionPulsePaymentRefunded`, `actionPulsePaymentFailed`, `actionPulsePayTerminalRequest`.
* **Events consumed**: `actionPulseBeforeCheckOut` (warn or auto-capture an open hold), `actionPulseCheckOut` (optionally release), `actionPulseNightAuditClosed` (roll the daily settlement summary, expire stale holds).

## Cron

```
*/10 * * * *  php modules/pulsepayments/cron/sweep.php <PULSE_PAY_CRON_TOKEN>
```
Verifies pending transactions the webhook never delivered, expires stale links / pre-auths / terminal requests, retries captures that failed while the link was down.

## Demo data

```
php modules/pulsepayments/seed/seed.php
```
Two gateways in test mode with obviously fake keys, a day of takings across card / transfer / cash / terminal, one open ₦150,000 hold, one payment link (`PHCDEM`), one queued terminal request, and `seed/settlement_sample.csv` to try Reconciliation with — it produces 2 matched rows, 1 fee variance, 1 unmatched and 1 captured-but-unsettled transaction.

Runs standalone: without Front Desk nothing is posted to a folio, but every transaction, link, refund and settlement still works. Licence entitlement: `pulsepayments`.

# Pulse POS v1.0 — benchmark vs eZee BurrP!/Optimus and Oracle MICROS Simphony

Legend: ✅ built · 🟡 partial · ⬜ not built (needs hardware, a third-party service, or a later phase)

| # | Feature | eZee | MICROS | Pulse POS | Notes |
|---|---|---|---|---|---|
| **Core order & table** |
| 1 | Graphical floor & table plan with live status | ✓ | ✓ | ✅ | sections, shapes, free/seated/ordered/billed/dirty/blocked/reserved, server & age on tile |
| 2 | Table joining, splitting, transfers, seat reassignment | ✓ | ✓ | ✅ | merge, transfer table/server, move item to seat, split by seat/item/equal/fixed amount |
| 3 | Conversational ordering: forced/optional modifiers, combos, allergy tagging | ✓ | ✓ | ✅ | min/max modifier groups, combo components with choice groups, per-check allergy note printed on every KOT |
| 4 | Handheld / mobile ordering (mPOS) | ✓ | ✓ | ✅ | responsive web app, add to home screen; same API for phones and fixed terminals |
| 5 | KDS: multi-station routing, timers, bump | ✓ | ✓ | ✅ | per-station screens, late/very-late colouring, bump per item or ticket, course holds; ESC/POS printers per station as alternative |
| **PMS integration** |
| 6 | Real-time room look-up by room number or name | ✓ | ✓ | ✅ | masked name + surname verification; VIP flag; credit-limit: 🟡 (Front Desk high-balance alert, no hard block) |
| 7 | Digital guest signature on room charge | ✓ | ✓ | ✅ | canvas capture, PNG stored with the payment, printed reference on folio |
| 8 | VIP & meal-plan/package recognition with allowance split | partial | ✓ | ✅ | BB/HB/FB/AI/custom per booking, allowance per person per meal per day, "Meal plan" tender pays the allowance, remainder to any tender |
| 9 | Room service workflow: delivery tracking, tray charge, PMS routing | ✓ | ✓ | ✅ | room-service outlet, auto tray charge, Delivered button, TV-portal ordering API, auto-charge of open checks at check-out |
| **Bar** |
| 10 | Bar tab management incl. card pre-auth, transfer to table/room | ✓ | ✓ | 🟡 | tabs by name, transfer, pre-auth recorded manually; live card pre-auth waits for `pulsepayments` |
| 11 | Speed screen / one-touch selling, quick cash | ✓ | ✓ | ✅ | "Quick" grid of the outlet's top-24 items (auto from 30-day sales), one-tap Quick cash settlement |
| 12 | Recipe costing & pour control | ✓ | ✓ | 🟡 | per-tot recipe deduction and variance report built; pour-monitor hardware interface (Berg/DraftChoice) ⬜ |
| 13 | Happy hour / dynamic pricing | ✓ | ✓ | ✅ | service periods by outlet, time, weekday → price level L1/L2/L3 and category visibility |
| **Billing & cashiering** |
| 14 | Complex bill splitting | ✓ | ✓ | ✅ | seat, item, equal N-way, fixed amount |
| 15 | Multi-tender settlement | ✓ | ✓ | ✅ | cash, card, transfer, mobile money, room, company/AR, voucher, foreign cash, meal plan, comp; tips; change |
| 16 | Currency & tax localisation, tax-exempt | ✓ | ✓ | ✅ | inclusive tax groups (VAT + consumption), taxable/non-taxable service charge, foreign cash with rate, per-check tax exemption with reference; fiscal printer ⬜ (not required in Nigeria) |
| 17 | Blind cashier close & audit trail | ✓ | ✓ | ✅ | expected cash hidden from non-managers, drops/paid-outs/no-sale logged, every void/discount/comp/reopen/drawer-open in `pulse_pos_audit` |
| **Menu, inventory, recipes** |
| 18 | Centralised menu across outlets | ✓ | ✓ | ✅ | single item list with per-outlet visibility and per-outlet price levels; CSV import |
| 19 | Ingredient tracking with recipe deduction, low-stock alerts | ✓ | ✓ | ✅ | deduction at kitchen fire (or settlement for no-prep items), reorder alerts on dashboard and owner report; yields/conversions 🟡 (single unit per ingredient) |
| 20 | Inter-department store transfers / requisitions | ✓ | ✓ | ✅ | requisition → issue, per-store quantities, transfer movements |
| 21 | Wastage & variance reporting | ✓ | ✓ | ✅ | waste with reasons; theoretical-vs-actual variance report (needs opening count) |
| **Enterprise** |
| 22 | Consolidated analytics across revenue centres | ✓ | ✓ | ✅ | 17 POS reports incl. menu engineering; settled checks flow into the outlet house folio so Reports/owner snapshot and cashiering reconcile |
| 23 | Role-based security with manager PIN authorisation | ✓ | ✓ | ✅ | per-user rights (void sent, discount + max %, comp, reopen, settle), manager PIN prompt inline |
| 24 | Offline continuity | ✓ | ✓ | 🟡 | service worker keeps the app shell; orders, sends, notes and voids are queued locally and replayed idempotently on reconnect; payments and kitchen printing need the server (local print-server fallback ⬜) |
| 25 | Time & attendance | ✓ | ✓ | ✅ | clock in on PIN login, clock out on lock |

**Left out (needs hardware / third party / next phase):** live card pre-authorisation and EMV terminal integration (`pulsepayments`), pour-monitoring hardware, fiscal printer compliance, ingredient yield/unit conversions, offline *payments* (a local print/relay server on the LAN is the standard answer and is a deployment task, not code).

Depends on `pulsecore`; Front Desk optional (room charge, meal plans and house-folio reconciliation need it). License entitlement: `pulsepos`.

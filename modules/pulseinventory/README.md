# Pulse Inventory & Stores v1.0

Hotel-wide stock control across F&B, housekeeping, rooms, front office and engineering. Benchmarks: eZee BurrP! Inventory / eZee Absolute stores, Oracle MICROS Inventory Management (Materials Control), and the stores practice of a mid-scale hotel.

| Capability | Pulse |
|---|---|
| Multiple stores (central, kitchen, bar, housekeeping, floor pantries, minibar, front office, engineering) with keepers and per-store par | ✅ |
| Item master with categories → expense codes, stock vs purchase units with conversion factor, barcodes, preferred supplier, batch/expiry tracking | ✅ |
| Weighted-average costing across stores, last cost, price history per supplier | ✅ |
| Requisitions: store issue (central → outlet) and purchase requests; approve, partial issue, auto-generate from reorder levels | ✅ |
| Purchase orders with supplier price list, approval limit (SuperAdmin above threshold), expected dates, print | ✅ |
| Goods received (GRN) against PO or ad hoc: partial receipt, rejections, batch/expiry capture, supplier invoice, automatic expense-ledger posting (Reports module) | ✅ |
| Transfers between stores, issue/consume/waste/return/production postings with department and cost centre | ✅ |
| Stock counts: full / cycle / spot, blind sheet printing, variance posting against live quantities, variance value | ✅ |
| Batches & expiry with FEFO consumption, expiry alerts to the front-desk trace board | ✅ |
| Reorder suggestions (level, reorder qty, max, on-order netting) and one-click purchase request | ✅ |
| Minibar: par list per room type, consumption posted to the guest folio (MINI) and deducted from minibar stock, complimentary flag, refill from central, check-out reminder if not checked, HK phone API | ✅ |
| Guest amenities: par per occupied room / on arrival, standard consumption posted nightly from the HK store, cost per occupied room | ✅ |
| POS integration: POS ingredients become linked items (Kitchen/Bar stores); recipe deductions, voids and waste flow in; receipts and counts flow out | ✅ |
| Maintenance integration: spare parts become linked items (Engineering store); work-order issues flow in | ✅ |
| Reports: stock on hand, valuation, movements, consumption by department, purchases, reorder, expiry, slow-moving, waste, count variances, supplier performance (lead time, rejections, late), minibar, cost per occupied room, stock card; CSV | ✅ |
| Owner snapshot: purchases already appear as expenses; stock valuation available to the Reports dashboard | ✅ |

Not in scope: multi-currency purchasing, supplier portals/EDI, production yields for kitchen batch recipes (POS recipes are per-portion), fixed-asset register (Maintenance owns assets). Depends on `pulsecore`; Front Desk, POS, Maintenance and Reports are optional and detected. License entitlement: `pulseinventory`.

**Operating rule once installed:** Inventory is the stock master. Receive all goods (including F&B) through Inventory → GRN; requisition to Kitchen/Bar/HK; POS keeps deducting recipes and Maintenance keeps issuing parts, and both see quantities that Inventory maintains.

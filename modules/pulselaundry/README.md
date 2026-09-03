# Pulse Laundry v1.0 — benchmark vs eZee Laundry & OPERA

eZee FrontDesk ships a laundry management screen (guest laundry with price list, express, post to folio, house laundry). OPERA has no laundry module of its own — laundry charges reach the folio through an interface (valet vendors, Materials Control) and linen is handled outside PMS. Pulse Laundry covers both operational sides in one module.

| Capability | eZee | OPERA | Pulse |
|---|---|---|---|
| Guest laundry order with itemised price list (wash / dry-clean / press) | ✓ | via IFC | ✅ |
| Express / same-day surcharge with cut-off and promised time | ✓ | — | ✅ |
| Pickup → in wash → ready → delivered tracking, valet & delivery staff logged | ✓ | — | ✅ |
| Post to guest folio (at ready or delivered), complimentary flag, tax | ✓ | ✓ | ✅ `PulseFolio::post('LNDY')` |
| Guest checks out with laundry in process → auto-post + desk alert | partial | — | ✅ hook `actionPulseCheckOut` |
| Damage / loss / quality claims with folio credit and Front Desk ticket | partial | — | ✅ |
| Outsourced vendor orders (rate/kg, turnaround) | ✓ | — | ✅ |
| House linen par stock: clean / in rooms / soiled / in wash / discarded, par target per room count, shortfall | ✓ (inventory) | via Materials Control | ✅ |
| Linen movements & loss report, wash-batch log (machine, program, kg, chemicals) | partial | — | ✅ |
| Guest request from TV/mobile portal, valet status updates via API | via partner | via partner | ✅ `/pulse/api/laundry/*` |
| Revenue, pieces, express mix, turnaround and late-order report | ✓ | ✓ (revenue only) | ✅ |
| Delivered notification to guest (SMS/email) | — | — | ✅ via Pulse Comms |

Runs standalone (orders and linen work without Front Desk; charges are simply not posted). License entitlement: `pulselaundry`.

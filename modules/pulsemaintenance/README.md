# Pulse Maintenance v1.0 — benchmark vs eZee Maintenance & OPERA Task Sheets / Work Orders

| Capability | eZee | OPERA Cloud | Pulse |
|---|---|---|---|
| Work orders with type, category, priority-based SLA and due time | ✓ | ✓ (Work orders / Task Sheets) | ✅ emergency 2h / high 8h / normal 24h / low 72h, editable |
| Raised from Front Desk guest ticket, housekeeping task, TV portal, technician phone, PM schedule | ✓ (from HK) | ✓ | ✅ all five sources |
| Room out-of-order tied to the work order; auto-return to housekeeping with post-repair clean task | ✓ | ✓ | ✅ `PulseRoom::setHkStatus` |
| Assignment, bulk assignment, "my work orders", start / hold / complete / supervisor verify | ✓ | ✓ | ✅ |
| Timeline notes with photos, resolution & root cause, labour minutes → cost | partial | ✓ | ✅ |
| Asset register (rooms, plant, public areas) with make/serial/warranty/criticality/vendor; bulk one-asset-per-room | ✓ | ✓ | ✅ |
| Preventive maintenance: rolling room programmes (N rooms/day, skips occupied rooms), asset/location schedules, checklists | ✓ | ✓ (Task Sheets) | ✅ 6 Nigerian-hotel schedules seeded (AC, electrical/plumbing, generator, fire safety, water, lift) |
| Spare parts stock with issue-to-work-order, receipts, reorder alerts | partial | — | ✅ |
| Meter readings: generator hours, diesel, kWh, water, gas | — | — | ✅ |
| Escalation of overdue normal/low orders, emergency SMS/email alert | ✓ | ✓ | ✅ daily cron + Pulse Comms |
| Reports: MTTR, SLA breaches, PM:corrective ratio, cost, by category / asset / room / technician, low stock | ✓ | ✓ | ✅ |
| Technician mobile API (my orders, status, notes, parts, meters) | app | app | ✅ `/pulse/api/maintenance/*` (scope `engineering`) |
| Guest notified when in-room repair completed | — | ✓ | ✅ Front Desk trace/message |

Runs standalone; when Front Desk is installed it also consumes tickets and HK tasks and controls OOO. License entitlement: `pulsemaintenance`.

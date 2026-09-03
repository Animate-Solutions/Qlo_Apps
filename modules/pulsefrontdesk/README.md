# Pulse Front Desk (`pulsefrontdesk`) v1.1

Front-office PMS layer for QloApps 1.7 — room board, arrivals/departures, check-in/out with ID capture, folios & cashiering, companies/city ledger, housekeeping, traces, night audit and manager reports.

- **BENCHMARK.md** — 27-point requirements list and 62-point matrix vs eZee Front Desk and Oracle OPERA, with Phase 2 backlog.
- **IMPLEMENTATION.md** — architecture, data model, flows, installation, verification points, UAT scripts, training outline.

Back-office menu (under *Pulse*): Front Desk · Room Board · Tape Chart · New Reservation · Arrivals & Departures · Groups & Blocks · Waitlist & Overbooking · Guest Services & Tickets · Folios & Cashier · Housekeeping · Guest Profiles · Companies / City Ledger · Night Audit · Reports · Front Desk Settings.

Depends on `pulsecore`. Raises `actionPulseCheckIn`, `actionPulseCheckOut`, `actionPulseRoomMove`, `actionPulseFolioPost`, `actionPulseRoomStatusChange`, `actionPulseNoShow`, `actionPulseHousekeepingTask`, `actionPulseNightAuditClosed` for the other Pulse modules.

# Pulse POS (`pulsepos`)

**Benchmark:** eZee Burrp

Restaurant & bar point of sale: outlets, tables, menus, KOT, bills, post-to-room.

## Tables
- `pulse_pos_outlet`
- `pulse_pos_table`
- `pulse_pos_menu_item`
- `pulse_pos_bill`
- `pulse_pos_bill_line`

## Hooks
- Core hooks: `displayBackOfficeHeader`, `moduleRoutes`
- Raises: `actionPulsePosBillSettled`, `actionPulsePosKotFired`
- Listens to: —

## Feature-parity checklist
- [ ] Multiple outlets per hotel with own menus, service charge and tax
- [ ] Table map, covers, merge/split bills, void with reason
- [ ] KOT routed by kitchen station (kitchen/bar) with status feedback
- [ ] Settle by cash/card/transfer, or post-to-room (writes a pulse_folio_line via actionPulseFolioPost)
- [ ] Happy-hour / time-based pricing, modifiers, combos
- [ ] Touch-friendly HTML5 POS front end served at /pulse-pos (tablet)
- [ ] Day-end sales by outlet, item-wise, cashier-wise reports
- [ ] Stock/recipe consumption hook (future: inventory module)

## Layout
```
pulsepos/
  pulsepos.php                 module class (install/uninstall/hooks)
  config.xml
  sql/install.sql, sql/uninstall.sql
  classes/                  ObjectModels + service classes
  controllers/admin/        AdminPulsePosController.php
  controllers/front/        api.php (JSON API)
  views/templates/admin/    dashboard.tpl
  views/css, views/js
```

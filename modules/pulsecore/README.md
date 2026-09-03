# Pulse Core (`pulsecore`)

**Benchmark:** —

Shared services for the Pulse hospitality suite: settings, event bus, REST API base, audit log.

## Tables
- `pulse_setting`
- `pulse_audit`
- `pulse_api_token`

## Hooks
- Core hooks: `displayBackOfficeHeader`, `actionAdminControllerSetMedia`, `moduleRoutes`
- Raises: `actionPulseEvent`
- Listens to: —

## Feature-parity checklist
- [ ] Central settings store used by all Pulse modules
- [ ] Event bus (actionPulseEvent) so modules stay decoupled
- [ ] Token-authenticated REST base controller for TV/mobile/POS clients
- [ ] Audit trail for every state change (folio post, key issue, rate push)

## Layout
```
pulsecore/
  pulsecore.php                 module class (install/uninstall/hooks)
  config.xml
  sql/install.sql, sql/uninstall.sql
  classes/                  ObjectModels + service classes
  controllers/admin/        AdminPulseCoreController.php
  controllers/front/        api.php (JSON API)
  views/templates/admin/    dashboard.tpl
  views/css, views/js
```

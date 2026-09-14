# Pulse Hotel (`pulsehotel`)

Multi-property for the Pulse suite.

- **A hotel is chosen at sign-in.** The first Pulse screen after login is the picker. An employee with
  one hotel is taken straight in; an employee with none is signed out with an explanation.
- **Access is per employee** (`pulse_hotel_access`), with QloApps' own profile→hotel table
  (`htl_access`) honoured as a migration fallback until every employee has an explicit grant.
- **Every Pulse query is scoped** to the session's hotel through `PulseHotelContext::sql()`.
- **The top bar carries a switcher** for anyone holding more than one hotel; a person can be pinned
  to one hotel for a whole session.
- **The cookie is never trusted.** The grant is re-checked on every request, so revoking access takes
  effect on the person's next click, not their next login.

## Tables
- `pulse_hotel_access` — employee → hotel, default, may-switch
- `pulse_hotel_session` — every selection, switch and refusal, for audit

## For module authors

```php
$sql .= PulseHotelContext::sql('f');            // AND f.`id_hotel` = 3
$row  = PulseHotelContext::stamp($row);         // stamps id_hotel on the way in
$id   = PulseHotelContext::id();                // 0 when nothing chosen — never means "all"
```

`sql()` returns `id_hotel = 0` when no hotel is set, so a query that loses its context returns nothing
rather than leaking another property's data.

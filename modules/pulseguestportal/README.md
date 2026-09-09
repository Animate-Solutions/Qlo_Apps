# Pulse Guest Portal v1.0 — benchmark vs Hoteza, SuitePad, Nevotek and Samsung LYNK REACH

Hoteza and Nevotek sell an in-room TV platform that talks to the PMS over an interface; SuitePad sells the
tablet; Samsung's LYNK REACH / Hospitality Display Solution manages the sets themselves (firmware, channel
list, welcome message) but knows nothing about the folio. Pulse Guest Portal is the guest-facing screen **and**
the PMS, so a room-service order is a real POS check, a towel request is a real housekeeping task and the
movie the guest starts is a real folio line — no middleware, no nightly file exchange.

| Capability | Hoteza | SuitePad | Nevotek | LYNK REACH | Pulse |
|---|---|---|---|---|---|
| Runs as a Samsung URL Launcher page on HG-series hospitality TVs | ✓ | — | ✓ | ✓ | ✅ `/pulse/tv?mac=…` |
| Tizen app package | ✓ | — | ✓ | ✓ | ✅ `tizen/` |
| Device registry with MAC/serial pairing approved at the desk | ✓ | ✓ | ✓ | ✓ | ✅ `pulse_gp_device` |
| Heartbeat, remote reload, remote message push | ✓ | ✓ | ✓ | ✓ | ✅ command queue |
| Wipe on check-out so the next guest sees nothing of the last | ✓ | ✓ | ✓ | partial | ✅ policy: wipe / lock / keep |
| Personalised welcome only when the room has a checked-in booking | ✓ | ✓ | ✓ | name only | ✅ signed short-lived session |
| Live folio with running balance | ✓ | ✓ | ✓ | — | ✅ `PulseFolio` |
| In-room dining that opens a real POS check and fires the kitchen | via IFC | via IFC | via IFC | — | ✅ `PulsePosService` room_service |
| Order tracking driven by the kitchen display | partial | partial | ✓ | — | ✅ `actionPulsePosItemReady` |
| Housekeeping / maintenance / laundry requests as real tasks & tickets | via IFC | via IFC | via IFC | — | ✅ `PulseTicket`, `PulseHousekeeping`, Pulse Laundry |
| DND / make-up-room, wake-up call, late check-out | ✓ | ✓ | ✓ | DND only | ✅ |
| Two-way chat with the front desk + desk inbox | ✓ | ✓ | ✓ | one-way | ✅ `pulse_gp_message` |
| CMS-managed hotel directory with images and multi-language | ✓ | ✓ | ✓ | text only | ✅ per-language pages & promos |
| Dayparted, room-type-targeted promotions | ✓ | ✓ | partial | — | ✅ |
| IP-multicast channel guide fed from the headend | ✓ | — | ✓ | ✓ | ✅ `pulse_gp_channel` (+ M3U/CSV import) |
| VOD with paid titles posted to the folio | ✓ | — | ✓ | — | ✅ `pulse_gp_vod`, charge code `VOD` |
| Radio, games/apps launcher, casting pairing code | ✓ | partial | ✓ | casting | ✅ |
| Room controls (lights / AC / curtains) | ✓ BMS | ✓ BMS | ✓ BMS | — | ✅ adapter interface + simulator + HTTP |
| Express check-out with bill review and stay rating | ✓ | ✓ | ✓ | — | ✅ `pulse_gp_feedback` for the CRM |
| Works with the LAN link to the server down | partial | ✓ | partial | ✓ | ✅ service worker + replay queue |
| Languages | many | many | many | many | ✅ English, French, Pidgin-friendly plain English, Arabic (RTL) |

Runs standalone: with Front Desk off the folio and check-out sections hide themselves, with POS off in-room
dining hides itself, with Pulse Laundry off a laundry request becomes a front-desk ticket. Licence
entitlement: `pulseguestportal`.

## Tables

| Table | What it holds |
|---|---|
| `pulse_gp_device` | every screen: MAC/serial (`uid`), type, model, firmware, IP, room, token, status, last seen |
| `pulse_gp_session` | signed short-lived guest sessions bound to a device, a room and a stay |
| `pulse_gp_command` | queue for reload / message / wipe / notify / order-ready, delivered on the heartbeat |
| `pulse_gp_message` | two-way guest ↔ desk chat, unread flags on both sides |
| `pulse_gp_request` | housekeeping, maintenance, laundry, DND, MUR, wake-up, late and express check-out |
| `pulse_gp_order` | portal room-service orders, mapped to the POS check, idempotent on `client_id` |
| `pulse_gp_page` / `_lang` | directory pages with per-language title, summary and body |
| `pulse_gp_promo` / `_lang` | banners: placement, daypart window, date range, room-type targeting |
| `pulse_gp_channel` | number, name, logo, multicast/HLS URL, category, adult and HD flags |
| `pulse_gp_vod` / `pulse_gp_vod_play` | catalogue and every play, with the folio line for paid titles |
| `pulse_gp_feedback` | stay ratings and NPS, `crm_synced` left for the CRM module |
| `pulse_gp_cast` | per-room casting pairing codes |
| `pulse_gp_control_point` / `_log` | the room's control register and every action with its result |
| `pulse_gp_rate` | per-device request counters for rate limiting |

## Admin screens

* **Guest Portal** — screens online/offline by floor, today's orders, open requests, unread messages, ratings, VOD plays, room-control activity, broadcast and reload-all.
* **Portal Devices** — pair a pending screen to a room, block/unblock, retire, reload, wipe, push a message, rotate a token, register a TV by MAC before it ever boots.
* **Portal Content** — directory pages, promotions, the welcome greeting per language, allergen notes on the POS menu, image upload to `/upload/pulseguestportal/`.
* **Channels & VOD** — the channel line-up with M3U/CSV import from the headend, the VOD catalogue, radio streams, the games/apps launcher, plays and VOD revenue.
* **Guest Messages** — inbox by stay, reply, broadcast to every occupied room.
* **Portal Settings** — branding colours, logo and fonts, sections and languages, pairing and wipe policy, session TTL, heartbeat, rate limit, adult PIN, WiFi credentials, weather, room-control adapter with a test and a provisioning run, portal API token and the cron URL.

## API — `/pulse/api/portal/{resource}/{id}`

Auth is one of: `X-Pulse-Device: <64 hex>` (the device token, issued at pairing, identifies the **room**),
`X-Pulse-Session: <sid.exp.sig>` (the signed guest session, identifies the **stay**), or
`Authorization: Bearer <pulse_api_token with scope portal>` for integrations. `ping` and `pair` are the only
resources reachable without one. Every call is rate limited per device (`PULSE_GP_RATE_PER_MIN`, default 120)
and per IP on the pairing endpoint.

`pair` · `session` · `home` · `folio` · `menu` · `order` · `order_status` · `request` · `requests` ·
`messages` · `message_send` · `directory` · `channels` · `vod` · `vod_play` · `wakeup` · `checkout` ·
`feedback` · `heartbeat` · `room_control` · `cast` · `cast_claim` · `language` · `ping`

Nothing personal is served unless the room has a booking in `STATUS_CHECKED_IN`: no name, no folio, no
messages, no requests. A room number posted by a client is never trusted — it is a hint the desk may use when
pairing, nothing more.

## Events

Listens to `actionPulseCheckIn` (arm the room, drop a welcome message), `actionPulseCheckOut` (revoke the
session, wipe the screen), `actionPulseRoomMove` (wipe the old room, arm the new one), `actionPulsePosItemReady`
(push order-ready), `actionPulsePosBillSettled` (close the tracker), `actionPulseFolioPost` (refresh the
balance badge) and `actionPulseNightAuditClosed` (expire sessions, casting codes and commands).

Raises `actionPulsePortalDevicePaired`, `actionPulsePortalRequest`, `actionPulsePortalOrder`,
`actionPulsePortalMessage`, `actionPulsePortalFeedback`, `actionPulsePortalVodPlay`.

## Cron

    */5 * * * * curl -s "https://<host>/modules/pulseguestportal/cron/portal.php?token=<PULSE_GP_CRON_TOKEN>"

Rings due wake-up calls (Comms + an on-screen notification, with a desk alert when neither can be delivered),
alerts on screens that have gone quiet, expires sessions and casting codes, re-queues unacknowledged commands
and wipes any room whose stay ended without a check-out event.

## Samsung HG50AU800 — URL Launcher setup

The property runs HG50AU800 sets on LYNK SINC REACH 4.0. The portal is a plain web app, so no REACH server is
needed for the guest experience — REACH keeps doing firmware and cloning, the portal does the guest screen.

1. On the set, open the hotel menu: **Mute → 1 → 1 → 9 → Enter** (on some firmwares **Mute → 1 → 1 → 9 → OK**).
2. **Hospitality Mode: On**, then **System → Power On Source: Last Source / URL Launcher**.
3. **External Source → URL Launcher Settings → URL Launcher Address**:
   `https://pms.carvington.local/pulse/tv?mac=$MACADDR&type=tv`
   The set substitutes `$MACADDR` itself; where a firmware does not, use the Tizen wrapper below, which reads
   the MAC through the system API instead.
4. **URL Launcher Settings → Server Type: Custom Server**, **Timeout: 30 s**, **Reload on error: On**.
5. **Menu → Multimedia → Channel Setup** — leave the RF/IP channel list to REACH; the portal's channel guide
   is a *guide*, and on a set without the Tizen player it hands off to the TV's own tuner by channel number.
6. **Security → Menu Lock: On**, **USB Lock: On**, so a guest cannot leave the launcher.
7. Boot the set. It appears in **Guest Portal ▸ Portal Devices** within seconds, showing a six-character
   pairing code; the desk picks the room and presses **Pair**. The screen reloads personalised.

Cloning to the other 51 sets: set one TV up fully, then **Menu → Support → Clone TV → Clone to USB**, and
**Clone from USB** on each remaining set. Every set boots the same URL and identifies itself by its own MAC —
nothing is per-room in the TV configuration.

**LYNK REACH-friendly plain-URL mode.** If the site prefers REACH to own the launcher, point REACH's *Custom
URL* at the same address without the MAC macro (`https://pms.carvington.local/pulse/tv`) and register each
TV's MAC in **Portal Devices** first; the portal then resolves the room from the client's MAC when the set
sends it, and otherwise shows the pairing code for the desk. Nothing else changes.

## Tizen packaging

The wrapper in `tizen/` is a thin bootstrapper: it reads the set's MAC/DUID/model/firmware and forwards to
`/pulse/tv`. Edit `PULSE_SERVER` in `tizen/index.html` before packaging.

    cd modules/pulseguestportal/tizen
    tizen build-web -e index.php -e .buildResult -e .sign     # index.php is the PrestaShop stub, never ship it
    cd .buildResult
    tizen package -t wgt -s <your-samsung-tv-profile> -- .
    tizen install -n PulseGuestPortal.wgt -t <target-tv> --   # target from `sdb devices`

To sign you need a Samsung TV **Partner** certificate profile created in Tizen Studio
(*Tools → Certificate Manager → Samsung → TV*), with each TV's DUID registered for a Partner profile.
For a bench test on a set in developer mode: **Apps → 1 2 3 4 5** on the remote, enter the workstation IP,
then `sdb connect <tv-ip>` before `tizen install`.

## Offline behaviour

The service worker caches the shell plus the read-only payloads a guest can safely see stale — directory,
channel list, menu and the last home payload — and never caches the folio, messages or anything that writes.
When the link drops the screen keeps showing hotel information and the channel guide, requests and orders are
queued in `localStorage`, and they are replayed (idempotent on `client_id` for orders) as soon as the server
answers again. A portal order that the kitchen could not accept is left `failed` with the reason on the
dashboard and a desk trace — the guest is told to dial 0 rather than being left believing food is coming.

## Parity checklist

- [x] Device registry, desk-approved pairing, heartbeat, remote reload / message / wipe, token rotation
- [x] Signed short-lived sessions; nothing personal without a checked-in booking; expiry at check-out and on room move
- [x] Welcome, folio, dining, requests, messaging, directory, entertainment, room controls, check-out
- [x] TV-remote navigation: D-pad focus order, 24px base type, 64px targets, no hover, Back/Return handling
- [x] Four languages including RTL Arabic, per-language CMS content with fallback
- [x] Real POS check for room service, real tickets and housekeeping tasks for requests, real folio lines for VOD
- [x] Back-office CMS with image upload, dayparted and room-type-targeted promotions
- [x] Channel list with M3U/CSV import, VOD catalogue, radio, apps, casting codes
- [x] Room-control adapter interface with a working simulator and a documented HTTP adapter
- [x] Tizen package and URL Launcher instructions for the HG50AU800
- [x] Service worker, offline cache and replay queue
- [ ] EPG (now/next programme data) — the headend exports none today; the guide is a channel list
- [ ] In-app payment for the folio — settlement stays at the desk or in Pulse Payments

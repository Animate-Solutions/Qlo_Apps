# Pulse CRM v1.0 — benchmark vs OPERA Customer Management / OCIS, Revinate, Cendyn, TrustYou and eZee

OPERA splits this across Customer Management (profiles, preferences, memberships), OCIS (the central
guest index) and a bolt-on for feedback. Revinate and Cendyn sell the marketing half — segmentation,
campaigns, journeys — as a separate subscription that reads your PMS. TrustYou and ReviewPro sell the
reputation half. eZee bundles a lighter version of feedback and loyalty into the PMS itself. Pulse CRM
does all four in one module, on top of the guest 360 Front Desk already keeps, and posts loyalty
redemption to the same folio as every other line.

| Capability | OPERA + OCIS | Revinate / Cendyn | TrustYou / ReviewPro | eZee | Pulse |
|---|---|---|---|---|---|
| Preference taxonomy with a managed list plus free text | ✓ | — | — | partial | ✅ `pulse_crm_preference` + option list |
| Allergies and dietary notes as **service notes** on the arrivals board | partial | — | — | — | ✅ auto-flagged, mirrored to the FD profile |
| Special occasions with reminders that surface at arrivals | ✓ | ✓ | — | — | ✅ `pulse_crm_occasion` + trace + journey |
| Relationships (travels with / assistant of / same company) | ✓ | — | — | — | ✅ reciprocal, `pulse_crm_relationship` |
| Per-channel consent with timestamp, source and evidence (NDPR) | partial | ✓ | — | — | ✅ `pulse_crm_consent`, gates every send |
| Right to be forgotten that spares the accounting trail | — | partial | — | — | ✅ `PulseCrmProfile::forget()` |
| Saved, re-evaluable segments with materialised membership | ✓ | ✓ | — | — | ✅ 25 fields, JSON rules, cron-refreshed |
| Email / SMS / WhatsApp campaigns with merge tags, throttle, A/B | via partner | ✓ | — | partial | ✅ through Pulse Comms |
| Quiet hours in Africa/Lagos, hard opt-out, per-recipient audit | — | ✓ | — | — | ✅ every exclusion is a logged row with a reason |
| Open pixel, signed click-redirect, one-click unsubscribe | — | ✓ | — | — | ✅ `/pulse/crm/{open,click,unsub}` |
| Event-triggered journeys with delay, condition, action, suppression | via partner | ✓ | — | — | ✅ 7 journeys shipped, `pulse_crm_journey_run` |
| Tiered loyalty: earn by department, tiers, expiry, redemption | ✓ (membership) | — | — | ✓ | ✅ FIFO lots, `LOYR` posts to the folio |
| Points liability report | ✓ | — | — | — | ✅ by tier |
| NPS / GSS surveys per touchpoint with department attribution | bolt-on | — | ✓ | ✓ | ✅ `pulse_crm_survey*` + mobile page |
| A low score opens a **service-recovery case** automatically | — | — | partial | — | ✅ and a ticket if the guest is still in house |
| Glitch tracking with root cause, recovery, **cost by department** | ✓ | — | — | — | ✅ `pulse_crm_case` + cost report |
| Review register with import from portal exports (CSV/JSON) | — | — | ✓ | — | ✅ loose column matching, dedupe by external id |
| Review dashboard: rolling average, response rate, median response time | — | — | ✓ | — | ✅ per source, plus a needs-a-reply queue |
| Corporate accounts, contacts, activities, pipeline, contracted rates | ✓ (S&C) | — | — | partial | ✅ `pulse_crm_account*` |
| Production report by company, year over year | ✓ | — | — | — | ✅ nights, revenue, ADR, variance |
| Guest-facing API for a TV portal (feedback, points, redeem) | — | — | — | — | ✅ `/pulse/api/crm/*` |

## Tables

| Table | What it holds |
|---|---|
| `pulse_crm_profile_ext` | source of business, market segment, preferred channel, last NPS band, erasure flag |
| `pulse_crm_preference_option` | the managed preference list the desk picks from (seeded, ~50 rows) |
| `pulse_crm_preference` | one guest's preferences; allergy and dietary rows are service notes |
| `pulse_crm_occasion` | birthdays, anniversaries and the rest, with a reminder lead time |
| `pulse_crm_relationship` | travels with / spouse / assistant of / same company, written both ways |
| `pulse_crm_consent` | per-channel state, source, evidence, IP, timestamp and unsubscribe reason |
| `pulse_crm_tag` / `pulse_crm_customer_tag` | tags a journey or a clerk can hang on a guest |
| `pulse_crm_segment` / `pulse_crm_segment_member` | the rule set and its materialised membership |
| `pulse_crm_campaign` / `pulse_crm_campaign_recipient` | the campaign and one row per guest with its outcome |
| `pulse_crm_journey` / `_step` / `_run` / `_log` | the automation, its steps, who is on it and what happened |
| `pulse_crm_send_log` | the suppression ledger — what was sent to whom, on what day |
| `pulse_crm_loyalty_program` / `pulse_crm_tier` / `pulse_crm_member` / `pulse_crm_points_txn` | the programme, its tiers, its members and every point that moved |
| `pulse_crm_survey` / `_question` / `_response` / `_answer` | the question sets, the invitations and what came back |
| `pulse_crm_case` | service recovery: source, severity, root cause, what was given, what it cost |
| `pulse_crm_review` | the review register with response, sentiment and department attribution |
| `pulse_crm_account` / `_rate` / `pulse_crm_contact` / `pulse_crm_activity` / `pulse_crm_opportunity` | the corporate side |

Charge code added: **`LOYR`** — Loyalty Redemption, department `adjustment`, tax 0. Redemption posts a
negative line so the guest's bill genuinely falls.

## Admin screens

CRM (dashboard: arrivals with VIP/occasion/preference/case flags, NPS trend, campaign performance,
loyalty liability, review scores, follow-ups) · Guests (search, duplicates and merge, the 360 with eight
CRM tabs, erasure) · Segments (rule builder with live preview) · Campaigns (compose, queue, throttled
send, A/B, per-recipient log) · Journeys (steps, conditions, live runs, logs) · Loyalty (programme,
tiers, members, ledger, liability, expiry) · Surveys (design, responses, department scoreboard, trend) ·
Service Recovery (open queue, cost by department, root causes) · Reviews (queue, dashboard, import) ·
Corporate (accounts, pipeline, production, follow-ups) · CRM Settings.

## API — `/pulse/api/crm/{resource}`

`ping` · `profile` (desk, marketing) · `preferences_save` (portal, desk) · `enrol` (portal, desk) ·
`points` (portal, desk) · `redeem` (portal, desk) · `survey_get` · `survey_submit` · `feedback` ·
`opt_out` (portal, marketing, desk). A portal token may address a room (`id_room` or `room_num`) rather
than a guest; the in-house booking is resolved for it. The TV portal uses `feedback`, `points` and
`redeem`.

Public pages (no token): `/pulse/survey?t=…` renders the mobile survey; `/pulse/crm/open`,
`/pulse/crm/click` and `/pulse/crm/unsub` handle tracking and the one-click unsubscribe.

## Hooks

Listens to `actionValidateOrder` (starts pre-arrival at T−3), `actionPulseCheckIn`, `actionPulseCheckOut`
(re-tier + thank-you journey), `actionPulseNoShow` (cancels journeys), `actionPulseFolioPost` (earns
points), `actionPulseNightAuditClosed` (qualifying nights), `actionPulseTicketCreated` (a complaint
becomes a recovery case). Raises `actionPulseCrmMemberEnrolled`, `actionPulseCrmPointsRedeemed`,
`actionPulseCrmSurveyCompleted`, `actionPulseCrmCaseOpened`, `actionPulseCrmReviewAdded`.

## Cron

`php modules/pulsecrm/cron/crm.php <token> [task]` every fifteen minutes. Tasks: `segments`,
`journeys`, `campaigns`, `loyalty`, `occasions`, `housekeeping`, or `all`. Every pass is chunked and
stateless, so a shared host that kills the request loses nothing — the next run resumes where it stopped.

## Standalone

Runs without Front Desk: preferences, consent, segments, campaigns, surveys, cases, reviews and the
corporate side all work, email still goes out through PrestaShop's mailer, and the screens say plainly
what is missing. Without Front Desk there is no folio to post a redemption to and no SMS adapter to
reach, and the UI disables those buttons rather than failing silently.

## Seed

`php modules/pulsecrm/seed/seed.php` — 40 enriched Port Harcourt guest profiles, three loyalty tiers
populated with a real points ledger, four materialised segments, two past campaigns with believable
open/click/unsubscribe rates, three live journeys with runs in flight, 60 survey responses giving a
plausible NPS, five recovery cases (two open), 25 reviews across seven portals, and four corporate
accounts with contacts, activities, pipeline, contracted rates and production history. Idempotent.

## Parity checklist

- [x] Preference taxonomy, managed list plus free text, mirrored to the Front Desk profile
- [x] Allergies as service notes on the arrivals board
- [x] Occasions with reminders, traces and greeting journeys
- [x] Relationships, written reciprocally
- [x] Per-channel consent with source, evidence and timestamp; every send gated on it
- [x] Right to be forgotten that leaves the accounting trail intact
- [x] Segments: 25 fields, JSON rules, preview, materialised membership, cron refresh
- [x] Seven ready-made segments shipped
- [x] Campaigns: email/SMS/WhatsApp, merge tags, schedule, throttle, quiet hours, A/B split
- [x] Per-recipient log with the reason for every exclusion
- [x] Tracking pixel, signed click-redirect, hard-stop unsubscribe
- [x] Journeys: pre-arrival, welcome, mid-stay, thank-you, review request, win-back, birthday, anniversary
- [x] Step delay, condition, action, per-guest suppression window
- [x] Loyalty: earn by department, three tiers, FIFO expiry, redemption to the folio via `LOYR`
- [x] Tier recalculation and point expiry on cron; enrolment at the desk and via the API
- [x] Surveys: NPS, 1–5, choice and free text; in-stay, post-stay and F&B templates
- [x] Signed-token mobile survey page; NPS/GSS and department attribution computed at submit
- [x] A low score opens a recovery case, and a ticket while the guest is still in house
- [x] Service recovery with root cause, recovery action, cost, SLA and a cost-by-department report
- [x] Review register, portal import (CSV/JSON), reply queue, response-time median, trend
- [x] Corporate accounts, contacts, activities, pipeline, contracted rates, production year on year
- [x] JSON API with portal / desk / marketing scopes
- [x] Token-guarded, chunked cron

License entitlement: `pulsecrm`.

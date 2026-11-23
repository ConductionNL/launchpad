# OpenRegister leaf integrations — Talk discussion on shared dashboards

## Why

OpenRegister ships a catalogue of app-agnostic integration leaves (email, calendar, contacts, files, talk, deck, forms, maps, photos, polls, shares, bookmarks, collectives, notes, activity, time-tracker, xwiki, analytics, cospend, openproject) that apps adopt declaratively via schema-level `configuration.linkedTypes`. LaunchPad currently adopts **none** of them — `grep` for `linkedTypes` across `lib/`, `appinfo/`, and `src/` returns zero hits, and `lib/Settings/register.d/` contains only a README.

LaunchPad's domain is deliberately narrow: a single `Dashboard` schema (`lib/Settings/launchpad_register.json`) describing a configuration object — slug, title, widgets array, `sharedWith`, `type` (`user` / `admin_template` / `group_shared`), `groupId`, `isDefault`. Most leaves attach to *domain records about the world* (people, cases, events, places) and therefore have no honest fit here. After evaluating the full catalogue against the schema (see "Scoping" below), exactly one leaf earns adoption: **talk**, giving the people who share a dashboard a discussion thread about that dashboard.

Group and shared dashboards are collaborative artifacts — the manifest advertises "Dashboard sharing" and "Group dashboards" as key features, and the app already ships dashboard reactions (`DashboardReactionApiController`, REQ-RXN-001..004) as a lightweight signal layer. What is missing is an actual conversation: "should we swap the burndown widget for the CI-status widget?" today happens outside the app. The talk leaf closes that gap with zero bespoke chat code.

## What Changes

- Add a register.d overlay `lib/Settings/register.d/dashboard-talk-leaf.json` declaring `configuration.linkedTypes: ["talk"]` on the `Dashboard` schema — the same overlay pattern larpingapp uses (`register.d/player-to-contacts-leaf.json`).
- The Talk discussion surface renders only for dashboards that are actually shared (type `group_shared`, or `sharedWith` non-empty, or having active shares via `DashboardShareService`) — a personal, unshared dashboard shows no discussion affordance.
- Access to a dashboard's discussion follows the existing view guard (`PermissionService::canViewDashboard()`); no parallel ACL is introduced.
- No launchpad-side wrapper controller or service for Talk — the leaf is consumed from OpenRegister's leaf service directly (ADR-022, apps consume OR abstractions).

## Scoping — why the other leaves are out

Evaluated against the `Dashboard` schema and the real controller surface:

- **files** (attachments): LaunchPad already has a purpose-built resource pipeline for dashboard assets (`ResourceController`, `ResourceService`, REQ-RES-001..014) and a files *widget* for browsing user storage (`FilesWidgetController`). A dashboard is a layout configuration, not a document-bearing case file; a generic attachments panel would duplicate the resource system while serving no user story. Out.
- **email** (`linkedTypes: ["mail"]` sidebar target, `mailObjectTemplate`): creating or linking a dashboard from an email has no meaning; the schema itself declares `"mailEnabled": false`. Out.
- **calendar, contacts, maps, forms, polls, deck, notes, collectives, bookmarks, photos, time-tracker, xwiki, analytics, cospend, openproject**: these attach to records that represent people, events, places, or work items. A dashboard configuration is none of those; several of these already exist in LaunchPad as *widgets* (calendar widget, people widget, analytics) where they belong — on the dashboard canvas, not on the object. Out.
- **activity**: LaunchPad already integrates the Nextcloud activity stream as a widget and has its own view-analytics capability (REQ-ANLT). Out.

This is deliberately a one-leaf change. Honest scoping over catalogue completeness.

## Capabilities

### New Capabilities

- `dashboard-talk-leaf` — Talk discussion thread on shared and group dashboards via OpenRegister's talk leaf.

### Modified Capabilities

(none)

## Impact

**Affected code:**

- `lib/Settings/register.d/dashboard-talk-leaf.json` — new overlay file (the only schema change)
- Frontend surface where the leaf renders (dashboard sidebar/settings panel) — wiring only, per the leaf's own render contract

**Affected APIs:**

- No new launchpad endpoints. Room lifecycle and message traffic go through OpenRegister's talk leaf service and Talk itself.

**Dependencies:**

- OpenRegister with the integration-leaves capability; Nextcloud Talk installed and enabled. When Talk is absent the leaf must degrade to not rendering (no error).

**Migration:**

- None. The overlay is additive; existing Dashboard objects are untouched. Schema version bump in the overlay only.

## Notes

- Reactions (REQ-RXN) stay: they are a one-tap signal on any visible dashboard; the talk leaf is a threaded conversation for dashboards people actually co-own. Different jobs.
- The archived `2026-05-02-dashboard-comments` change explored bespoke comments; adopting the talk leaf supersedes that direction without resurrecting custom comment storage.

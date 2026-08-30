<!--
SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
SPDX-License-Identifier: EUPL-1.2
-->

# LaunchPad — market position & gap analysis (2026-07-23)

Deep-research snapshot backing the `openspec/changes/*` market-gap wave. Full
evidence (33 competitors, 11 stakeholders, 17 market insights, 23 external
sources, 13 journeys, 17 gap features, 1 ecosystem gap) is logged in the
Spectr intelligence register (`spectr` register, `source_ref =
lp-research-2026-07-23`).

## Positioning in one line

**LaunchPad is the only governed multi-dashboard builder inside the Nextcloud
ecosystem** — and the only dashboard product anywhere that pairs
admin-distributed templates + conditional visibility + kiosk/public-share
with NL Design System theming, EUPL licensing and on-prem hosting. That is an
ownable, sovereignty-first position for Dutch gemeenten and MKB.

## The competitive field

| Segment | Players | Threat to LaunchPad |
|---|---|---|
| Dutch adaptive workspace | **Workspace 365** (£6.80–£10.20/user/mo) | High — same story, same buyers, but M365-tied |
| Microsoft incumbent | **Viva Connections** (free with M365) | High — free where the buyer is already on M365 |
| Intranet SaaS | Happeo, LumApps, Staffbase, Simpplr, Unily, Basaas, Omnia, Powell | Medium — upmarket, quote-based, US/DACH data-residency problems |
| Self-hosted OSS dashboards | Homarr, gethomepage, Glance, Dashy, Heimdall, Organizr, Flame | Sets the UX bar (live tiles, status pings, search) but none target organisations |
| BI dashboards | Grafana, Metabase, Superset, Redash | UX benchmark for composition/provisioning, not portals |
| Nextcloud-native | built-in Dashboard, Analytics (Rello), External Sites, AppOrder, Custom Menu, iFrame Widget | The DIY status quo LaunchPad replaces |

Commercial price corridor is **€6–12/user/month**; LaunchPad (EUPL, free)
undercuts all of it — a per-org support/hosting proposition differentiates
against every commercial player while OSS rivals ignore organisations
entirely.

## Why the moat holds

- The Nextcloud app-store Dashboard category is only micro-widgets + LaunchPad; no competing builder exists.
- Native Dashboard is single-page, fixed-layout, per-user; admin defaults are `occ`-only, instance-wide, and don't touch existing users. The highest-voted dashboard wishes (admin default per group #25553, resizable/pinned widgets #39562, iframe widget, per-group landing) sit **closed-unimplemented**, and Hub 25/26 shipped **no** dashboard investment — low risk of Nextcloud building this natively near-term.
- ~40 Dutch gemeenten are moving onto a sovereign Nextcloud cloud — direct pull for a gemeente-ready, NLDS-themed, WCAG-AA portal.

## The gaps we are closing (this change wave)

Ranked by researched demand. Each row is an `openspec/changes/*` change on
`development`.

| Change | Gap | Priority | Route |
|---|---|---|---|
| `live-data-tile-widget` | Static tiles → live data tiles (the #1 functional gap; 12/12 competitors) | must | LaunchPad widget **+ OpenConnector `dashboard-http-datasource` leaf** |
| `conditional-visibility-editor` | Rules engine has no UI; add editor + preview-as-audience/date | must | LaunchPad (UI over existing engine) |
| `admin-template-resync` | Template edits never reach already-provisioned copies | must | LaunchPad (extends admin-templates) |
| `tile-quick-search` | No on-dashboard launcher/search bar (9/9 competitors) | should | LaunchPad (runtime-shell) |
| `service-health-ping` | No tile up/down status ("is de zaakapplicatie bereikbaar?") | should | LaunchPad widget |
| `iframe-embed-widget` | CSP-aware external-URL embed (a whole micro-app niche) | should | LaunchPad widget |
| `tile-usage-analytics` | Per-tile click analytics for the KPI-review flow | should | LaunchPad (extends dashboard-view-analytics) |
| `clock-weather-widgets` | Ambient clock/weather widgets (startpage staples) | could | LaunchPad widgets |

### Leaf reintegration (cross-app boundary)

`live-data-tile-widget` deliberately does **not** put third-party HTTP,
credentials or egress control in LaunchPad. That capability lives in
**OpenConnector** as `dashboard-http-datasource` (a governed, read-only
"resolve one value from a configured source" façade over the existing
source/HTTP/auth engines). LaunchPad consumes it as a **leaf** through a
runtime capability probe — no static OpenConnector imports — and degrades to
a minimal allow-listed direct GET when OpenConnector is absent, per the
`runtime-or-consumption` policy.

## Already-specced-but-unbuilt (verify before duplicating)

Several proposed changes already cover adjacent gaps: `public-dashboard-publication`
and the public-share API (public-share UI), `scheduled-exports`,
`drill-down-cross-widget-filter` (dashboard variables), `embedded-analytics`
(iframe/JS-SDK embed + tokens), `keyboard-accessible-widget-repositioning`,
`map-support`, `launchpad-ai-dashboard-assistant`. The gap wave above is the
set with **no** existing change.

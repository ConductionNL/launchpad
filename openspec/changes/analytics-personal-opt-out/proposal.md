---
kind: spec
depends_on: []
---

# A person switches view counting off from their own settings

## Why

Parity row **g-optout** says people can opt out of view tracking. The server honours an opt-out: `lib/Service/AnalyticsService.php:134` reads the user value `analytics_optout` and skips the view event. Nobody can set that value from LaunchPad. No page in `src/` writes it, and the generic preferences route stores keys under a `pref_` prefix, so it cannot reach `analytics_optout` either. Only an administrator can set it, with `occ user:setting`. The row therefore loses its `built` state until a person can do it themselves.

## What changes

- `dashboard-view-analytics`: a person turns "Count my visits" off and on in LaunchPad's own settings, and LaunchPad stores the choice as `analytics_optout`.

## Capabilities

### Modified capabilities

- `dashboard-view-analytics`

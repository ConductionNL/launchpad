# Embed tokens: administrator guide

Embed tokens are managed by LaunchPad administrators in **Admin settings ▸
LaunchPad ▸ Embeds**, or through the admin API
(`/apps/launchpad/api/embed-tokens`) that page uses.

## In Admin settings

- **Embed tokens**: *New embed token* opens the form (what to embed, scope,
  the sites that may frame it, theme, expiry, rate limit). After saving, the
  token is shown **once** with a copy button; it cannot be retrieved later.
  Open a token to edit its name, description or rate limit, re-issue it,
  revoke it with a reason (or restore it), and see its usage over the last
  seven days.
- **Embed themes**: create and edit tenant themes with a live preview; a theme
  embed tokens still use is deleted only after a warning, and those tokens fall
  back to the default theme. Themes are also served at
  `/apps/launchpad/api/tenant-themes`.
- From a dashboard, an administrator's dashboard menu offers *Create embed
  token*, which opens this page with that dashboard pre-selected.

## Issue a token

`POST /api/embed-tokens` with:

| Field | Meaning |
| --- | --- |
| `name`, `description` | What the embed is for. |
| `subject` | `{type: "widget" \| "dashboard", id, filterContext?}`; `filterContext` pins filters. |
| `scope` | `{mode: "read" \| "read-with-interactions", allowedActions, allowedFilters}`. |
| `hostOrigins` | The exact origins of the pages that may show it, e.g. `["https://www.zeist.nl"]`. |
| `tenantThemeId` | Optional theme (colours, logo) for this embed. |
| `tokenExpiresAt` | Optional expiry. |
| `rateLimitPolicy` | Optional `{requestsPerMinute, burstSize}`. |

The answer contains the token **once**. Store it where the host website keeps its secrets;
it cannot be shown again. Re-issue it when it is lost.

## Revoke a token

`POST /api/embed-tokens/{id}/revoke` with a reason (`compromised`, `no_longer_needed`,
`host_decommissioned`, `re-issued`, `other`) and optional notes. The embed stops working within
60 seconds; `purge-cache` makes it immediate. The token's creator is notified.
`unrevoke` lifts a revocation.

## Themes

A tenant theme sets the embed's CSS custom properties on `:root` (for instance
`--c-primary`, `--c-surface`). Colours must keep a contrast of at least 4.5:1 against the
surface colour, and custom CSS may only set properties on `:root`.

## Usage

`GET /api/embed-tokens/{id}/usage` gives the last seven days in aggregate: views per day, host
origins, viewport sizes, browsers, interactions, refusals and rate-limit hits, and any use
after revocation.

Admin settings ▸ Operations ▸ **Embed usage** switches telemetry off for the whole
organisation; the change is written to the audit log.

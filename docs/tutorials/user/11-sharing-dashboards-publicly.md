---
sidebar_position: 11
title: Sharing dashboards publicly
---

# Sharing dashboards publicly

LaunchPad can mint a read-only, URL-safe token for any dashboard you own —
no Nextcloud login required to view it.

:::warning Feature status — API only for now
The public-share **HTTP API described below is live and stable**, but the
in-app UI for creating and managing public links is **not yet shipped**. The
dashboard **Share** button currently opens the *user &amp; group* sharing tab
only (see [Bookmark or share a dashboard URL](08-deep-link.md) for logged-in
sharing). Public links are therefore created via the API (or automation)
today; the point-and-click **Create public link** control, and the anonymous
rendered view at `/s/{token}`, are on the roadmap. Until then, treat this page
as the integrator's reference for the endpoints.
:::

## Creating a public share (API)

Call the create endpoint on the dashboard's UUID:

### API

```http
POST /apps/launchpad/api/dashboards/{uuid}/public-share
Content-Type: application/json

{
  "password": "SecurePass123!",   // optional
  "expiresAt": "2026-12-31T23:59:59Z"  // optional ISO 8601
}
```

Response `201 Created`:

```json
{
  "id": 42,
  "token": "vK9mP2q...",
  "url": "https://example.com/apps/launchpad/s/vK9mP2q...",
  "passwordRequired": true,
  "expiresAt": "2026-12-31 23:59:59"
}
```

## Listing active shares

```http
GET /apps/launchpad/api/dashboards/{uuid}/public-shares
```

Returns an array of active (non-revoked, non-expired) shares.

## Revoking a share

```http
DELETE /apps/launchpad/api/dashboards/{uuid}/public-shares/{id}
```

The share is **soft-revoked** (the row is kept for audit purposes).
Any subsequent access to the token returns `404`.

## Password protection

If a share has a password, accessing `/s/{token}` returns `401` with
`{ "passwordRequired": true }`.

Submit the password to the unlock endpoint:

```http
POST /apps/launchpad/s/{token}/unlock
Content-Type: application/json

{ "password": "SecurePass123!" }
```

Response `200 OK`:

```json
{ "access": true }
```

Alternatively, include the password in the initial render request via
query string (`?password=...`) or the `X-Share-Password` header.

## Expiry

A share with `expiresAt` in the past returns `404` — identical to a
revoked share so existence is not leaked.

## View-count tracking

Each render increments the share's `viewCount` at most once per IP per
60-second window to prevent refresh-spam inflation.

## Brute-force protection

Failed unlock attempts are throttled per IP across all shares:

| Action | Limit |
|---|---|
| `launchpad_share_access` (bad token / revoked / expired) | 60 / 60 s |
| `launchpad_share_password` (wrong password) | 10 / 60 s |

The 11th wrong-password attempt from the same IP returns `429 Too Many Requests`.

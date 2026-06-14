# Design — Dashboard Kiosk Mode

## Context

LaunchPad needs a wall-display surface. The naive approach — "just open the dashboard
full-screen on the TV" — fails on four axes: chrome (NC header + nav waste a third of a
1080p TV), rotation (reception screens cycle several boards), staleness (a dashboard
opened Monday morning shows Monday-morning data on Friday), and resilience (an
unattended screen has nobody to press F5 after a transient 500). This design pins the
decisions for all four and, critically, the security model: a kiosk URL is an anonymous
read grant, so it must inherit — not reimplement — the `dashboard-public-share`
security posture.

## Goals / Non-Goals

**Goals:**
- Decide how kiosk rendering is addressed (flag vs dedicated route vs playlist token).
- Decide where playlist state lives (URL params vs server entity) and its security model.
- Pin rotation/refresh mechanics and the unattended failure policy.
- Reuse, not fork, the public-share read-only and 404/throttle semantics.

**Non-Goals:**
- Device management (pairing, remote screen control, MDM) — out of scope for v1.
- Scheduled playlists (different boards at different times of day) — `conditional-visibility`
  time rules already adapt *content*; playlist-level scheduling is a follow-up.
- Offline-first/service-worker caching — v1 resilience is in-memory last-known state only.

## Decisions

### D1: Two entry points — `kiosk=1` flag and `/kiosk/{token}` playlist route

**Decision**: Chrome-less rendering is a presentation flag (`?kiosk=1`) available on the
authenticated single-dashboard route and on the public-share render route. Rotation is a
separate, dedicated public route `/kiosk/{token}` backed by a server-side playlist.

**Alternatives considered:**
- *Flag-only with URL-param rotation* (`/s/{token}?kiosk=1&rotate=uuid1,uuid2&dwell=60`):
  stateless and zero-migration, but every dashboard in the rotation would need its own
  public share, the URL becomes an unmanageable config artifact living only in a TV
  browser's homepage setting, and revoking the rotation means hunting down the device.
  Rejected.
- *Dedicated route only (no flag)*: loses the cheap win of full-screening a single
  authenticated dashboard (meeting-room screen logged in as a service user). Rejected.

**Rationale**: The playlist entity gives admins a managed, listable, revocable object
("what is currently on our screens?") and devices a single stable URL. The flag gives
single-screen cases a zero-setup path. Both share one renderer component.

### D2: Playlist is a first-class access grant — per-dashboard owner-or-admin check

**Decision**: `oc_launchpad_kiosk_playlists` rows carry their own `token` (64-byte
URL-safe, `ISecureRandom`) and `revokedAt`. Creating or updating a playlist validates
owner-or-admin permission for **every** referenced dashboard at write time; the public
render path re-checks dashboard existence at read time and skips (not errors) entries
whose dashboard has since been deleted.

**Rationale**: A playlist token makes dashboards anonymously readable — exactly what a
public share does. If the permission check were only "may create playlists", any user
could leak any dashboard by UUID. Write-time per-dashboard validation mirrors
REQ-PSHR-001's owner-or-admin rule. We deliberately do NOT require pre-existing public
shares for playlist entries: the playlist token *is* the grant (one revocation point per
screen), and entries are read through the same service-layer read path the public-share
renderer uses, including the read-only bearer guard (REQ-PSHR-006) and GroupFolder
service-account path (REQ-PSHR-010) when that lands.

### D3: 404 and throttle semantics — inherited verbatim from public-share

**Decision**: Unknown, revoked, and (future) expired playlist tokens return HTTP 404
with no existence leak. The public render route carries
`#[BruteForceProtection(action: 'launchpad_share_access')]` — the *same* action bucket
as public-share renders, not a new one.

**Rationale**: Token-scanning the kiosk route and the share route is the same attack;
splitting throttle buckets would double an attacker's budget. Reusing
`launchpad_share_access` keeps the admin-visible throttle model exactly as
`dashboard-public-share` design D1/D2 documented it.

### D4: Rotation and refresh are client-side; payload is per-entry lazy

**Decision**: `GET /kiosk/{token}` returns the playlist descriptor (entries, dwell,
refresh interval) plus the first entry's render payload; subsequent entries are fetched
just-in-time ~5 s before their dwell slot via the same public render endpoint family.
Widget data on the visible dashboard re-fetches in place every `refreshSeconds`
(default 300, clamped `[30, 86400]`) with no full page reload.

**Alternatives considered:** server-pushed rotation (SSE/WebSocket) — rejected for v1;
TV browsers and reverse proxies make long-lived connections the least reliable part of
the stack, and polling at dwell granularity is trivially cheap.

**Rationale**: Just-in-time fetch means each cycle shows data at most one dwell old
without hammering the server with a full-playlist fetch every loop. In-place refresh
avoids the white-flash reload artifact that makes signage look broken.

### D5: Unattended failure policy — skip, retain, retry

**Decision**: (a) An entry whose fetch or render fails is skipped after a 10 s grace and
the rotation advances; the entry is retried on the next loop. (b) On network failure the
view keeps the last successfully rendered content and shows a small reconnect indicator;
it resumes automatically when a poll succeeds. (c) If *every* entry fails and nothing
has ever rendered, a neutral branded placeholder (no stack traces, no NC error page) is
shown and polling continues. (d) A render-loop watchdog restarts the rotation engine if
no advance has occurred within `2 × max(dwell)` — a frozen screen is the worst outcome.

**Rationale**: The whole point of a kiosk surface is that nobody is there to fix it.
Every failure mode must degrade to "slightly stale content" or "calm placeholder", never
to a dead or leaking screen.

### D6: Chrome suppression scope

**Decision**: `kiosk=1` (and the playlist route, which implies it) suppresses the NC
header/navigation, the LaunchPad switcher sidebar, all edit/context-menu affordances,
and scrollbars; the grid scales to the full viewport. For *authenticated* kiosk views,
Esc exits back to the normal view (keyboard-operable, WCAG); the public playlist route
has no exit affordance (there is nothing to exit to). The cursor is hidden after 10 s
idle on kiosk surfaces.

**Rationale**: Read-only enforcement is already server-side (REQ-PSHR-006); hiding edit
affordances is presentation hygiene, not security. Esc-to-exit keeps the flag usable on
a normal workstation without trapping keyboard users.

## Spec changes implied

All requirements are net-new in the `dashboard-kiosk-mode` capability; no existing spec
text changes. The dependency on `dashboard-public-share` is consumption-only (D2, D3).

## Open follow-ups

- Playlist-level scheduling (different playlists per time window) once demand is shown.
- Device heartbeat ("screen X last fetched at T") for fleet monitoring of displays.
- Screen Wake Lock API adoption note once TV-browser support is broad enough to matter.

# Iframe-embed widget

Embed an external page — a status board, a Grafana panel, an internal
tool — directly on a dashboard, instead of only linking out to it.

## The host allow-list

Embeddable targets are governed by the `iframe_allowed_hosts` app config
and are **fail-closed**: enforced both when the widget is saved and again
whenever the dashboard is rendered. An empty (or unset) allow-list denies
**every** host — it is never interpreted as "allow all".

```bash
occ config:app:set launchpad iframe_allowed_hosts --value='["status.example.com","intranet.example.nl"]'
```

Removing a host from the list immediately stops any existing placement
pointing at it — the widget switches to the "no longer permitted" state
rather than continuing to render a stale live frame.

## What LaunchPad's CSP contributes — and what it doesn't

Every allow-listed host is added to LaunchPad's own `frame-src`
Content-Security-Policy directive (via an `AddContentSecurityPolicyEvent`
listener), so Nextcloud's own CSP never blocks an otherwise-permitted
embed. This is the **only** side of the framing relationship LaunchPad
controls.

The **target** site's own `X-Frame-Options: DENY` or
`Content-Security-Policy: frame-ancestors 'none'` header is a decision made
by that site's owner and **cannot be overridden** by the embedder — no CSP
change on LaunchPad's side can force such a target to render in a frame.
When the widget detects this (no `load` event within a timeout, or a
`load` event that resolves to an empty same-origin placeholder document),
it renders a fallback card instead of a silent blank frame: the
configured title, a plain-language explanation, and an "Open in new tab"
link. This is a client-side detection, not a proxy or CSP bypass — nothing
strips or spoofs the target's own headers.

## Sandbox

The iframe always carries a `sandbox` attribute. Authors may toggle
`allow-scripts`, `allow-same-origin`, `allow-forms`, and `allow-popups`;
`allow-top-navigation` (and its `-by-user-activation` variant) is never
offered and is stripped even if present in a saved config, so an embedded
frame can never navigate the host dashboard page away.

## Configuration

| Setting | Notes |
|---------|-------|
| URL | Validated against the admin allow-list, both client-side (fast feedback) and server-side (authoritative) |
| Title | Required — exposed as the iframe's accessible `title` for screen readers |
| Height / aspect ratio | Fixed pixel height, or one of `16:9` / `4:3` / `1:1` / `9:16` |
| Sandbox tokens | `allow-scripts`, `allow-same-origin`, `allow-forms`, `allow-popups` |

## Accessibility

The blocked/failed state is conveyed by an icon **and** a text label,
never by colour alone, and the "Open in new tab" link is keyboard-focusable
and announces that it opens in a new tab.

## Related

- [Widgets](widgets.md) — how widgets are discovered and placed.
- [Live-data tile](live-data-tile.md) — the sibling capability this widget's
  allow-list/CSP approach is modelled on.

# iframe-embed widget — embed an external URL in a sandboxed frame

LaunchPad dashboards can today link *out* to an external page but cannot render one *in place*. Embedding a live external portal, a Grafana panel, a status page or an internal tool directly on a dashboard is one of the most-requested Nextcloud dashboard capabilities — filed as **dashboard#53 in 2019** and still open — and is filled today only by third-party micro-apps (iFrame Widget, External Portal, DashLink) that ship no CSP handling and no fallback when the target refuses framing.

This change adds a first-class `launchpad_iframe` widget that embeds an admin-allow-listed external URL in a **sandboxed** `<iframe>`, contributes the target host to Nextcloud's `frame-src` Content-Security-Policy so the frame is permitted to load, and **degrades gracefully** to a clear fallback card (with an open-in-new-tab link) when the target site itself forbids framing via `X-Frame-Options: DENY` or `Content-Security-Policy: frame-ancestors 'none'` — constraints that a parent page cannot override. Market research (Spectr `lp-iframe-embed-widget`, demand 8, competitorCoverage 7) ranks it a high-demand, well-covered gap.

## Affected code units

- `lib/Widget/IframeWidgetProvider.php` — new v2 widget provider registering `launchpad_iframe` with the Nextcloud Dashboard `IManager`.
- `lib/Listener/CspListener.php` (or `Application.php` registering an `AddContentSecurityPolicyEvent` listener) — contributes each admin-allow-listed host to the app `IContentSecurityPolicy` `frame-src` directive so embedded frames are permitted to load; hosts read from admin config `iframe_allowed_hosts`.
- `src/components/widgets/IframeWidget.vue` — renders the sandboxed iframe, detects load failure (frame refused / blank), and swaps to a fallback card with an open-in-new-tab action; loading and error states.
- `src/components/widgets/IframeWidgetConfig.vue` — author UI: URL (validated against the allow-list), title, height/aspect-ratio, sandbox toggle set, allow-list-checked indicator.
- Admin setting `iframe_allowed_hosts` (LaunchPad app config) — the fail-closed allow-list of embeddable hosts; also the source of the CSP `frame-src` contribution.
- `lib/Db/WidgetPlacement.php` — no schema change; iframe config stored in the existing `widgetContent` JSON blob.

## Why a new change

Embedding external origins is a security-sensitive surface: it touches the instance Content-Security-Policy, it can leak a dashboard into a hostile frame, and it fails in ways (X-Frame-Options, frame-ancestors) that are invisible until runtime. Isolating it as its own change keeps the CSP contribution, the admin allow-list, and the graceful-degradation contract reviewable in one place rather than smeared across the generic widget surface. The widget stores its config client-side in `widgetContent` (no schema change) but is NOT purely client-side: the CSP contribution and the allow-list live in PHP.

## Approach

- **Allow-list, fail-closed.** An admin config `iframe_allowed_hosts` lists embeddable hosts. A URL whose host is not on the list is rejected at save time and refused at render time; an empty list means "embed nothing".
- **CSP contribution.** For every allow-listed host the app adds `https://<host>` to the `frame-src` directive of its own `IContentSecurityPolicy` via an `AddContentSecurityPolicyEvent` listener, so Nextcloud's own CSP does not block the frame. This is the only side the app controls.
- **Graceful degradation.** The *target* site's `X-Frame-Options: DENY` / `frame-ancestors 'none'` cannot be overridden by the embedder. The widget detects a failed/blank load (no load event within a timeout, or a load event with an inaccessible/empty document) and renders a fallback card: the title, an explanation, and an "Open in new tab" link — never a silent blank frame.
- **Sandbox.** The iframe always carries a `sandbox` attribute; the author may toggle a constrained set of tokens (e.g. `allow-scripts`, `allow-same-origin`, `allow-forms`) but MUST NOT be able to grant `allow-top-navigation`.
- **WCAG AA.** The iframe has an accessible `title`; the fallback card state is conveyed by icon + text, not colour alone; the open-in-new-tab link is keyboard-reachable and announces that it opens a new tab.

## Notes

- Out of scope: authenticated / SSO pass-through into the embedded target (follow-up `iframe-auth-passthrough`).
- Out of scope: per-user (non-admin) allow-listing — the allow-list is admin-only in v1.
- Out of scope: automatic reverse-proxying of frame-refusing targets to strip `X-Frame-Options` — the fallback card is the v1 answer.

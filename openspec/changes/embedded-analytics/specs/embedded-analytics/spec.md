# Embedded analytics

Embed a live LaunchPad dashboard or widget on the organisation's own website with a scoped, revocable embed token, a JS-SDK and per-token framing, rate and telemetry rules.

## ADDED Requirements

### Requirement: REQ-EMB-001 — Token issuance returns JWT exactly once

The system SHALL generate a JWT signed with the active signing key when a new `embed_token` is created, return it once in the create response, persist only the sha256 hash, and reject any subsequent request to re-retrieve the raw token.

#### Scenario: 1.1 — Create token and receive JWT once

GIVEN an admin user with token-management permission
  AND the system has an active RS256 signing key
WHEN they POST `/api/embed-tokens` with a valid token-create payload:
  ```json
  {
    "name": "Gemeente Zeist WOO",
    "description": "Public WOO requests widget",
    "subject": {"type": "widget", "id": "woo-widget-uuid"},
    "scope": {"mode": "read", "allowedFilters": ["status"], "allowedActions": []},
    "hostOrigins": ["https://intranet.zeist.nl"],
    "rateLimitPolicy": {"requestsPerMinute": 600, "burstSize": 60}
  }
  ```
THEN the response SHALL be 201 with body:
  ```json
  {
    "id": "token-id-uuid",
    "name": "Gemeente Zeist WOO",
    "jwt": "eyJhbGciOiJSUzI1NiIsImtpZCI6InJzYS0xIn0...",
    "warning": "keepThisSafe: This JWT is shown only once. Save it now. If lost, you must re-issue the token.",
    "createdAt": "2026-05-22T14:30:00Z"
  }
  ```
  AND the response body SHALL include the raw JWT exactly once
  AND a new row SHALL be created in `embed_token` register with:
    - `id` matching the response
    - `tokenHash` = sha256("eyJhbGciOiJSUzI1NiIsImtpZCI6InJzYS0xIn0...")
    - ALL other fields matching the request + createdAt

#### Scenario: 1.2 — Subsequent GET returns metadata but not JWT

GIVEN an existing `embed_token` row with id="token-1" and tokenHash set
WHEN any GET request to `/api/embed-tokens/token-1` is made
THEN the response SHALL be 200 with body:
  ```json
  {
    "id": "token-1",
    "name": "Gemeente Zeist WOO",
    "description": "Public WOO requests widget",
    "subject": {"type": "widget", "id": "woo-widget-uuid"},
    "scope": {"mode": "read", ...},
    "hostOrigins": ["https://intranet.zeist.nl"],
    "createdAt": "2026-05-22T14:30:00Z",
    "createdBy": "admin-user-id",
    "actions": [
      {"id": "re-issue", "label": "Re-issue token", "description": "Revoke this token and generate a new one"}
    ]
  }
  ```
  AND the response SHALL NOT include the `jwt` field
  AND the response SHALL NOT include the `tokenHash` field

#### Scenario: 1.3 — Token creation rejects malformed hostOrigins

GIVEN an admin user with token-management permission
WHEN they POST `/api/embed-tokens` with `hostOrigins` containing a malformed origin:
  ```json
  {
    "name": "Bad Token",
    "hostOrigins": ["https://www.example.com", "invalid-origin-missing-scheme"],
    ...
  }
  ```
THEN the response SHALL be 400 with body:
  ```json
  {
    "status": "error",
    "error": "invalid_hostOrigins",
    "message": "Invalid origin format at index 1: missing URI scheme. Expected format: https://domain.com",
    "fieldErrors": {
      "hostOrigins[1]": "invalid_hostOrigins"
    }
  }
  ```
  AND the `embed_token` row SHALL NOT be persisted

#### Scenario: 1.4 — Re-issue token (revoke + create new)

GIVEN an existing `embed_token` row with id="token-1"
WHEN an admin invokes the "re-issue" action via POST `/api/embed-tokens/token-1/re-issue`
THEN a new JWT SHALL be generated (signed with active key)
  AND the old token's `revokedAt` SHALL be set to now
  AND the old token's `revocationReason` SHALL be set to "re-issued"
  AND a new `embed_token` row SHALL be created with:
    - fresh `id` (new UUID)
    - fresh `tokenHash` (sha256 of new JWT)
    - same `subject`, `scope`, `hostOrigins`, `rateLimitPolicy` as the old token
    - new `createdAt` and `createdBy`
  AND the response SHALL include the new JWT (shown once)

---

### Requirement: REQ-EMB-002 — Public render route validates JWT and enforces scope

The render route SHALL verify the JWT signature against the `kid`-selected `signing_key`, reject expired or revoked tokens, enforce `subject` match, and respond with 403 on any mismatch without leaking the existence of the subject.

#### Scenario: 2.1 — Valid non-expired token renders widget

GIVEN a valid, non-expired, non-revoked JWT for widget W signed with RS256 key (kid=rsa-1)
  AND the embedded widget renders HTML (no token scope restriction assumed yet — REQ-EMB-004 covers scope enforcement)
WHEN the route is called with `GET /apps/launchpad/embed/widget/W?token=<jwt>`
THEN the response SHALL be 200 with HTML body
  AND the response SHALL include header `Content-Security-Policy: frame-ancestors <hostOrigins>`
  AND the response SHALL include header `X-Content-Type-Options: nosniff`
  AND the widget SHALL render with the token's context applied (e.g., filter pinning from `subject.filterContext`)
  AND a `embed_usage_event` row SHALL be written with:
    - `eventType: "pageView"`
    - `responseStatusCode: 200`
    - `responseLatencyMs: <measured>`

#### Scenario: 2.2 — Subject mismatch returns 403 without leaking existence

GIVEN a valid JWT for widget W
  AND the admin tries to render it against widget V (different UUID)
WHEN the route is called with `GET /apps/launchpad/embed/widget/V?token=<jwt>`
THEN the response SHALL be 403 with body:
  ```json
  {
    "error": "scope_mismatch",
    "message": "The provided token does not grant access to this resource"
  }
  ```
  AND the response SHALL NOT indicate whether widget V exists or not
  AND a `embed_usage_event` row SHALL be written with:
    - `eventType: "pageView"`
    - `responseStatusCode: 403`

#### Scenario: 2.3 — Expired token returns 401 with WWW-Authenticate header

GIVEN a JWT whose `tokenExpiresAt` is in the past (e.g., "2026-01-01T00:00:00Z" and current time is 2026-05-22)
WHEN the route is called with `GET /apps/launchpad/embed/widget/W?token=<jwt>`
THEN the response SHALL be 401 with body:
  ```json
  {
    "error": "token_expired",
    "message": "The provided token has expired"
  }
  ```
  AND the response SHALL include header:
    `WWW-Authenticate: Bearer error="invalid_token", error_description="The token has expired"`
  AND a `embed_usage_event` row SHALL be written with `responseStatusCode: 401`

#### Scenario: 2.4 — Revoked token returns 401 with constant-time comparison

GIVEN a JWT whose `embed_token` row has `revokedAt` set (token was revoked 5 minutes ago)
WHEN the route is called with `GET /apps/launchpad/embed/widget/W?token=<jwt>`
THEN the response SHALL be 401 with body:
  ```json
  {
    "error": "token_revoked",
    "message": "The provided token has been revoked"
  }
  ```
  AND the response latency SHALL be constant-time-comparable to a non-revoked rejection (to prevent timing oracle attacks)
  AND a `embed_usage_event` row SHALL be written with `responseStatusCode: 401`

#### Scenario: 2.5 — Invalid JWT signature returns 401

GIVEN a JWT with an invalid signature (e.g., tampered payload)
WHEN the route is called with `GET /apps/launchpad/embed/widget/W?token=<tampered-jwt>`
THEN the response SHALL be 401 with body:
  ```json
  {
    "error": "invalid_token",
    "message": "The provided token is invalid or malformed"
  }
  ```

#### Scenario: 2.6 — Missing token returns 401

GIVEN a request to the embed render route without a token
WHEN the route is called with `GET /apps/launchpad/embed/widget/W` (no ?token= parameter)
THEN the response SHALL be 401 with body:
  ```json
  {
    "error": "missing_token",
    "message": "No token provided. Include ?token=<jwt> or use the Authorization: Bearer <jwt> header"
  }
  ```

#### Scenario: 2.7 — Bearer header token (SDK form-factor)

GIVEN a valid JWT passed via Authorization header (SDK use case)
WHEN the route is called with `GET /apps/launchpad/embed/widget/W` + header `Authorization: Bearer <jwt>`
THEN the response SHALL be 200 with HTML body (same as Scenario 2.1)
  AND the response latency/rate-limit treatment SHALL be identical to query-parameter tokens
  AND the response CSP/cache headers SHALL be identical

#### Scenario: 2.8 — Query-parameter tokens enforce stricter rate limits

GIVEN a token with `rateLimitPolicy: {requestsPerMinute: 600, burstSize: 60}`
  AND requests arriving via `?token=<jwt>` (query parameter)
  AND requests arriving via `Authorization: Bearer <jwt>` (header)
WHEN both form-factors are used for the same embed in the same minute
THEN the query-parameter form SHALL count against the token's configured rate limit
  AND the Bearer-header form MAY have a separate (stricter) rate limit (e.g., 10× lower) to account for the token leak risk
  AND the admin UI SHOULD surface this distinction in the token-creation flow

### Requirement: REQ-EMB-003 — CSP frame-ancestors enforced from token hostOrigins

The render route SHALL emit `Content-Security-Policy: frame-ancestors <hostOrigins>` derived from the token's `hostOrigins`, and the embed SHALL refuse to render when the iframe's actual top-level origin is not in `hostOrigins`. This is enforced both at the CSP level (modern browsers) and via Fetch Metadata headers (defence in depth).

#### Scenario: 3.1 — Valid origin renders with CSP frame-ancestors header

GIVEN a token with `hostOrigins=["https://www.zeist.nl"]`
  AND the token is for widget W
WHEN the embed is loaded inside an iframe on `https://www.zeist.nl/woo/`
THEN the response to `GET /apps/launchpad/embed/widget/W?token=<jwt>` SHALL be 200 with HTML body
  AND the response SHALL include header:
    `Content-Security-Policy: frame-ancestors https://www.zeist.nl`
  AND the browser SHALL render the iframe normally (CSP allows framing from https://www.zeist.nl)

#### Scenario: 3.2 — Invalid origin blocked by CSP + server-side Sec-Fetch-Site check

GIVEN the same token with `hostOrigins=["https://www.zeist.nl"]`
WHEN the iframe is loaded from `https://evil.example.com/phishing-page`
  AND the iframe's top-level origin is `https://evil.example.com`
THEN the browser SHALL refuse to display the iframe due to CSP frame-ancestors violation
  AND the server-side render route SHALL check the `Sec-Fetch-Site` header:
    - If `Sec-Fetch-Site: cross-site` and top-origin from Referer ≠ allowed hostOrigins
    - Then respond 403 with body `{error: "origin_mismatch", message: "This iframe cannot be embedded on this origin"}`
  AND the browser still refuses to render (CSP violation takes precedence)
  AND a `embed_usage_event` SHALL be written with `responseStatusCode: 403`

#### Scenario: 3.3 — Multiple allowed origins

GIVEN a token with `hostOrigins=["https://intranet.zeist.nl", "https://www.zeist.nl"]`
WHEN the embed is loaded from either origin
THEN the response SHALL include header:
  `Content-Security-Policy: frame-ancestors https://intranet.zeist.nl https://www.zeist.nl`
  AND the browser SHALL allow framing from either origin

#### Scenario: 3.4 — Wildcard hostOrigins rejected at token creation

GIVEN an admin user
WHEN they attempt to POST `/api/embed-tokens` with `hostOrigins=["*"]`
THEN the response SHALL be 400 with body:
  ```json
  {
    "status": "error",
    "error": "invalid_hostOrigins",
    "message": "Wildcard origin '*' is not permitted. Specify explicit origins, e.g., [\"https://www.example.com\"]",
    "fieldErrors": {
      "hostOrigins": "wildcard_not_permitted"
    }
  }
  ```
  AND the token SHALL NOT be created

#### Scenario: 3.5 — X-Frame-Options fallback for legacy browsers

GIVEN any render route response
WHEN the response is generated
THEN the response SHALL ALSO include header:
  `X-Frame-Options: SAMEORIGIN`
  (As a fallback for browsers that do not support CSP Level 3; no protective claim is made about X-Frame-Options sufficiency on modern browsers per W3C spec)

#### Scenario: 3.6 — Referer header check (defence in depth)

GIVEN a request with `Authorization: Bearer <jwt>` (Bearer header, not query param)
  AND the token's `hostOrigins=["https://intranet.example.com"]`
WHEN the render route processes the request
  AND the `Referer` header is `https://evil.example.com/page`
THEN the route SHALL respond 403 with body `{error: "origin_mismatch"}`
  AND a `embed_usage_event` SHALL be written with `responseStatusCode: 403`

#### Scenario: 3.7 — No Referer header (privacy-preserving browser configuration)

GIVEN a request with no `Referer` header
  AND the `Sec-Fetch-Site` header indicates `same-site`
THEN the route SHALL allow the request (assume same-origin; Referer is not available in privacy-preserving configurations)
  AND a `embed_usage_event` SHALL be written with `responseStatusCode: 200`

#### Scenario: 3.8 — Admin UI displays CSP header preview

GIVEN the admin is creating or editing an embed token
WHEN they edit the `hostOrigins` field
THEN the admin UI SHALL display a live preview of the resulting CSP header:
  ```
  Content-Security-Policy: frame-ancestors https://intranet.zeist.nl
  ```
  AND a "Copy CSP header" button SHALL let them paste this into their host website's audit documentation

### Requirement: REQ-EMB-004 — Read-only enforcement at the API tier

The system SHALL reject any write operation (POST/PUT/PATCH/DELETE) issued under an `embed_token` whose `scope.mode=read`, even when the operation would otherwise be permitted by the subject's ACL. This enforcement happens on the API tier before any state mutation.

#### Scenario: 4.1 — Read-mode token rejects write operation

GIVEN an `embed_token` with `scope.mode="read"` for dashboard D
  AND an authenticated user holds this token (via JWT in Authorization or query parameter)
  AND the user (or JavaScript running in the iframe) attempts a POST to add a comment on dashboard D
WHEN the SDK or frontend invokes `POST /api/dashboards/D/comments` with a comment payload
THEN the API SHALL respond 403 with body:
  ```json
  {
    "error": "read_only_token",
    "message": "This token does not permit write operations. Token scope: mode=read"
  }
  ```
  AND NO state SHALL be mutated (no comment created, no audit log entry)
  AND a `embed_usage_event` SHALL be written with `eventType: "pageView"`, `responseStatusCode: 403`

#### Scenario: 4.2 — Read-mode token rejects DELETE

GIVEN the same read-only token for dashboard D
WHEN the frontend attempts `DELETE /api/dashboards/D/widgets/w1` (remove a widget from the dashboard)
THEN the API SHALL respond 403 with body:
  ```json
  {
    "error": "read_only_token",
    "message": "This token does not permit write operations. Token scope: mode=read"
  }
  ```
  AND NO state mutation occurs

#### Scenario: 4.3 — Read-mode token permits GET and HEAD

GIVEN the same read-only token
WHEN the frontend invokes `GET /api/dashboards/D` (read operation)
THEN the API SHALL respond 200 with the dashboard data
  AND read permission is determined by the subject-match (the token is scoped to dashboard D)
  AND no scope-mode check blocks the read

#### Scenario: 4.4 — Read-with-interactions token permits scoped actions

GIVEN an `embed_token` with `scope.mode="read-with-interactions", allowedActions=["interact", "drillDown"]` for dashboard D
  AND the dashboard has a filter UI
WHEN the SDK applies an in-bus filter clause (drill-down), invoking `POST /api/dashboards/D/filters` with `{dimension: "gemeente", value: "Amsterdam"}`
THEN the API SHALL respond 200 and process the filter
  AND the operation SHALL be captured in an `embed_usage_event` with `eventType: "filterApplied"`
  AND subsequent `GET` calls reflect the pinned filter

#### Scenario: 4.5 — Read-with-interactions token rejects disallowed action

GIVEN an `embed_token` with `scope.mode="read-with-interactions", allowedActions=["interact", "drillDown"]` (export NOT allowed)
WHEN the frontend attempts `POST /api/dashboards/D/export` with `format: "csv"`
THEN the API SHALL respond 403 with body:
  ```json
  {
    "error": "action_not_in_scope",
    "message": "The action 'export' is not permitted by this token. Allowed actions: interact, drillDown"
  }
  ```
  AND NO export is generated

#### Scenario: 4.6 — All write operations checked, regardless of subject type

GIVEN a read-only token for a widget W
WHEN any of the following write operations are attempted on the widget:
  - `POST /api/widgets/W/interactions` (add interaction)
  - `PUT /api/widgets/W/settings` (update widget settings)
  - `PATCH /api/widgets/W` (partial update)
THEN ALL SHALL respond 403 with the read-only error
  AND the API does NOT discriminate by operation type; ALL non-GET/HEAD are blocked

#### Scenario: 4.7 — Scope enforcement independent of frontend state

GIVEN a read-only token embedded in an iframe
WHEN the JavaScript in the iframe is patched (via browser DevTools) to remove the UI-layer read-only enforcement
  AND the patched JS attempts `POST /api/dashboards/D/widgets/new`
THEN the API-tier check SHALL still respond 403
  AND the tampered frontend cannot bypass the API check

#### Scenario: 4.8 — Scope metadata visible in token detail view

GIVEN an admin viewing an embed token's details
WHEN they inspect the token's scope
THEN the admin UI SHALL display:
  ```
  Mode: read
  Allowed Filters: status, periode
  Allowed Actions: (none)
  ```
  AND when creating a read-with-interactions token, the admin SHALL see checkboxes for each available action (interact, export, drillDown) to select

#### Scenario: 4.9 — Filter context pinning persisted with token

GIVEN an `embed_token` for dashboard D with `subject.filterContext={gemeente: "Amsterdam"}`
WHEN the embed is rendered
  AND the user attempts to change the gemeente filter to "Rotterdam" via the UI
THEN the filter interaction SHALL succeed (read-with-interactions mode allows it)
  BUT the filter SHALL NOT persist across page reloads — the next page load resets the gemeente filter to "Amsterdam"
  AND the filter context is enforced by the render route, not by the frontend

#### Scenario: 4.10 — API middleware integration point

GIVEN the LaunchPad API middleware/controller base
WHEN a request arrives with an `embed_token` (detected via JWT in Authorization header or token context)
THEN the middleware SHALL:
  1. Verify the token is valid and non-revoked (REQ-EMB-002)
  2. Extract the token's scope
  3. For non-GET/HEAD requests, check if `scope.mode=read` and respond 403 before the controller is invoked
  4. For GET/HEAD requests, proceed to the controller (the subject-match verification happens per-endpoint)
  5. For actions with allowedActions restrictions, check the action name against the allowed set before invoking the handler

### Requirement: REQ-EMB-005 — Per-tenant theming via CSS variables

The embed SHALL apply the token's `tenantThemeId` (if set) by injecting `cssVariables` into the embed's `:root` and SHALL refuse `customCss` rules that target selectors outside `:root`.

#### Scenario: 5.1 — CSS variables injected into :root

GIVEN a `tenant_theme` object with:
  ```json
  {
    "id": "theme-zeist",
    "name": "Gemeente Zeist",
    "cssVariables": {
      "--c-primary": "#21468B",
      "--font-family": "Inter, sans-serif",
      "--c-surface": "#FFFFFF",
      "--c-text": "#1a1a1a",
      "--c-border": "#e0e0e0"
    }
  }
  ```
  AND an `embed_token` with `tenantThemeId="theme-zeist"` for widget W
WHEN the embed render route generates HTML for widget W
THEN the response SHALL include:
  ```html
  <html>
    <head>
      <style>
        :root {
          --c-primary: #21468B;
          --font-family: Inter, sans-serif;
          --c-surface: #FFFFFF;
          --c-text: #1a1a1a;
          --c-border: #e0e0e0;
        }
      </style>
    </head>
    <body>
      <!-- widget HTML -->
    </body>
  </html>
  ```
  AND the widget's styles SHALL use these CSS variables
  AND the widget SHALL paint with the gemeente's brand colour (primary), font, and surface colour

#### Scenario: 5.2 — Custom CSS restricted to :root only

GIVEN a `tenant_theme` with `customCss=":root { --spacing: 8px; }"`
WHEN the theme is saved via `POST /api/tenant-themes`
THEN the API SHALL accept the theme and respond 201

#### Scenario: 5.3 — Custom CSS with non-:root selector rejected

GIVEN an admin attempting to save a `tenant_theme` with `customCss=".widget-title { color: red; }"`
WHEN the POST `/api/tenant-themes` request is processed
THEN the API SHALL respond 400 with body:
  ```json
  {
    "status": "error",
    "error": "invalid_customCss",
    "message": "CSS rules must be scoped to :root only. Found selector '.widget-title' which targets elements outside :root. Non-:root selectors can be used to disable required UI affordances (e.g., hide accessibility focus rings).",
    "fieldErrors": {
      "customCss": "non_root_selector"
    }
  }
  ```
  AND the theme SHALL NOT be created

#### Scenario: 5.4 — No theme uses platform default

GIVEN an `embed_token` with `tenantThemeId: null` (no theme selected)
WHEN the embed render route generates HTML
THEN the response SHALL use the platform's default NL Design theme (no custom :root injection)
  AND the widget SHALL render with standard LaunchPad colours and fonts

#### Scenario: 5.5 — Theme logo displayed in embed header

GIVEN a `tenant_theme` with `logoUrl="https://www.zeist.nl/logo.svg"`
  AND an `embed_token` referencing this theme
WHEN the embed is rendered in iframe mode
THEN the render route MAY include a minimal embed header with:
  ```html
  <div class="embed-header">
    <img src="https://www.zeist.nl/logo.svg" alt="Gemeente Zeist" class="embed-logo" />
  </div>
  ```
  AND the header SHALL be minimal (no chrome, no navigation, no user menu)
  OR the header MAY be omitted if the embed is configured for full-bleed rendering

#### Scenario: 5.6 — Theme inheritance in admin UI

GIVEN the admin creating a new embed token
WHEN they select a `tenantThemeId`
THEN the admin UI SHALL display:
  - Theme name ("Gemeente Zeist")
  - Live preview of brand colours (primary, surface, text)
  - Logo preview (if set)
  AND a "View CSS variables" button to inspect the variable overrides

#### Scenario: 5.7 — Multiple tokens can share a theme

GIVEN a `tenant_theme` "Gemeente Zeist"
WHEN two different `embed_token` objects both reference `tenantThemeId="theme-zeist"`
THEN both embeds SHALL render with the same brand
  AND theme updates affect both embeds immediately
  AND the theme is a reusable resource, not cloned per token

---

### Requirement: REQ-EMB-010 — WCAG 2.1 AA compliance enforced in embed rendering

The embed render route SHALL produce HTML output that meets WCAG 2.1 AA success criteria for the widget being embedded, SHALL pass automated axe-core checks at build time on a representative sample of widget shapes, and SHALL surface accessibility violations in the embed-token admin UI as a non-fatal warning.

#### Scenario: 10.1 — WCAG AA rendering requirements

GIVEN an embed render of a chart widget
WHEN the HTML is inspected against WCAG 2.1 AA criteria
THEN EVERY interactive element SHALL have:
  - An accessible name (`aria-label`, `aria-labelledby`, or visible text content)
  - Colour contrast ≥ 4.5:1 for text (WCAG 1.4.3)
  - Colour contrast ≥ 3:1 for graphical objects (WCAG 1.4.11)
AND the embed SHALL be navigable by keyboard alone:
  - All interactive elements reachable via Tab key
  - Tab order logical (left-to-right, top-to-bottom)
  - No keyboard traps (user can tab away from any element)
  - Focus indicators visible (outline or other visible focus marker)
AND semantic HTML is used:
  - Heading hierarchy correct (no skipped levels)
  - Lists marked with `<ul>`, `<ol>`, `<li>`
  - Form inputs have associated `<label>` elements

#### Scenario: 10.2 — Contrast check on theme CSS variables

GIVEN a `tenant_theme` with `cssVariables={"--c-primary": "#0099CC", "--c-surface": "#FFFFFF"}`
WHEN the theme is saved
THEN the API SHALL compute the colour contrast ratio using the WCAG luminance formula
  AND if contrast < 4.5:1, respond 400 with body:
  ```json
  {
    "status": "error",
    "error": "insufficient_contrast",
    "message": "Primary colour #0099CC against surface #FFFFFF has contrast ratio 3.8:1. WCAG AA requires 4.5:1 for text.",
    "fieldErrors": {
      "cssVariables.--c-primary": "insufficient_contrast"
    }
  }
  ```
  AND the theme SHALL NOT be created
  (Admin can choose a darker primary or lighter surface and retry)

#### Scenario: 10.3 — Build-time axe-core checks

GIVEN the LaunchPad build pipeline
WHEN `npm run build` executes
THEN a test step SHALL:
  1. Render a representative sample of widget shapes (e.g., chart, table, text, image, custom)
  2. Run axe-core automated checks against each rendered HTML
  3. Fail the build if high/critical violations are found
  4. Log a warning for medium/low violations with IDs
  AND the build output SHALL include an axe-core report

#### Scenario: 10.4 — Admin warned of accessibility violations on token creation

GIVEN an admin creating an `embed_token` for a widget W
  AND widget W has known accessibility violations (e.g., from a previous axe-core check)
WHEN the admin submits the token-create form
THEN the API SHALL check the widget's violation registry
  AND if violations exist, respond 200 (success, but with warning) with:
  ```json
  {
    "id": "token-1",
    "name": "...",
    ...
    "warnings": [
      {
        "level": "accessibility",
        "message": "Widget 'chart' has 2 known accessibility violations (axe-core build-time check):",
        "violations": [
          {
            "id": "color-contrast",
            "description": "Ensure the contrast between foreground and background colours meets WCAG AA thresholds",
            "help": "Element <text class=\"legend\"> has contrast ratio 2.8:1. Expected ≥4.5:1"
          },
          {
            "id": "aria-label",
            "description": "Ensure every graphical element has an accessible name",
            "help": "<svg class=\"chart\"> is missing aria-label"
          }
        ]
      }
    ]
  }
  ```
  AND the response includes an "Acknowledge and proceed" checkbox in the admin UI
  AND the admin MUST check the checkbox before creating the token (non-blocking, but requires acknowledgement)

#### Scenario: 10.5 — Axe-core check results per widget

GIVEN the admin viewing the list of available widgets
WHEN they see a widget's detail card
THEN the card SHALL display:
  - Widget name + description
  - Latest axe-core check date
  - Number of high/critical violations (in red if > 0)
  - Number of medium/low violations (in yellow if > 0)
  AND a "View accessibility report" link that opens the axe-core report for that widget

#### Scenario: 10.6 — Accessibility report exportable

GIVEN an admin with token-management permission
WHEN they request an accessibility compliance report
THEN the system SHALL generate a CSV/JSON report listing:
  - Widget name
  - Accessibility status (compliant / violations found)
  - Violation count by severity
  - Affected tokens (count of embeds using this widget)
  AND the report includes axe-core rule IDs and WCAG criteria references

#### Scenario: 10.7 — Manual accessibility review responsibility

GIVEN a widget with axe-core violations
WHEN the admin acknowledges and creates a token
THEN the token's audit trail SHALL record:
  - "Token created for widget 'chart' with acknowledged accessibility violations: color-contrast, aria-label"
  AND the responsibility for manual review (the remaining ~70% of WCAG criteria not mechanically checkable) is documented per-widget
  AND the widget author is responsible for ensuring manual compliance

#### Scenario: 10.8 — Keyboard navigation tested in embed iframe

GIVEN an embedded widget in an iframe
WHEN a user navigates using keyboard alone (Tab, Shift+Tab, Enter, Escape, arrow keys)
THEN:
  - All interactive elements are reachable
  - No keyboard traps exist (user can Tab out)
  - Focus indicator is visible and moves logically
  - Modals (if any) trap focus correctly

### Requirement: REQ-EMB-006 — JS-SDK exposes render, event, and resize APIs

The system SHALL publish a JS-SDK (`@launchpad/embed-sdk`, ESM and UMD builds) that exposes `LaunchPadEmbed.render(container, {token, subjectType, subjectId})`, `.on(eventType, handler)` for filter/drill/export events, and `.resize()` for host-driven layout changes.

#### Scenario: 6.1 — SDK initialization and iframe creation

GIVEN a host page (e.g., a SaaS customer portal) that imports the SDK:
  ```html
  <script src="https://cdn.example.com/@launchpad/embed-sdk@1.0.0/dist/embed-sdk.umd.js"></script>
  <!-- OR ESM: import LaunchPadEmbed from '@launchpad/embed-sdk'; -->
  ```
WHEN the host page calls:
  ```javascript
  const embed = LaunchPadEmbed.render('#embed-container', {
    token: 'eyJhbGciOiJSUzI1NiIsImtpZCI6InJzYS0xIn0...',
    subjectType: 'widget',
    subjectId: 'usage-widget-uuid'
  });
  ```
THEN the SDK SHALL:
  1. Create an `<iframe>` element with `src="https://launchpad.app/apps/launchpad/embed/widget/usage-widget-uuid"`
  2. Insert the iframe into the `#embed-container` div
  3. Wait for the iframe's `load` event
  4. Perform a postMessage handshake with the iframe to inject the JWT token
  5. Resolve a promise when the first render completes
  AND the host page code continues:
  ```javascript
  embed.then(() => {
    console.log('Widget loaded');
  });
  ```

#### Scenario: 6.2 — PostMessage handshake for token injection

GIVEN the SDK has created an iframe
WHEN the iframe loads (fires `load` event)
THEN the SDK SHALL:
  1. Post a message to the iframe:
     ```javascript
     iframe.contentWindow.postMessage({
       type: 'embed-token',
       token: 'eyJhbGciOiJSUzI1NiIsImtpZCI6InJzYS0xIn0...',
       origin: 'https://portal.example.com'
     }, 'https://launchpad.app')
     ```
  2. The iframe's origin verification (in the render route) SHALL check that `event.origin === 'https://launchpad.app'` before accepting the message
  3. The iframe stores the token in a secure context and uses it for subsequent API requests
  4. The iframe posts back a 'ready' message when the widget is fully rendered:
     ```javascript
     window.parent.postMessage({type: 'embed-ready', success: true}, '<SDK-origin>')
     ```
  5. The SDK resolves the promise returned by `render()` when the 'embed-ready' message arrives

#### Scenario: 6.3 — Filter event subscription and relay

GIVEN the host page has called `render()` successfully
WHEN the host page subscribes to filter events:
  ```javascript
  embed.on('filterApplied', ({dimension, value, source}) => {
    console.log(`Filter applied: ${dimension} = ${value}`);
    // Host page can now sync its own UI
  });
  ```
AND the end-user clicks on a bar in the embedded chart (drill-down interaction)
THEN the iframe SHALL:
  1. Emit an internal event (in-app bus) with `{dimension: 'gemeente', value: 'Amsterdam'}`
  2. Post a message to the parent:
     ```javascript
     window.parent.postMessage({
       type: 'embed-event',
       eventType: 'filterApplied',
       payload: {dimension: 'gemeente', value: 'Amsterdam', source: 'user-interaction'}
     }, '<SDK-origin>')
     ```
  3. The SDK receives the message and invokes the registered handler
  4. The host page can read the filter and update its own UI/URL

#### Scenario: 6.4 — Export event (read-with-interactions)

GIVEN an embed with `scope.allowedActions=["export"]`
WHEN the user clicks "Export to CSV" in the widget
THEN the iframe SHALL:
  1. Invoke the export API (allowed by scope)
  2. Post a 'export' event to the parent:
     ```javascript
     window.parent.postMessage({
       type: 'embed-event',
       eventType: 'export',
       payload: {format: 'csv', url: 'https://launchpad.app/api/downloads/export-123.csv'}
     }, '<SDK-origin>')
     ```
  3. The host page can download the file or handle it custom (e.g., send to email)

#### Scenario: 6.5 — Resize API for responsive layout

GIVEN the host page's layout changes (e.g., browser window resized, sidebar collapsed)
WHEN the host page calls:
  ```javascript
  embed.resize();
  ```
THEN the SDK SHALL:
  1. Post a message to the iframe:
     ```javascript
     iframe.contentWindow.postMessage({
       type: 'embed-resize',
       containerWidth: 1200,
       containerHeight: 600
     }, 'https://launchpad.app')
     ```
  2. The iframe receives the message and re-measures its container
  3. The iframe re-renders the widget with the new dimensions
  4. The iframe posts back a 'resized' message when complete:
     ```javascript
     window.parent.postMessage({type: 'embed-resized', success: true}, '<SDK-origin>')
     ```
  5. The SDK resolves the promise returned by `resize()`

#### Scenario: 6.6 — ESM build with TypeScript types

GIVEN a TypeScript host application
WHEN it imports the SDK:
  ```typescript
  import LaunchPadEmbed from '@launchpad/embed-sdk';
  import type { EmbedConfig, EmbedInstance } from '@launchpad/embed-sdk';
  
  const config: EmbedConfig = {
    token: 'eyJ...',
    subjectType: 'widget',
    subjectId: 'uuid'
  };
  
  const embed: EmbedInstance = await LaunchPadEmbed.render('#container', config);
  embed.on('filterApplied', (event) => {
    // event is typed as EmbedFilterEvent
  });
  ```
THEN the IDE SHALL provide autocomplete and type checking
  AND the TypeScript types SHALL document the API surface

#### Scenario: 6.7 — UMD build for vanilla JS

GIVEN a vanilla JavaScript page (no build step, no module bundler)
WHEN it loads the UMD bundle:
  ```html
  <script src="https://unpkg.com/@launchpad/embed-sdk@1.0.0/dist/embed-sdk.umd.js"></script>
  ```
THEN the SDK is available as a global:
  ```javascript
  window.LaunchPadEmbed.render('#container', {token: '...', ...})
    .then(() => console.log('Ready'));
  ```

#### Scenario: 6.8 — Error handling in SDK

GIVEN the host page calls `render()` with an invalid token
WHEN the iframe attempts to load the render route
THEN the render route responds 401
  AND the iframe posts an 'error' message to the parent:
  ```javascript
  window.parent.postMessage({
    type: 'embed-error',
    error: 'invalid_token',
    message: 'The provided token is invalid or has expired'
  }, '<SDK-origin>')
  ```
  AND the SDK's promise rejects with a descriptive error:
  ```javascript
  embed.catch((err) => {
    console.error('Failed to load widget:', err.message);
  });
  ```

#### Scenario: 6.9 — Event listener management

GIVEN the host page has subscribed to events:
  ```javascript
  const handler = (event) => { ... };
  embed.on('filterApplied', handler);
  ```
WHEN the host page wants to unsubscribe:
  ```javascript
  embed.off('filterApplied', handler);
  ```
THEN the event listener SHALL be removed
  AND no further 'filterApplied' events are delivered to that handler
  AND other handlers (if registered) still receive events

#### Scenario: 6.10 — Multiple embeds on same page

GIVEN a host page with three embed containers:
  ```html
  <div id="embed-1"></div>
  <div id="embed-2"></div>
  <div id="embed-3"></div>
  ```
WHEN the host page initializes all three:
  ```javascript
  const embed1 = LaunchPadEmbed.render('#embed-1', {token: '...', subjectId: 'widget-1'});
  const embed2 = LaunchPadEmbed.render('#embed-2', {token: '...', subjectId: 'widget-2'});
  const embed3 = LaunchPadEmbed.render('#embed-3', {token: '...', subjectId: 'widget-3'});
  ```
THEN each embed operates independently
  AND events from embed1 do not leak to embed2 or embed3
  AND each postMessage handshake is isolated

#### Scenario: 6.11 — SDK package publishing

GIVEN the LaunchPad build process
WHEN `npm run build:sdk` executes
THEN the SDK package SHALL be published to npm with:
  - ESM build: `dist/embed-sdk.esm.js`
  - UMD build: `dist/embed-sdk.umd.js`
  - TypeScript definitions: `dist/embed-sdk.d.ts`
  - Minified versions: `dist/embed-sdk.esm.min.js`, `dist/embed-sdk.umd.min.js`
  - Source maps for debugging
  - Package name: `@launchpad/embed-sdk`
  - Version matching the LaunchPad app version

### Requirement: REQ-EMB-007 — Rate limiting and request budgeting per token

The system SHALL apply the token's `rateLimitPolicy` to all requests, return 429 with a `Retry-After` header when the bucket is exhausted, and write a `embed_usage_event` of type `pageView` even on rate-limited responses for budgeting analysis.

#### Scenario: 7.1 — Token-bucket rate limiting with burst

GIVEN a token with `rateLimitPolicy={requestsPerMinute: 60, burstSize: 10}`
  AND the current time is T=0
WHEN 70 requests arrive between T=0 and T=10 seconds (10 seconds into the first minute)
THEN:
  - Requests 1-10 SHALL be processed (up to burst size)
  - Requests 11-70 SHALL receive 429 responses with `Retry-After: 50` (seconds until next bucket refill)
  AND each 429 response SHALL include an `embed_usage_event` with `responseStatusCode: 429`
  AND after 60 seconds (one full minute), the bucket is refilled and new requests can be processed

#### Scenario: 7.2 — Org-default rate limit for tokens without policy

GIVEN a token with `rateLimitPolicy: null` (no custom policy specified)
WHEN requests arrive
THEN the org-level default policy SHALL apply:
  ```json
  {
    "requestsPerMinute": 600,
    "burstSize": 60
  }
  ```
  AND the token operates under these defaults (high-throughput default for typical embeds)

#### Scenario: 7.3 — 429 response format

GIVEN a request that exceeds the rate limit
WHEN the render route processes it
THEN the response SHALL be 429 with:
  ```
  HTTP/1.1 429 Too Many Requests
  Content-Type: application/json
  Retry-After: 45
  
  {
    "error": "rate_limit_exceeded",
    "message": "Rate limit exceeded. The token allows 60 requests per minute. Try again in 45 seconds.",
    "retryAfter": 45,
    "resetAt": "2026-05-22T14:31:00Z"
  }
  ```

#### Scenario: 7.4 — Rate-limited request logged in usage events

GIVEN a request that hits the rate limit
WHEN the 429 response is returned
THEN an `embed_usage_event` row SHALL be written with:
  ```json
  {
    "id": "event-uuid",
    "tokenId": "token-id",
    "eventType": "pageView",
    "hostOrigin": "https://www.zeist.nl",
    "userAgent": "Chrome 124 / sha256(...)",
    "viewportSize": "medium",
    "timestamp": "2026-05-22T14:30:05Z",
    "responseStatusCode": 429,
    "responseLatencyMs": 12,
    "correlationId": "correlation-uuid"
  }
  ```
  AND the admin can query: "This token hit rate limits 45 times on 2026-05-22 between 14:00 and 15:00"

#### Scenario: 7.5 — Bucket refill and sliding window

GIVEN a token with `requestsPerMinute: 60, burstSize: 10` (1 req/sec, burst 10)
  AND 8 requests have been consumed at T=0
WHEN new requests arrive at T=30 seconds (midway through the first minute)
THEN:
  - 30 seconds of refill grants 30 requests (60 rpm ÷ 60 sec × 30 sec)
  - Bucket now has: 10 (burst) + 30 (refilled) - 8 (used) = 32 capacity
  - New request consumes 1, bucket now 31
  AND this is a token-bucket algorithm, not a fixed sliding window (continuous refill, not window resets)

#### Scenario: 7.6 — Query-parameter vs Bearer-header rate limit distinction

GIVEN a token with `rateLimitPolicy: {requestsPerMinute: 600, burstSize: 60}`
WHEN requests arrive via `?token=<jwt>` (query parameter)
THEN the query-parameter form MAY enforce a stricter limit:
  - Override to 10× lower: 60 rpm, burst 6 (token leak risk warrants stricter throttling)
  AND requests via `Authorization: Bearer <jwt>` (header) use the configured limit (600 rpm, burst 60)
  AND the admin UI displays this distinction when creating a token:
    ```
    Query-parameter form: up to 60 requests/minute (stricter for security)
    Bearer header form: up to 600 requests/minute (as configured)
    ```

#### Scenario: 7.7 — Rate limit policy updateable after token creation

GIVEN an existing token with `rateLimitPolicy: {requestsPerMinute: 60, burstSize: 10}`
WHEN an admin invokes `PUT /api/embed-tokens/token-id` with a new policy:
  ```json
  {
    "rateLimitPolicy": {
      "requestsPerMinute": 120,
      "burstSize": 15
    }
  }
  ```
THEN the API SHALL respond 200 and update the token
  AND the new rate limit applies to subsequent requests immediately
  AND in-flight requests (between update and next request) use the old policy (best-effort; eventual consistency is acceptable)

#### Scenario: 7.8 — Rate limit exhaustion analysis

GIVEN the admin accessing the usage report for a token
WHEN they view the analytics dashboard
THEN they SHALL see:
  ```
  Rate Limit Breaches
  ├─ 2026-05-22: 145 requests rejected (60 expected)
  │  Time: 14:00–15:00 (busiest hour)
  │  Breach threshold: 600 rpm → hit 700 rpm
  ├─ 2026-05-21: 23 requests rejected
  └─ 2026-05-20: 0 requests rejected
  
  Recommendation: Consider raising requestsPerMinute to 1200
  ```
  AND a button "Adjust rate limit" opens the token edit dialog

#### Scenario: 7.9 — Alert configuration (optional v1+)

GIVEN an admin who wants to monitor token health
WHEN they configure alerts on a token (v1.1 feature, out of scope here but schema-ready):
  ```json
  {
    "alertPolicy": {
      "thresholdPercentage": 80,
      "notifyEmail": "ops@gemeente.nl"
    }
  }
  ```
THEN when the token reaches 80% of its rate limit in a given minute, an email alert is sent
  (This is a note for future expansion; not implemented in v1, but the schema supports it)

#### Scenario: 7.10 — Rate limit headers in all responses

GIVEN any response from the embed render route
WHEN the response is generated
THEN the response SHALL include rate-limit headers (RFC 6585 style):
  ```
  RateLimit-Limit: 60
  RateLimit-Remaining: 45
  RateLimit-Reset: 2026-05-22T14:31:00Z
  ```
  AND the host page / SDK can read these headers to show a rate-limit warning to the user

### Requirement: REQ-EMB-008 — Usage telemetry without leaking PII

The system SHALL persist `embed_usage_event` rows for every page-view and interaction, SHALL capture `hostOrigin`, `userAgent` (truncated to family + major version + hashed), and `viewportSize` (bucketed), and SHALL NOT capture full IP addresses, full user-agents, or any identifier of the host-page's logged-in user.

#### Scenario: 8.1 — Page-view telemetry capture

GIVEN an embed page-view request from `https://www.zeist.nl/woo/` (host origin)
  AND the user's user-agent: `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36`
  AND the viewport size: 1920×1080 pixels
WHEN the render route processes the request and returns 200
THEN an `embed_usage_event` row SHALL be written with:
  ```json
  {
    "id": "event-uuid",
    "tokenId": "token-id",
    "eventType": "pageView",
    "hostOrigin": "https://www.zeist.nl",
    "userAgent": "Chrome 124",
    "userAgentHash": "sha256(Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36)",
    "viewportSize": "large",
    "responseStatusCode": 200,
    "responseLatencyMs": 87,
    "timestamp": "2026-05-22T14:30:00Z",
    "correlationId": "correlation-uuid"
  }
  ```

#### Scenario: 8.2 — Viewport size bucketing

GIVEN viewport widths:
  - 0–767px (small)
  - 768–1279px (medium)
  - 1280px+ (large)
WHEN embed requests arrive with various viewport sizes
THEN the event's `viewportSize` field SHALL be bucketed:
  ```json
  [
    {"width": 480, "height": 720, "viewportSize": "small"},
    {"width": 1024, "height": 768, "viewportSize": "medium"},
    {"width": 1920, "height": 1080, "viewportSize": "large"}
  ]
  ```

#### Scenario: 8.3 — NO IP address capture

GIVEN a request arriving with headers:
  ```
  X-Forwarded-For: 203.0.113.42
  X-Real-IP: 203.0.113.42
  ```
WHEN the embed usage event is written
THEN the event row SHALL NOT include:
  - Client IP address
  - X-Forwarded-For header value
  - Any IP-based geolocation
AND the only "location" data is the `hostOrigin` (domain, not geolocation)

#### Scenario: 8.4 — User-agent family + major version only

GIVEN user-agents:
  ```
  Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.6367.60
  Mozilla/5.0 (iPhone; CPU iPhone OS 17_4_1 like Mac OS X) Mobile Safari/604.1
  Mozilla/5.0 (X11; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0
  ```
WHEN events are written
THEN the `userAgent` field SHALL be truncated to family + major:
  ```json
  [
    {"userAgent": "Chrome 124"},
    {"userAgent": "Safari 17"},
    {"userAgent": "Firefox 125"}
  ]
  ```
AND the full user-agent string is hashed and stored separately in `userAgentHash` for analytics without fingerprinting

#### Scenario: 8.5 — NO end-user identifier in events

GIVEN a host page that is embedded in a SaaS portal, where the logged-in user is "customer-123"
  AND the LaunchPad request is made in the context of that user
WHEN the embed usage event is written
THEN the event row SHALL NOT include:
  - User ID from the host page (even if passed in a header)
  - Email address of the host-page's logged-in user
  - Account name or subscription tier
AND the only identifier is the `tokenId` (which widget/dashboard was embedded, not who viewed it)

#### Scenario: 8.6 — Host origin stored (not referrer, not full URL)

GIVEN a request from `https://www.zeist.nl/woo/requests-2026-05/?sort=date&limit=50`
WHEN the event is written
THEN the `hostOrigin` field SHALL be:
  ```json
  {"hostOrigin": "https://www.zeist.nl"}
  ```
NOT:
  - The full URL (path+query stripped)
  - The Referer header (may be empty or stripped by proxies)
  - Derived from IP geolocation

#### Scenario: 8.7 — Interaction events also captured

GIVEN the user interacts with an embedded widget (filter applied, drill-down, export)
WHEN the interaction is processed
THEN an `embed_usage_event` SHALL be written with:
  ```json
  {
    "eventType": "filterApplied",
    "payload": {
      "dimension": "status",
      "value": "openstaande"
    },
    "hostOrigin": "https://www.zeist.nl",
    "userAgent": "Chrome 124",
    "viewportSize": "large",
    "responseStatusCode": 200,
    "timestamp": "2026-05-22T14:30:15Z"
  }
  ```
OR:
  ```json
  {
    "eventType": "export",
    "payload": {
      "format": "csv"
    },
    ...
  }
  ```

#### Scenario: 8.8 — Usage report in admin UI

GIVEN an admin accessing the usage report for a token
WHEN they view the analytics dashboard
THEN they SHALL see:
  ```
  Pageviews
  ├─ Total (last 7 days): 1,243 views
  ├─ By date:
  │  └─ 2026-05-22: 324 views
  │  └─ 2026-05-21: 289 views
  │  └─ 2026-05-20: 215 views
  ├─ By host origin:
  │  └─ https://www.zeist.nl: 1,100 views
  │  └─ https://intranet.zeist.nl: 143 views
  └─ By viewport size:
     └─ large: 601 views
     └─ medium: 432 views
     └─ small: 210 views
  
  Interactions
  ├─ filterApplied: 87 times
  ├─ drillDown: 45 times
  └─ export: 12 times
  
  Browsers
  ├─ Chrome 124: 623 views
  ├─ Safari 17: 312 views
  └─ Firefox 125: 308 views
  ```

#### Scenario: 8.9 — No individual-user identifiers in report

GIVEN the admin viewing the usage report
WHEN they inspect the data
THEN they SHALL NOT see:
  - List of end-users who viewed the embed
  - User names or email addresses
  - Timestamps per individual user (only aggregated by day)
  - Correlation of two views to the same person (no fingerprinting via UA hash + viewport)
AND the report answers only: "Did this embed get traffic?" and "From where?"

#### Scenario: 8.10 — Org-level telemetry kill-switch

GIVEN an admin with org configuration access
WHEN they toggle "Disable embed usage telemetry" in org settings
THEN:
  - No `embed_usage_event` rows are written for any subsequent requests
  - Existing events are NOT deleted (audit trail remains)
  - The toggle is auditable (audit log records who disabled telemetry and when)
  - Re-enabling telemetry immediately resumes event capture

#### Scenario: 8.11 — Data retention policy

GIVEN the org's data retention policy (e.g., "keep events for 90 days")
WHEN 90 days have passed since an `embed_usage_event` was created
THEN a background job SHALL delete the old event
  AND the event is permanently removed (not soft-deleted; no recovery)
  AND the deletion is logged in the audit trail

#### Scenario: 8.12 — GDPR/AVG data subject access (not applicable)

GIVEN a data subject (end-user) requesting access to their personal data per GDPR Article 15
WHEN the org's DPO runs the data-subject-access query
THEN the system SHALL respond:
  ```
  No personal data on record for this data subject.
  Reasoning: Embed telemetry captures only host origin and viewport size.
  No user identifiers, emails, IP addresses, or other traceable personal data are stored.
  The only entity involved is the embed token (issued by the org administrator to a third-party host),
  not personal data of an end-user.
  ```
AND the org does NOT need to include embed telemetry in GDPR access response letters

### Requirement: REQ-EMB-009 — Token revocation takes effect within 60 seconds

The system SHALL revoke a token by setting `revokedAt`, propagate the revocation to all render-route workers within 60 seconds via a short-TTL cache, and reject subsequent render requests with 401.

#### Scenario: 9.1 — Revocation via admin UI

GIVEN an admin viewing an embed token's detail page
WHEN they click the "Revoke" button
THEN the admin UI SHALL display a confirmation dialog:
  ```
  Revoke token "Gemeente Zeist WOO"?
  
  [Dropdown] Revocation reason:
  ├─ Compromised
  ├─ No longer needed
  ├─ Host website decommissioned
  └─ Other...
  
  [Text field] Additional notes (optional):
  "Gemeente decided to move WOO tracking in-house"
  
  [Cancel] [Confirm Revoke]
  ```
WHEN they select a reason and confirm
THEN the UI SHALL POST `/api/embed-tokens/token-id/revoke` with:
  ```json
  {
    "reason": "no_longer_needed",
    "notes": "Gemeente decided to move WOO tracking in-house"
  }
  ```

#### Scenario: 9.2 — Revocation persisted in token record

GIVEN the revoke request
WHEN the API processes it
THEN the `embed_token` row SHALL be updated with:
  ```json
  {
    "id": "token-id",
    "name": "Gemeente Zeist WOO",
    ...
    "revokedAt": "2026-05-22T14:35:00Z",
    "revocationReason": "no_longer_needed",
    "revocationNotes": "Gemeente decided to move WOO tracking in-house",
    "revokedBy": "admin-user-id"
  }
  ```
  AND the response SHALL be 200 with the updated token metadata
  AND a notification SHALL be sent to the token's `createdBy` user:
    ```
    Token "Gemeente Zeist WOO" has been revoked by admin-user.
    Reason: no_longer_needed
    Notes: Gemeente decided to move WOO tracking in-house
    Revocation timestamp: 2026-05-22T14:35:00Z
    ```

#### Scenario: 9.3 — Render-route workers reject revoked token within 60 seconds

GIVEN a revoked token (revokedAt set 5 seconds ago)
  AND multiple render-route worker processes running
WHEN any request arrives with the revoked token
THEN within 60 seconds (the cache propagation window), ALL workers SHALL:
  1. Check their revocation cache
  2. If the token is in the cache as revoked, respond 401
  3. If the token is NOT in the cache yet (slow propagation), query the database:
     ```sql
     SELECT revokedAt FROM embed_token WHERE id = ? LIMIT 1;
     ```
  4. If `revokedAt IS NOT NULL`, respond 401 and update the local cache
  5. Cache the revocation status for 60 seconds (TTL)
AND the response SHALL be 401 with:
  ```json
  {
    "error": "token_revoked",
    "message": "The provided token has been revoked"
  }
  ```

#### Scenario: 9.4 — Usage event written even for revoked token

GIVEN a revoked token used after revocation
WHEN the render route returns 401
THEN an `embed_usage_event` SHALL be written with:
  ```json
  {
    "eventType": "pageView",
    "responseStatusCode": 401,
    "responseLatencyMs": 5,
    "timestamp": "2026-05-22T14:36:30Z"
  }
  ```
AND the admin can query the usage report:
  "Token 'Gemeente Zeist WOO' was used 23 times AFTER revocation (2026-05-22 14:35–14:45)"
AND this helps forensic review of potential security incidents

#### Scenario: 9.5 — Cache propagation guarantees

GIVEN a render-route worker A and worker B
WHEN a token is revoked at T=0
AND worker A immediately refreshes its cache from the database
AND worker B is busy and does not refresh for 50 seconds
THEN:
  - Worker A rejects the token starting at T=1 second
  - Worker B continues accepting the token until T=50 seconds
  - At T=60 seconds, worker B's cache expires and it must query the database
  - Worker B then rejects the token starting at T=61 seconds
AND the 60-second window bounds the maximum delay across all workers

#### Scenario: 9.6 — Purge cache action for critical incidents

GIVEN a critical incident (token compromised, attacker active)
WHEN an admin clicks "Purge cache" on the revoked token's detail page
THEN the API SHALL:
  1. Immediately invalidate all render-route worker caches for this token
  2. Send a broadcast message to all workers (Redis, AMQP, or similar):
     ```json
     {
       "type": "token_cache_purge",
       "tokenId": "token-id",
       "timestamp": "2026-05-22T14:35:30Z"
     }
     ```
  3. All workers receive the message and drop the token from their cache
  4. The next request with the revoked token queries the database and is rejected immediately
AND the response shows:
  ```
  Cache purged. All render workers will immediately reject this token.
  Purge initiated at 2026-05-22T14:35:30Z.
  ```

#### Scenario: 9.7 — Re-issue (new token, revoke old)

GIVEN an admin who has lost a token or wants to refresh it
WHEN they invoke the "Re-issue" action on the token
THEN the system SHALL:
  1. Generate a new JWT
  2. Create a new `embed_token` row with the same subject, scope, hostOrigins, etc.
  3. Revoke the old token (set revokedAt and revocationReason: "re-issued")
  4. Return the new JWT to the admin (shown once)
AND the old token is rejected starting immediately (cache expires within 60s, database rejects immediately)
AND subsequent integration uses the new token

#### Scenario: 9.8 — Revocation audit trail

GIVEN the audit log for a token
WHEN an admin reviews the token's history
THEN they SHALL see:
  ```
  Audit Trail for Token "Gemeente Zeist WOO"
  ├─ 2026-05-22T14:35:00Z — REVOKED by admin@gemeente.nl
  │  Reason: no_longer_needed
  │  Notes: Gemeente decided to move WOO tracking in-house
  ├─ 2026-05-20T10:00:00Z — ACCESSED 342 times
  │  Host origins: https://intranet.zeist.nl (340), https://www.zeist.nl (2)
  ├─ 2026-05-15T09:30:00Z — CREATED by admin@gemeente.nl
  │  Subject: widget / woo-widget
  │  Scope: read, filters=[status, periode]
  └─ (no further events)
  ```

#### Scenario: 9.9 — Token state visibility

GIVEN a list of all embed tokens in the admin UI
WHEN the admin views the token table
THEN each row SHALL display:
  - Token name
  - Status: [ACTIVE | REVOKED | EXPIRING SOON]
  - Subject: (widget/dashboard name)
  - Created: 2026-05-15
  - Last used: 2026-05-22 14:30
  - Revoked: (empty if active, or "2026-05-22 14:35" if revoked)
  - Actions: [View] [Edit] [Re-issue] [Revoke] (or [Un-revoke] if revoked — soft-revoke only)

#### Scenario: 9.10 — Soft revocation (can be undone)

GIVEN a revoked token
WHEN an admin clicks "Undo revocation" (or "Un-revoke")
THEN the system SHALL:
  1. Clear the `revokedAt` and `revocationReason` fields
  2. Set `unrevokedAt` and `unrevokedBy` for audit
  3. Respond 200 with the token's updated state
  4. Send a notification to the token's creator
AND the token is immediately re-activated (cache expires, database accepts)
AND the full history (revocation + un-revocation) is auditable

#### Scenario: 9.11 — Bulk revocation (future feature, out of scope)

GIVEN multiple tokens that are no longer needed
WHEN an admin selects them and invokes "Revoke selected" (future v1.1 feature)
THEN all tokens are revoked in a single operation
  (This is a note for future; not implemented in v1, but the schema supports bulk operations)

#### Scenario: 9.12 — Revocation email notification

GIVEN a token that was revoked
WHEN the revocation is confirmed
THEN an email SHALL be sent to the token's creator:
  ```
  Subject: Token Revoked: Gemeente Zeist WOO
  
  Your embed token "Gemeente Zeist WOO" has been revoked.
  
  Revoked by: admin@gemeente.nl
  Reason: no_longer_needed
  Notes: Gemeente decided to move WOO tracking in-house
  Revocation time: 2026-05-22 14:35 UTC
  
  If this was unexpected, contact the administrator.
  ```

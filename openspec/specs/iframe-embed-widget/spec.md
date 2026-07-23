# iframe-embed-widget Specification

## Purpose
TBD - created by archiving change iframe-embed-widget. Update Purpose after archive.

## Requirements

### Requirement: REQ-IFRAME-001 Register iframe-embed widget

The system MUST register a `launchpad_iframe` widget with the Nextcloud Dashboard Widget API (v2) so it appears in the widget picker.

#### Scenario: Widget appears in discovery
- GIVEN the LaunchPad app is installed and enabled
- WHEN the user opens the "Add Widget" modal on a dashboard
- THEN the iframe-embed widget MUST appear in the widget list with id `launchpad_iframe`
- AND the widget MUST have a title and an icon
- AND the widget MUST declare `supportsV2 = true`

#### Scenario: Registration via IManager
- GIVEN `OCP\Dashboard\IManager` is available
- WHEN the LaunchPad app boots
- THEN the app MUST register `IframeWidgetProvider` by calling `$manager->registerWidget(...)`

#### Scenario: Config stored in widgetContent
- GIVEN an iframe widget is placed on a dashboard
- WHEN the author saves its configuration
- THEN the config MUST persist in the placement `widgetContent` JSON as `{ "url": "https://…", "title": "…", "height": 400, "aspect": "16:9", "sandbox": ["allow-scripts","allow-same-origin"], "allowListChecked": true }`
- AND NO database migration or schema change MUST be required

### Requirement: REQ-IFRAME-002 Admin allow-list of embeddable hosts, fail-closed

The system MUST restrict embeddable targets to an administrator-controlled allow-list of hosts and MUST fail closed when a host is not listed.

#### Scenario: Allow-list default is empty and denies all
- GIVEN no `iframe_allowed_hosts` admin setting has been configured
- WHEN an author tries to save an iframe widget with any URL
- THEN the save MUST be rejected with a validation error
- AND an empty allow-list MUST be interpreted as "embed nothing" (deny all), never "allow all"

#### Scenario: Allowed host saves
- GIVEN the admin has set `iframe_allowed_hosts` to include `status.example.com`
- WHEN an author saves an iframe widget with URL `https://status.example.com/board`
- THEN the save MUST succeed and `allowListChecked` MUST be `true`

#### Scenario: Disallowed host rejected at save
- GIVEN the admin allow-list contains only `status.example.com`
- WHEN an author tries to save an iframe widget with URL `https://evil.example.net/`
- THEN the save MUST be rejected with a validation error naming the disallowed host
- AND the placement MUST NOT be created or updated

#### Scenario: Host removed after configuration is refused at render
- GIVEN an existing iframe placement whose host was later removed from `iframe_allowed_hosts`
- WHEN the dashboard is rendered
- THEN the frame MUST NOT be embedded
- AND the widget MUST render a "This embed is no longer permitted by your administrator" state, never a live frame

### Requirement: REQ-IFRAME-003 Contribute allow-listed hosts to the frame-src CSP

The system MUST add every allow-listed host to the app's Content-Security-Policy `frame-src` directive so Nextcloud's own CSP permits the frame to load.

#### Scenario: Allow-listed host is added to frame-src
- GIVEN the admin allow-list contains `status.example.com`
- WHEN LaunchPad handles the `AddContentSecurityPolicyEvent`
- THEN the app `IContentSecurityPolicy` MUST include `https://status.example.com` in its `frame-src` directive

#### Scenario: Non-allow-listed hosts are never added
- GIVEN the admin allow-list contains only `status.example.com`
- WHEN the CSP is assembled
- THEN `frame-src` MUST NOT contain any host absent from the allow-list
- AND the app MUST NOT add a wildcard (`*`) to `frame-src`

#### Scenario: CSP contribution is scoped to LaunchPad pages
- GIVEN the CSP listener runs
- WHEN it contributes `frame-src` hosts
- THEN it MUST use the app-scoped `IContentSecurityPolicy` (merged only into LaunchPad responses) and MUST NOT loosen the global instance CSP for unrelated apps

### Requirement: REQ-IFRAME-004 Sandbox the frame and degrade gracefully when the target refuses framing

The system MUST embed the target in a sandboxed iframe and, when the target site forbids framing, MUST render a clear fallback card instead of a blank frame. WCAG AA compliant.

#### Scenario: Sandbox attribute is always present
- GIVEN an iframe widget renders a permitted URL
- WHEN the iframe element is produced
- THEN it MUST carry a `sandbox` attribute
- AND the sandbox token set MUST be limited to the author-toggled tokens
- AND the sandbox MUST NEVER include `allow-top-navigation` (the frame cannot navigate the host page)

#### Scenario: Accessible frame title
- GIVEN an iframe widget with a configured title
- WHEN it renders
- THEN the iframe MUST expose that title via its `title` attribute for screen readers

#### Scenario: Target sends X-Frame-Options DENY
- GIVEN a permitted URL whose target responds with `X-Frame-Options: DENY` (or `Content-Security-Policy: frame-ancestors 'none'`)
- WHEN the widget attempts to load it
- THEN — because the target's framing refusal CANNOT be overridden by the embedder — the widget MUST detect the failed/blank load (no load event within a timeout, or a load event yielding an inaccessible/empty document)
- AND it MUST render a fallback card showing the title, a plain-language explanation, and an "Open in new tab" link to the URL
- AND it MUST NOT leave a silent blank frame on the dashboard

#### Scenario: Fallback state is not colour-only and is keyboard-reachable
- GIVEN the fallback card is shown
- WHEN a keyboard or screen-reader user reaches it
- THEN the failure state MUST be conveyed with an icon and text label, not colour alone
- AND the "Open in new tab" link MUST be focusable, activatable by keyboard, and announce that it opens in a new tab

#### Scenario: Loading and generic error states
- GIVEN an iframe widget is loading
- WHEN the frame has not yet loaded
- THEN the widget MUST show a loading indicator
- AND on any other load error (network failure, invalid URL) it MUST render the fallback card, never crash the dashboard

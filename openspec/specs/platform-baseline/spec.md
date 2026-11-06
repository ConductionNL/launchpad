# platform-baseline Specification

## Purpose
What LaunchPad takes from the Nextcloud it runs on instead of building itself: sign-in, hosting, the organisation's theme, the language, group membership, installing as a phone app and light or dark mode. Each requirement names what LaunchPad must do so that the platform behaviour reaches every LaunchPad page.

## Requirements

### Requirement: LaunchPad has no login of its own (REQ-PLB-001)

LaunchPad MUST run in the Nextcloud session and MUST NOT offer a login form, password store or token flow of its own. Every LaunchPad page and API route MUST require a signed-in Nextcloud user, except the routes that exist to serve visitors without an account: a public dashboard share (`PageController::publicShare`, `PublicShareController`), a kiosk link (`KioskController::render`) and the health check (`HealthController::index`). Single sign-on therefore comes from whatever Nextcloud is set up with (user_oidc, user_saml or LDAP), without a LaunchPad setting.

#### Scenario: Signed in once, no second prompt

- **GIVEN** Pieter signed in to Nextcloud through the organisation's identity provider
- **WHEN** he opens `/apps/launchpad/`
- **THEN** his start page loads without a LaunchPad sign-in step

#### Scenario: Signed-out visitor goes to the Nextcloud login

- **GIVEN** a browser without a Nextcloud session
- **WHEN** it opens `/apps/launchpad/`
- **THEN** Nextcloud answers with its own login page (and from there the identity provider)
- **AND** no LaunchPad page or dashboard data is sent before sign-in

#### Scenario: Only the visitor routes are public

- **GIVEN** the LaunchPad controllers
- **WHEN** their routes are listed with their auth attributes
- **THEN** only the public share, kiosk and health routes carry `#[PublicPage]`

### Requirement: LaunchPad runs on the organisation's own servers (REQ-PLB-002)

LaunchPad MUST install as an ordinary Nextcloud app on the organisation's own instance and MUST keep its dashboards, placements, rules and settings in that instance's database and app config. It MUST NOT depend on a hosted Conduction service. A server-side outbound request MUST go only to an address an administrator configured (weather provider, feeds, health pings) or an author placed on a dashboard (live tile, embed, news feed).

#### Scenario: Installed from the app store on an own instance

- **GIVEN** a Nextcloud instance run by the organisation
- **WHEN** an administrator enables LaunchPad
- **THEN** every dashboard is stored in that instance's database
- **AND** LaunchPad works with no account at, or connection to, any outside service

#### Scenario: No outbound call without a configured address

- **GIVEN** a fresh install with no weather provider, feed, live tile or embed configured
- **WHEN** users open their dashboards
- **THEN** the LaunchPad server makes no outbound HTTP request

### Requirement: Every page takes on the organisation's theme (REQ-PLB-003)

LaunchPad styles MUST take colours, fonts and corner radii from Nextcloud's theme variables (`--color-*`, `--border-radius*`, `--font-face`), so a Nextcloud theme or a thematiq NL Design System theme restyles every LaunchPad page without a LaunchPad setting. A hard-coded colour MAY only appear as a user-chosen value (a tile or widget colour) or as a fallback behind a theme variable.

#### Scenario: A government theme restyles the start page

- **GIVEN** thematiq applies the organisation's NL Design System theme
- **WHEN** Pieter opens his start page
- **THEN** the page chrome, buttons and links show the theme's colours and font

#### Scenario: Per-dashboard looks are separate

- **GIVEN** the organisation theme and a dashboard with its own look (change `platform-dashboard-theme-and-css`)
- **WHEN** the dashboard renders
- **THEN** the organisation theme still sets every value the dashboard look leaves open

### Requirement: Every string is translatable and follows the user's language (REQ-PLB-004)

Every user-facing string in LaunchPad MUST go through the Nextcloud l10n domain `launchpad` (`t('launchpad', ...)` in the frontend, `IL10N` for the app id in PHP). LaunchPad MUST ship Dutch and English translations, and pages MUST show the language chosen in the user's Nextcloud personal settings.

#### Scenario: Dutch user

- **GIVEN** Pieter set his Nextcloud language to Nederlands
- **WHEN** he opens LaunchPad
- **THEN** the menus, buttons and empty states are in Dutch

#### Scenario: No string in another app's domain

- **GIVEN** the frontend sources
- **WHEN** every `t(` and `n(` call is listed
- **THEN** each one names the domain `launchpad`

### Requirement: Group dashboards follow the groups set at sign-in (REQ-PLB-005)

LaunchPad MUST read a user's groups from Nextcloud (`IGroupManager`) at the moment it resolves a dashboard or a visibility rule, and MUST NOT keep its own copy of group membership. Groups an identity provider sends at sign-in (through user_oidc or user_saml group provisioning) therefore decide the group dashboards and group rules from that request on. Routing to a group dashboard follows the resolver in `admin-templates` (REQ-TMPL-013).

#### Scenario: New group at sign-in

- **GIVEN** the identity provider adds Pieter to group "KCC" and Nextcloud provisions it at sign-in
- **AND** a group dashboard is shared with "KCC"
- **WHEN** Pieter opens LaunchPad after that sign-in
- **THEN** the "KCC" dashboard is available to him without an administrator action in LaunchPad

#### Scenario: Group removed

- **GIVEN** the identity provider no longer sends "KCC" for Pieter
- **WHEN** he signs in again and opens LaunchPad
- **THEN** the "KCC" dashboard and widgets with a "KCC" visibility rule are no longer shown to him

### Requirement: Pages install on a phone through Nextcloud's web app manifest (REQ-PLB-006)

LaunchPad pages MUST be installable from the browser's "add to home screen" option through the web app manifest Nextcloud serves, and LaunchPad MUST NOT ship a manifest or service worker of its own. The installed app opens where Nextcloud's default app points; with LaunchPad set as default app, that is the LaunchPad start page. The page layout on a phone follows board LpMobiel.

#### Scenario: Add to home screen

- **GIVEN** LaunchPad is the Nextcloud default app
- **WHEN** Pieter chooses "Add to home screen" on a LaunchPad page in his phone's browser
- **THEN** a home screen icon appears
- **AND** opening it shows his LaunchPad start page in a window without the browser bar

### Requirement: Pages follow light or dark mode (REQ-PLB-007)

LaunchPad pages MUST use Nextcloud's theme variables for every surface, text and border colour, so they follow the user's Nextcloud theme setting, including "follow the system" light or dark, without a LaunchPad setting.

#### Scenario: Device in dark mode

- **GIVEN** Pieter's Nextcloud theme follows the system and his device is in dark mode
- **WHEN** he opens his start page
- **THEN** the page, widget frames and menus show the dark theme
- **AND** text keeps WCAG AA contrast against its background

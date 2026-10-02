---
kind: code
depends_on: []
---

# A public start page for visitors who have not signed in

## Why

A dashboard can already be published read-only on a public link
(`appinfo/routes.php:189-201`, `PublicShareController::show()` at line 252 with
`#[PublicPage]`, page at `/s/{token}`). But a visitor who opens the
organisation's Nextcloud address without signing in still gets the login form;
nothing makes a public dashboard the page they land on. Libraries, schools and
municipalities want a start page that shows opening hours, news and links to
everyone, with a sign-in button for staff.

Matrix row **d-public-start** (`openspec/parity/capabilities.json`), "Visitors
who have not signed in see a public start page instead of the login screen",
rated `partial`, `built.state` `built` (the public link). This change
specifies the missing half: the landing address.

- Demand: tender, https://www.publicprocurement.be/nl/publication-workspaces/204ca8ae-6191-43b9-ac3b-c943f91d1d2d. VVSG Blink 2025: "Er is een publieke startpagina die kan aangepast worden en die verschilt van de inlogpagina".
- Homarr, yes: source read at v1.77.2, boards can be public (`packages/db/schema/sqlite.ts:264` isPublic) and anonymous visitors land on the server home board (`packages/server-settings/src/index.ts:28`).
- Dashy, yes: source read at 4.7.0, `ConfigSchema.json:606-611` enableGuestAccess gives visitors read-only access without logging in (`src/utils/auth/Auth.js:123`).

The open change `public-dashboard-publication` adds publication modes, branding
and search-engine settings for published dashboards; it does not change what a
signed-out visitor sees at the Nextcloud address. This change does only that,
and works with today's public links.

## What changes

- An administrator picks one existing public link (no password, not expired) as the public start page and switches it on.
- A signed-out visitor who opens the Nextcloud address, and would get the login form, is sent to that start page instead. The page shows the dashboard read-only with a clear "Sign in" button.
- "Sign in" goes to the login form with `direct=1`, so staff are never sent back to the start page, and single sign-on setups keep working.
- Anyone who opens a deep link (a file, a share, a specific app) still gets the login form with their redirect intact.

## Capabilities

### Modified capabilities

- `dashboard-public-share`: adds the public start page.

## Impact

- `lib/Db/AdminSettingKey.php` (`public_start_enabled`, `public_start_share_token`), an admin section on the Sharing tab
- A listener on `OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent` that adds a small redirect script on the plain login page only
- A new public route `/start` in `PageController` that renders the chosen share with the existing public page, plus the "Sign in" button

## Out of scope

- Branding, indexing and caching of public pages (open change `public-dashboard-publication`).
- Changing what signed-in users land on (Nextcloud's default app setting already does that).

# Design: sharing-public-start-page

Read at development `d767c282`.

## Context

- Public links: `lib/Service/PublicShareService.php` (create at line 121), routes at `appinfo/routes.php:189-201` (`/s/{token}/data`, `/s/{token}/unlock`, `/s/{token}`), `PublicShareController::show()` (`#[PublicPage]`, line 248-252) and `PageController::publicShare()` (`lib/Controller/PageController.php:574-596`), which renders the `public` template with the `launchpad-public` script, `RENDER_AS_PUBLIC`, a rate limit and a CSP. Shares can carry a password and an expiry (REQ-PSHR, `openspec/specs/dashboard-public-share/spec.md`).
- The admin Sharing tab is `src/components/admin/tabs/SharingTab.vue`; admin settings are typed keys in `lib/Db/AdminSettingKey.php`.
- Nextcloud 32 to 34 dispatch `OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent` when the login form renders, and listeners may add scripts (`OCP\Util::addScript`). The login page honours `direct=1`, which login apps (`user_oidc`, `user_saml`) also use to skip their automatic redirect.

## Decisions

### D1: The start page is an existing public link

`public_start_share_token` holds the token of one public share. Saving refuses a share with a password or an expiry in the past, and the admin UI lists only eligible shares. If the share is later revoked, protected or expired, the start page switches itself off (the route returns the login redirect) and the admin section says why.

### D2: Redirect only from the plain login page

The listener acts only when `public_start_enabled` is on, the request is a GET for the login page, there is no `redirect_url`, no `direct` parameter and no `clear` parameter, and the user is not signed in. It adds `launchpad-start-redirect.js`, which replaces the location with `/apps/launchpad/start`. Deep links keep their `redirect_url` and therefore their login form. Without JavaScript the login form stays usable.

### D3: `/start` renders the share with a sign-in bar

`PageController::start()` (`#[PublicPage]`, `#[NoCSRFRequired]`, same rate limit as `publicShare()`) resolves the configured token through `PublicShareService`, then renders the same public template with a `startPage` flag. The public view shows a top bar with the organisation name and a "Sign in" button linking to `/login?direct=1`. The data call reuses `/s/{token}/data`, so the read-only guards of REQ-PSHR apply unchanged.

## Declarative-vs-imperative decision

Routing and a login-page listener; no schema register involved.

## Security

- Nothing new is exposed: the page shows a dashboard its owner already published publicly, through the existing read-only path.
- Password-protected and expired shares cannot be the start page (D1), so the start page never asks visitors for a share password.
- The redirect is a same-origin path, never taken from the request.

## Test plan

- PHPUnit: setting validation (password, expiry, unknown token), the listener's conditions (redirect_url, direct, clear, POST, signed-in), `start()` falling back when the share is gone.
- Vitest: the sign-in bar in the public view.
- Playwright: with the start page on, opening `/` signed out lands on the public dashboard; "Sign in" leads to the login form and signing in lands on the Workspace; opening `/apps/files/` signed out still shows the login form with its redirect.

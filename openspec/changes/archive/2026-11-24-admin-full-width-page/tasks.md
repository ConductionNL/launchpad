# Tasks: admin-full-width-page

- [x] Admin page route `/apps/launchpad/admin/{tab}` checked server-side for administrators (and delegated admins)
- [x] Mount the admin component in the Launchpad shell: top bar, no settings menu, tabs over the full width
- [x] Nextcloud's admin settings entry for Launchpad shows a short text and a link to the page
- [x] Deep links to a tab keep working from the More menu
- [x] Unit tests: non-admin gets 403 before rendering; tab from the route
- [x] Playwright: beheer opens Administration settings from More and sees no left menu (written, runs in nightly: tests/e2e/admin-full-width-page.spec.ts)

# Tasks: admin-full-width-page

- [ ] Admin page route `/apps/launchpad/admin/{tab}` checked server-side for administrators (and delegated admins)
- [ ] Mount the admin component in the Launchpad shell: top bar, no settings menu, tabs over the full width
- [ ] Nextcloud's admin settings entry for Launchpad shows a short text and a link to the page
- [ ] Deep links to a tab keep working from the More menu
- [ ] Unit tests: non-admin gets 403 before rendering; tab from the route
- [ ] Playwright: beheer opens Administration settings from More and sees no left menu

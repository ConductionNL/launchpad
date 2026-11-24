# Proposal: admin-full-width-page

## Why

The LpBeheer boards (Algemeen, Sjablonen, Rollen, Delen, Onderhoud, Analyse, Navigatie) draw Launchpad's administration as a page of its own over the full width: the Launchpad top bar, the title "Launchpad-beheer: <tab>", a path line, tabs over the full width and no left menu. runtime-shell REQ-SHELL-012 forbids a left panel inside Launchpad, but the administration lives at `/settings/admin/launchpad`, which Nextcloud's settings framework renders inside its own settings menu, so every LpBeheer board measures with a 264 px menu the board does not have.

## What changes

- `admin-settings`: the administration as a Launchpad page at `/apps/launchpad/admin/<tab>` without Nextcloud's settings menu (REQ-ADM-030); the Nextcloud settings entry links to it (REQ-ADM-031).

## Impact

- `appinfo/routes.php` and a page controller for the admin page (admin-only, server-side check), `src/admin.js` mounted in the Launchpad shell, `lib/Settings/LaunchPadAdmin.php` reduced to a link, `AdminSettingsRedirect.vue`.

---
kind: code
depends_on: []
chain: []
---

# Proposal: admin-page-in-nextcloud-settings

## Why

Ruben's review of 27 November 2026, item 19 (ADR-004, ADR-079): launchpad's admin page moves into Nextcloud's settings
framework. Today it is a launchpad page at `/apps/launchpad/admin/<tab>` (REQ-ADM-030): launchpad's top bar and More
menu, no Nextcloud settings menu, tabs over the full width, rendered by the app's own router and manifest. Nextcloud's
admin entry only links to it (REQ-ADM-031). That breaks two rules:

- ADR-004: admin settings are rendered by Nextcloud's settings framework (`lib/Settings/*Admin.php` and a settings entry
  point), never as a component in the app's vue-router.
- ADR-079: instance configuration lives at `/settings/admin/<app>`, where Nextcloud authorizes it server-side before
  anything renders.

And because the page is a manifest page in the app shell, buildiq can reach and customise it. Admin pages must not be
customisable.

The boards (design-system PR 1212, the shared part LpBeheer and every LpBeheer board) draw the page at
`/settings/admin/launchpad` inside Nextcloud's settings shell: Nextcloud's admin menu on the left with Launchpad
selected, the version card first on the first tab, then one settings section per feature, a tab per former admin tab.

## What changes

- `admin-settings`: REQ-ADM-030 and REQ-ADM-031 are removed. REQ-ASET-008 is rewritten. New: REQ-ADM-036 (the page in
  Nextcloud's settings framework, with its tabs), REQ-ADM-033 (the old route redirects), REQ-ADM-034 (delegated admin
  settings per area, the Announcements tab among them), REQ-ADM-035 (no admin page in the app's router or manifest).
- `runtime-shell` REQ-SHELL-009: the admin pages are no longer launchpad pages; More links out to the settings page.

## Out of scope

- What the Announcements tab does (change `announcements-managed-in-settings`); this change gives it its place and its
  delegation.
- The fleet-wide check of item 20.
- Changing what any other tab stores: every setting keeps its key and endpoint.

## Boards

LpBeheer (shared part), LpBeheerAlgemeen, LpBeheerSjablonen, LpBeheerRollen, LpBeheerDelen, LpBeheerOnderhoud,
LpBeheerAnalyse, LpBeheerNavigatie, LpAankondiging (the tab's place), LpMeer (Admin settings entry).

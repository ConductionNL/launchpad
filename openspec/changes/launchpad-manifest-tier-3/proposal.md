# LaunchPad manifest tier 3 — serve the pages the manifest declares

> **Status: implemented 2026-09-05.** Decisions below were taken by Ruben;
> what changed is in `tasks.md`.

`src/manifest.json` declares nine pages and eight menu entries. LaunchPad
serves one. `/store`, `/reports` and `/flows` each redirect to `/dashboard`,
and `/reports/dashboards` renders the workspace grid.

This change gives LaunchPad the routing its manifest already assumes, so that a
declared destination is a reachable one.

## Why now

`main.js` has named this change since Tier 1 adoption landed:

> Tier 1 manifest adoption (ADR-024): register the bundled manifest with nc-vue
> so the shared shell can read menu/page declarations. The vue-router definition
> below remains hand-wired (Tier 1 — not yet manifest-driven).
> **Tier 3 (launchpad-manifest-tier-3) will replace hand-wired routes.**

It was never filed, and in the meantime three things arrived that assume it:

- **ADR-114 app chrome.** `feat(chrome): give launchpad a Store` (2026-09-04)
  added the fourth footer destination and took gate-107 from 4 of 5 to 5 of 5.
  gate-107 reads the manifest. It cannot see that nothing routes what the
  manifest declares.
- **An E2E spec that has never passed.** `tests/e2e/app-chrome.spec.ts` landed
  2026-09-03 and that is the run in which LaunchPad first went red. It has not
  been green since. It asserted `[data-testid="cn-nav"]`, which this app does
  not render; retargeting it to `.workspace-shell` (#547) only moved the
  failure onto the true cause — the destinations do not resolve.
- **Four cards in the setup wizard's own chrome.** Documentation is an external
  href and works. Store, Reports and Features & roadmap are pages this app
  claims to host.

## The shape of the problem

LaunchPad is the only app in the fleet that does not root on `CnAppRoot`, and
it has **no vue-router at all** — `createRouter` appears nowhere in `src/`.
Navigation is Pinia state: `dashboard.js` and `orgNavigation.js` switch what
the grid shows, in place, without touching the URL.

That is not an oversight. `App.vue` records the decision where it writes its own
skip link, because not rooting on `NcContent`/`CnAppRoot` means not inheriting
Nextcloud's. `WorkspaceApp.vue` owns five regions — sidebar backdrop, hamburger
strip, grid surface, branded `DashboardFooter` — and `#launchpad-main-content`
carries both the WCAG 2.2 bypass target and the quick-search Esc contract.

So this is not "add a router". It is "give a single-surface app a second
surface without breaking the first".

## Affected code units

- `src/main.js` — create a `vue-router` instance in path mode and mount it;
  replace the `useAppManifest` Tier 1 registration with the manifest-driven
  route table nc-vue derives from `manifest.pages`.
- `src/App.vue` — root on `CnAppRoot` so the shared chrome renders the four
  footer destinations, with `WorkspaceApp` as the dashboard route's component
  rather than the app's only child. The skip link moves to whichever shell ends
  up owning it; it must not be dropped.
- `src/views/WorkspaceApp.vue` — becomes the `/dashboard` route's view. Its
  five regions stay; `#launchpad-main-content` keeps `tabindex="-1"`.
- `src/manifest.json` — no new declarations. This change makes the existing
  ones true.
- `tests/e2e/app-chrome.spec.ts` — the four `test.fail()` markers this change
  is named in come off, and the assertions become ordinary ones.

## Decisions taken (2026-09-05)

- **`CnAppRoot` hosts the workspace, with the default `CnAppNav`.** The org
  navigation rail is NOT an app menu and could not fold into one: it renders an
  org-wide link tree fetched from `GET /api/admin/org-navigation`, group-
  filtered, with its own position setting (REQ-ONAV-002/004/005), while
  `CnAppNav` renders `manifest.menu`. They coexist, and the rail already renders
  only when an admin has configured a tree — so a default instance has one
  navigation, not two.
- **Nextcloud's skip link, not LaunchPad's.** The bespoke one existed *because*
  the app did not root on `NcContent`; `CnAppRoot` renders it, so the platform
  link is there and two bypass links would be worse than one.
  `#launchpad-main-content` keeps `tabindex="-1"`, which the quick-search Esc
  contract needs independently of any link.
- **Dashboard URLs are in scope.** `/dashboards/:id` was already declared in the
  manifest and routed nowhere. `WorkspaceApp` watches the route param and calls
  `switchDashboard`, so a dashboard can be linked, bookmarked and reopened
  (`dashboard-deeplinking`).

## Found while implementing

Two of the nine declared pages were not in-app pages at all.
`admin-settings` named a component — `AdminSettingsPage` — that **has never
existed**, and `admin-templates-index` named `TemplatesPage`, which is a TAB
inside the Nextcloud admin section (`lib/Settings/LaunchPadAdmin.php`, mounted
by `src/admin.js`). Routing them would have rendered half an admin surface
beside the real one.

Both now resolve to `AdminSettingsRedirect`, which sends the operator to
`/settings/admin/launchpad`. The menu entries stop being dead links and land
where the functionality lives. The redirect renders a real anchor as well as
navigating, because a redirect that only runs in `mounted` leaves a blank page
for anyone whose navigation is slow or blocked.

## Alternative considered

**Make LaunchPad a documented ADR-114 exception**: drop the pages it cannot
serve from the manifest, move the chrome into `DashboardFooter`, and record the
exception in ADR-114 so gate-107 stops reporting a chrome that is not there.

Rejected as the default because every other app in the fleet roots on
`CnAppRoot`, and the manifest already declares the pages — the cheaper change
is to make the declaration true rather than to withdraw it. Worth revisiting if
the decisions above turn out to cost more than the chrome is worth.

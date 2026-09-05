# LaunchPad manifest tier 3 — serve the pages the manifest declares

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

## Decisions needed

- **Does `CnAppRoot` host the workspace, or the other way round?** The org
  navigation rail and the slide-in sidebar are LaunchPad's own and have no
  equivalent in `CnAppNav`. Nesting the workspace inside `CnAppRoot`'s content
  area gives two navigation systems on one page unless the rail is reconciled
  with the nav.
- **The skip link.** `CnAppRoot` brings Nextcloud's; LaunchPad writes its own
  against `#launchpad-main-content`. Two bypass links is worse than one.
- **Whether the dashboard keeps its URL.** Dashboards switch in Pinia today, so
  a dashboard has no address. `dashboard-deeplinking` (an existing spec) wants
  one. Routing is the natural place to settle that, and it widens this change.

## Alternative considered

**Make LaunchPad a documented ADR-114 exception**: drop the pages it cannot
serve from the manifest, move the chrome into `DashboardFooter`, and record the
exception in ADR-114 so gate-107 stops reporting a chrome that is not there.

Rejected as the default because every other app in the fleet roots on
`CnAppRoot`, and the manifest already declares the pages — the cheaper change
is to make the declaration true rather than to withdraw it. Worth revisiting if
the decisions above turn out to cost more than the chrome is worth.

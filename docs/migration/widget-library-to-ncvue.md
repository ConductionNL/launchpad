# Phase 3 — Migrate the widget library to @conduction/nextcloud-vue

> Status: **planned, not started.** Phases 1 (catalog deprecation) and 2 (dead-code
> cleanup) are done. This document is the migration plan + parity audit for the
> remaining work: removing launchpad's local widget renderers/forms and consuming
> the shared `Cn*Widget` / `Cn*WidgetForm` library from nc-vue.

## Goal

Launchpad currently ships its **own** copy of ~36 widget renderers + config forms
under `src/components/Widgets/Renderers/` and `src/components/Widgets/Forms/`.
nc-vue has a parallel `Cn*Widget` / `Cn*WidgetForm` set. The grid engine already
moved to nc-vue (`CnWidgetGrid` / `CnWidgetWrapper`); the widgets should follow so
launchpad keeps only the workspace shell + glue (`widgetRegistry.js`, the
`WidgetRenderer` dispatcher, the `nc-widget` bridge).

## The reality (why this is a program, not a cleanup)

The nc-vue widget library is **not finished as a public API**, and **not published**:

- The published package launchpad consumes (`@conduction/nextcloud-vue` beta.102)
  ships **exactly one** of these components: `CnTileWidget`. Everything else is
  absent from the published dist.
- In nc-vue *source*, the components exist as files but are **not wired into the
  barrel** (`src/components/index.js` / `src/index.js`) — so they are not part of
  the public API even locally.
- nc-vue enforces a **`check:docs` CI gate**: every barrel export must have a
  `docs/components/<kebab>.md`. None of these widgets have docs yet.
- nc-vue also enforces a **`check:jsdoc` ratchet** (new components require 100%
  prop/event/slot JSDoc) and a docgen freshness gate.

So "port to nc-vue" means: finish, export, document, test, parity-audit, and
**publish** a ~36-component library in the shared fleet lib (consumed by
OpenRegister / OpenCatalogi / Procest / Pipelinq / MyDash), then migrate launchpad
onto it. That is multi-day and has fleet-wide blast radius — it cannot be done in
one pass, and a half-done state breaks both repos.

## Parity audit — current nc-vue readiness

`exists` = dir present in `nextcloud-vue/src/components/`; `export` = in the barrel;
`test` = has `tests/components/<name>*`; `doc` = has `docs/components/<kebab>.md`.

| launchpad renderer/form | nc-vue component | exists | export | test | doc |
|---|---|:--:|:--:|:--:|:--:|
| Renderers/LabelWidget | CnLabelWidget | Y | – | – | – |
| Renderers/TextDisplayWidget | CnTextWidget | Y | – | Y | – |
| Renderers/ImageWidget | CnImageWidget | Y | – | Y | – |
| Renderers/LinkButtonWidget | CnLinkButtonWidget | Y | – | Y | – |
| Renderers/HeaderWidget | CnHeaderWidget | Y | – | Y | – |
| Renderers/DividerWidget | CnDividerWidget | Y | – | – | – |
| Renderers/FilesWidget | CnFilesWidget | Y | – | Y | – |
| Renderers/PeopleWidget | CnPeopleWidget | Y | – | Y | – |
| Renderers/NewsWidget | CnNewsWidget | Y | – | Y | – |
| Renderers/QuicklinksWidget | CnQuicklinksWidget | Y | – | Y | – |
| Renderers/LinksWidget | CnLinksWidget | Y | – | Y | – |
| Renderers/MenuWidget | CnMenuWidget | Y | – | Y | – |
| Renderers/ContainerWidget | CnContainerWidget | Y | – | Y | – |
| Renderers/VideoWidget | CnVideoWidget | Y | – | Y | – |
| Renderers/CalendarWidget | CnCalendarWidget | Y | – | Y | – |
| Renderers/SpendAnalyticsWidget | CnSpendAnalyticsWidget | Y | – | Y | – |
| Renderers/NcDashboardWidget | CnNcWidgetWidget | Y | – | Y | – |
| Renderers/TileWidget | CnTileWidget / CnDashTileWidget | Y | partial | – | partial |
| Forms/LabelForm | CnLabelWidgetForm | Y | – | – | – |
| Forms/TextDisplayForm | CnTextWidgetForm | Y | – | – | – |
| Forms/ImageForm | CnImageWidgetForm | Y | – | Y | – |
| Forms/LinkButtonForm | CnLinkButtonWidgetForm | Y | – | – | – |
| Forms/HeaderForm | CnHeaderWidgetForm | Y | – | – | – |
| Forms/DividerForm | CnDividerWidgetForm | Y | – | – | – |
| Forms/FilesForm | CnFilesWidgetForm | Y | – | Y | – |
| Forms/NewsForm | CnNewsWidgetForm | Y | – | – | – |
| Forms/QuicklinksForm | CnQuicklinksWidgetForm | Y | – | – | – |
| Forms/LinksForm | CnLinksWidgetForm | Y | – | – | – |
| Forms/MenuForm | CnMenuWidgetForm | Y | – | – | – |
| Forms/ContainerForm | CnContainerWidgetForm | Y | – | – | – |
| Forms/TileForm | CnDashTileWidgetForm | Y | – | – | – |
| Forms/VideoForm | CnVideoWidgetForm | Y | – | – | – |
| Forms/PeopleForm | CnPeopleWidgetForm | **–** | – | – | – |
| Forms/CalendarForm | CnCalendarWidgetForm | **–** | – | – | – |
| Forms/SpendAnalyticsForm | CnSpendAnalyticsWidgetForm | **–** | – | – | – |
| Forms/NcDashboardForm | CnNcDashboardWidgetForm | **–** | – | – | – |

**Totals:** 0/36 exported, 0/36 documented, ~15/18 renderers tested, ~3/18 forms
tested, **4 forms missing entirely** (PeopleForm, CalendarForm, SpendAnalyticsForm,
NcDashboardForm).

**Launchpad-specific behaviour to preserve during the port (not assume "identical"):**
- `Renderers/TileWidget` supports the legacy `oc_launchpad_tiles` schema shape.
- `Renderers/NcDashboardWidget` is the NC Dashboard API bridge (v1/v2 polling +
  the `hideHeader` path used by `WidgetWrapper`).
- i18n domain differs (`mydash`/`launchpad` vs `nextcloud-vue`).
- Helper sub-components with no standalone nc-vue export: `MenuItemIcon`,
  `MenuTreeNode`, `MenuItemEditor`, `TextTableEditor`, `ContainerChild`.

## Migration plan (publishable slices)

Do **not** big-bang this. Each slice is independently shippable.

**0. Author the migration OpenSpec change** (per-app, in `openspec/changes/`) +
   a parity checklist. Decide the nc-vue branch (currently the widgets live on a
   feature branch `release/cn-table-widget-borderless`, not `development`).

**1. nc-vue: finish the library (per component or small group):**
   - Parity-diff launchpad vs nc-vue source; reconcile drift (props, defaults,
     i18n, styling). Treat launchpad as the behavioural reference.
   - Add the 4 missing forms (`CnPeopleWidgetForm`, `CnCalendarWidgetForm`,
     `CnSpendAnalyticsWidgetForm`, `CnNcDashboardWidgetForm`).
   - Wire each into the barrel (`src/components/index.js`, `src/index.js`).
   - Write a `docs/components/<kebab>.md` per export (satisfies `check:docs`).
   - Add/port tests; bump the jsdoc baselines; run `npm test`, `check:docs`,
     `check:jsdoc`, docgen freshness.

**2. nc-vue: publish a beta** (Codeberg CI) and note the version.

**3. launchpad: consume + delete:**
   - Bump `@conduction/nextcloud-vue` to the new beta; `npm install`.
   - Rewire `src/constants/widgetRegistry.js` to import `Cn*Widget` /
     `Cn*WidgetForm` from the barrel instead of the local files.
   - Keep the registry, the `WidgetRenderer` dispatcher, and the `nc-widget`
     bridge wiring (these stay in launchpad).
   - Delete `src/components/Widgets/Renderers/*` and `src/components/Widgets/Forms/*`
     that now resolve to nc-vue (keep launchpad-only helpers if any remain).
   - Update/move the corresponding `__tests__`.
   - Build + full vitest + live-verify every widget type renders and edits.

**4. Optional follow-ups:** promote `Dashboard/IconPicker` + `IconRenderer` and
   `WidgetStyleEditor` to nc-vue; fold `WidgetEditCog` into `CnWidgetWrapper`.

## Risks / gotchas

- **Fleet blast radius:** adding ~36 exports + publishing affects every nc-vue
  consumer. Parity drift that "works in launchpad" could regress another app.
- **CI gates:** `check:docs` hard-fails without a doc per export; budget ~36 doc
  files. `check:jsdoc` ratchet requires 100% on new components.
- **Publish dependency:** launchpad cannot verify the deletion until the beta is
  live; never bump launchpad to an unpublished version on `development`.
- **No silent parity assumptions:** earlier analysis called the components
  "identical" from excerpts — they must be diffed properly before deletion.

## Effort estimate

Roughly: 1 day reconcile/parity + write 4 forms, ~1 day docs (×36) + tests +
baselines, publish/verify cycle, then ~0.5 day launchpad rewire + delete + verify.
Realistically a multi-session program gated on an nc-vue release.

---

## Slice 1 — Parity audit (DONE)

Key finding: **nc-vue's widgets were deliberately refactored to be app-agnostic** —
data comes from `dataSource` props or `cn*Source` injections / `*Endpoint`
builders, NOT hardcoded `/apps/mydash/...` calls. So the port is "drop-in reuse"
only for the static/presentational widgets; the data-driven ones need launchpad
to wire an adapter that points the nc-vue component at launchpad's existing
endpoints. Nothing here blocks the migration, but it changes the work from
"delete + import" to "delete + import + wire data source" for ~6 widgets.

### Drop-in reuse (identical except i18n domain / icon-wrapper)
Renderers: `CnLabelWidget`, `CnTextWidget`, `CnImageWidget`, `CnHeaderWidget`,
`CnDividerWidget`, `CnVideoWidget`, `CnQuicklinksWidget`, `CnLinksWidget`,
`CnMenuWidget`. Forms: Label, Text, Image, Header, Divider, Quicklinks, Links,
Menu, Container, Video, and `TileForm → CnDashTileWidgetForm` (rename).

### Reuse + wire a data adapter (launchpad supplies the source)
| widget | nc-vue hook | launchpad endpoint to wire |
|---|---|---|
| PeopleWidget → CnPeopleWidget | `cnPeopleSource` inject / `dataSource` | `/apps/mydash/api/people` |
| CalendarWidget → CnCalendarWidget | `cnCalendarSource` inject | `/api/widgets/calendar/events` ⚠ **view-mode regression: nc-vue is agenda/upcoming only — month/week grid views are missing** |
| SpendAnalyticsWidget → CnSpendAnalyticsWidget | `cnSpendSource` inject | `/api/widgets/spend-analytics/...` |
| NewsWidget → CnNewsWidget | `itemsEndpoint` prop | `/api/widgets/news/{id}/items` |
| FilesWidget → CnFilesWidget | folder-picker/list endpoint | path moves `/apps/mydash/...` → `/apps/files/...` (verify) |
| NcDashboardWidget → CnNcWidgetWidget | `widgetId` + `itemsEndpoint` | NC Dashboard API bridge route |

### Needs an nc-vue enhancement before parity
- **CnCalendarWidget**: add month/week grid views (or accept the regression).
- **CnContainerWidget**: enforce REQ-CONT max nesting depth (=3) — launchpad guards it, nc-vue doesn't.
- **CnLinkButtonWidget**: internal-action **registry + create-file modal** is launchpad-specific; nc-vue emits `internal-action`/`create-file` events instead → launchpad keeps that handler host-side.

### Port from launchpad (no nc-vue counterpart yet) → add as `Cn*WidgetForm`
- `PeopleForm` → `CnPeopleWidgetForm` (props: layout, filters, excludeDisabled, showBirthdays, birthdayWindowDays, sortBy, columns)
- `CalendarForm` → `CnCalendarWidgetForm` (viewMode, daysAhead, eventSources, excludeNcalendars)
- `SpendAnalyticsForm` → `CnSpendAnalyticsWidgetForm` (viewMode, defaultPeriod, colorByVendor)
- `NcDashboardForm` → `CnNcDashboardWidgetForm` (widgetId, itemLimit, displayMode)

### Recommended next slices
2. nc-vue: enhancements (Calendar views, Container depth) + the 4 forms, each with barrel export + doc + test.
3. nc-vue: wire the static drop-ins into the barrel + docs; publish a beta.
4. launchpad: add the data-source adapters (people/calendar/spend/news/files/nc), rewire `widgetRegistry.js`, delete local copies, verify.

---

## Slices 2–3 — DONE (committed on branches)

**nc-vue** branch `feat/widget-library-exports` (commit `e91b1ab1`):
- Exported the 17 renderers + 18 forms via `src/components/index.js` + `src/index.js`.
- Created the 4 missing forms (`CnPeopleWidgetForm`, `CnCalendarWidgetForm`,
  `CnSpendAnalyticsWidgetForm`, `CnNcDashboardWidgetForm`).
- A doc page per export (`check:docs` green incl. prop coverage), regenerated
  docgen partials, updated jsdoc baselines (`check:jsdoc` green), rollup build green.
- Fixed pre-existing doc debt on `CnDetailPage` / `CnActionsBar`.

**launchpad** branch `feat/widget-library-consumption` (commit `fda6bd06`):
- `widgetRegistry.js` now sources the **9 presentational renderers + all 18 forms**
  from `@conduction/nextcloud-vue` (aliased to the historical names).
- Deleted the 9 local renderers + 18 forms + 5 orphaned helpers + their tests
  (~64 files); updated the vitest stub + AddWidgetModal/ContainerWidget specs.
- Full vitest green (571), build green, **live-verified end-to-end**: adding a
  Label widget renders via nc-vue's `CnLabelWidgetForm → CnLabelWidget`.
- Verified locally by injecting the freshly-built nc-vue dist into `node_modules`.

## Remaining (release mechanics + a follow-up slice)

1. **Publish** (CI/release — cannot be done from a dev shell): merge the nc-vue
   branch → publish a `@conduction/nextcloud-vue` beta containing the widget library.
2. **Bump + merge launchpad**: set launchpad's `@conduction/nextcloud-vue` dep to
   that beta, then merge `feat/widget-library-consumption` to `development`. (Its CI
   build fails until the dep is bumped — that's expected; do not merge before the publish.)
3. **Data-widget adapter slice** — DONE for the cleanly-adaptable renderers:
   **people / news / spend-analytics** now use nc-vue components, bridged via
   `WidgetRenderer`'s `provide()` (`cnPeopleSource`, `cnSpendAnalyticsSource`) +
   the news `itemsEndpoint` prop (commit `b67cd6a9`).

## Migration progress — 16 of 18 renderers + all 18 forms done

Resolved since the audit (committed on the two feature branches):
- **calendar** — added month/week views to `CnCalendarWidget` (nc-vue) + wired `cnCalendarSource`.
- **files** — added a configurable `apiBase` prop to `CnFilesWidget` (nc-vue) + pointed it at `/apps/mydash`.
- **link** — `LinkButtonHost` wrapper renders `CnLinkButtonWidget`, keeps the internal-action registry + create-file modal host-side.
- **people / news / spend-analytics** — `cnPeopleSource`/`cnSpendAnalyticsSource` provide() + news `itemsEndpoint`.
- **nc-widget** — `CnNcWidgetWidget` (native OCA.Dashboard fast-path + core OCS items API, no launchpad proxy). **Live-verified**: all 6 NC widgets on the dashboard render with 0 console errors.

## Renderers still local — 2 remaining, blockers verified by code inspection

| renderer | blocker (confirmed) | what's needed |
|---|---|---|
| `container` | `CnContainerChild.vue` hard-imports `getWidgetTypeEntry` from nc-vue's **own** `CnWidgetGrid/dashboardWidgetRegistry.js` — nested launchpad widget types can't resolve there; and nested data widgets wouldn't get the per-placement `cn*Source` adapters that `WidgetRenderer` provide()s (calendar/news/files need each child's own `placement.id`) | nc-vue: make `CnContainerChild`'s registry injectable; launchpad: provide its registry + flow per-child data adapters into nested placements |
| `tile` | the app-tile-row renderer (`src/components/TileWidget.vue`) injects a runtime `<style>` to override **nldesign's** aggressive CSS (`color … !important`) + uses several `!important` tile-colour rules + icon brightness/invert filters — theming workarounds `CnTileWidget`/`CnDashTileWidget` don't carry; it's also dual-path (DashboardGrid direct render + registry) and the most visible UI | port the nldesign theming workarounds into `CnTileWidget` (or a host wrapper) + reconcile the dual path/schema; verify the app-tile row under nldesign |

Both need an nc-vue change (injectable container registry / tile theming) plus
careful verification of heavily-used surfaces, so they're a watched follow-up.

## Release handoff (CI/review — not doable from a dev shell)

1. Merge nc-vue `feat/widget-library-exports` → publish a `@conduction/nextcloud-vue` beta.
2. Bump launchpad's dep to that beta; merge `feat/widget-library-consumption` → `development`.
   (The launchpad branch's CI build fails until the dep is bumped — by design.)

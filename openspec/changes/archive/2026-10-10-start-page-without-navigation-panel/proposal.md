---
kind: code
depends_on: []
---

# Start page without navigation panel

## Why

The Zuiddrecht design (`LpStart`) shows the employee's start page at full width under the top bar: no left navigation panel, the dashboard switcher and the footer destinations not in a rail. LaunchPad roots on `CnAppRoot`, which renders `CnAppNav` with ADR-114's four footer destinations, and the library has no prop to leave it out. For the demo the panel was hidden with one CSS line on the instance, which keeps an invisible landmark and takes Documentation, Store, Reports, Features & roadmap and the settings entries with it.

## What changes

- An admin option "Start page without navigation panel" (`start_page_without_navigation`, API `startPageWithoutNavigation`, default off) on the admin settings page beside the other layout options, stored with the other admin settings, pushed to the workspace initial state as an optional key (runtime-shell REQ-SHELL-009, admin-settings REQ-ASET-001).
- On, the dashboard view (`/`, `/dashboards/:id`) renders without the panel: `App.vue` fills `CnAppRoot`'s `#menu` slot with a hidden, empty node, so the panel is not rendered and the content takes the full width. Other pages keep the panel.
- `StartPageMenu` in the workspace's top-right controls, beside the dashboard switcher (and in the empty state), offers every entry the panel would have shown: the manifest's main, footer and settings entries after the panel's permission filter, the personal settings dialog, and for an administrator the link to Nextcloud's admin settings.
- Tests: option off renders the panel, on does not, other routes keep it; every panel entry has a destination and every router destination is a declared page (route no loss).

## Library gap

`@conduction/nextcloud-vue` 2.57.1, `src/components/CnAppRoot/CnAppRoot.vue` lines 202 to 213: the `#menu` slot defaults to `<CnAppNav>` and there is no prop to render the shell without it. Vue renders a slot's default content when the override holds no real node (`@vue/runtime-core` `ensureValidVNode`), so an empty override does not drop the panel; the hidden span is the smallest thing that does.

## Out of scope

- The design's "Startpagina aanpassen" button and widgets; this change is about the chrome.
- A settings export: the app's export is of dashboards and the site, not of admin settings, so there is nothing to add the option to.
